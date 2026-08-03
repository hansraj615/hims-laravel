<?php

namespace App\Domain\IPD\Services;

use App\Domain\Diagnostics\Models\DiagnosticOrder;
use App\Domain\IPD\Models\Admission;
use App\Domain\Patients\Models\PatientDocument;
use Carbon\CarbonInterface;

class DischargePackageBuilder
{
    /**
     * @return array{
     *   documents: array<int, array<string, mixed>>,
     *   diagnostic_orders: array<int, array<string, mixed>>,
     *   counts: array<string, int>
     * }
     */
    public function build(Admission $admission): array
    {
        $from = $admission->admitted_at;
        $to = now();

        $documents = PatientDocument::query()
            ->where('hospital_id', $admission->hospital_id)
            ->where('patient_id', $admission->patient_id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get()
            ->map(fn (PatientDocument $document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'created_at' => $document->created_at?->toISOString(),
            ])
            ->all();

        $orders = DiagnosticOrder::query()
            ->where('hospital_id', $admission->hospital_id)
            ->where('patient_id', $admission->patient_id)
            ->whereBetween('ordered_at', [$from, $to])
            ->with('items')
            ->orderBy('ordered_at')
            ->get()
            ->map(fn (DiagnosticOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'category' => $order->category,
                'status' => $order->status,
                'result_summary' => $order->result_summary,
                'patient_document_id' => $order->patient_document_id,
                'items' => $order->items->map(fn ($item) => [
                    'service_code' => $item->service_code,
                    'service_name' => $item->service_name,
                ])->all(),
            ])
            ->all();

        return [
            'admission_number' => $admission->admission_number,
            'outcome' => $admission->discharge_outcome,
            'period' => [
                'from' => $from instanceof CarbonInterface ? $from->toISOString() : null,
                'to' => $to->toISOString(),
            ],
            'documents' => $documents,
            'diagnostic_orders' => $orders,
            'counts' => [
                'documents' => count($documents),
                'diagnostic_orders' => count($orders),
            ],
        ];
    }
}
