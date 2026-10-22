<?php

namespace App\Http\Requests\Admin;

/** Angka dari input form bisa berawalan nol ("05"); aturan `integer` menolaknya, jadi diubah ke bilangan bulat dulu. */
trait NormalizesWholeNumbers
{
    protected function wholeNumber(mixed $value): mixed
    {
        return is_string($value) && ctype_digit($value) ? (int) $value : $value;
    }
}
