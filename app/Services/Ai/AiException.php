<?php

namespace App\Services\Ai;

use RuntimeException;

/** Kegagalan memanggil router AI; pesannya aman ditampilkan ke admin. */
class AiException extends RuntimeException {}
