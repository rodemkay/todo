<?php
/**
 * Plugin Name: WP Project To-Dos
 * Plugin URI: https://forexsignale.trade/
 * Description: Zentralisierte Aufgabenverwaltung mit Claude Code Integration, Multi-Scope Support und automatischem Directory-Switching
 * Version: 1.0.0
 * Author: ForexSignale Magazine Development Team
 * Author URI: https://forexsignale.trade/
 * License: GPL v2 or later
 * Text Domain: wp-project-todos
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_PROJECT_TODOS_VERSION', '1.0.0');
define('WP_PROJECT_TODOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_PROJECT_TODOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_PROJECT_TODOS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load plugin textdomain
add_action('plugins_loaded', 'wp_project_todos_load_textdomain');
function wp_project_todos_load_textdomain() {
    load_plugin_textdomain('wp-project-todos', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'WP_Project_Todos\\';
    $base_dir = WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('\\', '/', strtolower(str_replace('_', '-', $relative_class))) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Activation Hook
register_activation_hook(__FILE__, 'wp_project_todos_activate');
function wp_project_todos_activate() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-activator.php';
    WP_Project_Todos\Activator::activate();
}

// Deactivation Hook
register_deactivation_hook(__FILE__, 'wp_project_todos_deactivate');
function wp_project_todos_deactivate() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-deactivator.php';
    WP_Project_Todos\Deactivator::deactivate();
}

// Initialize the plugin
add_action('plugins_loaded', 'wp_project_todos_init');
function wp_project_todos_init() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-wp-project-todos.php';
    $plugin = new WP_Project_Todos\WP_Project_Todos();
    $plugin->run();
}

// Add custom capabilities
add_action('admin_init', 'wp_project_todos_add_capabilities');
function wp_project_todos_add_capabilities() {
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_project_todos');
        $role->add_cap('edit_project_todos');
        $role->add_cap('delete_project_todos');
        $role->add_cap('view_project_todos');
    }
}

// Register REST API routes
add_action('rest_api_init', 'wp_project_todos_register_rest_routes');
function wp_project_todos_register_rest_routes() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-api.php';
    $api = new WP_Project_Todos\API();
    $api->register_routes();
}

// Add admin menu
add_action('admin_menu', 'wp_project_todos_admin_menu');
function wp_project_todos_admin_menu() {
    add_menu_page(
        __('Project To-Dos', 'wp-project-todos'),
        __('Project To-Dos', 'wp-project-todos'),
        'manage_project_todos',
        'wp-project-todos',
        'wp_project_todos_admin_page',
        'dashicons-editor-ul',
        25
    );
    
    add_submenu_page(
        'wp-project-todos',
        __('Alle To-Dos', 'wp-project-todos'),
        __('Alle To-Dos', 'wp-project-todos'),
        'manage_project_todos',
        'wp-project-todos',
        'wp_project_todos_admin_page'
    );
    
    add_submenu_page(
        'wp-project-todos',
        __('Neue Aufgabe', 'wp-project-todos'),
        __('Neue Aufgabe', 'wp-project-todos'),
        'edit_project_todos',
        'wp-project-todos-new',
        'wp_project_todos_new_page'
    );
    
    add_submenu_page(
        'wp-project-todos',
        __('Claude Output', 'wp-project-todos'),
        __('Claude Output', 'wp-project-todos'),
        'manage_options',
        'wp-project-todos-claude',
        'wp_project_todos_claude_page'
    );
    
    add_submenu_page(
        'wp-project-todos',
        __('Screenshots', 'wp-project-todos'),
        __('Screenshots', 'wp-project-todos'),
        'manage_project_todos',
        'wp-project-todos-screenshots',
        'wp_project_todos_screenshots_page'
    );
    
    add_submenu_page(
        'wp-project-todos',
        __('Playwright Screenshots', 'wp-project-todos'),
        __('Playwright Screenshots', 'wp-project-todos'),
        'manage_project_todos',
        'wp-project-todos-playwright',
        'wp_project_todos_playwright_page'
    );
    
    // Plans-Integration laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-plans.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-plans.php';
    }
    
    // Report Generator laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-report-generator.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-report-generator.php';
    }
    
    // Continue Todo System laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-continue-todo.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-continue-todo.php';
        new WP_Project_Todos\Continue_Todo();
    }
    
    // Remote Control System laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-remote-control.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-remote-control.php';
        new WP_Project_Todos\Remote_Control();
    }
    
    // Planning Mode System laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-planning-mode.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-planning-mode.php';
        new WP_Project_Todos\Planning_Mode();
    }
    
    // Status Report System laden
    if (file_exists(WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-status-report.php')) {
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-status-report.php';
        new WP_Project_Todos\Status_Report();
    }
    
    add_submenu_page(
        'wp-project-todos',
        __('Einstellungen', 'wp-project-todos'),
        __('Einstellungen', 'wp-project-todos'),
        'manage_project_todos',
        'wp-project-todos-settings',
        'wp_project_todos_settings_page'
    );
}

// Admin page callbacks
function wp_project_todos_admin_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-admin.php';
    $admin = new WP_Project_Todos\Admin();
    $admin->render_list_page();
}

function wp_project_todos_new_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-admin.php';
    $admin = new WP_Project_Todos\Admin();
    $admin->render_new_page();
}

function wp_project_todos_claude_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-claude.php';
    $claude = new WP_Project_Todos\Claude_Integration();
    $claude->render_output_page();
}

function wp_project_todos_screenshots_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-screenshots-uploader.php';
    $uploader = new WP_Project_Todos\Screenshots_Uploader();
    wp_project_todos_render_screenshots_upload_page($uploader);
}

function wp_project_todos_playwright_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-playwright-screenshots.php';
    $manager = new WP_Project_Todos\Playwright_Screenshots();
    wp_project_todos_render_playwright_page($manager);
}

function wp_project_todos_settings_page() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-settings.php';
    $settings = new WP_Project_Todos\Settings();
    $settings->render_page();
}

// AJAX Handler für CLAUDE.md Laden
add_action('wp_ajax_load_claude_md', 'wp_project_todos_load_claude_md');
function wp_project_todos_load_claude_md() {
    // Nonce-Überprüfung
    if (!wp_verify_nonce($_POST['nonce'], 'load_claude_md')) {
        wp_send_json_error(['message' => 'Ungültiger Nonce']);
        return;
    }
    
    $path = sanitize_text_field($_POST['path']);
    
    // Sicherheitsprüfung - nur bestimmte Pfade erlauben
    $allowed_paths = [
        '/home/rodemkay/www/react/CLAUDE.md',
        '/home/rodemkay/www/react/mt5/CLAUDE.md',
        '/home/rodemkay/www/react/development/CLAUDE.md'
    ];
    
    if (!in_array($path, $allowed_paths)) {
        wp_send_json_error(['message' => 'Ungültiger Pfad']);
        return;
    }
    
    // Datei auf dem Server lesen (da wir auf Hetzner sind)
    if (strpos($path, '/home/rodemkay/') === 0) {
        // Für lokale Pfade - SSH zum RyzenServer
        $ssh_cmd = sprintf(
            'ssh -o ConnectTimeout=5 rodemkay@100.89.207.122 "if [ -f %s ]; then head -n 50 %s; else echo \'Datei nicht gefunden\'; fi"',
            escapeshellarg($path),
            escapeshellarg($path)
        );
        
        $output = [];
        $return_code = 0;
        exec($ssh_cmd, $output, $return_code);
        
        if ($return_code === 0 && !empty($output)) {
            $content = implode("\n", $output);
            if ($content !== 'Datei nicht gefunden') {
                wp_send_json_success(['content' => $content]);
            } else {
                wp_send_json_error(['message' => 'CLAUDE.md nicht gefunden']);
            }
        } else {
            wp_send_json_error(['message' => 'Fehler beim Lesen der Datei']);
        }
    } else {
        wp_send_json_error(['message' => 'Ungültiger Pfadtyp']);
    }
}

// Render screenshots upload page
function wp_project_todos_render_screenshots_upload_page($uploader) {
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_screenshot')) {
            $result = $uploader->delete_screenshot($_GET['file']);
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>' . __('Screenshot gelöscht!', 'wp-project-todos') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            }
        }
    }
    
    // Handle bulk upload
    if (isset($_POST['bulk_upload']) && wp_verify_nonce($_POST['_wpnonce'], 'bulk_upload_screenshots')) {
        $result = $uploader->bulk_upload_local_screenshots();
        echo '<div class="notice notice-success"><p>';
        echo sprintf(__('%d Screenshots hochgeladen!', 'wp-project-todos'), $result['count']);
        if (!empty($result['errors'])) {
            echo '<br>' . __('Fehler:', 'wp-project-todos') . '<br>' . implode('<br>', $result['errors']);
        }
        echo '</p></div>';
    }
    
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $screenshots = $uploader->get_uploaded_screenshots($search);
    ?>
    <div class="wrap">
        <h1><?php _e('Playwright Screenshots', 'wp-project-todos'); ?></h1>
        
        <p><?php _e('Screenshots aus WordPress Uploads Verzeichnis.', 'wp-project-todos'); ?></p>
        
        <!-- Bulk Upload Form -->
        <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3><?php _e('Lokale Screenshots hochladen', 'wp-project-todos'); ?></h3>
            <p><?php _e('Lädt alle Screenshots von deinem lokalen System auf den WordPress Server.', 'wp-project-todos'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('bulk_upload_screenshots'); ?>
                <input type="submit" name="bulk_upload" class="button button-primary" value="<?php _e('Alle lokalen Screenshots hochladen', 'wp-project-todos'); ?>" />
            </form>
        </div>
        
        <!-- Search Form -->
        <form method="get" action="" style="margin: 20px 0;">
            <input type="hidden" name="page" value="wp-project-todos-screenshots" />
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Screenshot suchen...', 'wp-project-todos'); ?>" style="width: 300px;" />
            <input type="submit" class="button" value="<?php _e('Suchen', 'wp-project-todos'); ?>" />
            <?php if ($search): ?>
            <a href="<?php echo admin_url('admin.php?page=wp-project-todos-screenshots'); ?>" class="button"><?php _e('Zurücksetzen', 'wp-project-todos'); ?></a>
            <?php endif; ?>
        </form>
        
        <p style="color: #666;">
            <?php echo sprintf(__('%d Screenshots gefunden', 'wp-project-todos'), count($screenshots)); ?>
            <?php if ($search): ?>
                <?php echo sprintf(__(' für "%s"', 'wp-project-todos'), $search); ?>
            <?php endif; ?>
        </p>
        
        <style>
            .screenshots-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .screenshot-item {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .screenshot-preview {
                width: 100%;
                height: 150px;
                object-fit: cover;
                border-radius: 3px;
                margin-bottom: 10px;
                cursor: pointer;
            }
            .screenshot-info {
                font-size: 12px;
                color: #666;
            }
            .screenshot-name {
                font-weight: bold;
                word-break: break-all;
                margin-bottom: 5px;
                font-size: 11px;
            }
            .screenshot-actions {
                margin-top: 10px;
                display: flex;
                gap: 5px;
            }
            .screenshot-actions a {
                text-decoration: none;
            }
            
            /* Lightbox */
            .lightbox {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                cursor: pointer;
            }
            .lightbox img {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 90%;
                max-height: 90%;
                border: 2px solid #fff;
            }
            .lightbox-close {
                position: absolute;
                top: 20px;
                right: 40px;
                color: #fff;
                font-size: 40px;
                font-weight: bold;
                cursor: pointer;
            }
        </style>
        
        <div class="screenshots-grid">
            <?php foreach ($screenshots as $screenshot): ?>
            <div class="screenshot-item">
                <img src="<?php echo esc_url($screenshot['url']); ?>" 
                     alt="<?php echo esc_attr($screenshot['filename']); ?>" 
                     class="screenshot-preview"
                     onclick="openLightbox('<?php echo esc_js($screenshot['url']); ?>')" />
                <div class="screenshot-name"><?php echo esc_html($screenshot['filename']); ?></div>
                <div class="screenshot-info">
                    <div><?php echo $uploader->format_size($screenshot['size']); ?></div>
                    <div><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $screenshot['modified']); ?></div>
                </div>
                <div class="screenshot-actions">
                    <a href="<?php echo esc_url($screenshot['url']); ?>" target="_blank" class="button button-small">
                        <?php _e('Öffnen', 'wp-project-todos'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos-screenshots&action=delete&file=' . urlencode($screenshot['filename'])), 'delete_screenshot'); ?>" 
                       class="button button-small" 
                       style="color: #dc3545;"
                       onclick="return confirm('<?php _e('Wirklich löschen?', 'wp-project-todos'); ?>');">
                        <?php _e('Löschen', 'wp-project-todos'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($screenshots)): ?>
        <p><?php _e('Keine Screenshots gefunden. Klicke auf "Alle lokalen Screenshots hochladen" um zu beginnen.', 'wp-project-todos'); ?></p>
        <?php endif; ?>
        
        <!-- Lightbox -->
        <div id="lightbox" class="lightbox" onclick="closeLightbox()">
            <span class="lightbox-close">&times;</span>
            <img id="lightboxImg" src="" alt="">
        </div>
        
        <script>
        function openLightbox(url) {
            document.getElementById('lightboxImg').src = url;
            document.getElementById('lightbox').style.display = 'block';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
        </script>
    </div>
    <?php
}

// Keep old function for backwards compatibility
function wp_project_todos_render_screenshots_page($screenshots_obj) {
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_screenshot')) {
            $filepath = base64_decode($_GET['file']);
            $result = $screenshots_obj->delete($filepath);
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>' . __('Screenshot gelöscht!', 'wp-project-todos') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            }
        }
    }
    
    // Handle copy to uploads
    if (isset($_GET['action']) && $_GET['action'] === 'copy' && isset($_GET['file'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'copy_screenshot')) {
            $filepath = base64_decode($_GET['file']);
            $result = $screenshots_obj->copy_to_uploads($filepath);
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('Screenshot kopiert nach: %s', 'wp-project-todos'), $result['url']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            }
        }
    }
    
    // Get filter parameters
    $filter_dir = isset($_GET['filter_dir']) ? sanitize_text_field($_GET['filter_dir']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    $all_screenshots = $screenshots_obj->get_all_screenshots($filter_dir, $search);
    ?>
    <div class="wrap">
        <h1><?php _e('Playwright Screenshots', 'wp-project-todos'); ?></h1>
        
        <p><?php _e('Alle Screenshots aus den Projekt-Verzeichnissen, sortiert nach Änderungsdatum (neueste zuerst).', 'wp-project-todos'); ?></p>
        
        <!-- Filter Form -->
        <form method="get" action="" style="margin: 20px 0;">
            <input type="hidden" name="page" value="wp-project-todos-screenshots" />
            
            <select name="filter_dir" style="margin-right: 10px;">
                <option value=""><?php _e('Alle Verzeichnisse', 'wp-project-todos'); ?></option>
                <option value="/home/rodemkay/www/react/" <?php selected($filter_dir, '/home/rodemkay/www/react/'); ?>>react (Hauptverzeichnis)</option>
                <option value="/home/rodemkay/www/react/screenshots/" <?php selected($filter_dir, '/home/rodemkay/www/react/screenshots/'); ?>>screenshots</option>
                <option value="/home/rodemkay/www/react/.playwright-mcp/" <?php selected($filter_dir, '/home/rodemkay/www/react/.playwright-mcp/'); ?>>.playwright-mcp</option>
                <option value="/home/rodemkay/www/react/playwright-screenshots/" <?php selected($filter_dir, '/home/rodemkay/www/react/playwright-screenshots/'); ?>>playwright-screenshots</option>
            </select>
            
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Dateiname suchen...', 'wp-project-todos'); ?>" style="margin-right: 10px;" />
            
            <input type="submit" class="button" value="<?php _e('Filtern', 'wp-project-todos'); ?>" />
            
            <?php if ($filter_dir || $search): ?>
            <a href="<?php echo admin_url('admin.php?page=wp-project-todos-screenshots'); ?>" class="button"><?php _e('Filter zurücksetzen', 'wp-project-todos'); ?></a>
            <?php endif; ?>
        </form>
        
        <p style="color: #666;">
            <?php echo sprintf(__('Zeige %d Screenshots', 'wp-project-todos'), count($all_screenshots)); ?>
            <?php if ($filter_dir): ?>
                <?php echo sprintf(__('aus %s', 'wp-project-todos'), basename($filter_dir) ?: 'react'); ?>
            <?php endif; ?>
            <?php if ($search): ?>
                <?php echo sprintf(__('mit "%s" im Namen', 'wp-project-todos'), $search); ?>
            <?php endif; ?>
        </p>
        
        <style>
            .screenshots-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .screenshot-item {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .screenshot-preview {
                width: 100%;
                height: 150px;
                object-fit: cover;
                border-radius: 3px;
                margin-bottom: 10px;
                cursor: pointer;
            }
            .screenshot-info {
                font-size: 12px;
                color: #666;
            }
            .screenshot-name {
                font-weight: bold;
                word-break: break-all;
                margin-bottom: 5px;
            }
            .screenshot-actions {
                margin-top: 10px;
                display: flex;
                gap: 10px;
            }
            .screenshot-actions a {
                text-decoration: none;
            }
            
            /* Lightbox styles */
            .screenshot-lightbox {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                cursor: pointer;
            }
            .screenshot-lightbox img {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 90%;
                max-height: 90%;
                border: 2px solid #fff;
                box-shadow: 0 0 20px rgba(0,0,0,0.5);
            }
            .screenshot-lightbox-close {
                position: absolute;
                top: 20px;
                right: 40px;
                color: #fff;
                font-size: 40px;
                font-weight: bold;
                cursor: pointer;
            }
            .screenshot-lightbox-close:hover {
                color: #f1f1f1;
            }
        </style>
        
        <div class="screenshots-grid">
            <?php foreach ($all_screenshots as $screenshot): ?>
            <div class="screenshot-item">
                <img src="<?php echo esc_url($screenshot['url']); ?>" 
                     alt="<?php echo esc_attr($screenshot['filename']); ?>" 
                     class="screenshot-preview"
                     onclick="openLightbox('<?php echo esc_js($screenshot['url']); ?>')" />
                <div class="screenshot-name"><?php echo esc_html($screenshot['filename']); ?></div>
                <div class="screenshot-info">
                    <div><?php echo $screenshots_obj->format_size($screenshot['size']); ?></div>
                    <div><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $screenshot['modified']); ?></div>
                    <div style="color: #007cba;"><?php echo esc_html($screenshot['directory_name']); ?></div>
                </div>
                <div class="screenshot-actions">
                    <a href="<?php echo esc_url($screenshot['url']); ?>" target="_blank" class="button button-small">
                        <?php _e('Öffnen', 'wp-project-todos'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos-screenshots&action=copy&file=' . base64_encode($screenshot['filepath'])), 'copy_screenshot'); ?>" 
                       class="button button-small">
                        <?php _e('Kopieren', 'wp-project-todos'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos-screenshots&action=delete&file=' . base64_encode($screenshot['filepath'])), 'delete_screenshot'); ?>" 
                       class="button button-small" 
                       style="color: #dc3545;"
                       onclick="return confirm('<?php _e('Wirklich löschen?', 'wp-project-todos'); ?>');">
                        <?php _e('Löschen', 'wp-project-todos'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($all_screenshots)): ?>
        <p><?php _e('Keine Screenshots gefunden.', 'wp-project-todos'); ?></p>
        <?php endif; ?>
        
        <!-- Lightbox -->
        <div id="screenshotLightbox" class="screenshot-lightbox" onclick="closeLightbox()">
            <span class="screenshot-lightbox-close">&times;</span>
            <img id="lightboxImage" src="" alt="">
        </div>
        
        <script>
        function openLightbox(url) {
            document.getElementById('lightboxImage').src = url;
            document.getElementById('screenshotLightbox').style.display = 'block';
        }
        
        function closeLightbox() {
            document.getElementById('screenshotLightbox').style.display = 'none';
        }
        
        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
        </script>
    </div>
    <?php
}

// Render Playwright Screenshots page
function wp_project_todos_render_playwright_page($manager) {
    // Handle delete single screenshot
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_playwright_screenshot')) {
            $result = $manager->delete_screenshot($_GET['file']);
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>' . __('Screenshot gelöscht!', 'wp-project-todos') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            }
        }
    }
    
    // Handle delete all screenshots
    if (isset($_POST['delete_all']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_all_screenshots')) {
        $result = $manager->delete_all_screenshots();
        echo '<div class="notice notice-success"><p>';
        echo sprintf(__('%d von %d Screenshots gelöscht!', 'wp-project-todos'), $result['deleted'], $result['total']);
        if (!empty($result['errors'])) {
            echo '<br>' . __('Fehler:', 'wp-project-todos') . '<br>' . implode('<br>', $result['errors']);
        }
        echo '</p></div>';
    }
    
    // Handle upload all screenshots
    if (isset($_POST['upload_all']) && wp_verify_nonce($_POST['_wpnonce'], 'upload_all_screenshots')) {
        $result = $manager->upload_all_screenshots();
        echo '<div class="notice notice-success"><p>';
        echo sprintf(__('%d von %d Screenshots hochgeladen!', 'wp-project-todos'), $result['uploaded'], $result['total']);
        if (!empty($result['errors'])) {
            echo '<br>' . __('Fehler:', 'wp-project-todos') . '<br>' . implode('<br>', $result['errors']);
        }
        echo '</p></div>';
    }
    
    $screenshots = $manager->get_uploaded_screenshots();
    $local_count = count($manager->get_local_screenshots());
    
    ?>
    <div class="wrap">
        <h1>🎭 <?php _e('Playwright Screenshots', 'wp-project-todos'); ?></h1>
        
        <p><?php _e('Dedizierte Verwaltung für Screenshots aus dem playwright-screenshots Verzeichnis.', 'wp-project-todos'); ?></p>
        
        <!-- Status Info -->
        <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
            <p><strong><?php _e('Status:', 'wp-project-todos'); ?></strong></p>
            <ul style="margin: 10px 0;">
                <li>📁 <?php echo sprintf(__('Lokales Verzeichnis: %s', 'wp-project-todos'), '<code>' . $manager->get_local_directory() . '</code>'); ?></li>
                <li>🖼️ <?php echo sprintf(__('Lokale Screenshots: %d', 'wp-project-todos'), $local_count); ?></li>
                <li>☁️ <?php echo sprintf(__('Hochgeladene Screenshots: %d', 'wp-project-todos'), count($screenshots)); ?></li>
            </ul>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 10px; margin: 20px 0;">
            <?php if ($local_count > 0): ?>
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('upload_all_screenshots'); ?>
                <input type="submit" name="upload_all" class="button button-primary" 
                       value="📤 <?php echo sprintf(__('Alle %d lokalen Screenshots hochladen', 'wp-project-todos'), $local_count); ?>" />
            </form>
            <?php endif; ?>
            
            <?php if (count($screenshots) > 0): ?>
            <form method="post" action="" style="display: inline;" 
                  onsubmit="return confirm('<?php _e('Wirklich ALLE Screenshots löschen? Diese Aktion kann nicht rückgängig gemacht werden!', 'wp-project-todos'); ?>');">
                <?php wp_nonce_field('delete_all_screenshots'); ?>
                <input type="submit" name="delete_all" class="button" style="background: #dc3545; color: white; border-color: #dc3545;" 
                       value="🗑️ <?php _e('Alle Screenshots löschen', 'wp-project-todos'); ?>" />
            </form>
            <?php endif; ?>
        </div>
        
        <h2><?php echo sprintf(__('%d Screenshots', 'wp-project-todos'), count($screenshots)); ?></h2>
        
        <style>
            .screenshots-list-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                margin-top: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .screenshots-list-table thead {
                background: #f5f5f5;
                border-bottom: 2px solid #ddd;
            }
            .screenshots-list-table th {
                text-align: left;
                padding: 12px;
                font-weight: 600;
                color: #333;
            }
            .screenshots-list-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
                vertical-align: middle;
            }
            .screenshots-list-table tbody tr:hover {
                background: #f9f9f9;
            }
            .screenshot-thumb-cell {
                width: 80px;
            }
            .screenshot-list-thumb {
                width: 60px;
                height: 40px;
                object-fit: cover;
                border-radius: 4px;
                cursor: pointer;
                border: 1px solid #ddd;
                display: block;
            }
            .screenshot-list-thumb:hover {
                border-color: #667eea;
                box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
            }
            .screenshot-name-cell {
                font-weight: 500;
                color: #333;
            }
            .screenshot-size-cell {
                color: #666;
                font-size: 13px;
            }
            .screenshot-date-cell {
                color: #666;
                font-size: 13px;
            }
            .screenshot-actions-cell {
                text-align: right;
            }
            .screenshot-actions-cell a {
                text-decoration: none;
                flex: 1;
                text-align: center;
            }
            
            /* Enhanced Lightbox */
            .pw-lightbox {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.95);
                animation: fadeIn 0.3s;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .pw-lightbox img {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 90%;
                max-height: 90%;
                border: 3px solid #fff;
                border-radius: 4px;
                box-shadow: 0 0 30px rgba(0,0,0,0.5);
            }
            .pw-lightbox-close {
                position: absolute;
                top: 30px;
                right: 50px;
                color: #fff;
                font-size: 45px;
                font-weight: 300;
                cursor: pointer;
                transition: color 0.2s;
                background: rgba(0,0,0,0.5);
                width: 50px;
                height: 50px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
            }
            .pw-lightbox-close:hover {
                color: #ff6b6b;
            }
            .pw-lightbox-filename {
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                color: #fff;
                background: rgba(0,0,0,0.7);
                padding: 10px 20px;
                border-radius: 4px;
                font-size: 14px;
            }
        </style>
        
        <table class="screenshots-list-table">
            <thead>
                <tr>
                    <th class="screenshot-thumb-cell">Vorschau</th>
                    <th>Dateiname</th>
                    <th>Größe</th>
                    <th>Erstellt</th>
                    <th class="screenshot-actions-cell">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($screenshots as $screenshot): ?>
                <tr>
                    <td class="screenshot-thumb-cell">
                        <img src="<?php echo esc_url($screenshot['url']); ?>" 
                             alt="<?php echo esc_attr($screenshot['filename']); ?>" 
                             class="screenshot-list-thumb"
                             onclick="openPlaywrightLightbox('<?php echo esc_js($screenshot['url']); ?>', '<?php echo esc_js($screenshot['filename']); ?>')" />
                    </td>
                    <td class="screenshot-name-cell">
                        <?php echo esc_html($screenshot['filename']); ?>
                    </td>
                    <td class="screenshot-size-cell">
                        <?php echo $manager->format_size($screenshot['size']); ?>
                    </td>
                    <td class="screenshot-date-cell">
                        <?php echo date_i18n('d.m.Y H:i', $screenshot['modified']); ?>
                    </td>
                    <td class="screenshot-actions-cell">
                        <a href="<?php echo esc_url($screenshot['url']); ?>" target="_blank" class="button button-small">
                            👁️ <?php _e('Öffnen', 'wp-project-todos'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos-playwright&action=delete&file=' . urlencode($screenshot['filename'])), 'delete_playwright_screenshot'); ?>" 
                           class="button button-small" 
                           style="background: #fff5f5; color: #dc3545; border-color: #dc3545; margin-left: 5px;"
                           onclick="return confirm('<?php echo esc_js(sprintf(__('Screenshot "%s" wirklich löschen?', 'wp-project-todos'), $screenshot['filename'])); ?>');">
                            🗑️ <?php _e('Löschen', 'wp-project-todos'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($screenshots)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 8px; margin-top: 20px;">
            <p style="font-size: 48px; margin: 0;">📸</p>
            <p style="font-size: 18px; color: #666; margin: 20px 0;">
                <?php _e('Keine Screenshots vorhanden', 'wp-project-todos'); ?>
            </p>
            <?php if ($local_count > 0): ?>
            <p><?php echo sprintf(__('Es gibt %d lokale Screenshots. Klicke oben auf "Hochladen" um sie zu importieren.', 'wp-project-todos'), $local_count); ?></p>
            <?php else: ?>
            <p><?php _e('Keine lokalen Screenshots gefunden.', 'wp-project-todos'); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Enhanced Lightbox -->
        <div id="pwLightbox" class="pw-lightbox" onclick="closePlaywrightLightbox(event)">
            <span class="pw-lightbox-close" onclick="closePlaywrightLightbox(event)">&times;</span>
            <img id="pwLightboxImg" src="" alt="">
            <div class="pw-lightbox-filename" id="pwLightboxFilename"></div>
        </div>
        
        <script>
        function openPlaywrightLightbox(url, filename) {
            document.getElementById('pwLightboxImg').src = url;
            document.getElementById('pwLightboxFilename').textContent = filename;
            document.getElementById('pwLightbox').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closePlaywrightLightbox(event) {
            if (event.target.id === 'pwLightbox' || event.target.className === 'pw-lightbox-close') {
                document.getElementById('pwLightbox').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('pwLightbox').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // Arrow key navigation
        let currentIndex = -1;
        const screenshots = <?php echo json_encode(array_values($screenshots)); ?>;
        
        document.addEventListener('keydown', function(e) {
            const lightbox = document.getElementById('pwLightbox');
            if (lightbox.style.display === 'block') {
                if (e.key === 'ArrowRight') {
                    navigateScreenshot(1);
                } else if (e.key === 'ArrowLeft') {
                    navigateScreenshot(-1);
                }
            }
        });
        
        function navigateScreenshot(direction) {
            if (screenshots.length === 0) return;
            
            const currentSrc = document.getElementById('pwLightboxImg').src;
            currentIndex = screenshots.findIndex(s => s.url === currentSrc);
            
            if (currentIndex === -1) currentIndex = 0;
            
            currentIndex += direction;
            if (currentIndex < 0) currentIndex = screenshots.length - 1;
            if (currentIndex >= screenshots.length) currentIndex = 0;
            
            document.getElementById('pwLightboxImg').src = screenshots[currentIndex].url;
            document.getElementById('pwLightboxFilename').textContent = screenshots[currentIndex].filename;
        }
        </script>
    </div>
    <?php
}

// AJAX handler for serving screenshots
add_action('wp_ajax_wp_project_todos_serve_screenshot', 'wp_project_todos_serve_screenshot');
function wp_project_todos_serve_screenshot() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-screenshots.php';
    $screenshots = new WP_Project_Todos\Screenshots();
    $screenshots->serve_screenshot();
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'wp_project_todos_admin_enqueue');
function wp_project_todos_admin_enqueue($hook) {
    if (strpos($hook, 'wp-project-todos') === false) {
        return;
    }
    
    wp_enqueue_style(
        'wp-project-todos-admin',
        WP_PROJECT_TODOS_PLUGIN_URL . 'admin/css/admin.css',
        [],
        WP_PROJECT_TODOS_VERSION
    );
    
    wp_enqueue_script(
        'wp-project-todos-admin',
        WP_PROJECT_TODOS_PLUGIN_URL . 'admin/js/admin.js',
        ['jquery', 'wp-api'],
        WP_PROJECT_TODOS_VERSION,
        true
    );
    
    wp_localize_script('wp-project-todos-admin', 'wpProjectTodos', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'apiUrl' => rest_url('todos/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'strings' => [
            'confirmDelete' => __('Möchten Sie diese Aufgabe wirklich löschen?', 'wp-project-todos'),
            'saving' => __('Speichern...', 'wp-project-todos'),
            'saved' => __('Gespeichert!', 'wp-project-todos'),
            'error' => __('Ein Fehler ist aufgetreten.', 'wp-project-todos'),
        ]
    ]);
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'wp_project_todos_frontend_enqueue');
function wp_project_todos_frontend_enqueue() {
    wp_enqueue_style(
        'wp-project-todos-public',
        WP_PROJECT_TODOS_PLUGIN_URL . 'public/css/public.css',
        [],
        WP_PROJECT_TODOS_VERSION
    );
    
    wp_enqueue_script(
        'wp-project-todos-public',
        WP_PROJECT_TODOS_PLUGIN_URL . 'public/js/public.js',
        ['jquery'],
        WP_PROJECT_TODOS_VERSION,
        true
    );
}

// Register shortcodes
add_action('init', 'wp_project_todos_register_shortcodes');
function wp_project_todos_register_shortcodes() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-shortcodes.php';
    $shortcodes = new WP_Project_Todos\Shortcodes();
    $shortcodes->register();
}

// AJAX handlers
add_action('wp_ajax_wp_project_todos_quick_edit', 'wp_project_todos_ajax_quick_edit');
function wp_project_todos_ajax_quick_edit() {
    if (!current_user_can('edit_project_todos')) {
        wp_die(__('Keine Berechtigung', 'wp-project-todos'));
    }
    
    check_ajax_referer('wp_project_todos_nonce', 'nonce');
    
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-ajax.php';
    $ajax = new WP_Project_Todos\Ajax_Handler();
    $ajax->quick_edit();
}

add_action('wp_ajax_wp_project_todos_bulk_action', 'wp_project_todos_ajax_bulk_action');
function wp_project_todos_ajax_bulk_action() {
    if (!current_user_can('manage_project_todos')) {
        wp_die(__('Keine Berechtigung', 'wp-project-todos'));
    }
    
    check_ajax_referer('wp_project_todos_nonce', 'nonce');
    
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-ajax.php';
    $ajax = new WP_Project_Todos\Ajax_Handler();
    $ajax->bulk_action();
}

// Claude /todo command support
add_filter('wp_project_todos_claude_commands', 'wp_project_todos_register_claude_command');
function wp_project_todos_register_claude_command($commands) {
    $commands['todo'] = [
        'description' => __('Nächste offene Aufgabe abrufen und bearbeiten', 'wp-project-todos'),
        'callback' => 'wp_project_todos_handle_todo_command',
        'capabilities' => ['view_project_todos', 'edit_project_todos'],
    ];
    return $commands;
}

function wp_project_todos_handle_todo_command($args = []) {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-claude.php';
    $claude = new WP_Project_Todos\Claude_Integration();
    return $claude->handle_todo_command($args);
}

// Add custom cron schedule for auto-reload after compacting
add_filter('cron_schedules', 'wp_project_todos_cron_schedules');
function wp_project_todos_cron_schedules($schedules) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display' => __('Alle 5 Minuten', 'wp-project-todos')
    ];
    return $schedules;
}

// Schedule cron event
add_action('wp', 'wp_project_todos_schedule_cron');
function wp_project_todos_schedule_cron() {
    if (!wp_next_scheduled('wp_project_todos_check_compacting')) {
        wp_schedule_event(time(), 'every_5_minutes', 'wp_project_todos_check_compacting');
    }
}

// Cron callback for checking compacting
add_action('wp_project_todos_check_compacting', 'wp_project_todos_handle_compacting_check');
function wp_project_todos_handle_compacting_check() {
    require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-context-reload.php';
    $context = new WP_Project_Todos\Context_Reload();
    $context->check_and_reload();
}

// Clean up on uninstall
register_uninstall_hook(__FILE__, 'wp_project_todos_uninstall');
function wp_project_todos_uninstall() {
    // Remove capabilities
    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap('manage_project_todos');
        $role->remove_cap('edit_project_todos');
        $role->remove_cap('delete_project_todos');
        $role->remove_cap('view_project_todos');
    }
    
    // Clear scheduled cron
    wp_clear_scheduled_hook('wp_project_todos_check_compacting');
    
    // Option to delete database tables
    if (get_option('wp_project_todos_delete_on_uninstall')) {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_todos");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_todo_history");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_todo_comments");
    }
    
    // Delete options
    delete_option('wp_project_todos_version');
    delete_option('wp_project_todos_settings');
    delete_option('wp_project_todos_delete_on_uninstall');
}