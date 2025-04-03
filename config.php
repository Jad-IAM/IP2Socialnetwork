<?php
return [
    'debug' => true,
    'database' => [
        'driver' => 'pgsql',
        'host' => getenv('PGHOST'),
        'port' => getenv('PGPORT'),
        'database' => getenv('PGDATABASE'),
        'username' => getenv('PGUSER'),
        'password' => getenv('PGPASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ],
    'url' => 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co',
    'paths' => [
        'api' => 'api',
        'admin' => 'admin',
    ],
];
