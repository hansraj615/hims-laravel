<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceCalculator;
use App\Domain\Billing\Services\InvoiceNumberGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $invoices = Invoice::query()
            ->forHospital($context->hospitalId())
            ->with(['patient:id,uhid,first_name,middle_name,last_name,mobile', 'items'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('patient_id'), fn (Builder $query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('created_at', $request->date('date')))
            ->latest('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: InvoiceResource::collection($invoices),
            message: 'Invoices loaded',
        );
    }

    public function store(
        InvoiceRequest $request,
        TenantContext $context,
        InvoiceNumberGenerator $generator,
        InvoiceCalculator $calculator,
    ): JsonResponse {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data, $request, $context, $generator, $calculator): Invoice {
            $calculation = $calculator->calculate($context->hospitalId(), $data['items']);

            $invoice = Invoice::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $data['branch_id'] ?? $context->branchId(),
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'invoice_number' => $generator->nextForHospital($context->hospital),
                'invoice_type' => $data['invoice_type'] ?? 'opd',
                'payer_type' => $data['payer_type'] ?? 'self',
                'scheme_type' => $data['scheme_type'] ?? null,
                'tpa_name' => $data['tpa_name'] ?? null,
                'claim_reference' => $data['claim_reference'] ?? null,
                'status' => 'draft',
                ...$calculation['totals'],
                'paid_total' => 0,
                'balance_total' => $calculation['totals']['grand_total'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($calculation['items'] as $line) {
                $invoice->items()->create($line);
            }

            return $invoice;
        });

        return ApiResponse::success(
            request: $request,
            data: new InvoiceResource($invoice->load(['patient', 'items', 'payments'])),
            message: 'Invoice created',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new InvoiceResource($invoice->load(['patient', 'items', 'payments'])),
            message: 'Invoice loaded',
        );
    }

    public function update(
        InvoiceRequest $request,
        TenantContext $context,
        Invoice $invoice,
        InvoiceCalculator $calculator,
    ): JsonResponse {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be updated.');

        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $context, $invoice, $calculator): void {
            $calculation = $calculator->calculate($context->hospitalId(), $data['items']);

            $invoice->update([
                'patient_id' => $data['patient_id'] ?? $invoice->patient_id,
                'encounter_id' => array_key_exists('encounter_id', $data) ? $data['encounter_id'] : $invoice->encounter_id,
                'invoice_type' => $data['invoice_type'] ?? $invoice->invoice_type,
                'payer_type' => $data['payer_type'] ?? $invoice->payer_type,
                'scheme_type' => array_key_exists('scheme_type', $data) ? $data['scheme_type'] : $invoice->scheme_type,
                'tpa_name' => array_key_exists('tpa_name', $data) ? $data['tpa_name'] : $invoice->tpa_name,
                'claim_reference' => array_key_exists('claim_reference', $data) ? $data['claim_reference'] : $invoice->claim_reference,
                ...$calculation['totals'],
                'balance_total' => round($calculation['totals']['grand_total'] - (float) $invoice->paid_total, 2),
                'updated_by' => $request->user()->id,
            ]);

            $invoice->items()->delete();

            foreach ($calculation['items'] as $line) {
                $invoice->items()->create($line);
            }
        });

        return ApiResponse::success(
            request: $request,
            data: new InvoiceResource($invoice->refresh()->load(['patient', 'items', 'payments'])),
            message: 'Invoice updated',
        );
    }

    public function finalize(Request $request, TenantContext $context, Invoice $invoice, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be finalized.');

        $old = ['status' => $invoice->status];

        $invoice->update([
            'status' => 'issued',
            'billed_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'billing',
            event: 'invoice.finalized',
            auditable: $invoice,
            old: $old,
            new: ['status' => $invoice->status, 'billed_at' => $invoice->billed_at?->toISOString(), 'grand_total' => $invoice->grand_total],
        );

        return ApiResponse::success(
            request: $request,
            data: new InvoiceResource($invoice->refresh()->load(['patient', 'items', 'payments'])),
            message: 'Invoice finalized',
        );
    }

    public function voidInvoice(Request $request, TenantContext $context, Invoice $invoice, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);
        abort_if($invoice->status === 'voided', 422, 'Invoice is already voided.');
        abort_if((float) $invoice->paid_total > 0, 422, 'Paid invoices cannot be voided.');

        $old = ['status' => $invoice->status];

        $invoice->update([
            'status' => 'voided',
            'updated_by' => $request->user()->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'billing',
            event: 'invoice.voided',
            auditable: $invoice,
            old: $old,
            new: ['status' => 'voided'],
        );

        return ApiResponse::success(
            request: $request,
            data: new InvoiceResource($invoice->refresh()->load(['patient', 'items', 'payments'])),
            message: 'Invoice voided',
        );
    }
}
