<?php

namespace App\Services\Lead;

use App\Exceptions\InvalidPhoneNumberException;

/**
 * Normalisasi nomor telepon Indonesia ke format internasional E.164 (+62xxxxxxxxxx).
 * Menerima format umum: 08123456789, 8123456789, 628123456789, +628123456789,
 * dengan spasi/strip/kurung di antaranya.
 */
class PhoneNumberNormalizer
{
    public function normalize(string $rawInput): string
    {
        // Buang semua karakter kecuali digit.
        $digits = preg_replace('/\D+/', '', $rawInput) ?? '';

        if ($digits === '') {
            throw new InvalidPhoneNumberException("Nomor telepon kosong atau tidak valid: \"{$rawInput}\"");
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        } elseif (! str_starts_with($digits, '62')) {
            throw new InvalidPhoneNumberException("Nomor telepon tidak dikenali formatnya: \"{$rawInput}\"");
        }

        // Nomor seluler Indonesia setelah kode negara 62: umumnya 9-12 digit lagi.
        $nationalPart = substr($digits, 2);
        $length = strlen($nationalPart);

        if ($length < 8 || $length > 13) {
            throw new InvalidPhoneNumberException("Panjang nomor telepon tidak wajar: \"{$rawInput}\"");
        }

        return '+62'.$nationalPart;
    }

    public function isValid(string $rawInput): bool
    {
        try {
            $this->normalize($rawInput);

            return true;
        } catch (InvalidPhoneNumberException) {
            return false;
        }
    }
}
