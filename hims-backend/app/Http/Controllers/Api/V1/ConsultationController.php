<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Service;
use App\Domain\Billing\Services\InvoiceCalculator;
use App\Domain\Billing\Services\InvoiceNumberGenerator;
use App\Domain\EMR\Models\Encounter;
use App\Domain\EMR\Models\Prescription;
use App\Domain\EMR\Services\EncounterNumberGenerator;
use App\Domain\EMR\Services\FhirPayloadBuilder;
use App\Domain\EMR\Services\PrescriptionNumberGenerator;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\OPD\Models\OpdQueue;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultationStartRequest;
use App\Http\Requests\ConsultationUpdateRequest;
use App\Http\Resources\EncounterResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsultationController extends Controller
{
    private const ADMIN_OVERRIDE_ROLES = ['platform-admin', 'hospital-admin'];

    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $encounters = Encounter::query()
            ->forHospital($context->hospitalId())
            ->with([
                'patient:id,uhid,first_name,middle_name,last_name,mobile',
                'doctor:id,name',
                'department:id,name,code',
                'prescriptions.items',
            ])
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('created_at', $request->date('date')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('doctor_user_id'), fn (Builder $query) => $query->where('doctor_user_id', $request->integer('doctor_user_id')))
            ->when($request->filled('patient_id'), fn (Builder $query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->latest('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: EncounterResource::collection($encounters),
            message: 'Consultations loaded',
        );
    }

    public function store(
        ConsultationStartRequest $request,
        TenantContext $context,
        EncounterNumberGenerator $generator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $data = $request->validated();

        $encounter = DB::transaction(function () use ($data, $request, $context, $generator): Encounter {
            $queue = null;
            $appointment = null;

            if (filled($data['opd_queue_id'] ?? null)) {
                $queue = OpdQueue::query()->whereKey($data['opd_queue_id'])->lockForUpdate()->firstOrFail();
                abort_unless($queue->hospital_id === $context->hospitalId(), 404);

                if ($queue->appointment_id !== null) {
                    $appointment = Appointment::find($queue->appointment_id);
                }
            } else {
                $appointment = Appointment::query()->whereKey($data['appointment_id'])->firstOrFail();
                abort_unless($appointment->hospital_id === $context->hospitalId(), 404);
            }

            $patientId = $queue->patient_id ?? $data['patient_id'] ?? $appointment?->patient_id;
            $departmentId = $queue->department_id ?? $appointment?->department_id;
            $branchId = $queue->branch_id ?? $appointment?->branch_id ?? $context->branchId();

            $doctorUserId = $request->user()->id;

            if (filled($data['doctor_user_id'] ?? null) && $request->user()->hasRole(self::ADMIN_OVERRIDE_ROLES)) {
                $doctorUserId = $data['doctor_user_id'];
            }

            $encounter = Encounter::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $branchId,
                'patient_id' => $patientId,
                'appointment_id' => $appointment?->id,
                'opd_queue_id' => $queue?->id,
                'department_id' => $departmentId,
                'doctor_user_id' => $doctorUserId,
                'encounter_number' => $generator->nextForHospital($context->hospital),
                'encounter_type' => 'opd',
                'status' => 'draft',
                'vitals' => $queue?->vitals,
                'started_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            if ($queue !== null && $queue->status !== 'in_consultation') {
                $queue->update([
                    'status' => 'in_consultation',
                    'started_at' => $queue->started_at ?? now(),
                ]);
            }

            return $encounter;
        });

        $auditLogger->record(
            request: $request,
            module: 'opd_consultation',
            event: 'encounter.started',
            auditable: $encounter,
            new: $encounter->only(['encounter_number', 'status', 'patient_id', 'doctor_user_id']),
        );

        return ApiResponse::success(
            request: $request,
            data: new EncounterResource($encounter->load(['patient', 'doctor', 'department', 'prescriptions.items'])),
            message: 'Consultation started',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, Encounter $encounter): JsonResponse
    {
        abort_unless($encounter->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new EncounterResource($encounter->load(['patient', 'doctor', 'department', 'prescriptions.items'])),
            message: 'Consultation loaded',
        );
    }

    public function update(
        ConsultationUpdateRequest $request,
        TenantContext $context,
        Encounter $encounter,
        PrescriptionNumberGenerator $prescriptionNumberGenerator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        abort_unless($encounter->hospital_id === $context->hospitalId(), 404);
        abort_unless($encounter->status === 'draft', 422, 'Only draft consultations can be updated.');

        $data = $request->validated();
        $old = $encounter->only(['vitals', 'chief_complaints', 'clinical_history', 'examination', 'diagnoses', 'care_plan', 'follow_up']);

        DB::transaction(function () use ($data, $request, $context, $encounter, $prescriptionNumberGenerator): void {
            $encounter->update([
                'vitals' => array_key_exists('vitals', $data) ? $data['vitals'] : $encounter->vitals,
                'chief_complaints' => array_key_exists('chief_complaints', $data) ? $data['chief_complaints'] : $encounter->chief_complaints,
                'clinical_history' => array_key_exists('clinical_history', $data) ? $data['clinical_history'] : $encounter->clinical_history,
                'examination' => array_key_exists('examination', $data) ? $data['examination'] : $encounter->examination,
                'diagnoses' => array_key_exists('diagnoses', $data) ? $data['diagnoses'] : $encounter->diagnoses,
                'care_plan' => array_key_exists('care_plan', $data) ? $data['care_plan'] : $encounter->care_plan,
                'follow_up' => array_key_exists('follow_up', $data) ? $data['follow_up'] : $encounter->follow_up,
                'updated_by' => $request->user()->id,
            ]);

            if (array_key_exists('prescription_items', $data)) {
                $prescription = Prescription::query()
                    ->where('encounter_id', $encounter->id)
                    ->where('status', 'draft')
                    ->first();

                if ($prescription === null) {
                    $prescription = Prescription::create([
                        'hospital_id' => $context->hospitalId(),
                        'branch_id' => $encounter->branch_id,
                        'patient_id' => $encounter->patient_id,
                        'encounter_id' => $encounter->id,
                        'prescribed_by' => $encounter->doctor_user_id,
                        'prescription_number' => $prescriptionNumberGenerator->nextForHospital($context->hospital),
                        'status' => 'draft',
                    ]);
                }

                $prescription->items()->delete();

                foreach ($data['prescription_items'] as $item) {
                    $prescription->items()->create([
                        'medicine_name' => $item['medicine_name'],
                        'generic_name' => $item['generic_name'] ?? null,
                        'formulation' => $item['formulation'] ?? null,
                        'strength' => $item['strength'] ?? null,
                        'route' => $item['route'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'instructions' => $item['instructions'] ?? null,
                        'is_schedule_h' => $item['is_schedule_h'] ?? false,
                        'is_schedule_h1' => $item['is_schedule_h1'] ?? false,
                    ]);
                }
            }
        });

        $encounter->refresh();

        $auditLogger->record(
            request: $request,
            module: 'opd_consultation',
            event: 'encounter.updated',
            auditable: $encounter,
            old: $old,
            new: $encounter->only(['vitals', 'chief_complaints', 'clinical_history', 'examination', 'diagnoses', 'care_plan', 'follow_up']),
        );

        return ApiResponse::success(
            request: $request,
            data: new EncounterResource($encounter->load(['patient', 'doctor', 'department', 'prescriptions.items'])),
            message: 'Consultation updated',
        );
    }

    public function complete(
        Request $request,
        TenantContext $context,
        Encounter $encounter,
        FhirPayloadBuilder $fhirPayloadBuilder,
        InvoiceNumberGenerator $invoiceNumberGenerator,
        InvoiceCalculator $invoiceCalculator,
        AuditLogger $auditLogger,
        NotificationDispatcher $notifications,
    ): JsonResponse {
        abort_unless($encounter->hospital_id === $context->hospitalId(), 404);
        abort_unless($encounter->status === 'draft', 422, 'Only draft consultations can be completed.');

        DB::transaction(function () use ($request, $context, $encounter, $fhirPayloadBuilder, $invoiceNumberGenerator, $invoiceCalculator): void {
            $locked = Encounter::query()->whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 422, 'Only draft consultations can be completed.');

            $prescription = Prescription::query()->where('encounter_id', $locked->id)->first();
            $fhirPayload = $fhirPayloadBuilder->buildEncounterBundle($locked, $prescription);

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
                'fhir_payload' => $fhirPayload,
                'updated_by' => $request->user()->id,
            ]);

            if ($prescription !== null && $prescription->items()->exists()) {
                $prescription->update([
                    'status' => 'issued',
                    'prescribed_at' => now(),
                ]);
            }

            if ($locked->opd_queue_id !== null) {
                $queue = OpdQueue::find($locked->opd_queue_id);

                if ($queue !== null && $queue->status !== 'completed') {
                    $queue->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }
            }

            $this->createDraftInvoiceIfBillable($context, $locked, $invoiceNumberGenerator, $invoiceCalculator, $request);
        });

        $encounter->refresh()->load(['patient', 'doctor', 'department', 'prescriptions.items']);

        $auditLogger->record(
            request: $request,
            module: 'opd_consultation',
            event: 'encounter.completed',
            auditable: $encounter,
            new: ['status' => 'completed', 'completed_at' => $encounter->completed_at?->toISOString()],
        );

        $this->dispatchPrescriptionEmail($encounter, $notifications);

        return ApiResponse::success(
            request: $request,
            data: new EncounterResource($encounter),
            message: 'Consultation completed',
        );
    }

    private function dispatchPrescriptionEmail(Encounter $encounter, NotificationDispatcher $notifications): void
    {
        $patient = $encounter->patient;
        $prescription = $encounter->prescriptions->first();

        if (
            $patient === null
            || blank($patient->email)
            || ! $patient->consent_email
            || $prescription === null
            || $prescription->status !== 'issued'
            || $prescription->items->isEmpty()
        ) {
            return;
        }

        $itemsSummary = $prescription->items
            ->map(fn ($item) => trim(collect([
                $item->medicine_name,
                $item->strength,
                $item->frequency,
                $item->duration,
            ])->filter()->implode(' ')))
            ->filter()
            ->implode("\n");

        $notifications->dispatch(
            hospitalId: $encounter->hospital_id,
            branchId: $encounter->branch_id,
            templateCode: 'prescription.ready',
            channel: 'email',
            recipient: $patient->email,
            context: [
                'patient_name' => $patient->full_name,
                'prescription_number' => $prescription->prescription_number,
                'doctor_name' => $encounter->doctor?->name ?? 'Doctor',
                'items_summary' => $itemsSummary,
                'uhid' => $patient->uhid,
            ],
            patientId: $patient->id,
            related: $prescription,
        );
    }

    private function createDraftInvoiceIfBillable(
        TenantContext $context,
        Encounter $encounter,
        InvoiceNumberGenerator $invoiceNumberGenerator,
        InvoiceCalculator $invoiceCalculator,
        Request $request,
    ): void {
        $existingInvoice = Invoice::query()->where('encounter_id', $encounter->id)->first();

        if ($existingInvoice !== null) {
            return;
        }

        $feeAmount = null;

        if ($encounter->appointment_id !== null) {
            $appointment = Appointment::find($encounter->appointment_id);

            if ($appointment !== null && (float) $appointment->fee_amount > 0) {
                $feeAmount = (float) $appointment->fee_amount;
            }
        }

        $service = Service::query()
            ->forHospital($context->hospitalId())
            ->where('code', 'OPDCONSULT')
            ->first();

        if ($feeAmount === null && $service === null) {
            return;
        }

        $item = $service !== null
            ? ['service_id' => $service->id, 'quantity' => 1, 'unit_rate' => $feeAmount ?? (float) $service->base_rate]
            : ['description' => 'OPD Consultation', 'quantity' => 1, 'unit_rate' => $feeAmount, 'is_tax_exempt' => true];

        $calculation = $invoiceCalculator->calculate($context->hospitalId(), [$item]);

        $invoice = Invoice::create([
            'hospital_id' => $context->hospitalId(),
            'branch_id' => $encounter->branch_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'invoice_number' => $invoiceNumberGenerator->nextForHospital($context->hospital),
            'invoice_type' => 'opd',
            'payer_type' => 'self',
            'status' => 'draft',
            ...$calculation['totals'],
            'paid_total' => 0,
            'balance_total' => $calculation['totals']['grand_total'],
            'created_by' => $request->user()->id,
        ]);

        foreach ($calculation['items'] as $line) {
            $invoice->items()->create($line);
        }
    }
}
