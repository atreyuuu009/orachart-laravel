<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\EmailToken;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = User::with(['role', 'dentist.specialties', 'patient'])->when($request->filled('search'), function ($q) use ($request) {
            $search = trim((string) $request->string('search'));
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $q->where(function ($w) use ($search, $terms) {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $w->where(fn ($name) => $name->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like));
                }

                $fullSearch = '%'.$search.'%';
                $w->orWhere('username', 'like', $fullSearch)
                    ->orWhere('email', 'like', $fullSearch);
            });
        })->when($request->filled('role'), fn ($q) => $q->whereHas('role', fn ($r) => $r->where('role_name', $request->role)));

        $statusCounts = [
            'all' => (clone $q)->count(),
            'active' => (clone $q)->where('is_active', 1)->count(),
            'inactive' => (clone $q)->where('is_active', 0)->count(),
            'locked' => (clone $q)->where('locked_until', '>', now())->count(),
        ];

        $q->when($request->status === 'active', fn ($q) => $q->where('is_active', 1))
          ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', 0))
          ->when($request->status === 'locked', fn ($q) => $q->where('locked_until', '>', now()))
          ->orderBy('last_name')->orderBy('first_name');
        return view('admin.users.index', [
            'users' => $q->paginate(20)->withQueryString(),
            'roles' => Role::whereIn('role_name', ['admin','clinic_staff','dentist','patient'])->get(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create() { return view('admin.users.form', ['user' => new User(), 'roles' => $this->staffRoles(), 'specialties' => Specialty::orderBy('specialty_name')->get(), 'editing' => false]); }
    public function edit(User $user) { return view('admin.users.form', ['user' => $user->load('role','dentist.specialties'), 'roles' => $user->hasRole('patient') ? Role::where('role_name','patient')->get() : $this->staffRoles(), 'specialties' => Specialty::orderBy('specialty_name')->get(), 'editing' => true]); }

    public function store(Request $request)
    {
        $d = $this->validated($request, false);
        $role = Role::where('role_id', $d['role_id'])->firstOrFail();
        abort_unless(in_array($role->role_name, ['admin','clinic_staff','dentist'], true), 422);
        $user = DB::transaction(function () use ($d, $role) {
            $user = User::create(['role_id'=>$role->role_id, 'username'=>$this->username($d['first_name'], $d['last_name']),
                'email'=>$d['email'], 'password_hash'=>Hash::make(Str::random(64)), 'first_name'=>$d['first_name'], 'last_name'=>$d['last_name'],
                'contact_number'=>$d['contact_number'] ?? null, 'is_active'=>1, 'is_email_verified'=>0]);
            if ($role->role_name === 'dentist') {
                $dentist = Dentist::create(['user_id'=>$user->user_id, 'license_number'=>$d['license_number'] ?? null]);
                $dentist->specialties()->sync($d['specialties'] ?? []);
            }
            return $user;
        });
        Audit::log('create', 'usr_users', $user->user_id, 'Staff account created', null, $this->snapshot($user->fresh('role','dentist.specialties')));
        $this->sendToken($user, 'reset_password', now()->addMinutes(60));
        return redirect()->route('admin.users')->with('status', 'Account created. A password setup link has been sent.');
    }

    public function update(Request $request, User $user)
    {
        $d = $this->validated($request, true);
        $role = Role::where('role_id', $d['role_id'])->firstOrFail();
        $old = $this->snapshot($user->load('role','dentist.specialties'));
        if (($user->hasRole('patient') && $role->role_name !== 'patient') || (! $user->hasRole('patient') && $role->role_name === 'patient')) return back()->withErrors(['role_id' => 'Role changes between patient and staff accounts are not allowed.']);
        if ($user->hasRole('admin') && $role->role_name !== 'admin' && User::where('is_active',1)->whereHas('role', fn($q)=>$q->where('role_name','admin'))->count() <= 1)
            return back()->withErrors(['role_id' => 'The system must always have at least one active admin.']);
        if ($user->user_id === auth()->id() && ! $user->is_active) abort(403);
        $user->update(['role_id'=>$role->role_id, 'email'=>$d['email'], 'first_name'=>$d['first_name'], 'last_name'=>$d['last_name'], 'contact_number'=>$d['contact_number'] ?? null]);
        if ($role->role_name === 'dentist') {
            $dentist = $user->dentist ?: Dentist::create(['user_id'=>$user->user_id]);
            $dentist->update(['license_number'=>$d['license_number'] ?? null]);
            $dentist->specialties()->sync($d['specialties'] ?? []);
        } elseif ($user->dentist) {
            $user->dentist->update(['is_accepting_bookings'=>0]);
        }
        Audit::log('update', 'usr_users', $user->user_id, 'Staff account edited', $old, $this->snapshot($user->fresh('role','dentist.specialties')));
        return redirect()->route('admin.users')->with('status', 'User updated.');
    }

    public function deactivate(User $user)
    {
        abort_if($user->user_id === auth()->id(), 403, 'You cannot deactivate your own account.');
        if ($user->hasRole('admin') && User::where('is_active',1)->whereHas('role', fn($q)=>$q->where('role_name','admin'))->count() <= 1)
            return back()->withErrors(['user' => 'The system must always have at least one active admin.']);
        $old = $this->snapshot($user->load('role','dentist.specialties'));
        DB::transaction(function () use ($user) {
            $user->update(['is_active'=>0]);
            if ($user->dentist) {
                $user->dentist->update(['is_accepting_bookings'=>0]);
                $appointments = Appointment::open()->where('assigned_dentist_id', $user->dentist->dentist_id)->where('needs_reschedule',0)->with('patient.user','service','dentist.user')->get();
                foreach ($appointments as $appointment) {
                    $appointment->update(['needs_reschedule'=>1]);
                    Notifier::forAppointment('schedule_changed', $appointment);
                }
            }
        });
        Audit::log('deactivate','usr_users',$user->user_id,'User deactivated',$old,$this->snapshot($user->fresh('role','dentist.specialties')));
        return back()->with('status','User deactivated.');
    }

    public function reactivate(User $user) { $old=$this->snapshot($user->load('role','dentist.specialties')); $user->update(['is_active'=>1]); if ($user->dentist) { $previous=\App\Models\AuditLog::where('table_affected','usr_users')->where('record_id',(string)$user->user_id)->where('action_type','deactivate')->orderByDesc('audit_log_id')->value('old_value'); $user->dentist->update(['is_accepting_bookings'=>(bool)($previous['dentist_accepting_bookings'] ?? true)]); } Audit::log('reactivate','usr_users',$user->user_id,'User reactivated',$old,$this->snapshot($user->fresh('role','dentist.specialties'))); return back()->with('status','User reactivated.'); }
    public function unlock(User $user) { $old=$this->snapshot($user->load('role')); $user->update(['locked_until'=>null,'failed_login_count'=>0]); Audit::log('unlock','usr_users',$user->user_id,'Login lock cleared',$old,$this->snapshot($user->fresh('role'))); return back()->with('status','User unlocked.'); }

    public function sendReset(User $user) { $old=$this->snapshot($user->load('role','dentist.specialties')); $this->sendToken($user,'reset_password',now()->addMinutes(60)); Audit::log('reset_sent','usr_users',$user->user_id,'Password reset link sent',$old,$old); return back()->with('status','If the account can receive mail, a reset link has been sent.'); }
    public function resendVerification(User $user) { $old=$this->snapshot($user->load('role','dentist.specialties')); $this->sendToken($user,'verify_email',now()->addHours(24)); Audit::log('verification_sent','usr_users',$user->user_id,'Verification link resent',$old,$old); return back()->with('status','Verification link sent.'); }

    private function validated(Request $r, bool $editing): array
    {
        return $r->validate(['first_name'=>'required|string|max:100','last_name'=>'required|string|max:100','email'=>'required|email|max:255|unique:usr_users,email'.($editing ? ','.$r->route('user')->user_id.',user_id' : ''),
            'contact_number'=>['nullable','regex:/^[0-9+\-\s()]{7,20}$/'],'role_id'=>'required|exists:usr_roles,role_id', 'license_number'=>'nullable|string|max:50', 'specialties'=>'nullable|array','specialties.*'=>'integer|exists:usr_specialties,specialty_id']);
    }
    private function staffRoles() { return Role::whereIn('role_name',['admin','clinic_staff','dentist'])->orderBy('role_id')->get(); }
    private function username(string $first,string $last): string { $base=mb_substr(Str::lower(Str::slug($first.$last,'')) ?: 'staff',0,40); do {$u=$base.random_int(100,99999);} while(User::withTrashed()->where('username',$u)->exists()); return $u; }
    private function sendToken(User $user,string $type,$expires): void
    {
        EmailToken::where('user_id',$user->user_id)->where('token_type',$type)->whereNull('used_at')->update(['used_at'=>now()]);
        $token=Str::random(64); EmailToken::create(['user_id'=>$user->user_id,'token_type'=>$type,'token_hash'=>hash('sha256',$token),'expires_at'=>$expires]);
        Notifier::queue($type,$user->email,['first_name'=>$user->first_name,'expires_minutes'=>$type==='reset_password'?60:null,'action_url'=>$type==='reset_password'?route('password.reset',$token):route('verification.verify',$token)],$user->user_id);
    }
    private function snapshot(User $u): array { return ['role'=>$u->role?->role_name,'username'=>$u->username,'email'=>$u->email,'first_name'=>$u->first_name,'last_name'=>$u->last_name,'contact_number'=>$u->contact_number,'is_active'=>$u->is_active,'is_email_verified'=>$u->is_email_verified,'locked_until'=>$u->locked_until?->toDateTimeString(),'failed_login_count'=>$u->failed_login_count,'dentist_license'=>$u->dentist?->license_number,'dentist_accepting_bookings'=>$u->dentist?->is_accepting_bookings,'specialties'=>$u->dentist?->specialties->pluck('specialty_name')->all()]; }
}
