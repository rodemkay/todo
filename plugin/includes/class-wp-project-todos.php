<?php
/**
 * Main plugin class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class WP_Project_Todos {
    
    /**
     * Plugin version
     */
    protected $version;
    
    /**
     * Plugin name
     */
    protected $plugin_name;
    
    /**
     * Loader instance
     */
    protected $loader;
    
    /**
     * Admin instance
     */
    protected $admin;
    
    /**
     * API instance
     */
    protected $api;
    
    /**
     * Claude integration instance
     */
    protected $claude;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->version = WP_PROJECT_TODOS_VERSION;
        $this->plugin_name = 'wp-project-todos';
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load core classes
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-todo-model.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-api.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-claude.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-directory-manager.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-output-capture.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-context-reload.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once WP_PROJECT_TODOS_PLUGIN_DIR . 'includes/class-auto-todo.php';
        
        $this->loader = new Loader();
    }
    
    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        $this->admin = new Admin();
        
        // Admin scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
        
        // Admin menu
        $this->loader->add_action('admin_menu', $this->admin, 'add_menu_pages');
        
        // Admin notices
        $this->loader->add_action('admin_notices', $this->admin, 'display_admin_notices');
        
        // Screen options
        $this->loader->add_filter('set-screen-option', $this->admin, 'set_screen_option', 10, 3);
        
        // Export/Import
        $this->loader->add_action('admin_post_export_todos', $this->admin, 'handle_export');
        $this->loader->add_action('admin_post_import_todos', $this->admin, 'handle_import');
        
        // Initialize AJAX handlers
        $ajax = new Ajax();
    }
    
    /**
     * Define public hooks
     */
    private function define_public_hooks() {
        $shortcodes = new Shortcodes();
        
        // Register shortcodes
        $this->loader->add_action('init', $shortcodes, 'register');
        
        // Public scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', $shortcodes, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $shortcodes, 'enqueue_scripts');
    }
    
    /**
     * Define API hooks
     */
    private function define_api_hooks() {
        $this->api = new API();
        $this->claude = new Claude_Integration();
        
        // REST API routes
        $this->loader->add_action('rest_api_init', $this->api, 'register_routes');
        
        // Claude integration
        $this->loader->add_filter('wp_project_todos_claude_commands', $this->claude, 'register_commands');
        
        // Output capture
        $output_capture = new Output_Capture();
        $this->loader->add_action('wp_project_todos_capture_output', $output_capture, 'capture', 10, 3);
        
        // Context reload
        $context_reload = new Context_Reload();
        $this->loader->add_action('wp_project_todos_check_compacting', $context_reload, 'check_and_reload');
        
        // Auto Todo execution system
        $auto_todo = new Auto_Todo();
        // The Auto_Todo constructor already registers its hooks, so we just need to instantiate it
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * Get plugin name
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }
}

/**
 * Loader class to register hooks
 */
class Loader {
    
    /**
     * Array of actions
     */
    protected $actions;
    
    /**
     * Array of filters
     */
    protected $filters;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }
    
    /**
     * Add action
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add filter
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add hook to collection
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        ];
        
        return $hooks;
    }
    
    /**
     * Register all hooks
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
        
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
    }
}