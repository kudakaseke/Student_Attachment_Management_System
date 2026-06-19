<?php
declare(strict_types=1);

return [
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'instance' => getenv('DB_INSTANCE') ?: '',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'sams_db',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'encrypt' => getenv('DB_ENCRYPT') ?: '0',
        'trust_cert' => getenv('DB_TRUST_CERT') ?: '1',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'upload_path' => __DIR__ . '/../uploads',
    'max_file_size' => 10 * 1024 * 1024,
];
