<?php

    class AbprotectorTrackers{

        private static $instance;
        private $utils = null;
        private $cart_mgr = null;

        /**
         *  Initiator
         */

        public static function get_instance() {
            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct(){
            $this->utils = AbprotectorUtils::get_instance();

            if( class_exists('AbprotectorCartMgr') ){
                $this->cart_mgr = AbprotectorCartMgr::get_instance();
            }
        }

        // inicializa las funciones, debe ser llamada, no es constructor
        function prepare_tracking_data(){
            add_action( 'woocommerce_after_checkout_form', array( $this, 'check_notify_checkout_with_logged_customer' ) );

            add_action('wp_ajax_save_abprotector_customer_data', array( $this, 'save_abprotector_customer_data' ));
            add_action('wp_ajax_nopriv_save_abprotector_customer_data', array( $this, 'save_abprotector_customer_data' ));
            add_action('wp_ajax_save_abprotector_gpdr_response', array( $this, 'save_abprotector_gpdr_response' ));
            add_action('wp_ajax_nopriv_save_abprotector_gpdr_response', array( $this, 'save_abprotector_gpdr_response' ));

            add_filter( 'wp', array( $this, '_restore_cart_abandonment_data' ), 10 );

            // Borrar los datos de carritos abandonados si ya fueron convertidos a ordenes
            add_action( 'woocommerce_new_order', array( $this, '_delete_cart_abandonment_data' ) );
            add_action( 'woocommerce_thankyou', array( $this, '_delete_cart_abandonment_data' ) );
        }

        function add_abprotector_tracking_script(){
            // esa funcion se llama cuando wp_enqueue_scripts es disparado (en todas las paginas)
            // solo agregar si la pagina es checkout

            // Checar si woocommere esta instalado
            if( !function_exists( 'WC' ) ){
                return;
            }

            if( !is_checkout() ){
                return;
            }

            $script_path = ABPROTECTOR_PLUGIN_URL . '/resources/js/abprotector_cart_track.js';
            // IMPORTANTE: CAMBIAR LA VERSION AL HACER CAMBIOS
            $version = 11;


            $accepted_policy = isset( $_COOKIE['abprotector_accepted_policy'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_policy']) : "empty";
            $accepted_gdpr = isset( $_COOKIE['abprotector_accepted_gdpr'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_gdpr']) : "false";

            if( isset($_GET['chpabp_crt_tk']) ){
                // obtener las politicas guardadas en un abandoned cart
                $policies = $this->_get_abprotector_policies_status_from_cart(filter_input(INPUT_GET, 'chpabp_crt_tk', FILTER_SANITIZE_STRING));
                $accepted_policy = $policies['accepted_policy'];
                $accepted_gdpr = $policies['accepted_gdpr'];
            }

            wp_enqueue_script(
                'abprotector-tracking',
                $script_path,
                array( 'jquery' ),
                $version
            );

            $vars = array(
              'ajaxurl' => admin_url( 'admin-ajax.php' ),
              '_nonce_data'  => wp_create_nonce( 'save_abprotector_customer_data' ),
              '_nonce_gdpr' => wp_create_nonce( 'save_abprotector_gpdr_response' ),
              'accepted_policy' => $accepted_policy,
              'accepted_gdpr' => $accepted_gdpr
            );
            wp_localize_script( 'abprotector-tracking', 'AbprotectorTrackingVars', $vars );
        }


        function save_abprotector_customer_data(){
            global $wpdb, $woocommerce;

            check_ajax_referer( 'save_abprotector_customer_data', 'abtoken' );

            //Post details
            $params = array(
                'billing_first_name' => (isset($_POST['billing_first_name'])) ? sanitize_text_field($_POST['billing_first_name']) : '',
                'billing_last_name' => (isset($_POST['billing_last_name'])) ? sanitize_text_field($_POST['billing_last_name']) : '',
                'billing_company' => (isset($_POST['billing_company'])) ? sanitize_text_field($_POST['billing_company']) : '',
                'billing_address_1' => (isset($_POST['billing_address_1'])) ? sanitize_text_field($_POST['billing_address_1']) : '',
                'billing_address_2' => (isset($_POST['billing_address_2'])) ? sanitize_text_field($_POST['billing_address_2']) : '',
                'billing_city' => (isset($_POST['billing_city'])) ? sanitize_text_field($_POST['billing_city']) : '',
                'billing_state' => (isset($_POST['billing_state'])) ? sanitize_text_field($_POST['billing_state']) : '',
                'billing_zipcode' => (isset($_POST['billing_postcode'])) ? sanitize_text_field($_POST['billing_postcode']) : '',
                'billing_country' => (isset($_POST['billing_country'])) ? sanitize_text_field($_POST['billing_country']) : '',
                'billing_phone' => (isset($_POST['billing_phone'])) ? sanitize_text_field($_POST['billing_phone']) : '',
                'billing_email' => sanitize_text_field($_POST['billing_email'])
            );

            $customer_data = $this->utils->format_customer_data_from_params($params);

            $order_data = $this->cart_mgr->get_data_for_webhook();
            $order_data['created_at'] = date("c");
            $order_data['email'] = $customer_data['email'];
            $order_data['customer'] = $customer_data['customer'];
            $order_data['billing_address'] = $customer_data['billing_address'];

            if( $order_data['email'] == '' ){
                wp_send_json_success();
                return;
            }

            // ENVIAR A ABPROTECTOR
            $url = ABPROTECTOR_API_BASE . "/checkout";
            $this->utils->send_webhook($url, $order_data);

            // guardar datos a una tabla de bd para recuperar despues
            $this->save_cart_abandonment_data($order_data);

            wp_send_json_success();
        }

        function check_notify_checkout_with_logged_customer(){
            $user_id = get_current_user_id();

            if( $user_id == 0 ){
                return;
            }

            $order_data = $this->cart_mgr->get_data_for_webhook();
            $order_data['created_at'] = date("c");

            $url = ABPROTECTOR_API_BASE . "/checkout";
            $this->utils->send_webhook($url, $order_data);
        }


        // -------------- cookies --------------
        function save_abprotector_gpdr_response(){
            check_ajax_referer('save_abprotector_gpdr_response', 'abtoken');

            // guardar en una cookie que aceptó que recabaramos datos
            $this->_check_set_abprotector_policies_cookies(array(
                'accepted_policy' => sanitize_text_field($_POST['accepted_policy']),
                'accepted_gdpr' => sanitize_text_field($_POST['accepted_gdpr'])
            ));

            wp_send_json_success();
        }

        function _get_abprotector_policies_status_from_cart($cart_token){
            // busca en la base de datos por un carrito abandonado para setear las cookies desde esos datos
            $result = $this->_get_cart_data_db($cart_token);

            if( is_null($result) ){
                return array('accepted_policy' => 'empty', 'accepted_gdpr' => 'false' );
            }

            return unserialize( $result->extra_data );
        }

        function _check_set_abprotector_policies_cookies($args){
            // coloca los valores en las cookies
            if( isset($args['accepted_policy']) ){
                setcookie("abprotector_accepted_policy", sanitize_text_field($args['accepted_policy']), time()+(86400*30), "/"); // expira en 30 dias
            }
            if( isset($args['accepted_gdpr']) ){
                setcookie("abprotector_accepted_gdpr", sanitize_text_field($args['accepted_gdpr']), time()+(86400*30), "/"); // expira en 30 dias
            }
        }
        // -------------- cookies --------------


        // ---------------- cart abandonment database ----------------
        function save_cart_abandonment_data($order_data){
            global $wpdb;

            $cart_token = $order_data['cart_token'];
            $user_email = sanitize_email( $order_data['email'] );
            $checkout_data = $this->_format_abandonment_data($order_data);
            $cart_abandonment_table = $wpdb->prefix . ABPROTECTOR_CARTS_TABLE;

            if( !isset($checkout_data['cart_total']) || $checkout_data['cart_total'] == 0 ){
                return;
            }

            // checar si ya existen los datos del carrito con el token
            $result = $this->_get_cart_data_db($cart_token);

            if( is_null($result) ){
                // insertar
                $wpdb->insert($cart_abandonment_table, $checkout_data);
            }else{
                // actualizar
                $wpdb->update($cart_abandonment_table, $checkout_data, array( 'cart_token' => $cart_token ));
            }
        }

        function _get_cart_data_db($cart_token){
            global $wpdb;

            $cart_abandonment_table = $wpdb->prefix . ABPROTECTOR_CARTS_TABLE;
            $result = $wpdb->get_row(
                $wpdb->prepare('SELECT * FROM `' . $cart_abandonment_table . '` WHERE cart_token = %s', $cart_token )
            );
            return $result;
        }

        // formatear los datos para guardar en bd
        function _format_abandonment_data( $order_data ) {
            if( function_exists( 'WC' ) ){
                $cart_total = WC()->cart->total;

                // obtener los datos de los productos del carrito
                $products = WC()->cart->get_cart();
                $extra_data = $order_data['billing_address'];
                $extra_data['accepted_policy'] = isset( $_COOKIE['abprotector_accepted_policy'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_policy']) : "empty";
                $extra_data['accepted_gdpr'] = isset( $_COOKIE['abprotector_accepted_gdpr'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_gdpr']) : "empty";

                $checkout_data = array(
                    'email'        => $order_data['email'],
                    'cart_content' => serialize( $products ),
                    'cart_total'   => sanitize_text_field( $cart_total ),
                    'cart_token'  => $order_data['cart_token'],
                    'extra_data'  => serialize( $extra_data )
                );
            }
            return $checkout_data;
        }

        function _restore_cart_abandonment_data( $fields = array() ){
            global $woocommerce;
            $result = array();

            $cart_token = filter_input( INPUT_GET, 'chpabp_crt_tk', FILTER_SANITIZE_STRING );
            $loaded_cart = false;

            if( !is_null($cart_token) ){
                $result = $this->_get_cart_data_db($cart_token);

                if( isset($result) ) {
                    $cart_content = unserialize( $result->cart_content );

                    if ( $cart_content ) {
                        $woocommerce->cart->empty_cart();
                        wc_clear_notices();

                        foreach( $cart_content as $cart_item ){
                            $id = $cart_item['product_id'];
                            $qty = $cart_item['quantity'];
                            $cart_item_data = array();
                            $variation_data = array();

                            // omitir los productos bundled al agregar el principal
                            if( isset( $cart_item['bundled_by'] ) ){
                                continue;
                            }

                            if( isset( $cart_item['variation'] ) ){
                                foreach ( $cart_item['variation']  as $key => $value ){
                                    $variation_data[ $key ] = $value;
                                }
                            }

                            $cart_item_data = $cart_item;

                            $woocommerce->cart->add_to_cart( $id, $qty, $cart_item['variation_id'], $variation_data, $cart_item_data );
                        }

                        $loaded_cart = true;

                        // si se carga un carrito abandonado guardar el token para seguir actualizando el carrito si es necesario
                        $this->cart_mgr->set_cart_token($cart_token);
                        if( isset( WC()->session ) ){
                            // guardar aqui para consultar despues cuando se haya convertido a orden (si se convierte).
                            WC()->session->set('abp_session_cart_token', $cart_token);
                        }
                    }

                    $extra_data = unserialize($result->extra_data);

                    // setear la info de direccion para que se vea en el formulario
                    $_POST['billing_email']      = sanitize_email( $result->email );
                    $_POST['billing_first_name'] = sanitize_text_field( $extra_data['first_name'] );
                    $_POST['billing_last_name']  = sanitize_text_field( $extra_data['last_name'] );
                    $_POST['billing_phone']      = sanitize_text_field( $extra_data['phone'] );
                    $_POST['billing_city']       = sanitize_text_field( $extra_data['city'] );
                    $_POST['billing_country']    = sanitize_text_field( $extra_data['country_code'] );

                    $this->_check_set_abprotector_policies_cookies(array(
                        'accepted_policy' => $extra_data['accepted_policy'],
                        'accepted_gdpr' => $extra_data['accepted_gdpr']
                    ));
                }
            }

            if( $loaded_cart ){
                $params = esc_url_raw($_SERVER['QUERY_STRING']);

                if( isset($params) ){
                    $params = $this->utils->remove_url_protocol($params);
                    // quitar el parametro del carrito
                    $params = preg_replace('/chpabp_crt_tk=.+&/', '', $params);
                    $params = preg_replace('/chpabp_crt_tk=.+$/', '', $params);
                }
                $cart_url = wc_get_cart_url();
                $url_with_params = $cart_url."?".$params;
                wp_redirect($url_with_params);
                exit();
            }

            return $fields;
        }

        function _delete_cart_abandonment_data($order_id){
            global $wpdb;
            $cart_abandonment_table = $wpdb->prefix . ABPROTECTOR_CARTS_TABLE;

            // borrar el carrito que fue completado a orden
            if( isset( WC()->session ) ){
                $cart_token = WC()->session->get('abp_session_cart_token');

                if ( isset( $cart_token ) ) {
                    $wpdb->delete( $cart_abandonment_table, array( 'cart_token' => sanitize_key( $cart_token ) ) );
                    WC()->session->__unset('abp_session_cart_token');
                }
            }
        }
        // ---------------- cart abandonment database ----------------

    }

?>