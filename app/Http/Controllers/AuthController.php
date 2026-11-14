<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\EmailToken;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PatientService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    // ------------------------------------------------------------------ login
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::with('role')->where('email', $data['email'])->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $mins = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Too many failed attempts. Try again in about {$mins} minute(s)."]);
        }

        // A deactivated account is blocked by is_active, not the temporary login lock.
        // Keep its feedback distinct so the user knows an administrator must reactivate it.
        if ($user && ! $user->is_active) {
            Audit::log('login_failed', 'usr_users', $user->user_id, 'Login blocked for inactive account '.$data['email']);
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account has been locked.']);
        }

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            if ($user) {
                $fails = $user->failed_login_count + 1;
                $user->update($fails >= self::MAX_ATTEMPTS
                    ? ['failed_login_count' => 0, 'locked_until' => now()->addMinutes(self::LOCK_MINUTES)]
                    : ['failed_login_count' => $fails]);
            }
            Audit::log('login_failed', 'usr_users', $user?->user_id, 'Failed login for '.$data['email']);
            return back()->withInput($request->only('email'))->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->is_email_verified) {
            return back()->withInput($request->only('email'))
                ->with('unverified_email', $user->email)
                ->withErrors(['email' => 'Please verify your e-mail address first. Check your inbox for the link.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity', time());
        $user->update(['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => now()]);
        Audit::log('login', 'usr_users', $user->user_id, 'User logged in');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Audit::log('logout', 'usr_users', Auth::id(), 'User logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }

    // --------------------------------------------------------------- register
    public function showRegister() { return view('auth.register'); }

    public function register(Request $request, PatientService $patients)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:usr_users,email',
            'contact_number' => ['required', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'birth_date' => 'required|date|before:today|after:1900-01-01',
            'sex' => 'nullable|in:male,female,other',
            'password' => 'required|string|min:8|confirmed',
            'privacy_consent' => 'accepted',
        ], [
            'privacy_consent.accepted' => 'You must accept the Data Privacy Consent to create an account.',
            'contact_number.regex' => 'Enter a valid phone number.',
        ]);

        $user = DB::transaction(function () use ($data, $request, $patients) {
            $user = User::create([
                'role_id' => 4,                                   // patient
                'username' => $this->makeUsername($data['first_name'], $data['last_name']),
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'contact_number' => $data['contact_number'],
            ]);
            $consent = Consent::create([
                'user_id' => $user->user_id, 'consent_type' => 'data_privacy',
                'policy_version' => Settings::get('privacy_policy_version'), 'is_accepted' => 1, 'ip_address' => $request->ip(),
            ]);
            $patient = $patients->create($data, $user);
            $consent->update(['patient_id' => $patient->patient_id]);
            return $user;
        });

        Audit::log('create', 'usr_users', $user->user_id, 'Patient account registered');
        $this->sendVerification($user);

        return redirect()->route('login')->with('status', 'Account created! We sent a verification link to '.$user->email.'. Please verify before logging in.');
    }

    // ----------------------------------------------------------- verify e-mail
    public function verifyEmail(string $token)
    {
        $row = $this->findToken($token, 'verify_email');
        if (! $row) return redirect()->route('login')->withErrors(['email' => 'That verification link is invalid or has expired. Request a new one below.']);

        $row->update(['used_at' => now()]);
        User::whereKey($row->user_id)->update(['is_email_verified' => 1]);
        Audit::log('update', 'usr_users', $row->user_id, 'E-mail verified');
        return redirect()->route('login')->with('status', 'E-mail verified. You can log in now.');
    }

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->where('is_email_verified', 0)->first();
        if ($user) $this->sendVerification($user);
        return back()->with('status', 'If that account needs verification, a new link is on its way.');
    }

    // --------------------------------------------------------- password reset
    public function showForgot() { return view('auth.forgot'); }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->where('is_active', 1)->first();
        if ($user) {
            $token = $this->makeToken($user, 'reset_password', now()->addMinutes(60));
            Notifier::queue('reset_password', $user->email, [
                'first_name' => $user->first_name, 'expires_minutes' => 60,
                'action_url' => route('password.reset', $token),
            ], $user->user_id);
        }
        // Same answer either way, so the form can't be used to discover registered e-mails.
        return back()->with('status', 'If that e-mail is registered, a reset link has been sent.');
    }

    public function showReset(string $token) { return view('auth.reset', ['token' => $token]); }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $row = $this->findToken($data['token'], 'reset_password');
        if (! $row) return redirect()->route('password.request')->withErrors(['email' => 'That reset link is invalid or has expired.']);

        User::whereKey($row->user_id)->update([
            'password_hash' => Hash::make($data['password']), 'failed_login_count' => 0, 'locked_until' => null,
        ]);
        $row->update(['used_at' => now()]);
        Audit::log('update', 'usr_users', $row->user_id, 'Password reset');
        return redirect()->route('login')->with('status', 'Password updated. You can log in now.');
    }

    // ---------------------------------------------------------------- helpers
    private function sendVerification(User $user): void
    {
        $token = $this->makeToken($user, 'verify_email', now()->addHours(24));
        Notifier::queue('verify_email', $user->email, [
            'first_name' => $user->first_name, 'action_url' => route('verification.verify', $token),
        ], $user->user_id);
    }

    /** Only the SHA-256 hash of the token is stored (usr_email_tokens.token_hash). */
    private function makeToken(User $user, string $type, $expires): string
    {
        EmailToken::where('user_id', $user->user_id)->where('token_type', $type)->whereNull('used_at')->update(['used_at' => now()]);
        $token = Str::random(64);
        EmailToken::create([
            'user_id' => $user->user_id, 'token_type' => $type,
            'token_hash' => hash('sha256', $token), 'expires_at' => $expires,
        ]);
        return $token;
    }

    private function findToken(string $token, string $type): ?EmailToken
    {
        return EmailToken::where('token_hash', hash('sha256', $token))->where('token_type', $type)
            ->whereNull('used_at')->where('expires_at', '>', now())->first();
    }

    private function makeUsername(string $first, string $last): string
    {
        $base = Str::lower(Str::slug($first.$last, ''));
        $base = mb_substr($base !== '' ? $base : 'patient', 0, 40);
        do { $name = $base.random_int(100, 99999); } while (User::withTrashed()->where('username', $name)->exists());
        return $name;
    }
}
