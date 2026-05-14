<?php

namespace App\Common\Support;

class PhoneNormalizer
{
    /**
     * Normalize a Vietnamese phone number:
     * - Strip whitespace, dots, dashes, parentheses
     * - Convert leading +84 or 84 prefix to 0
     * - Return empty string for null/blank input
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if (str_starts_with($cleaned, '+84')) {
            return '0'.substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '84') && strlen($cleaned) >= 10) {
            return '0'.substr($cleaned, 2);
        }

        return $cleaned;
    }
}
