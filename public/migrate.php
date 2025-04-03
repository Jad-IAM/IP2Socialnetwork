<?php
echo "<h1>Flarum SQLite Database Migration</h1>";
echo "<pre>";

// Make sure SQLite database directory exists
$dbDirectory = __DIR__ . '/../storage/sqlite';
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0755, true);
    echo "Created SQLite directory: $dbDirectory\n";
}

// Set up the SQLite database file
$dbFile = $dbDirectory . '/flarum.sqlite';
if (!file_exists($dbFile)) {
    touch($dbFile);
    chmod($dbFile, 0666);
    echo "Created SQLite database file: $dbFile\n";
} else {
    echo "SQLite database file exists: $dbFile\n";
    echo "File size: " . filesize($dbFile) . " bytes\n";
}

// Check file permissions
echo "\nChecking directory permissions...\n";
$dirs = [
    __DIR__ . '/..',
    __DIR__ . '/../storage',
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/sqlite'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir\n";
    }
    
    if (!is_writable($dir)) {
        chmod($dir, 0777);
        echo "Updated permissions for: $dir\n";
    } else {
        echo "Directory is writable: $dir\n";
    }
}

// Create configuration for SQLite
$configPath = __DIR__ . '/../config.php';
$configContent = <<<'CONFIG'
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
CONFIG;

file_put_contents($configPath, $configContent);
echo "\nCreated/updated configuration file: $configPath\n";

// Run the Flarum migrations
echo "\nAttempting to run Flarum migrations...\n";
$migrateCmdPath = __DIR__ . "/../flarum";

if (file_exists($migrateCmdPath)) {
    echo "Flarum CLI exists: $migrateCmdPath\n";
    
    // Run the migration command
    $command = "cd " . __DIR__ . "/.. && php flarum migrate";
    echo "\nRunning migration command: $command\n";
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    
    if ($returnVar !== 0) {
        echo "\nMigration failed with return code: $returnVar\n";
    } else {
        echo "\nMigration completed successfully!\n";
    }
    
    // Install Flarum
    echo "\nAttempting to install Flarum...\n";
    $command = "cd " . __DIR__ . "/.. && php flarum install";
    echo "Running install command: $command\n";
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    
    if ($returnVar !== 0) {
        echo "\nInstallation failed with return code: $returnVar\n";
        
        // Try with specific parameters
        echo "\nAttempting installation with specific parameters...\n";
        $command = "cd " . __DIR__ . "/.. && php flarum install";
        $command .= " --title=\"My Forum\"";
        $command .= " --admin-username=\"admin\"";
        $command .= " --admin-email=\"admin@example.com\"";
        $command .= " --admin-password=\"admin123\"";
        
        echo "Running command: $command\n";
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        foreach ($output as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        
        if ($returnVar !== 0) {
            echo "\nDetailed installation also failed with return code: $returnVar\n";
        } else {
            echo "\nDetailed installation completed successfully!\n";
        }
    } else {
        echo "\nInstallation completed successfully!\n";
    }
} else {
    echo "Error: Flarum CLI not found at $migrateCmdPath\n";
}

echo "\n\nSetup process completed. Please check for any errors above.";
echo "\n\n<a href='/'>Try to access your forum</a>";
echo "</pre>";
?>
