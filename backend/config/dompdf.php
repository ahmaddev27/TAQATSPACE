<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DomPDF configuration (M07 — T044)
|--------------------------------------------------------------------------
| Defaults the document font to DejaVu Sans, which is bundled with dompdf
| and covers Arabic codepoints, so the bilingual invoice template renders
| Arabic glyphs (note: dompdf does not perform full Arabic shaping).
*/

return [

    'show_warnings' => false,

    'public_path' => null,

    'convert_entities' => true,

    'options' => [
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
        'temp_dir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],
        'artifactPathValidation' => null,
        'log_output_file' => null,
        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',

        // DejaVu Sans ships with dompdf and covers Arabic codepoints.
        'default_font' => 'DejaVu Sans',

        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => false,
        'enable_remote' => false,
        'allowed_remote_hosts' => null,
        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],

];
