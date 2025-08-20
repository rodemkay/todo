<?php
/**
 * Settings class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Settings {
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WP Project To-Dos Einstellungen', 'wp-project-todos'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wp_project_todos_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Standard Arbeitsverzeichnis', 'wp-project-todos'); ?></th>
                        <td>
                            <input type="text" name="wp_project_todos_default_working_directory" 
                                   value="<?php echo esc_attr(get_option('wp_project_todos_default_working_directory', '/home/rodemkay/www/react/')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Claude Integration', 'wp-project-todos'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_project_todos_claude_enabled" value="1" 
                                       <?php checked(get_option('wp_project_todos_claude_enabled', true)); ?> />
                                <?php _e('Claude Integration aktivieren', 'wp-project-todos'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Auto-Reload nach Compacting', 'wp-project-todos'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_project_todos_auto_reload_configs" value="1" 
                                       <?php checked(get_option('wp_project_todos_auto_reload_configs', true)); ?> />
                                <?php _e('Konfigurationen automatisch neu laden', 'wp-project-todos'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('API Key', 'wp-project-todos'); ?></th>
                        <td>
                            <code><?php echo esc_html(get_option('wp_project_todos_api_key', 'Wird beim ersten API-Zugriff generiert')); ?></code>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}