<?php
/**
 * Debug-Script für MCP-Server Funktionalität
 * Aufrufen via: https://forexsignale.trade/staging/wp-content/plugins/todo/debug-mcp-server.php
 */

// WordPress laden
require_once('../../../wp-load.php');

// Sicherstellen dass wir Admin sind
if (!current_user_can('manage_options')) {
    die('Zugriff verweigert: Administrator-Rechte erforderlich');
}

echo '<h1>MCP-Server Debug Test</h1>';

// Test 1: Nonce Generation
echo '<h2>Test 1: Nonce Generation</h2>';
$save_nonce = wp_create_nonce('save_mcp_defaults');
$load_nonce = wp_create_nonce('load_mcp_defaults');
echo "Save MCP Defaults Nonce: <code>$save_nonce</code><br>";
echo "Load MCP Defaults Nonce: <code>$load_nonce</code><br>";

// Test 2: Nonce Verification
echo '<h2>Test 2: Nonce Verification</h2>';
$verify_save = wp_verify_nonce($save_nonce, 'save_mcp_defaults');
$verify_load = wp_verify_nonce($load_nonce, 'load_mcp_defaults');
echo "Save Nonce Verification: " . ($verify_save ? '<span style="color:green">✅ VALID</span>' : '<span style="color:red">❌ INVALID</span>') . "<br>";
echo "Load Nonce Verification: " . ($verify_load ? '<span style="color:green">✅ VALID</span>' : '<span style="color:red">❌ INVALID</span>') . "<br>";

// Test 3: WordPress Options
echo '<h2>Test 3: WordPress Options</h2>';
$current_defaults = get_option('todo_mcp_defaults', []);
echo "Current MCP Defaults in Database: <pre>" . print_r($current_defaults, true) . "</pre>";

// Test 4: Test Save Operation
echo '<h2>Test 4: Test Save Operation</h2>';
$test_servers = ['context7', 'playwright', 'filesystem'];
$save_result = update_option('todo_mcp_defaults_test', $test_servers);
echo "Test Save Result: " . ($save_result ? '<span style="color:green">✅ SUCCESS</span>' : '<span style="color:red">❌ FAILED</span>') . "<br>";

$verify_saved = get_option('todo_mcp_defaults_test', []);
echo "Verification of Saved Data: <pre>" . print_r($verify_saved, true) . "</pre>";

// Test 5: AJAX Endpoints
echo '<h2>Test 5: AJAX Endpoint Check</h2>';
echo "Admin AJAX URL: <code>" . admin_url('admin-ajax.php') . "</code><br>";

// Test 6: Class Loading
echo '<h2>Test 6: Class Loading</h2>';
if (class_exists('ProjectTodos_Admin')) {
    echo "ProjectTodos_Admin Class: <span style='color:green'>✅ LOADED</span><br>";
    $admin = new ProjectTodos_Admin();
    if (method_exists($admin, 'ajax_save_mcp_defaults')) {
        echo "ajax_save_mcp_defaults Method: <span style='color:green'>✅ EXISTS</span><br>";
    } else {
        echo "ajax_save_mcp_defaults Method: <span style='color:red'>❌ MISSING</span><br>";
    }
    if (method_exists($admin, 'ajax_load_mcp_defaults')) {
        echo "ajax_load_mcp_defaults Method: <span style='color:green'>✅ EXISTS</span><br>";
    } else {
        echo "ajax_load_mcp_defaults Method: <span style='color:red'>❌ MISSING</span><br>";
    }
} else {
    echo "ProjectTodos_Admin Class: <span style='color:red'>❌ NOT LOADED</span><br>";
}

// Test 7: Action Hooks
echo '<h2>Test 7: Action Hooks</h2>';
$save_priority = has_action('wp_ajax_save_mcp_defaults');
$load_priority = has_action('wp_ajax_load_mcp_defaults');
echo "wp_ajax_save_mcp_defaults Hook: " . ($save_priority !== false ? "<span style='color:green'>✅ REGISTERED (Priority: $save_priority)</span>" : "<span style='color:red'>❌ NOT REGISTERED</span>") . "<br>";
echo "wp_ajax_load_mcp_defaults Hook: " . ($load_priority !== false ? "<span style='color:green'>✅ REGISTERED (Priority: $load_priority)</span>" : "<span style='color:red'>❌ NOT REGISTERED</span>") . "<br>";

// Test 8: Manual AJAX Test
echo '<h2>Test 8: Manual AJAX Test</h2>';
echo '<div id="test-result"></div>';
echo '<button onclick="testMCPSave()" style="padding:10px; background:#0073aa; color:white; border:none; cursor:pointer;">Test MCP Save</button>';
echo '<button onclick="testMCPLoad()" style="padding:10px; background:#00a32a; color:white; border:none; margin-left:10px; cursor:pointer;">Test MCP Load</button>';

?>

<script>
const testNonces = {
    save_mcp_defaults: '<?php echo $save_nonce; ?>',
    load_mcp_defaults: '<?php echo $load_nonce; ?>'
};

function testMCPSave() {
    console.log('Testing MCP Save with nonce:', testNonces.save_mcp_defaults);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=save_mcp_defaults&servers=${encodeURIComponent(JSON.stringify(['context7', 'playwright']))}&nonce=${testNonces.save_mcp_defaults}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Save Test Result:', data);
        document.getElementById('test-result').innerHTML = 
            '<h3>Save Test Result:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        console.error('Save Test Error:', error);
        document.getElementById('test-result').innerHTML = 
            '<h3>Save Test Error:</h3><pre style="color:red;">' + error + '</pre>';
    });
}

function testMCPLoad() {
    console.log('Testing MCP Load with nonce:', testNonces.load_mcp_defaults);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=load_mcp_defaults&nonce=${testNonces.load_mcp_defaults}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Load Test Result:', data);
        document.getElementById('test-result').innerHTML += 
            '<h3>Load Test Result:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        console.error('Load Test Error:', error);
        document.getElementById('test-result').innerHTML += 
            '<h3>Load Test Error:</h3><pre style="color:red;">' + error + '</pre>';
    });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #0073aa; }
h2 { color: #666; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; }
pre { background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; overflow-x: auto; }
</style>