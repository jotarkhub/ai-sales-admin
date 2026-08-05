<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar kalau kode mencoba memanggil AI sungguhan (OpenAiProvider) tapi API key belum
 * diisi. Sengaja TIDAK diam-diam gagal atau mengarang balasan — lihat prinsip "jangan
 * pernah menulis kode yang berpura-pura berfungsi".
 */
class AiNotConfiguredException extends RuntimeException {}
