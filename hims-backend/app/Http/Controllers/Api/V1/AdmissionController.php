<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceCalculator;
use App\Domain\Billing\Services\InvoiceNumberGenerator;
use App\Domain\IPD\Models\Admission;
use App\Domain\IPD\Models\AdmissionClearance;
use App\Domain\IPD\Models\Bed;
use App\Domain\IPD\Models\BedTransfer;
use App\Domain\IPD\Models\IpdChargeLine;
use App\Domain\IPD\Models\IpdNursingNote;
use App\Domain\IPD\Models\Ward;
use App\Domain\IPD\Services\AdmissionClearanceBootstrapper;
use App\Domain\IPD\Services\AdmissionNumberGenerator;
use App\Domain\IPD\Services\DischargePackageBuilder;
use App\Domain\IPD\Services\IpdChargePoster;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdmissionDischargeRequest;
use App\Http\Requests\AdmissionRequest;
use App\Http\Requests\AdmissionTransferRequest;
use App\Http\Resources\AdmissionClearanceResource;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\BedResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\IpdChargeLineResource;
use App\Http\Resources\IpdNursingNoteResource;
use App\Http\Resources\WardResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionController extends Controller
{
    public function wards(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeIpd($request);

        $wards = Ward::query()
            ->forHospital($context->hospitalId())
            ->when($context->branchId(), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: WardResource::collection($wards),
            message: 'Wards loaded',
        );
    }

    public function board(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeIpd($request);

        $beds = Bed::query()
            ->forHospital($context->hospitalId())
            ->when($context->branchId(), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->with([
                'ward',
                'currentAdmission.patient:id,uhid,first_name,middle_name,last_name,mobile',
            ])
            ->when($request->filled('ward_id'), fn (Builder $query) => $query->where('ward_id', $request->integer('ward_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->orderBy('ward_id')
            ->orderBy('bed_number')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: BedResource::collection($beds),
            message: 'Bed board loaded',
        );
    }

    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeIpd($request);

        $admissions = Admission::query()
            ->forHospital($context->hospitalId())
            ->with(['patient:id,uhid,first_name,middle_name,last_name,mobile', 'ward', 'bed', 'admittingDoctor:id,name', 'clearances'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('patient_id'), fn (Builder $query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when(! $request->filled('status'), fn (Builder $query) => $query->where('status', 'admitted'))
            ->latest('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: AdmissionResource::collection($admissions),
            message: 'Admissions loaded',
        );
    }

    public function store(
        AdmissionRequest $request,
        TenantContext $context,
        AdmissionNumberGenerator $generator,
        AdmissionClearanceBootstrapper $clearanceBootstrapper,
        IpdChargePoster $chargePoster,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeIpd($request);

        $data = $request->validated();
        $patient = Patient::query()->forHospital($context->hospitalId())->findOrFail($data['patient_id']);
        $bed = Bed::query()->forHospital($context->hospitalId())->with('ward')->findOrFail($data['bed_id']);

        abort_unless($bed->status === 'available' && $bed->current_admission_id === null, 422, 'Selected bed is not available.');
        abort_if(
            Admission::query()
                ->forHospital($context->hospitalId())
                ->where('patient_id', $patient->id)
                ->where('status', 'admitted')
                ->exists(),
            422,
            'Patient already has an active admission.',
        );

        $admission = DB::transaction(function () use ($data, $request, $context, $generator, $patient, $bed, $clearanceBootstrapper, $chargePoster, $auditLogger): Admission {
            $lockedBed = Bed::query()->whereKey($bed->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedBed->status === 'available' && $lockedBed->current_admission_id === null, 422, 'Selected bed is not available.');

            $admission = Admission::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $lockedBed->branch_id,
                'admission_number' => $generator->nextForHospital($context->hospital),
                'patient_id' => $patient->id,
                'admitting_doctor_user_id' => $data['admitting_doctor_user_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'ward_id' => $lockedBed->ward_id,
                'bed_id' => $lockedBed->id,
                'admitted_at' => isset($data['admitted_at']) ? Carbon::parse($data['admitted_at']) : now(),
                'provisional_diagnosis' => $data['provisional_diagnosis'] ?? null,
                'attendant_name' => $data['attendant_name'] ?? null,
                'attendant_mobile' => $data['attendant_mobile'] ?? null,
                'attendant_relation' => $data['attendant_relation'] ?? null,
                'status' => 'admitted',
                'created_by' => $request->user()->id,
            ]);

            $lockedBed->update([
                'status' => 'occupied',
                'current_admission_id' => $admission->id,
            ]);

            $clearanceBootstrapper->seedPending($admission);
            $chargePoster->ensureBedDayCharges($admission, $request->user()->id);

            $auditLogger->record(
                request: $request,
                module: 'ipd',
                event: 'admission.created',
                auditable: $admission,
                new: $admission->toArray(),
            );

            return $admission;
        });

        return ApiResponse::success(
            request: $request,
            data: new AdmissionResource($admission->load(['patient', 'ward', 'bed', 'admittingDoctor', 'clearances'])),
            message: 'Patient admitted',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, Admission $admission): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new AdmissionResource($admission->load(['patient', 'ward', 'bed', 'admittingDoctor', 'clearances'])),
            message: 'Admission loaded',
        );
    }

    public function nursingNotes(Request $request, TenantContext $context, Admission $admission): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);

        $notes = $admission->nursingNotes()->with('recordedBy:id,name')->latest('recorded_at')->limit(100)->get();

        return ApiResponse::success(
            request: $request,
            data: IpdNursingNoteResource::collection($notes),
            message: 'Nursing notes loaded',
        );
    }

    public function storeNursingNote(Request $request, TenantContext $context, Admission $admission, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);
        abort_unless($admission->isActive(), 422, 'Nursing notes can only be added to active admissions.');

        $data = $request->validate([
            'recorded_at' => ['nullable', 'date'],
            'vitals' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless(($data['vitals'] ?? null) || ($data['notes'] ?? null), 422, 'Provide vitals or notes.');

        $note = IpdNursingNote::create([
            'hospital_id' => $context->hospitalId(),
            'admission_id' => $admission->id,
            'recorded_at' => isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now(),
            'vitals' => $data['vitals'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'ipd',
            event: 'admission.nursing_note',
            auditable: $admission,
            new: $note->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new IpdNursingNoteResource($note->load('recordedBy')),
            message: 'Nursing note recorded',
            status: 201,
        );
    }

    public function charges(Request $request, TenantContext $context, Admission $admission, IpdChargePoster $chargePoster): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);

        if ($admission->isActive()) {
            $chargePoster->ensureBedDayCharges($admission, $request->user()->id);
        }

        $lines = $admission->chargeLines()->orderBy('charge_date')->orderBy('id')->get();

        return ApiResponse::success(
            request: $request,
            data: IpdChargeLineResource::collection($lines),
            message: 'IPD charges loaded',
        );
    }

    public function postDailyCharges(Request $request, TenantContext $context, Admission $admission, IpdChargePoster $chargePoster): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);
        abort_unless($admission->isActive(), 422, 'Daily charges can only be posted for active admissions.');

        $created = $chargePoster->ensureBedDayCharges($admission, $request->user()->id);
        $lines = $admission->chargeLines()->orderBy('charge_date')->orderBy('id')->get();

        return ApiResponse::success(
            request: $request,
            data: [
                'created_count' => count($created),
                'charges' => IpdChargeLineResource::collection($lines),
            ],
            message: 'Daily charges posted',
        );
    }

    public function clearances(Request $request, TenantContext $context, Admission $admission, AdmissionClearanceBootstrapper $bootstrapper): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);

        $bootstrapper->seedPending($admission);

        return ApiResponse::success(
            request: $request,
            data: AdmissionClearanceResource::collection(
                $admission->clearances()->with('clearedBy:id,name')->orderBy('clearance_type')->get()
            ),
            message: 'Clearances loaded',
        );
    }

    public function updateClearance(Request $request, TenantContext $context, Admission $admission, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);
        abort_unless($admission->isActive(), 422, 'Clearances can only be updated for active admissions.');

        $data = $request->validate([
            'clearance_type' => ['required', 'in:'.implode(',', AdmissionClearance::TYPES)],
            'status' => ['required', 'in:cleared,waived,pending'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $clearance = AdmissionClearance::query()->firstOrCreate(
            [
                'admission_id' => $admission->id,
                'clearance_type' => $data['clearance_type'],
            ],
            [
                'hospital_id' => $context->hospitalId(),
                'status' => 'pending',
            ],
        );

        $before = $clearance->toArray();
        $clearance->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $clearance->notes,
            'cleared_by' => $data['status'] === 'pending' ? null : $request->user()->id,
            'cleared_at' => $data['status'] === 'pending' ? null : now(),
        ]);

        $auditLogger->record(
            request: $request,
            module: 'ipd',
            event: 'admission.clearance_updated',
            auditable: $admission,
            old: $before,
            new: $clearance->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new AdmissionClearanceResource($clearance->refresh()->load('clearedBy')),
            message: 'Clearance updated',
        );
    }

    public function transfer(
        AdmissionTransferRequest $request,
        TenantContext $context,
        Admission $admission,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);
        abort_unless($admission->isActive(), 422, 'Only active admissions can be transferred.');

        $data = $request->validated();

        $admission = DB::transaction(function () use ($data, $request, $context, $admission, $auditLogger): Admission {
            $admission = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();
            $fromBed = Bed::query()->whereKey($admission->bed_id)->lockForUpdate()->firstOrFail();
            $toBed = Bed::query()->forHospital($context->hospitalId())->whereKey($data['bed_id'])->lockForUpdate()->firstOrFail();

            abort_if($toBed->id === $fromBed->id, 422, 'Patient is already on the selected bed.');
            abort_unless($toBed->status === 'available' && $toBed->current_admission_id === null, 422, 'Target bed is not available.');

            $before = $admission->toArray();

            BedTransfer::create([
                'hospital_id' => $context->hospitalId(),
                'admission_id' => $admission->id,
                'from_bed_id' => $fromBed->id,
                'to_bed_id' => $toBed->id,
                'from_ward_id' => $fromBed->ward_id,
                'to_ward_id' => $toBed->ward_id,
                'reason' => $data['reason'] ?? null,
                'transferred_by' => $request->user()->id,
                'transferred_at' => now(),
            ]);

            $fromBed->update([
                'status' => 'available',
                'current_admission_id' => null,
            ]);

            $toBed->update([
                'status' => 'occupied',
                'current_admission_id' => $admission->id,
            ]);

            $admission->update([
                'ward_id' => $toBed->ward_id,
                'bed_id' => $toBed->id,
            ]);

            $auditLogger->record(
                request: $request,
                module: 'ipd',
                event: 'admission.transferred',
                auditable: $admission,
                old: $before,
                new: $admission->fresh()->toArray(),
            );

            return $admission;
        });

        return ApiResponse::success(
            request: $request,
            data: new AdmissionResource($admission->refresh()->load(['patient', 'ward', 'bed', 'admittingDoctor'])),
            message: 'Patient transferred',
        );
    }

    public function discharge(
        AdmissionDischargeRequest $request,
        TenantContext $context,
        Admission $admission,
        DischargePackageBuilder $packageBuilder,
        InvoiceNumberGenerator $invoiceNumberGenerator,
        InvoiceCalculator $calculator,
        AdmissionClearanceBootstrapper $clearanceBootstrapper,
        IpdChargePoster $chargePoster,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeIpd($request);
        abort_unless($admission->hospital_id === $context->hospitalId(), 404);
        abort_unless($admission->isActive(), 422, 'Only active admissions can be discharged.');
        abort_unless($clearanceBootstrapper->allCleared($admission), 422, 'All clearances (nursing, diagnostics, billing, ward) must be cleared or waived before exit.');

        $data = $request->validated();
        $createInvoice = (bool) ($data['create_invoice'] ?? true);

        $result = DB::transaction(function () use (
            $data,
            $request,
            $context,
            $admission,
            $packageBuilder,
            $invoiceNumberGenerator,
            $calculator,
            $chargePoster,
            $auditLogger,
            $createInvoice,
        ): array {
            $admission = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();
            abort_unless($admission->isActive(), 422, 'Only active admissions can be discharged.');

            $before = $admission->toArray();
            $outcome = $data['outcome'];
            $status = match ($outcome) {
                'lama' => 'lama',
                'dopr' => 'dopr',
                'death' => 'deceased',
                default => 'discharged',
            };

            $admission->discharge_outcome = $outcome;
            $admission->discharge_summary = $data['discharge_summary'];
            $admission->discharged_at = now();
            $admission->discharged_by = $request->user()->id;
            $admission->death_at = $outcome === 'death'
                ? Carbon::parse($data['death_at'] ?? now())
                : null;
            $admission->status = $status;

            $package = $packageBuilder->build($admission);
            $admission->discharge_package = $package;

            $documentType = match ($outcome) {
                'lama' => 'lama_summary',
                'dopr' => 'dopr_summary',
                'death' => 'death_summary',
                default => 'discharge_summary',
            };

            $document = PatientDocument::create([
                'hospital_id' => $context->hospitalId(),
                'patient_id' => $admission->patient_id,
                'document_type' => $documentType,
                'title' => sprintf('%s — %s', strtoupper($outcome), $admission->admission_number),
                'metadata' => [
                    'admission_id' => $admission->id,
                    'admission_number' => $admission->admission_number,
                    'outcome' => $outcome,
                    'discharge_summary' => $data['discharge_summary'],
                    'package' => $package,
                ],
                'uploaded_by' => $request->user()->id,
            ]);

            $admission->discharge_document_id = $document->id;

            $invoice = null;
            if ($createInvoice && $admission->invoice_id === null) {
                $chargePoster->ensureBedDayCharges($admission, $request->user()->id);
                $invoice = $this->createIpdDraftInvoice($admission, $request, $context, $invoiceNumberGenerator, $calculator);
                $admission->invoice_id = $invoice->id;
            }

            $admission->save();

            $bed = Bed::query()->whereKey($admission->bed_id)->lockForUpdate()->firstOrFail();
            $bed->update([
                'status' => 'available',
                'current_admission_id' => null,
            ]);

            $auditLogger->record(
                request: $request,
                module: 'ipd',
                event: 'admission.exited',
                auditable: $admission,
                old: $before,
                new: $admission->fresh()->toArray(),
                metadata: ['outcome' => $outcome, 'package_counts' => $package['counts']],
            );

            return [
                'admission' => $admission,
                'invoice' => $invoice,
            ];
        });

        return ApiResponse::success(
            request: $request,
            data: [
                'admission' => new AdmissionResource($result['admission']->refresh()->load(['patient', 'ward', 'bed', 'admittingDoctor', 'clearances'])),
                'invoice' => $result['invoice']
                    ? new InvoiceResource($result['invoice']->load(['patient', 'items', 'payments']))
                    : null,
            ],
            message: 'Exit documented',
        );
    }

    private function createIpdDraftInvoice(
        Admission $admission,
        Request $request,
        TenantContext $context,
        InvoiceNumberGenerator $invoiceNumberGenerator,
        InvoiceCalculator $calculator,
    ): Invoice {
        $openLines = IpdChargeLine::query()
            ->where('admission_id', $admission->id)
            ->where('status', 'open')
            ->orderBy('charge_date')
            ->orderBy('id')
            ->get();

        abort_if($openLines->isEmpty(), 422, 'No open IPD charges available to bill.');

        $lineInput = $openLines->map(fn (IpdChargeLine $line) => [
            'service_id' => $line->service_id,
            'description' => sprintf('%s (%s)', $line->description, $line->charge_date?->toDateString()),
            'quantity' => (float) $line->quantity,
            'unit_rate' => (float) $line->unit_rate,
            'billable_type' => $line::class,
            'billable_id' => $line->id,
        ])->all();

        $calculation = $calculator->calculate($context->hospitalId(), $lineInput);

        $invoice = Invoice::create([
            'hospital_id' => $context->hospitalId(),
            'branch_id' => $admission->branch_id,
            'patient_id' => $admission->patient_id,
            'invoice_number' => $invoiceNumberGenerator->nextForHospital($context->hospital),
            'invoice_type' => 'ipd',
            'payer_type' => 'self',
            'status' => 'draft',
            ...$calculation['totals'],
            'paid_total' => 0,
            'balance_total' => $calculation['totals']['grand_total'],
            'created_by' => $request->user()->id,
        ]);

        foreach ($calculation['items'] as $index => $line) {
            $source = $openLines[$index];
            $invoice->items()->create([
                ...$line,
                'billable_type' => $source::class,
                'billable_id' => $source->id,
            ]);
        }

        IpdChargeLine::query()
            ->whereIn('id', $openLines->pluck('id'))
            ->update([
                'status' => 'invoiced',
                'invoice_id' => $invoice->id,
            ]);

        return $invoice;
    }

    private function authorizeIpd(Request $request): void
    {
        abort_unless($request->user()->can('ipd.manage'), 403);
    }
}
