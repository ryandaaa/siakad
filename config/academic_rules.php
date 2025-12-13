<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aturan Akademik per Program Studi
    |--------------------------------------------------------------------------
    |
    | Konfigurasi ini berisi aturan akademik yang digunakan oleh AI Academic
    | Advisor untuk memberikan jawaban yang akurat dan grounded.
    |
    */

    'prodi' => [
        'sistem_informasi_unri' => [
            'nama' => 'Sistem Informasi',
            'universitas' => 'Universitas Riau',
            'graduation_total_sks' => 144,
            'thesis_min_sks' => 144,
            'internship' => [
                'sks' => 3,
                'min_sks_required' => 90,
                'nama' => 'Kerja Praktek',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Rules (fallback jika prodi tidak spesifik)
    |--------------------------------------------------------------------------
    */

    'default' => [
        'graduation_total_sks' => 144,
        'thesis_min_sks' => 144,
        'internship' => [
            'sks' => 3,
            'min_sks_required' => 90,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kurikulum Mata Kuliah per Semester
    |--------------------------------------------------------------------------
    |
    | Daftar mata kuliah per semester untuk Prodi Sistem Informasi UNRI.
    | Digunakan untuk menentukan status TERSEDIA_DI_KURIKULUM.
    |
    */

    'kurikulum' => [
        'sistem_informasi_unri' => [
            6 => [
                ['kode' => 'TIF601', 'nama' => 'Rekayasa Perangkat Lunak', 'sks' => 3],
                ['kode' => 'TIF602', 'nama' => 'Pemrograman Web Lanjut', 'sks' => 3],
                ['kode' => 'TIF603', 'nama' => 'Sistem Informasi Manajemen', 'sks' => 3],
                ['kode' => 'TIF604', 'nama' => 'Data Mining', 'sks' => 3],
                ['kode' => 'TIF605', 'nama' => 'Jaringan Komputer', 'sks' => 3],
                ['kode' => 'TIF606', 'nama' => 'Keamanan Sistem Informasi', 'sks' => 3],
            ],
            7 => [
                ['kode' => 'TIF701', 'nama' => 'Big Data', 'sks' => 3],
                ['kode' => 'TIF702', 'nama' => 'Machine Learning', 'sks' => 3],
                ['kode' => 'TIF703', 'nama' => 'Cloud Computing', 'sks' => 3],
                ['kode' => 'TIF704', 'nama' => 'Enterprise Resource Planning', 'sks' => 3],
                ['kode' => 'TIF705', 'nama' => 'Kerja Praktek', 'sks' => 3],
                ['kode' => 'TIF706', 'nama' => 'Metodologi Penelitian', 'sks' => 3],
            ],
            8 => [
                ['kode' => 'TIF801', 'nama' => 'Skripsi', 'sks' => 6],
                ['kode' => 'TIF802', 'nama' => 'Seminar', 'sks' => 2],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Mata Kuliah
    |--------------------------------------------------------------------------
    |
    | Definisi status mata kuliah untuk AI Advisor.
    |
    */

    'course_status' => [
        'LULUS' => 'Sudah lulus dengan nilai final di KHS',
        'SEDANG_DIAMBIL' => 'Sedang diambil di KRS aktif',
        'TERSEDIA_DI_KURIKULUM' => 'Tersedia di kurikulum semester mendatang',
        'TIDAK_TERSEDIA' => 'Tidak ada di kurikulum/data sistem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Threshold dan Batasan
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'attendance_minimum_percentage' => 75,
        'attendance_warning_percentage' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Forbidden Assumption Phrases
    |--------------------------------------------------------------------------
    |
    | Kata-kata yang dilarang digunakan AI dalam konteks aturan akademik.
    | Jika AI menggunakan kata ini, akan di-trigger retry atau sanitize.
    |
    */

    'forbidden_assumption_phrases' => [
        'biasanya',
        'umumnya',
        'tergantung',
        'pada umumnya',
        'lazimnya',
        'seringkali',
        'mungkin sekitar',
        'kira-kira',
        'sekitar',
        'kurang lebih',
        'rata-rata universitas',
        'standar nasional',
    ],

];
