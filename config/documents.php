<?php

return [
    'disk' => 'documents',
    'max_file_kb' => (int) env('DOCUMENT_MAX_FILE_KB', 25600),
    'max_zip_bytes' => (int) env('DOCUMENT_MAX_ZIP_BYTES', 262144000),
    'max_zip_files' => (int) env('DOCUMENT_MAX_ZIP_FILES', 50),
    'per_page' => 20,
    // MIME values come from fileinfo, not the browser. CAO and legacy Office
    // formats have ambiguous signatures; these checks are not antivirus scans.
    'formats' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'webp' => ['image/webp'],
        'dwg' => ['image/vnd.dwg', 'application/acad', 'application/x-dwg', 'application/octet-stream'],
        'dxf' => ['image/vnd.dxf', 'application/dxf', 'text/plain', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip'],
        'txt' => ['text/plain'],
    ],
    'inline_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    'inline_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
];
