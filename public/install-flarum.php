<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Requiring autoloader
require __DIR__ . '/../vendor/autoload.php';

use Flarum\Install\AdminUser;
use Flarum\Install\BaseUrl;
use Flarum\Install\DatabaseConfig;
use Flarum\Install\Installation;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

// Set up basic configuration
$baseUrl = 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co';
$databaseConfig = [
    'driver'   => 'pgsql',
    'host'     => getenv('PGHOST'),
    'database' => getenv('PGDATABASE'),
    'username' => getenv('PGUSER'),
    'password' => getenv('PGPASSWORD'),
    'port'     => getenv('PGPORT'),
    'prefix'   => '',
];
$adminUser = [
    'username' => 'admin',
    'password' => 'password',
    'email'    => 'admin@example.com',
];
$settings = [
    'forum_title' => 'Flarum Forum',
    'mail_from' => 'noreply@example.com',
    'welcome_title' => 'Welcome to Flarum',
    'welcome_message' => 'This is a new Flarum forum installation. Everyone is welcome!',
];

try {
    // Create a Container instance
    $container = new Container();

    // Initialize site instance
    $site = require __DIR__ . '/../site.php';
    $paths = $site->getPaths();

    // Create installation object
    $installation = new Installation($paths);

    // Configure base URL
    $installation->baseUrl(new BaseUrl($baseUrl));

    // Configure database
    $installation->databaseConfig(new DatabaseConfig($databaseConfig));

    // Configure admin user
    $installation->adminUser(new AdminUser(
        $adminUser['username'],
        $adminUser['password'],
        $adminUser['email']
    ));

    // Configure settings
    $installation->settings($settings);

    // Run the installation
    $installation->build();

    echo "<h1>Flarum has been successfully installed!</h1>";
    echo "<p>You can now <a href='/'>visit your forum</a>.</p>";
    echo "<p>Admin details:</p>";
    echo "<ul>";
    echo "<li>Username: {$adminUser['username']}</li>";
    echo "<li>Password: {$adminUser['password']}</li>";
    echo "<li>Email: {$adminUser['email']}</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h1>Installation Error</h1>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h2>Stack Trace:</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
