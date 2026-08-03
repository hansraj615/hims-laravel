<?php

namespace App\Domain\ABDM\Contracts;

interface AbdmGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_txn_id: string, status: string, message: string, raw?: array<string, mixed>}
     */
    public function initiateAbhaVerify(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_txn_id: string, status: string, profile?: array<string, mixed>, message: string, raw?: array<string, mixed>}
     */
    public function confirmAbhaVerify(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_txn_id: string, status: string, message: string, raw?: array<string, mixed>}
     */
    public function initiateAbhaCreate(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_txn_id: string, status: string, profile?: array<string, mixed>, message: string, raw?: array<string, mixed>}
     */
    public function confirmAbhaCreate(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_txn_id: string, status: string, profile?: array<string, mixed>, message: string, raw?: array<string, mixed>}
     */
    public function resolveScanShare(array $payload): array;

    public function providerName(): string;
}
