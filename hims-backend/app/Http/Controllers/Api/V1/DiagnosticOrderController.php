<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Service;
use App\Domain\Billing\Services\InvoiceCalculator;
use App\Domain\Billing\Services\InvoiceNumberGenerator;
use App\Domain\Diagnostics\Models\DiagnosticOrder;
use App\Domain\Diagnostics\Services\DiagnosticOrderNumberGenerator;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiagnosticOrderRequest;
use App\Http\Requests\DiagnosticOrderResultRequest;
use App\Http\Resources\DiagnosticOrderResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ServiceResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiagnosticOrderController extends Controller
{
    private const BILLABLE_STATUSES = ['ordered', 'sample_collected', 'in_progress', 'result_ready'];

    private const RESULTABLE_STATUSES = ['ordered', 'sample_collected', 'in_progress'];

    private const COLLECTABLE_STATUSES = ['ordered', 'in_progress'];

    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeDiagnosticsAccess($request);

        $orders = DiagnosticOrder::query()
            ->forHospital($context->hospitalId())
            ->with(['patient:id,uhid,first_name,middle_name,last_name,mobile', 'items', 'orderedBy:id,name'])
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('patient_id'), fn (Builder $query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('ordered_at', $request->date('date')))
            ->latest('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: DiagnosticOrderResource::collection($orders),
            message: 'Diagnostic orders loaded',
        );
    }

    public function catalog(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeOrder($request);

        $request->validate([
            'category' => ['nullable', Rule::in(DiagnosticOrder::CATEGORIES)],
        ]);

        $services = Service::query()
            ->forHospital($context->hospitalId())
            ->where('status', 'active')
            ->whereIn('category', DiagnosticOrder::CATEGORIES)
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: ServiceResource::collection($services),
            message: 'Diagnostic catalog loaded',
        );
    }

    public function store(
        DiagnosticOrderRequest $request,
        TenantContext $context,
        DiagnosticOrderNumberGenerator $generator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeOrder($request);

        $data = $request->validated();
        $patient = Patient::query()->forHospital($context->hospitalId())->findOrFail($data['patient_id']);
        $branchId = $context->branchId() ?? $patient->branch_id;
        abort_unless($branchId !== null, 422, 'Branch context is required.');

        $serviceIds = collect($data['items'])->pluck('service_id')->unique()->all();
        $services = Service::query()
            ->forHospital($context->hospitalId())
            ->where('status', 'active')
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        abort_unless($services->count() === count($serviceIds), 422, 'One or more services are invalid for this hospital.');

        foreach ($services as $service) {
            abort_unless($service->category === $data['category'], 422, "Service {$service->code} does not match order category {$data['category']}.");
        }

        $order = DB::transaction(function () use ($data, $request, $context, $generator, $services, $patient, $branchId, $auditLogger): DiagnosticOrder {
            $order = DiagnosticOrder::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $branchId,
                'order_number' => $generator->nextForHospital($context->hospital),
                'patient_id' => $patient->id,
                'encounter_id' => $data['encounter_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'routine',
                'status' => 'ordered',
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'ordered_by' => $request->user()->id,
                'ordered_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $service = $services[$item['service_id']];
                $order->items()->create([
                    'service_id' => $service->id,
                    'service_code' => $service->code,
                    'service_name' => $service->name,
                    'category' => $service->category,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_rate' => $service->base_rate,
                    'status' => 'ordered',
                ]);
            }

            $auditLogger->record(
                request: $request,
                module: 'diagnostics',
                event: 'diagnostic_order.created',
                auditable: $order,
                new: $order->load('items')->toArray(),
            );

            return $order;
        });

        return ApiResponse::success(
            request: $request,
            data: new DiagnosticOrderResource($order->load(['patient', 'items', 'orderedBy'])),
            message: 'Diagnostic order created',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, DiagnosticOrder $diagnosticOrder): JsonResponse
    {
        $this->authorizeDiagnosticsAccess($request);
        abort_unless($diagnosticOrder->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new DiagnosticOrderResource($diagnosticOrder->load(['patient', 'items', 'orderedBy'])),
            message: 'Diagnostic order loaded',
        );
    }

    public function cancel(Request $request, TenantContext $context, DiagnosticOrder $diagnosticOrder, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeOrder($request);
        abort_unless($diagnosticOrder->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($diagnosticOrder->status, ['ordered', 'sample_collected', 'in_progress'], true), 422, 'Only open orders can be cancelled.');

        $before = $diagnosticOrder->toArray();
        $diagnosticOrder->update(['status' => 'cancelled']);
        $diagnosticOrder->items()->update(['status' => 'cancelled']);

        $auditLogger->record(
            request: $request,
            module: 'diagnostics',
            event: 'diagnostic_order.cancelled',
            auditable: $diagnosticOrder,
            old: $before,
            new: $diagnosticOrder->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DiagnosticOrderResource($diagnosticOrder->refresh()->load(['patient', 'items', 'orderedBy'])),
            message: 'Diagnostic order cancelled',
        );
    }

    public function collect(Request $request, TenantContext $context, DiagnosticOrder $diagnosticOrder, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeResult($request);
        abort_unless($diagnosticOrder->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($diagnosticOrder->status, self::COLLECTABLE_STATUSES, true), 422, 'Only ordered orders can be marked collected.');

        $before = $diagnosticOrder->toArray();
        $diagnosticOrder->update([
            'status' => 'sample_collected',
            'collected_by' => $request->user()->id,
            'collected_at' => now(),
        ]);
        $diagnosticOrder->items()->update(['status' => 'sample_collected']);

        $auditLogger->record(
            request: $request,
            module: 'diagnostics',
            event: 'diagnostic_order.collected',
            auditable: $diagnosticOrder,
            old: $before,
            new: $diagnosticOrder->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DiagnosticOrderResource($diagnosticOrder->refresh()->load(['patient', 'items', 'orderedBy'])),
            message: 'Sample / preparation marked collected',
        );
    }

    public function result(
        DiagnosticOrderResultRequest $request,
        TenantContext $context,
        DiagnosticOrder $diagnosticOrder,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeResult($request);
        abort_unless($diagnosticOrder->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($diagnosticOrder->status, self::RESULTABLE_STATUSES, true), 422, 'Results can only be entered for open orders.');

        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $request, $context, $diagnosticOrder, $auditLogger): DiagnosticOrder {
            $before = $diagnosticOrder->toArray();

            $documentType = match ($diagnosticOrder->category) {
                'radiology' => 'radiology_report',
                'procedure' => 'procedure_report',
                default => 'pathology_report',
            };

            $document = PatientDocument::create([
                'hospital_id' => $context->hospitalId(),
                'patient_id' => $diagnosticOrder->patient_id,
                'document_type' => $documentType,
                'title' => sprintf('%s report %s', ucfirst($diagnosticOrder->category), $diagnosticOrder->order_number),
                'metadata' => [
                    'diagnostic_order_id' => $diagnosticOrder->id,
                    'order_number' => $diagnosticOrder->order_number,
                    'category' => $diagnosticOrder->category,
                    'result_summary' => $data['result_summary'],
                    'result_payload' => $data['result_payload'] ?? null,
                ],
                'uploaded_by' => $request->user()->id,
            ]);

            $diagnosticOrder->update([
                'status' => 'result_ready',
                'result_summary' => $data['result_summary'],
                'result_payload' => $data['result_payload'] ?? null,
                'resulted_by' => $request->user()->id,
                'resulted_at' => now(),
                'patient_document_id' => $document->id,
            ]);
            $diagnosticOrder->items()->update(['status' => 'result_ready']);

            $auditLogger->record(
                request: $request,
                module: 'diagnostics',
                event: 'diagnostic_order.resulted',
                auditable: $diagnosticOrder,
                old: $before,
                new: $diagnosticOrder->fresh()->load('items')->toArray(),
            );

            return $diagnosticOrder;
        });

        return ApiResponse::success(
            request: $request,
            data: new DiagnosticOrderResource($order->refresh()->load(['patient', 'items', 'orderedBy'])),
            message: 'Diagnostic result recorded',
        );
    }

    public function bill(
        Request $request,
        TenantContext $context,
        DiagnosticOrder $diagnosticOrder,
        InvoiceNumberGenerator $invoiceNumberGenerator,
        InvoiceCalculator $calculator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        abort_unless($request->user()->can('billing.manage'), 403);
        abort_unless($diagnosticOrder->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($diagnosticOrder->status, self::BILLABLE_STATUSES, true), 422, 'Order cannot be billed in its current status.');
        abort_unless($diagnosticOrder->invoice_id === null, 422, 'Order is already linked to an invoice.');

        $diagnosticOrder->load('items');

        $invoice = DB::transaction(function () use ($request, $context, $diagnosticOrder, $invoiceNumberGenerator, $calculator, $auditLogger): Invoice {
            $lineInput = $diagnosticOrder->items->map(fn ($item) => [
                'service_id' => $item->service_id,
                'description' => $item->service_name,
                'quantity' => (float) $item->quantity,
                'unit_rate' => (float) $item->unit_rate,
                'billable_type' => $item::class,
                'billable_id' => $item->id,
            ])->all();

            $calculation = $calculator->calculate($context->hospitalId(), $lineInput);

            $invoice = Invoice::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $diagnosticOrder->branch_id,
                'patient_id' => $diagnosticOrder->patient_id,
                'encounter_id' => $diagnosticOrder->encounter_id,
                'invoice_number' => $invoiceNumberGenerator->nextForHospital($context->hospital),
                'invoice_type' => $diagnosticOrder->category,
                'payer_type' => 'self',
                'status' => 'draft',
                ...$calculation['totals'],
                'paid_total' => 0,
                'balance_total' => $calculation['totals']['grand_total'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($calculation['items'] as $index => $line) {
                $source = $diagnosticOrder->items[$index];
                $invoice->items()->create([
                    ...$line,
                    'billable_type' => $source::class,
                    'billable_id' => $source->id,
                ]);
            }

            $before = $diagnosticOrder->toArray();
            $diagnosticOrder->update([
                'status' => 'billed',
                'invoice_id' => $invoice->id,
            ]);
            $diagnosticOrder->items()->update(['status' => 'billed']);

            $auditLogger->record(
                request: $request,
                module: 'diagnostics',
                event: 'diagnostic_order.billed',
                auditable: $diagnosticOrder,
                old: $before,
                new: $diagnosticOrder->fresh()->toArray(),
            );

            return $invoice;
        });

        return ApiResponse::success(
            request: $request,
            data: [
                'order' => new DiagnosticOrderResource($diagnosticOrder->refresh()->load(['patient', 'items', 'orderedBy'])),
                'invoice' => new InvoiceResource($invoice->load(['patient', 'items', 'payments'])),
            ],
            message: 'Draft invoice created from diagnostic order',
            status: 201,
        );
    }

    private function authorizeDiagnosticsAccess(Request $request): void
    {
        abort_unless(
            $request->user()->canAny(['diagnostics.order', 'diagnostics.result', 'billing.manage']),
            403,
        );
    }

    private function authorizeOrder(Request $request): void
    {
        abort_unless($request->user()->can('diagnostics.order'), 403);
    }

    private function authorizeResult(Request $request): void
    {
        abort_unless($request->user()->can('diagnostics.result'), 403);
    }
}
