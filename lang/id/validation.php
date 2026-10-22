<?php

return [
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'distinct' => ':attribute tidak boleh sama dengan baris lain.',
    'enum' => 'Pilihan :attribute tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'image' => ':attribute harus berupa gambar.',
    'integer' => ':attribute harus berupa angka bulat.',
    'mimes' => ':attribute harus berformat: :values.',
    'regex' => 'Format :attribute tidak valid.',
    'uploaded' => ':attribute gagal diunggah.',
    'accepted' => ':attribute harus disetujui.',
    'confirmed' => ':attribute dan konfirmasinya tidak cocok.',
    'digits' => ':attribute harus berupa :digits digit angka.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'max' => [
        'array' => ':attribute maksimal :max item.',
        'file' => ':attribute maksimal :max KB.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal :min item.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',

    'password' => [
        'letters' => ':attribute harus mengandung minimal satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus mengandung minimal satu angka.',
        'symbols' => ':attribute harus mengandung minimal satu simbol.',
        'uncompromised' => ':attribute ini pernah bocor di internet. Pilih kata sandi lain.',
    ],

    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'terms' => 'Persetujuan',
        'code' => 'Kode',
    ],
];
