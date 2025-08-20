<?php
/**
 * Directory Manager class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Directory_Manager {
    
    public function get_presets() {
        return get_option('wp_project_todos_directory_presets', [
            'React Project' => '/home/rodemkay/www/react/',
            'n8n Workflows' => '/home/rodemkay/n8n-workflows/',
            'MT5 Development' => '/home/rodemkay/mt5/',
            'Staging Server' => '/var/www/forexsignale/staging/',
            'Kommandozentrale' => '/home/rodemkay/kommandozentrale/',
        ]);
    }
    
    public function validate_directory($path) {
        return is_dir($path) && is_readable($path);
    }
    
    public function switch_directory($path) {
        if ($this->validate_directory($path)) {
            chdir($path);
            return true;
        }
        return false;
    }
}