<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\CashierDaybook;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\ReceiptNumberGenerator;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private const NON_PAYABLE_STATUSES = ['draft', 'voided'];

    public function store(
        PaymentRequest $request,
        TenantContext $context,
        Invoice $invoice,
        ReceiptNumberGenerator $generator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);
        abort_if(in_array($invoice->status, self::NON_PAYABLE_STATUSES, true), 422, 'Payments cannot be posted for this invoice status.');

        $data = $request->validated();
        $amount = round((float) $data['amount'], 2);

        $payment = DB::transaction(function () use ($invoice, $data, $amount, $context, $request, $generator): Payment {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            abort_if(in_array($lockedInvoice->status, self::NON_PAYABLE_STATUSES, true), 422, 'Payments cannot be posted for this invoice status.');

            $balance = (float) $lockedInvoice->balance_total;
            abort_if($amount > $balance + 0.01, 422, 'Payment amount exceeds the outstanding balance.');

            $branchId = $context->branchId() ?? $lockedInvoice->branch_id;
            $daybook = $this->ensureDaybook($context->hospitalId(), $branchId, $request->user()->id);

            $payment = Payment::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $branchId,
                'invoice_id' => $lockedInvoice->id,
                'patient_id' => $lockedInvoice->patient_id,
                'cashier_daybook_id' => $daybook->id,
                'receipt_number' => $generator->nextForHospital($context->hospital),
                'payment_type' => 'receipt',
                'payment_mode' => $data['payment_mode'],
                'amount' => $amount,
                'status' => 'posted',
                'reference_number' => $data['reference_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'paid_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            $newPaidTotal = round((float) $lockedInvoice->paid_total + $amount, 2);
            $newBalance = round((float) $lockedInvoice->grand_total - $newPaidTotal, 2);
            $newStatus = $newBalance <= 0.01 ? 'paid' : 'partially_paid';

            $lockedInvoice->update([
                'paid_total' => $newPaidTotal,
                'balance_total' => max($newBalance, 0),
                'status' => $newStatus,
                'cashier_daybook_id' => $lockedInvoice->cashier_daybook_id ?? $daybook->id,
            ]);

            if ($data['payment_mode'] === 'cash') {
                $daybook->increment('cash_collected', $amount);
            }

            return $payment;
        });

        $invoice->refresh();

        $auditLogger->record(
            request: $request,
            module: 'billing',
            event: 'payment.posted',
            auditable: $payment,
            new: $payment->only(['receipt_number', 'amount', 'payment_mode', 'status']),
            metadata: ['invoice_id' => $invoice->id, 'invoice_status' => $invoice->status],
        );

        app(NotificationDispatcher::class)->dispatch(
            hospitalId: $context->hospitalId(),
            branchId: $context->branchId() ?? $invoice->branch_id,
            templateCode: 'payment.received',
            channel: 'in_app',
            recipient: $request->user()->email,
            context: [
                'amount' => number_format($amount, 2),
                'receipt_number' => $payment->receipt_number,
                'invoice_number' => $invoice->invoice_number,
            ],
            patientId: $invoice->patient_id,
            userId: $request->user()->id,
            related: $payment,
        );

        return ApiResponse::success(
            request: $request,
            data: new PaymentResource($payment),
            message: 'Payment posted',
            status: 201,
        );
    }

    public function receipt(Request $request, TenantContext $context, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->hospital_id === $context->hospitalId(), 404);

        $invoice->load(['patient', 'items', 'payments', 'hospital', 'branch']);

        $payload = [
            'hospital' => [
                'name' => $invoice->hospital?->name,
                'gstin' => $invoice->hospital?->gstin,
                'phone' => $invoice->hospital?->phone,
            ],
            'branch' => [
                'name' => $invoice->branch?->name,
                'address' => $invoice->branch?->address,
            ],
            'patient' => $invoice->patient === null ? null : [
                'uhid' => $invoice->patient->uhid,
                'name' => $invoice->patient->full_name,
                'mobile' => $invoice->patient->mobile,
            ],
            'invoice' => new InvoiceResource($invoice),
            'items' => InvoiceItemResource::collection($invoice->items),
            'payments' => PaymentResource::collection($invoice->payments),
            'gst_summary' => [
                'taxable_total' => $invoice->taxable_total,
                'cgst_total' => $invoice->cgst_total,
                'sgst_total' => $invoice->sgst_total,
                'igst_total' => $invoice->igst_total,
                'grand_total' => $invoice->grand_total,
            ],
        ];

        return ApiResponse::success(
            request: $request,
            data: $payload,
            message: 'Receipt generated',
        );
    }

    private function ensureDaybook(int $hospitalId, int $branchId, int $userId): CashierDaybook
    {
        // A plain firstOrCreate() compares the raw date string against the "date" cast
        // column, which Eloquent persists with a full timestamp suffix; whereDate()
        // performs the comparison in SQL so it matches regardless of stored format.
        $daybook = CashierDaybook::query()
            ->where('hospital_id', $hospitalId)
            ->where('branch_id', $branchId)
            ->where('cashier_user_id', $userId)
            ->whereDate('business_date', today())
            ->lockForUpdate()
            ->first();

        if ($daybook !== null) {
            return $daybook;
        }

        return CashierDaybook::create([
            'hospital_id' => $hospitalId,
            'branch_id' => $branchId,
            'cashier_user_id' => $userId,
            'business_date' => today()->toDateString(),
            'status' => 'open',
            'opening_cash' => 0,
            'cash_collected' => 0,
            'cash_refunded' => 0,
            'opened_at' => now(),
        ]);
    }
}
