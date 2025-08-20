<?php
// Test-Script für SSH-Verbindung
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Current user
echo "Current user: " . exec('whoami') . "\n";
echo "PHP version: " . PHP_VERSION . "\n\n";

// Check if vendor dir exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("ERROR: vendor/autoload.php not found. Run: composer install\n");
}

require_once __DIR__ . '/vendor/autoload.php';

// Check if SSH handler exists
if (!file_exists(__DIR__ . '/includes/class-ssh-handler.php')) {
    die("ERROR: class-ssh-handler.php not found\n");
}

require_once __DIR__ . '/includes/class-ssh-handler.php';

echo "Testing SSH Connection to RyzenServer...\n";
echo "========================================\n\n";

// Check if phpseclib classes are available
if (!class_exists('phpseclib3\\Net\\SSH2')) {
    die("ERROR: phpseclib3 not properly installed\n");
}

$handler = new WP_Todo_SSH_Handler();

// Test 1: Connection Test
echo "1. Testing SSH Connection:\n";
$test = $handler->testConnection();
print_r($test);
echo "\n";

// Test 2: Check Claude Status
echo "2. Checking Claude Status:\n";
$status = $handler->checkStatus();
print_r($status);
echo "\n";

// Test 3: Send ./todo Command
echo "3. Sending ./todo command:\n";
$result = $handler->sendTodoCommand();
print_r($result);
echo "\n";

echo "Test complete!\n";
