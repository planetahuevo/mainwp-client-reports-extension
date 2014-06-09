<?php
/*
Plugin Name: MainWP Client Reports Extension
Plugin URI: http://extensions.mainwp.com
Description: MainWP Client Reports Extension allows you to generate activity reports for your clients sites. Requires MainWP Dashboard.
Version: 0.0.1
Author: MainWP
Author URI: 
Icon URI: http://extensions.mainwp.com/wp-content/uploads/2014/05/mainwp-client-reports-extension.png
*/


class MainWPCReportExtension
{
    public static $instance = null;
    public  $plugin_handle = "mainwp-wpcreport-extension";
    public static $plugin_url;
    public $plugin_slug;
    public $plugin_dir;    
    protected $option;    
    protected $option_handle = 'mainwp_wpcreport_extension';    
    
    static function Instance()
    {
        if (MainWPCReportExtension::$instance == null) MainWPCReportExtension::$instance = new MainWPCReportExtension();
        return MainWPCReportExtension::$instance;
    }

    public function __construct()
    {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        self::$plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_slug = plugin_basename(__FILE__);
        $this->option = get_option($this->option_handle);
        
        add_action('init', array(&$this, 'init'));
        add_filter('plugin_row_meta', array(&$this, 'plugin_row_meta'), 10, 2);
        add_action('admin_init', array(&$this, 'admin_init'));
        
        MainWPCReportDB::Instance()->install();
        
        if (isset($_GET['page']) && $_GET['page'] == "Extensions-Mainwp-Client-Reports-Extension" &&
            isset($_GET['action']) && $_GET['action'] == "savepdf" &&
            isset($_GET['id']) && $_GET['id'])
        {   
            $report = MainWPCReportDB::Instance()->getReportBy('id', $_GET['id']);
            if (!empty($report))
                require_once $this->plugin_dir.'/includes/save_as_pdf.php';
        }
    }

    public function init()
    {
        $mwp_creport = new MainWPCReport();
        $mwp_creport->init_cron();
    }
 
    public function plugin_row_meta($plugin_meta, $plugin_file)
    {
        if ($this->plugin_slug != $plugin_file) return $plugin_meta;

        $plugin_meta[] = '<a href="?do=checkUpgrade" title="Check for updates.">Check for updates now</a>';
        return $plugin_meta;
    }

    public function admin_init()
    {
        wp_enqueue_style('mainwp-creport-extension', self::$plugin_url . 'css/mainwp-reporting.css');
        wp_enqueue_script('mainwp-creport-extension', self::$plugin_url . 'js/mainwp-reporting.js');        
        $translation_array = array( 'dashboard_sitename' => get_bloginfo( 'name' ));
        MainWPCReport::init();
        $mwp_creport = new MainWPCReport();
        $mwp_creport->admin_init();
        $mwp_creport_stream = new MainWPCReportStream();
        $mwp_creport_stream->admin_init();
    }
    
    public function get_option($key, $default = '') {
        if (isset($this->option[$key]))
            return $this->option[$key];
        return $default;
    }
    public function set_option($key, $value) {
        $this->option[$key] = $value;
        return update_option($this->option_handle, $this->option);
    }
    
        
}


register_activation_hook(__FILE__, 'wpcreport_extension_activate');
register_deactivation_hook(__FILE__, 'wpcreport_extension_deactivate');

function wpcreport_extension_activate()
{   
    
}

function wpcreport_extension_deactivate()
{

}    

class MainWPCReportExtensionActivator
{
    protected $mainwpMainActivated = false;
    protected $childEnabled = false;
    protected $childKey = false;
    protected $childFile;

    public function __construct()
    {
        $this->childFile = __FILE__;        
        add_filter('mainwp-getextensions', array(&$this, 'get_this_extension'));
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', false);

        if ($this->mainwpMainActivated !== false)
        {
            $this->activate_this_plugin();
        }
        else
        {
            add_action('mainwp-activated', array(&$this, 'activate_this_plugin'));
        }
        add_action('admin_notices', array(&$this, 'mainwp_error_notice'));
    }

    function get_this_extension($pArray)
    {
        $pArray[] = array('plugin' => __FILE__, /*'api' => 'mainwp-client-reports-extension', */ 'mainwp' => true, 'callback' => array(&$this, 'settings'));
        return $pArray;
    }
 
    function settings()
    {
        do_action('mainwp-pageheader-extensions', __FILE__);
        if ($this->childEnabled)
        { 
            MainWPCReport::render();
        }
        else
        {
            ?><div class="mainwp_info-box-yellow"><strong><?php _e("The Extension has to be enabled to change the settings."); ?></strong></div><?php
        }
        do_action('mainwp-pagefooter-extensions', __FILE__);
    }
    
    function activate_this_plugin()
    {
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', $this->mainwpMainActivated);

        $this->childEnabled = apply_filters('mainwp-extension-enabled-check', __FILE__);
        if (!$this->childEnabled) return;

        $this->childKey = $this->childEnabled['key'];

        new MainWPCReportExtension();
    }

    public function getChildKey()
    {
        return $this->childKey;
    }

    public function getChildFile()
    {
        return $this->childFile;
    }

    function mainwp_error_notice()
    {
        global $current_screen;
        if ($current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false)
        {
            echo '<div class="error"><p>MainWP Client Reports Extension ' . __('requires <a href="http://mainwp.com/" target="_blank">MainWP</a> Plugin to be activated in order to work. Please install and activate <a href="http://mainwp.com/" target="_blank">MainWP</a> first.') . '</p></div>';
        }
    }

}

function mainwp_wpcreport_extension_autoload($class_name)
{
    $allowedLoadingTypes = array('class', 'page');

    foreach ($allowedLoadingTypes as $allowedLoadingType)
    {
        $class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . $allowedLoadingType . DIRECTORY_SEPARATOR . $class_name . '.' . $allowedLoadingType . '.php';
        if (file_exists($class_file))
        {
            require_once($class_file);
        }
    }
}

if (function_exists('spl_autoload_register'))
{
    spl_autoload_register('mainwp_wpcreport_extension_autoload');
}
else
{
    function __autoload($class_name)
    {
        mainwp_wpcreport_extension_autoload($class_name);
    }
}

$mainWPCReportExtensionActivator = new MainWPCReportExtensionActivator();