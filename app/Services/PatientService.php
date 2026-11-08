<?php

namespace App\Services;

use App\Models\HealthProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PatientService
{
    /** Creates pat_patients (+ empty health profile) with the next GS-YYYY-00001 code. */
    public function create(array $d, ?User $account = null, ?User $createdBy = null): Patient
    {
        return DB::transaction(function () use ($d, $account, $createdBy) {
            $p = Patient::create([
                'user_id' => $account?->user_id,
                'patient_code' => Patient::nextCode(),
                'first_name' => $d['first_name'],
                'middle_name' => $d['middle_name'] ?? null,
                'last_name' => $d['last_name'],
                'birth_date' => $d['birth_date'],
                'sex' => $d['sex'] ?? null,
                'contact_number' => $d['contact_number'],
                'email' => $d['email'] ?? null,
                'created_by_user_id' => $createdBy?->user_id ?? $account?->user_id,
            ]);
            HealthProfile::create(['patient_id' => $p->patient_id, 'blood_type' => 'unknown']);
            return $p;
        });
    }
}
