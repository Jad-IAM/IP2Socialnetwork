<?php
$url = 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co';

return [
    'debug' => true,
    'database' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/storage/sqlite/flarum.sqlite',
        'prefix' => '',
    ],
    'url' => $url,
    'paths' => [
        'api' => 'api',
        'admin' => 'admin',
    ],
];