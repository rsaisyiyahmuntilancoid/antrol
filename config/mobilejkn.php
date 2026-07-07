<?php

return [
    'base_url' => env('MOBILEJKN_BASE_URL', 'https://apijkn.bpjs-kesehatan.go.id/antreanrs'),
    'cons_id' => env('MOBILEJKN_CONS_ID'),
    'user_key' => env('MOBILEJKN_USER_KEY'),
    'secret_key' => env('MOBILEJKN_SECRET_KEY'),
    'kd_pj' => env('BPJS_KD_PJ', 'BPJ'),
    'exclude_poli' => env('BPJS_EXCLUDE_POLI', 'HD,IGD,IGDK'),
];
