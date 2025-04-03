<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load autoloader from parent directory
require __DIR__.'/../vendor/autoload.php';

try {
    $site = require __DIR__.'/../site.php';
    
    // Get the database configuration from config.php
    $config = require __DIR__.'/../config.php';
    
    echo "<h1>Flarum Installation Script</h1>";
    
    // Create a console application for migrations
    $app = new \Flarum\Foundation\InstalledApp($site);
    $console = $app->getContainer()->make('flarum.console');
    
    echo "<h2>Running Database Migrations</h2>";
    
    // Run migrations
    $input = new \Symfony\Component\Console\Input\ArrayInput([
        'command' => 'migrate',
    ]);
    $output = new \Symfony\Component\Console\Output\BufferedOutput;
    $console->run($input, $output);
    
    echo "<pre>" . $output->fetch() . "</pre>";
    
    echo "<h2>Installation Complete</h2>";
    echo "<p>You can now <a href='/'>visit your forum</a>.</p>";
    
} catch (Exception $e) {
    echo "<h1>Installation Error</h1>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h2>Stack Trace:</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
