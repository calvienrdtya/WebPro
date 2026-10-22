<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan alur pesanan yang pesannya aman ditampilkan ke pengguna (stok habis, voucher tidak valid, status salah). */
class OrderException extends RuntimeException {}
