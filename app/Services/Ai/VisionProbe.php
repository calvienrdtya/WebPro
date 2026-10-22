<?php

namespace App\Services\Ai;

/** Gambar uji untuk Tes vision: angka acak 4 digit besar, hitam di latar putih. Kelas sendiri agar bisa diganti di tes. */
class VisionProbe
{
    /** @return array{code: string, png: string} */
    public function make(): array
    {
        $code = (string) random_int(1000, 9999);

        $small = imagecreatetruecolor(60, 24);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagestring($small, 5, 10, 4, $code, imagecolorallocate($small, 0, 0, 0));
        $big = imagescale($small, 480, 192, IMG_NEAREST_NEIGHBOUR);

        ob_start();
        imagepng($big);

        return ['code' => $code, 'png' => (string) ob_get_clean()];
    }
}
