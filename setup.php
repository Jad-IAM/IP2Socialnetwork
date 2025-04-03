<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

try {
    // Load the Flarum site
    $site = require __DIR__.'/site.php';

    // Create a new install app
    $app = new \Flarum\Install\InstallServiceProvider();
    $container = new \Illuminate\Container\Container();
    $app->register($container);

    // Set up configuration data
    $data = [
        'debug' => true,
        'baseUrl' => 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co',
        'databaseConfig' => [
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
        'adminUser' => [
            'username' => 'admin',
            'password' => 'password',
            'email' => 'admin@example.com',
        ],
        'settings' => [
            'forum_title' => 'Flarum Discussion Forum',
        ],
    ];

    // Perform the installation
    $prerequisites = $container->make(\Flarum\Install\Prerequisite\PrerequisiteInterface::class);
    $prerequisites->check();

    $installer = $container->make(\Flarum\Install\Installation::class);
    $installer->install($data);

    echo "Flarum has been successfully installed.";
    echo "<p>You can now <a href='/'>visit your forum</a>.</p>";

} catch (Exception $e) {
    echo "<h1>Installation Error</h1>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h2>Stack Trace:</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
