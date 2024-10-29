<?php
/**
 * Plugin Name:       Abandonment Protector
 * Plugin URI:        http://wordpress.org/plugins/abandonment_protector
 * Description:       Abandonment Protector provides tools for creating and sending newsletters, popup forms and email automations.
 * Version:           1.0.9
 * Author:            Chilliapps
 * Author URI:        https://www.chilliapps.com
 * License:           GPL
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.4
 */


define('ABP_VERSION', '1.0.9');

define('ABPROTECTOR_FILE', __FILE__ );
define('ABPROTECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__ ) );
define('ABPROTECTOR_PLUGIN_URL', plugins_url('', __FILE__) );
define('ABPROTECTOR_POPUPS_SCRIPTS_KEY', 'abprotector_popup_scripts');
define('ABPROTECTOR_SESSION_KEY', 'abprotector_user_session_key');
define('ABPROTECTOR_SETTINGS_KEYS', 'abprotector_keys');
define('ABPROTECTOR_CARTS_TABLE', 'abprotector_abandoned_carts');
define('ABPROTECTOR_API_BASE', 'https://ko.chilliapps.com/webhooks/woocommerce');
define('ABPROTECTOR_APP_URL', 'https://wp.chilliapps.com');

class AbandonmentProtector{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $absettings_keys;
    private static $instance;
    public $utils = null;
    

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // constructor
    public function __construct(){
        $this->load_wp_functions();
        $this->setup_admin();
        
        add_action( 'woocommerce_loaded',  array($this, 'on_woo_commerce_loaded') );
        add_action( 'wp_enqueue_scripts',  array($this, '_load_abprotector_popups_script') );
        add_action( 'updated_option',  array($this, '_check_updated_site_options') ); // para detectar cuando hubo cambios en los datos del sitio
        add_filter( 'woocommerce_get_settings_pages', array($this, '_load_woo_settings_checker') );
    }

    function setup_admin(){
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        // Mandar webhook a chillicore para indicar que se cambiaron (posible instalacion/reinstalacion), alla manejar
        add_action('add_option_abprotector_keys', array($this, 'check_keys_added'));
        add_action('update_option_abprotector_keys', array($this, 'check_keys_updated'));
    }

    // Esta funcion lo que hace es agregar la opcion de nuestro menu en el sidebar
    // de la izquierda de wordpress
    public function add_plugin_page(){

        // encolar una hoja de estilos css
        function add_admin_css(){
            $stylesheet_path = ABPROTECTOR_PLUGIN_URL . '/resources/css/wp-abprotector.css';

            wp_enqueue_style(
                'wp_abprotector_css',
                $stylesheet_path,
                null,
                5 // cambiar la version cuando se hagan cambios al css
            );
        }

        function add_admin_js(){
            $js_path = ABPROTECTOR_PLUGIN_URL . '/resources/js/admin.js';

            wp_enqueue_script(
                'abprotector-admin',
                $js_path,
                array( 'jquery' ),
                4 // cambiar la version cuando se hagan cambios al js
            );
        }

        add_action( 'admin_enqueue_scripts', 'add_admin_css' );
        add_action( 'admin_enqueue_scripts', 'add_admin_js' );


        // Metodo para agregar la opcion en el sidebar izquierdo
        add_menu_page(
            'Abandonment Protector', // Nombre del plugin
            'Abandonment Protector', // Nombre del inciso como aparecera en el sidebar
            'manage_options',
            'abandonment-protector',
            array( $this, 'create_admin_page' ), // Callback que creara la vista que sera desplegada al oprimir ese inciso en el sidebar
            'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iNjRweCIgaGVpZ2h0PSI1M3B4IiB2aWV3Qm94PSIwIDAgNjQgNTMiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+CiAgICA8IS0tIEdlbmVyYXRvcjogU2tldGNoIDUwLjIgKDU1MDQ3KSAtIGh0dHA6Ly93d3cuYm9oZW1pYW5jb2RpbmcuY29tL3NrZXRjaCAtLT4KICAgIDx0aXRsZT5BcnRib2FyZDwvdGl0bGU+CiAgICA8ZGVzYz5DcmVhdGVkIHdpdGggU2tldGNoLjwvZGVzYz4KICAgIDxkZWZzPjwvZGVmcz4KICAgIDxnIGlkPSJBcnRib2FyZCIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPHBhdGggZD0iTTI1LjQxMzU2NjIsMzEuNTI3MjY4OCBDMjUuNDEzNTY2MiwzMC44OTY4NjI5IDI0Ljk2OTgyMTMsMzAuMzgxMDc2MyAyNC4yOTg3NTIxLDMwLjM4MTA3NjMgTDE2LjE5NjQ0NjYsMzAuMzgxMDc2MyBDMTUuNTI1Mzc3NCwzMC4zODEwNzYzIDE1LjM0NjAxODksMjkuOTk5Mzk0MiAxNS43OTc0NjU0LDI5LjUzNDA0MDEgTDMxLjE2OTgzMDYsMTMuNjc1MzIwNyBDMzEuNjIxMjc3MiwxMy4yMDk5NjY1IDMyLjM2MDY3MzQsMTMuMjA5OTY2NSAzMi44MTIxMiwxMy42NzUzMjA3IEw0OC4xODQ0ODUxLDI5LjUzNDA0MDEgQzQ4LjYzNzE1MTgsMjkuOTk5Mzk0MiA0OC40NTc3OTMzLDMwLjM4MTA3NjMgNDcuNzg2NzI0MSwzMC4zODEwNzYzIEwzOS41NzUyNDQzLDMwLjM4MTA3NjMgQzM4LjkwNDE3NTEsMzAuMzgxMDc2MyAzOC43NTQ4Njc5LDMwLjg5Njg2MjkgMzguNzU0ODY3OSwzMS41MjcyNjg4IEwzOC43NTQ4Njc5LDUxLjQwNzk3NzcgQzM4Ljc1NDg2NzksNTIuMDM4MzgzNSAzOS44MjI5NjI5LDUyLjE4OTY4MDkgNDAuMjk3NTkxOSw1MS43NDM4MTIxIEw2Mi40MDk5MzIsMzEuNTI3MjY4OCBDNjIuNjUwMDc5OCwzMS4zMDE2NzI4IDYzLjAwMjg0OTcsMzAuNzA0ODk5MSA2Mi45OTk5ODI2LDMwLjEwOTY1ODkgQzYyLjk5NzE4MzIsMjkuNTI4NDYzOSA2Mi42NDQ0MTMzLDI4Ljk0ODU5NDEgNjIuNDA5OTMyLDI4LjcyODMyMTIgTDMzLjU2NTM1MTYsMS41ODUyODIyIEMzMy4zMjgxODIxLDEuMzYyNDg0IDMyLjY0OTI2NjcsMS4wMDAxMzYxOSAzMS45OTA5NzUzLDEuMDAwMDAwMDQgQzMxLjMzMTg3ODgsMC45OTk4NjM3MTggMzAuNjkzNDA1OCwxLjM2MjIxMTUzIDMwLjQ1NTk0NjMsMS41ODUyODIyIEwxLjU3MjAxODU0LDI4LjcyODMyMTIgQzEuMzM3MDY5MDgsMjguOTQ5MDMzOSAxLjAwMjM1NDE1LDI5LjUyNzI4NzYgMS4wMDAwMTI3MSwzMC4xMDk2NTg5IEMwLjk5NzYyNDEzNCwzMC43MDM3NTQ0IDEuMzMyMzM5MDYsMzEuMzAyMTEyNyAxLjU3MjAxODU0LDMxLjUyNzI2ODggTDIzLjY4NDM1ODcsNTEuNzQzODEyMSBDMjQuMTU4OTg3Nyw1Mi4xODk2ODA5IDI1LjQxMzU2NjIsNTIuMDM4MzgzNSAyNS40MTM1NjYyLDUxLjQwNzk3NzcgTDI1LjQxMzU2NjIsMzEuNTI3MjY4OCBaIiBpZD0iRmlsbC0xIiBmaWxsPSIjYTBhNWFhIj48L3BhdGg+CiAgICA8L2c+Cjwvc3ZnPg==',
            20
        );
    }

     /**
     * Register and add settings
     */
    public function page_init(){
        // registrar settings  a whitelist

        register_setting(
            'abprotector_settings', // Option group
            'abprotector_keys', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        // Primero creamos la seccion donde iran los campos de las API KEYS
        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'abprotector_admin_settings' // El donde se va a pintar
        );  

        add_settings_field(
            'api_key', // ID
            'Public key', // Title 
            array( $this, 'print_api_key_input' ), // Callback que sera llamada para agregar el input field
            'abprotector_admin_settings', // Page
            'setting_section_id' // ID de la seccion a donde pertenece          
        );
        
        add_settings_field(
            'secret_key', // ID
            'Secret key', // Title 
            array( $this, 'print_secret_key_input' ), // Callback que sera llamada para agregar el input field
            'abprotector_admin_settings', // Page
            'setting_section_id' // Section           
        );
    }

    function check_keys_added( $data ){
        // crear de nuevo el objeto utils aqui para que traiga de nuevo las keys
        $utils = new AbprotectorUtils();
        $resp = $utils->send_webhook(ABPROTECTOR_API_BASE."/keys_changed", array('data' => $data, '_cmd' => 'added'));

        $this->_parse_keys_response($resp);
    }

    function check_keys_updated( $data ){
        // crear de nuevo el objeto utils aqui para que traiga de nuevo las keys
        $utils = new AbprotectorUtils();
        $resp = $utils->send_webhook(ABPROTECTOR_API_BASE."/keys_changed", array('data' => $data, '_cmd' => 'updated'));

        $this->_parse_keys_response($resp);
    }

    function _parse_keys_response($resp){
        try {
            if( is_null($resp) ){
                delete_option("abp_keys_status");
                return;
            }

            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body);

            if( $data->status == "valid" ){
                delete_option("abp_keys_status");
            }else{
                // dejar la opcion en blanco si fueron invalidas
                update_option("abprotector_keys", "");

                if( is_null(get_option("abp_keys_status")) ){
                    add_option("abp_keys_status", "invalid");
                }else{
                    update_option("abp_keys_status", "invalid");
                }
            }
        } catch (Exception $e) {
            // TODO: do something
        }
    }

     // Este callback crea la vista que se mostrara del plugin (La del disenio)
    public function create_admin_page(){
        // Set class property
        $this->absettings_keys = get_option(ABPROTECTOR_SETTINGS_KEYS);
        $abprotector_keys = $this->absettings_keys;
        $keys_already_saved = is_array($this->absettings_keys);

        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $current_user = wp_get_current_user();

        // para evitar problemas con acentos, espacios y otras cosas en el titulo
        $site_name = base64_encode($site_name);
        $shop_domain = $this->utils->remove_url_protocol($site_url);
        $host_url = ABPROTECTOR_APP_URL.'/wp/woo_access_callback';

        $user_id = $this->utils->guidv4();
        $callback_params = array(
            'wp_shop' => rawurlencode($shop_domain),
            'wp_shop_name' => rawurlencode($site_name),
            'user_id' => $user_id,
            'user_email' => rawurlencode($current_user->user_email)
        );
        $callback_url = $host_url."?".http_build_query($callback_params);

        $auth_params = array(
            'app_name' => 'Abandonment Protector',
            'scope' => 'read_write',
            'user_id' => $user_id,
            'return_url' => (ABPROTECTOR_APP_URL.'/wp/complete_register'),
            'callback_url' => $callback_url
        );

        if( function_exists( 'WC' ) ){
            $auth_url = $site_url.'/wc-auth/v1/authorize?'.http_build_query($auth_params);
        }else{
            include(ABPROTECTOR_PLUGIN_DIR . "/partials/no_woocommerce.php");
            $auth_url = $site_url.'/wp-json/abprotector/authorize?'.http_build_query($auth_params);
        }
        
        $keys_validation_status = get_option("abp_keys_status");

        if( $keys_already_saved ){
            include(ABPROTECTOR_PLUGIN_DIR . "/partials/already_registered_keys.php");
        }else{
            include(ABPROTECTOR_PLUGIN_DIR . "/partials/no_registered_keys.php");
        }
    }


    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ){
        $new_input = array();

        // En esta parte es donde creas los valors de los inputs con sus llaves que seran guardadas

        if( isset( $input['api_key'] ) ) {
            $new_input['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        if( isset( $input['secret_key'] ) ) {
            $new_input['secret_key'] = sanitize_text_field( $input['secret_key'] );
        }
            
        return $new_input;
    }


    public function print_section_info(){
        // do something
    }

    public function print_api_key_input(){
        // El printf imprime el string que mandamos, por lo que en esta parte creamos los inputs con el name, el value y el id 
        // con el que guardaremos el API KEY en las variables
        printf(
            '<input type="text" class="input_large fs16" id="api_key" name="abprotector_keys[api_key]" value="" placeholder="API key" required="true" />'
        );
    }

    public function print_secret_key_input(){
        // El printf imprime el string que mandamos, por lo que en esta parte creamos los inputs con el name, el value y el id 
        // con el que guardaremos el API KEY en las variables
        printf(
            '<input type="text" class="input_large fs16" id="secret_key" name="abprotector_keys[secret_key]" value="" placeholder="Secret key" required="true" />'
        );
    }


    // ----------------- setup abandonment protector plugin functions ---------------

    function on_woo_commerce_loaded(){
        $this->_load_trackers_and_cart_mgrs();
        $this->_load_webhook_classes();
        $this->_load_woo_abprotector_apis();
    }

    function load_wp_functions(){
        // carga las funciones que no requieren de woocoommerce ni otro plugin, solo de wordpress

        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-utils.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-setup.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'api/abprotector-api-endpoints.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'api/abprotector-api-popups.php';

        // usar esta variable para acceder a los metodos de la clase AbprotectorUtils
        $this->utils = AbprotectorUtils::get_instance();
        $setup = new AbprotectorSetup();

        // registrar nuestros endpoints para consultas
        $api_cart = new AbprotectorApiEndpoints();
        $api_cart->init();

        // api popups
        $api_popups = new AbprotectorApiPopups();
        $api_popups->init();
    }

    function _load_trackers_and_cart_mgrs(){
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-woo-session.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-cart-mgr.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-trackers.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-auto-apply-coupons.php';

        $abtrackers = AbprotectorTrackers::get_instance();
        $abtrackers->prepare_tracking_data();

        $auto_apply = new AbprotectorAutoApplyCoupons();
        $auto_apply->init();
    }

    function _load_webhook_classes(){
        include_once ABPROTECTOR_PLUGIN_DIR . 'webhooks/abprotector-orders.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'webhooks/abprotector-products.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'webhooks/abprotector-customers.php';

        $whs_orders = AbprotectorOrdersWebhooks::get_instance();
        $whs_orders->init_webhooks();

        $whs_products = AbprotectorProductsWebhooks::get_instance();
        $whs_products->init_webhooks();

        $whs_customers = AbprotectorCustomersWebhooks::get_instance();
        $whs_customers->init_webhooks();
    }

    function _load_woo_abprotector_apis(){
        include_once ABPROTECTOR_PLUGIN_DIR . 'api/abprotector-api-products.php';
        include_once ABPROTECTOR_PLUGIN_DIR . 'api/abprotector-api-orders.php';

        $api_prods = new AbprotectorApiProducts();
        $api_prods->init_reponses_include_variations();

        $api_orders = new AbprotectorApiOrders();
        $api_orders->init_reponses_formatter();
    }

    // ------- cargar el script de popups -------
    function _load_abprotector_popups_script(){
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-popups.php';
        // cargar tracker (otra vez, si es que no esta) para agregar los js (include_once)
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-trackers.php';

        $api_popups = new AbprotectorPopups();
        $api_popups->add_abprotector_popups_script();

        $abtrackers = AbprotectorTrackers::get_instance();
        $abtrackers->add_abprotector_tracking_script(); // agregar solo los tracker JS
    }

    // ------ detectar si cambiaron los datos de la tienda -------
    function _load_woo_settings_checker($settings){
        include_once ABPROTECTOR_PLUGIN_DIR . 'classes/abprotector-woo-settings.php';
        new AbprotectorWooSettings();

        return $settings;
    }

    function _check_updated_site_options($option){
        $valid_options = array('blogname' => 'name', 'siteurl' => 'domain', 'WPLANG' => 'primary_locale');

        if( !in_array($option, $valid_options) ){
            return;
        }

        $site_url = $this->utils->remove_url_protocol(get_bloginfo('url'));
        $option_value = get_option($option);

        $data = array(
            $valid_options[$option] => $option_value,
            'wordpress_url' => $site_url
        );

        $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/shop_update', $data );
    }
}

AbandonmentProtector::get_instance();