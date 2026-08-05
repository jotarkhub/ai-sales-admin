<?php

namespace App\Enums;

enum LeadFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Phone = 'phone';
    case Email = 'email';
    case Nik = 'nik'; // Nomor Induk Kependudukan / KTP — selalu diperlakukan sensitif di UI form builder

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks Singkat',
            self::Textarea => 'Teks Panjang',
            self::Number => 'Angka',
            self::Date => 'Tanggal',
            self::Select => 'Pilihan',
            self::Phone => 'Nomor Telepon',
            self::Email => 'Email',
            self::Nik => 'NIK/KTP',
        };
    }

    public function isSensitiveByDefault(): bool
    {
        return $this === self::Nik;
    }
}
