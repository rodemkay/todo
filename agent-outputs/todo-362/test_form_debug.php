<?php
// Test script to debug form submission
// This script simulates what happens when the form is submitted

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== FORM DEBUG TEST ===\n\n";

// Simulate $_POST data
$_POST = [
    'save_todo' => '1',
    'title' => 'Test Attachment Upload',
    'description' => 'Testing if attachments work',
    'project_name' => 'Todo-Plugin',
    'working_directory' => '/home/rodemkay/www/react/plugin-todo/',
    'status' => 'offen',
    'priority' => 'mittel',
    'bearbeiten' => '1'
];

// Simulate $_FILES data (empty)
$_FILES = [];

echo "1. POST Data:\n";
print_r($_POST);

echo "\n2. FILES Data:\n";
print_r($_FILES);

echo "\n3. Working Directory Test:\n";
// Test the working directory logic
$saved_projects = [
    'Todo-Plugin' => [
        'paths' => [
            '/home/rodemkay/www/react/plugin-todo/',
            '/home/rodemkay/www/react/plugin-todo/hooks/',
            '/home/rodemkay/www/react/plugin-todo/docs/'
        ],
        'default_working_directory' => '/home/rodemkay/www/react/plugin-todo/',
        'dev_area' => 'Backend'
    ]
];

echo "Saved Projects:\n";
print_r($saved_projects['Todo-Plugin']);

echo "\n4. JavaScript Data Test:\n";
echo "JSON encoded: " . json_encode($saved_projects) . "\n";

echo "\n5. Attachment Test:\n";
if (isset($_FILES['attachments'])) {
    echo "✅ attachments key exists\n";
    if (!empty($_FILES['attachments']['name'][0])) {
        echo "✅ First file exists\n";
    } else {
        echo "❌ First file is empty\n";
    }
} else {
    echo "❌ No attachments key\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>