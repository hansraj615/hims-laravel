<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Service;

class InvoiceCalculator
{
    /**
     * Compute server-authoritative line items and invoice totals from raw item input.
     * Client-supplied totals/grand totals are never trusted; only rates and quantities
     * are read from the request (falling back to the catalog Service when referenced).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function calculate(int $hospitalId, array $items): array
    {
        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxableTotal = 0.0;
        $cgstTotal = 0.0;
        $sgstTotal = 0.0;
        $igstTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($items as $item) {
            $service = null;

            if (! empty($item['service_id'])) {
                $service = Service::query()
                    ->forHospital($hospitalId)
                    ->find($item['service_id']);
            }

            $quantity = (float) ($item['quantity'] ?? 1);
            $unitRate = array_key_exists('unit_rate', $item) && $item['unit_rate'] !== null
                ? (float) $item['unit_rate']
                : (float) ($service->base_rate ?? 0);
            $description = $item['description'] ?? $service?->name ?? 'Service';
            $hsnSacCode = $item['hsn_sac_code'] ?? $service?->hsn_sac_code;
            $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);

            $grossAmount = round($quantity * $unitRate, 2);
            $taxableAmount = round(max($grossAmount - $discountAmount, 0), 2);

            $isTaxExempt = array_key_exists('is_tax_exempt', $item)
                ? (bool) $item['is_tax_exempt']
                : (bool) ($service->is_tax_exempt ?? true);

            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = 0.0;

            if (! $isTaxExempt) {
                $suppliedIgstRate = array_key_exists('igst_rate', $item) ? (float) $item['igst_rate'] : null;

                if ($suppliedIgstRate !== null && $suppliedIgstRate > 0) {
                    $igstRate = $suppliedIgstRate;
                    $igstAmount = round($taxableAmount * $igstRate / 100, 2);
                } else {
                    $cgstRate = array_key_exists('cgst_rate', $item) ? (float) $item['cgst_rate'] : (float) ($service->cgst_rate ?? 0);
                    $sgstRate = array_key_exists('sgst_rate', $item) ? (float) $item['sgst_rate'] : (float) ($service->sgst_rate ?? 0);
                    $cgstAmount = round($taxableAmount * $cgstRate / 100, 2);
                    $sgstAmount = round($taxableAmount * $sgstRate / 100, 2);
                }
            }

            $netAmount = round($taxableAmount + $cgstAmount + $sgstAmount + $igstAmount, 2);

            $lines[] = [
                'service_id' => $service?->id,
                'billable_type' => $item['billable_type'] ?? null,
                'billable_id' => $item['billable_id'] ?? null,
                'description' => $description,
                'hsn_sac_code' => $hsnSacCode,
                'quantity' => $quantity,
                'unit_rate' => $unitRate,
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'cgst_rate' => $cgstRate,
                'sgst_rate' => $sgstRate,
                'igst_rate' => $igstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'net_amount' => $netAmount,
                'status' => 'active',
            ];

            $subtotal += $grossAmount;
            $discountTotal += $discountAmount;
            $taxableTotal += $taxableAmount;
            $cgstTotal += $cgstAmount;
            $sgstTotal += $sgstAmount;
            $igstTotal += $igstAmount;
            $grandTotal += $netAmount;
        }

        return [
            'items' => $lines,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'taxable_total' => round($taxableTotal, 2),
                'cgst_total' => round($cgstTotal, 2),
                'sgst_total' => round($sgstTotal, 2),
                'igst_total' => round($igstTotal, 2),
                'round_off' => 0.0,
                'grand_total' => round($grandTotal, 2),
            ],
        ];
    }
}
