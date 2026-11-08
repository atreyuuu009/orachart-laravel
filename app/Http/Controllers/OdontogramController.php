<?php

namespace App\Http\Controllers;

use App\Models\OdoConditionCode;
use App\Models\OdoFinding;
use App\Models\OdoFindingHistory;
use App\Models\OdoTooth;
use App\Models\Patient;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Digital odontogram (FDI). Dentists create/edit/remove findings; admin has read-only access.
 * Every change is appended to odo_finding_history (never updated or deleted).
 */
class OdontogramController extends Controller
{
    private const SURFACES = ['whole', 'mesial', 'distal', 'occlusal', 'buccal', 'lingual'];

    public function index(Request $request)
    {
        return view('odontogram.index', [
            'patients' => Patient::orderBy('last_name')->orderBy('first_name')->get(['patient_id', 'patient_code', 'first_name', 'middle_name', 'last_name', 'birth_date', 'sex']),
            'conditions' => OdoConditionCode::where('is_active', 1)->orderBy('category')->orderBy('label')->get(),
            'canEdit' => $request->user()->hasRole('dentist') && $request->user()->dentist,
            'selected' => (int) $request->query('patient'),
        ]);
    }

    public function findings(Patient $patient)
    {
        $rows = OdoFinding::with('condition', 'dentist.user')->where('patient_id', $patient->patient_id)->orderBy('tooth_number')->get();
        return response()->json([
            'patient' => ['id' => $patient->patient_id, 'name' => $patient->full_name, 'code' => $patient->patient_code, 'age' => $patient->age, 'sex' => $patient->sex],
            'findings' => $rows->map(fn ($f) => $this->present($f)),
        ]);
    }

    public function history(Request $request, Patient $patient)
    {
        $rows = OdoFindingHistory::with('dentist.user')->where('patient_id', $patient->patient_id)
            ->when($request->query('tooth'), fn ($q, $t) => $q->where('tooth_number', (int) $t))
            ->orderByDesc('finding_history_id')->limit(100)->get();

        return response()->json($rows->map(fn ($h) => [
            'tooth' => $h->tooth_number, 'action' => $h->action,
            'old' => $h->old_value, 'new' => $h->new_value,
            'by' => $h->dentist?->name, 'at' => $h->performed_at->format('M j, Y g:i A'),
        ]));
    }

    public function store(Request $request, Patient $patient)
    {
        $dentist = $this->writer($request);
        $v = $this->validated($request);
        $cond = OdoConditionCode::findOrFail($v['condition_code_id']);
        $surface = $cond->applies_to === 'tooth' ? 'whole' : $v['surface'];
        if ($cond->applies_to === 'surface' && $surface === 'whole') {
            return response()->json(['message' => 'Choose a surface for this condition.'], 422);
        }

        $f = OdoFinding::updateOrCreate(
            ['patient_id' => $patient->patient_id, 'tooth_number' => $v['tooth_number'], 'surface' => $surface, 'condition_code_id' => $cond->condition_code_id],
            ['notes' => $v['notes'] ?? null, 'recorded_by_dentist_id' => $dentist->dentist_id]
        );
        $f->load('condition', 'dentist.user');

        $this->log($f->wasRecentlyCreated ? 'created' : 'updated', $f, null, $this->snapshot($f), $dentist);
        Audit::log('create', 'odo_findings', $f->finding_id, "Tooth {$f->tooth_number}: {$cond->label}");
        return response()->json($this->present($f), 201);
    }

    public function update(Request $request, OdoFinding $finding)
    {
        $dentist = $this->writer($request);
        $v = $request->validate([
            'condition_code_id' => 'required|exists:odo_condition_codes,condition_code_id',
            'surface' => ['required', Rule::in(self::SURFACES)], 'notes' => 'nullable|string|max:500',
        ]);
        $cond = OdoConditionCode::findOrFail($v['condition_code_id']);
        $old = $this->snapshot($finding->load('condition'));
        $finding->update([
            'condition_code_id' => $cond->condition_code_id,
            'surface' => $cond->applies_to === 'tooth' ? 'whole' : $v['surface'],
            'notes' => $v['notes'] ?? null, 'recorded_by_dentist_id' => $dentist->dentist_id,
        ]);
        $finding->load('condition', 'dentist.user');

        $this->log('updated', $finding, $old, $this->snapshot($finding), $dentist);
        Audit::log('update', 'odo_findings', $finding->finding_id, "Tooth {$finding->tooth_number} finding updated");
        return response()->json($this->present($finding));
    }

    public function destroy(Request $request, OdoFinding $finding)
    {
        $dentist = $this->writer($request);
        $finding->load('condition');
        $this->log('removed', $finding, $this->snapshot($finding), null, $dentist);
        Audit::log('delete', 'odo_findings', $finding->finding_id, "Tooth {$finding->tooth_number} finding removed");
        $finding->delete();
        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- helpers
    private function writer(Request $request)
    {
        $d = $request->user()->hasRole('dentist') ? $request->user()->dentist : null;
        abort_if(! $d, 403, 'Only dentists can edit the odontogram.');
        return $d;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'tooth_number' => 'required|integer|exists:odo_teeth,tooth_number',
            'condition_code_id' => 'required|exists:odo_condition_codes,condition_code_id',
            'surface' => ['nullable', Rule::in(self::SURFACES)],
            'notes' => 'nullable|string|max:500',
        ]);
        $data['surface'] = $data['surface'] ?? 'whole';
        return $data;
    }

    private function snapshot(OdoFinding $f): array
    {
        return ['tooth' => $f->tooth_number, 'surface' => $f->surface, 'condition' => $f->condition?->label, 'code' => $f->condition?->code, 'notes' => $f->notes];
    }

    private function log(string $action, OdoFinding $f, ?array $old, ?array $new, $dentist): void
    {
        OdoFindingHistory::create([
            'finding_id' => $action === 'removed' ? null : $f->finding_id, 'patient_id' => $f->patient_id,
            'tooth_number' => $f->tooth_number, 'action' => $action, 'old_value' => $old, 'new_value' => $new,
            'performed_by_dentist_id' => $dentist->dentist_id,
        ]);
    }

    private function present(OdoFinding $f): array
    {
        return [
            'id' => $f->finding_id, 'tooth' => $f->tooth_number, 'surface' => $f->surface, 'notes' => $f->notes,
            'condition_id' => $f->condition_code_id, 'code' => $f->condition?->code, 'label' => $f->condition?->label,
            'category' => $f->condition?->category, 'color' => $f->condition?->color_hex,
            'by' => $f->dentist?->name, 'at' => $f->updated_at ?? $f->recorded_at,
        ];
    }
}
