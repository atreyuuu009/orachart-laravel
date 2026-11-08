<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\EmergencyBlock;
use App\Models\HealthProfile;
use App\Models\OdoConditionCode;
use App\Models\OdoFinding;
use App\Models\Patient;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Demo accounts from the prototype (password 123456). Remove or change before going live. */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('123456');
        $mk = fn (int $role, string $username, string $email, string $first, string $last, string $phone) => User::firstOrCreate(
            ['email' => $email],
            ['role_id' => $role, 'username' => $username, 'password_hash' => $pw, 'first_name' => $first, 'last_name' => $last,
             'contact_number' => $phone, 'is_email_verified' => 1]
        );

        $mk(1, 'atreyu', 'atreyu@gmail.com', 'Atreyu', 'Tagaban', '09201234567');
        $mk(3, 'pericano', 'pericano@gmail.com', 'Joshua', 'Pericano', '09191234567');
        $guda = $mk(2, 'guda', 'Guda@gmail.com', 'Krishia Ann', 'Guda', '09171234567');
        $sor = $mk(2, 'soriano', 'Soriano@gmail.com', 'Glidel', 'Soriano', '09181234567');
        foreach ([$guda, $sor] as $u) {
            $d = Dentist::firstOrCreate(['user_id' => $u->user_id]);
            $d->specialties()->syncWithoutDetaching([1]);          // General Dentistry
        }

        // Demo patient with an account
        $pu = $mk(4, 'patient', 'patient@demo.com', 'Patient', 'Patient', '09170000000');
        $this->patient(['user_id' => $pu->user_id, 'first_name' => 'Patient', 'last_name' => 'Patient', 'birth_date' => '1998-05-14',
            'contact_number' => '09170000000', 'email' => 'patient@demo.com', 'sex' => 'female']);

        // Walk-in patients (no account) used by the odontogram demo
        $pedro = $this->patient(['first_name' => 'Pedro', 'last_name' => 'Penduko', 'birth_date' => '2002-03-02', 'contact_number' => '09175550001', 'sex' => 'male']);
        $this->patient(['first_name' => 'Marian', 'last_name' => 'Magdalena', 'birth_date' => '1995-08-21', 'contact_number' => '09175550002', 'sex' => 'female']);
        $this->patient(['first_name' => 'Atreyu', 'middle_name' => 'Da', 'last_name' => 'Gaban', 'birth_date' => '1998-01-09', 'contact_number' => '09175550003', 'sex' => 'male']);

        if (! OdoFinding::where('patient_id', $pedro->patient_id)->exists()) {
            $by = Dentist::first()->dentist_id;
            foreach ([[16, 'CARIES', 'occlusal'], [17, 'FILLING', 'occlusal'], [13, 'CROWN', 'whole'], [26, 'RCT', 'whole'], [27, 'MISSING', 'whole'], [36, 'CARIES', 'mesial']] as [$tooth, $code, $surface]) {
                OdoFinding::create(['patient_id' => $pedro->patient_id, 'tooth_number' => $tooth, 'surface' => $surface,
                    'condition_code_id' => OdoConditionCode::where('code', $code)->value('condition_code_id'), 'recorded_by_dentist_id' => $by]);
            }
        }

        // Reserved emergency windows from the prototype (11:00 and 16:00, Mon–Sat, any dentist)
        if (! EmergencyBlock::exists()) {
            foreach (range(1, 6) as $dow) {
                EmergencyBlock::create(['day_of_week' => $dow, 'start_time' => '11:00', 'end_time' => '11:30', 'is_active' => 1]);
                EmergencyBlock::create(['day_of_week' => $dow, 'start_time' => '16:00', 'end_time' => '16:30', 'is_active' => 1]);
            }
        }

        // Settings that are not in the SQL seed (defaults live in App\Support\Settings)
        foreach (['auto_confirm_bookings' => 1, 'reminders_enabled' => 1, 'enforce_service_specialty' => 0] as $k => $v) {
            \App\Models\SysSetting::firstOrCreate(['setting_key' => $k], ['setting_value' => (string) $v]);
        }
    }

    private function patient(array $d): Patient
    {
        $existing = Patient::where('first_name', $d['first_name'])->where('last_name', $d['last_name'])->where('birth_date', $d['birth_date'])->first();
        if ($existing) return $existing;
        $p = Patient::create($d + ['patient_code' => Patient::nextCode()]);
        HealthProfile::create(['patient_id' => $p->patient_id, 'blood_type' => 'unknown']);
        return $p;
    }
}
