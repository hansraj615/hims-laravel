<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalizeIndianMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }

        return $value;
    }
}
