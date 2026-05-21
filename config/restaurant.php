<?php

return [
    'iva_rate' => 0.12,
    'currency' => 'GTQ',
    'currency_symbol' => 'Q',
    'check_number_prefix' => 'C-',
    'check_number_padding' => 6,
    'default_tip_suggestion' => 0.10,

    'fel' => [
        'enabled'  => env('FEL_ENABLED', false),
        // Adapter: 'null' (dev/test) | 'infile'
        'adapter'  => env('FEL_ADAPTER', 'null'),

        // Datos del emisor (empresa restaurante)
        'emisor_nit'              => env('FEL_EMISOR_NIT'),
        'emisor_name'             => env('FEL_EMISOR_NAME'),
        'emisor_commercial_name'  => env('FEL_EMISOR_COMMERCIAL_NAME'),
        'emisor_address'          => env('FEL_EMISOR_ADDRESS'),
        'emisor_postal_code'      => env('FEL_EMISOR_POSTAL_CODE', '01001'),
        'emisor_city'             => env('FEL_EMISOR_CITY', 'Guatemala'),
        'emisor_department'       => env('FEL_EMISOR_DEPARTMENT', 'Guatemala'),
        'emisor_country'          => 'GT',
        'establishment_code'      => env('FEL_ESTABLISHMENT_CODE', '1'),

        // Credenciales Infile
        'infile_user'             => env('FEL_INFILE_USER'),
        'infile_api_key'          => env('FEL_INFILE_API_KEY'),
        'infile_signature_key'    => env('FEL_INFILE_SIGNATURE_KEY'),
        'infile_api_url'          => env('FEL_INFILE_API_URL', 'https://cert.api.infile.com.gt'),
    ],
];
