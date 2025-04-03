<?php
echo "<h1>Manual Setup for Flarum with SQLite</h1>";
echo "<pre>";

// Create the SQLite database directory
$dbDirectory = __DIR__ . '/../storage/sqlite';
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0755, true);
    echo "Created SQLite directory: $dbDirectory\n";
} else {
    echo "SQLite directory already exists: $dbDirectory\n";
}

// Create the database file
$dbFile = $dbDirectory . '/flarum.sqlite';
if (!file_exists($dbFile)) {
    touch($dbFile);
    chmod($dbFile, 0666);
    echo "Created SQLite database file: $dbFile\n";
} else {
    echo "SQLite database file already exists: $dbFile\n";
}

// Create simple configuration file
$configPath = __DIR__ . '/../config.php';
$configContent = <<<'CONFIG'
<?php
return [
    'debug' => true,
    'database' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/storage/sqlite/flarum.sqlite',
        'prefix' => ''
    ],
    'url' => 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co',
    'paths' => [
        'api' => 'api',
        'admin' => 'admin',
    ],
];
CONFIG;

file_put_contents($configPath, $configContent);
echo "Created/updated configuration file: $configPath\n";

// Run the Flarum install command
$command = "cd " . __DIR__ . "/.. && php flarum install";
echo "\nAttempting to run Flarum install command: $command\n";
$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}

if ($returnVar !== 0) {
    echo "\nCommand failed with return code: $returnVar\n";
    
    // Try alternative approach - setup database
    echo "\nAttempting alternative approach with basic database setup...\n";
    
    // Check for required PHP modules
    echo "\nChecking for required PHP modules...\n";
    $requiredExtensions = ['pdo_sqlite', 'mbstring', 'tokenizer', 'json'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    if (!empty($missingExtensions)) {
        echo "Missing required PHP extensions: " . implode(', ', $missingExtensions) . "\n";
    } else {
        echo "All required PHP extensions are loaded.\n";
    }
    
    // Check file permissions
    echo "\nChecking file permissions...\n";
    $dirs = [
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
            chmod($dir, 0755);
            echo "Updated permissions for: $dir\n";
        } else {
            echo "Directory is writable: $dir\n";
        }
    }
}

echo "\n\nSetup process completed. Please check for any errors above.";
echo "\n\n<a href='/'>Try to access your forum</a>";
echo "</pre>";
?>
