<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar kalau kode mencoba mengirim WhatsApp sungguhan (MetaWhatsAppProvider) tapi
 * token/phone_number_id belum diisi. Sengaja TIDAK diam-diam gagal atau berpura-pura
 * terkirim — lihat prinsip "jangan pernah menulis kode yang berpura-pura berfungsi".
 */
class WhatsAppNotConfiguredException extends RuntimeException {}
