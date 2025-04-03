<?php
require __DIR__ . '/../vendor/autoload.php';

// Create SQLite database directory if it doesn't exist
$dbDirectory = __DIR__ . '/../storage/sqlite';
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0755, true);
}

// Create empty SQLite file if it doesn't exist
$dbFile = $dbDirectory . '/flarum.sqlite';
if (!file_exists($dbFile)) {
    touch($dbFile);
    chmod($dbFile, 0666);
}

// Set up the Flarum installation
$config = require __DIR__ . '/../config.php';
$sitePath = __DIR__ . '/..';

// Get the base URL
$baseUrl = $config['url'];

// Admin user details
$adminUser = 'admin';
$adminEmail = 'admin@example.com';
$adminPassword = 'admin123';

// Forum details
$forumTitle = 'My Forum';

// Create the Flarum installer command
$command = "cd $sitePath && php flarum install --file=\"$sitePath/config.php\" ";
$command .= "--admin-username=\"$adminUser\" ";
$command .= "--admin-email=\"$adminEmail\" ";
$command .= "--admin-password=\"$adminPassword\" ";
$command .= "--title=\"$forumTitle\"";

echo "<h1>Setting up Flarum with SQLite</h1>";
echo "<pre>";

// Execute the command
echo "Executing: " . $command . "\n\n";
$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}

if ($returnVar === 0) {
    echo "\n\nFlarum has been successfully installed!";
    echo "\n\n<a href='/'>Go to your forum</a>";
} else {
    echo "\n\nThere was an error installing Flarum. Please check the output above.";
}

echo "</pre>";
?>
