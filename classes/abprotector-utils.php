<?php

    class AbprotectorUtils{
        
        private static $instance;
        private $api_keys = null;

        /**
         *  Initiator
         */
        public static function get_instance() {
            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        function __construct(){
            $this->api_keys = get_option(ABPROTECTOR_SETTINGS_KEYS);
        }

        // create random string uuid
        function guidv4(){
            $data = openssl_random_pseudo_bytes( 16 );
            $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
            $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

            return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
        }

        function get_user_session_key(){
            $session_key = $this->get_session_variable(ABPROTECTOR_SESSION_KEY);

            if (empty($session_key)) {
                $session_key = $this->guidv4();
                $this->set_session_variable(ABPROTECTOR_SESSION_KEY, $session_key);
            }
            return $session_key;
        }

        function get_session_variable($key){
            if (empty($key))
                return NULL;

            $this->check_start_session();

            if (isset($_SESSION[$key])) {
                return sanitize_text_field($_SESSION[$key]);
            }
            return NULL;
        }

        function set_session_variable($key, $value){
            if (empty($key) || empty($value))
                return false;

            $this->check_start_session();

            $_SESSION[$key] = sanitize_text_field($value);
            return true;
        }

        function check_start_session(){
            if( function_exists("session_status") && session_status() != PHP_SESSION_NONE ){
                return ;
            }

            if( session_id() != "" ){
                return;
            }

            session_start();
        }

        // ------------- formatear informacion del cliente (usuario) desde params -------------
        function format_customer_data_from_params($params){
            $accepted_gdpr = isset( $_COOKIE['abprotector_accepted_gdpr'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_gdpr']) : "false";

            $customer = array(
                'email' => $params['billing_email'],
                'accepts_marketing' => ($accepted_gdpr == "true"),
                'first_name' => $params['billing_first_name'],
                'last_name' => $params['billing_last_name'],
                'phone' => $params['billing_phone'],
                'currency' => 'USD'
            );

            $billing_address = array(
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'company' => $params['billing_company'],
                'address1' => $params['billing_address_1'],
                'address2' => $params['billing_address_2'],
                'city' => $params['billing_city'],
                'state' => $params['billing_state'],
                'postcode' => $params['billing_zipcode'],
                'country_code' => $params['billing_country'],
                'phone' => $customer['phone']
            );

            return array(
                'email' => $customer['email'],
                'customer' => $customer,
                'billing_address' => $billing_address
            );
        }

        // ------------- formatear informacion del cliente (usuario) en sesion -------------
        function get_logged_customer_data(){
            $user_id = get_current_user_id();

            if( $user_id == 0 ){
                return NULL;
            }

            $accepted_gdpr = isset( $_COOKIE['abprotector_accepted_gdpr'] ) ? sanitize_text_field($_COOKIE['abprotector_accepted_gdpr']) : "false";

            $customer = array(
                'email' => get_user_meta( $user_id, 'billing_email', true ),
                'accepts_marketing' => ($accepted_gdpr == "true"),
                'first_name' => get_user_meta( $user_id, 'first_name', true ),
                'last_name' => get_user_meta( $user_id, 'billing_last_name', true ),
                'phone' => get_user_meta( $user_id, 'billing_phone', true ),
                'currency' => 'USD'
            );

            $billing_address = array(
                'id' => $user_id,
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'company' => get_user_meta( $user_id, 'billing_company', true ),
                'address1' => get_user_meta( $user_id, 'billing_address_1', true ),
                'address2' => get_user_meta( $user_id, 'billing_address_2', true ),
                'city' => get_user_meta( $user_id, 'billing_city', true ),
                'state' => get_user_meta( $user_id, 'billing_state', true ),
                'postcode' => get_user_meta( $user_id, 'billing_postcode', true ),
                'country_code' => get_user_meta( $user_id, 'billing_country', true ),
                'phone' => $customer['phone']
            );

            return array(
                'email' => $customer['email'],
                'customer' => $customer,
                'billing_address' => $billing_address
            );
        }


        // ---- formatear info de ordenes -------
        function _assign_status_data($o, $od){
            // convierte del estatus woo a estatus para procesar en chilliapps
            $equivalences = array(
                'pending-payment' => 'pending',
                'canceled' => 'canceled',
                'processing' => 'paid',
                'completed' => 'paid',
                'on-hold' => 'pending',
                'refunded' => 'refunded',
                'failed' => 'voided'
            );

            $status = $o->get_status();

            $equivalent_status = $equivalences[$status];
            if( is_null($equivalent_status) ){
                $equivalent_status = 'authorized';
            }

            $od['financial_status'] = $equivalent_status;

            if( $status == 'completed' ){
                $od['fulfillment_status'] = 'fulfilled';
            }

            return $od;
        }

        function remove_url_protocol($url){
            return preg_replace('/^.*:\/\//', "", $url);
        }


        // ---------------------------------------------------------------
        function send_webhook($url, $data){
            // agregar el api_key y el hmac a los headers
            // IMPORTANTE: wordpress agrega el prefijo HTTP_ a los nombres de los headers al enviarlos
            $shop_url = $this->remove_url_protocol(get_bloginfo('url'));

            $headers = array(
                'X_CH_API_KEY' => $this->api_keys['api_key'],
                'X_CH_HMAC_SHA256' => $this->_generate_hmac($data),
                'ABP_PLUGIN_VERSION' => ABP_VERSION,
                'X_SHOP_URL' => $shop_url
            );

            $params = array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $data
            );

            try {
                return wp_remote_post($url, $params);
            } catch (Exception $e) {
                // TODO: hacer algo aqui
            };

            return null;
        }

        function _generate_hmac($data){
            $encoded_params = http_build_query($data);

            $secret_key = $this->api_keys['secret_key'];
            $hmac = hash_hmac('sha256', $encoded_params, $secret_key, true);

            $hmac_encoded = base64_encode($hmac);

            return $hmac_encoded;
        }

        function _validate_hmac($hmac, $data){
            $new_hmac = $this->_generate_hmac($data);

            return ( $new_hmac == $hmac );
        }

        function _validate_request($request, $data){
            // valida los headers y hmac

            $api_key = $request->get_header('X_AB_API_KEY');
            $hmac = $request->get_header('X_AB_HMAC_SHA256');

            $api_keys = get_option(ABPROTECTOR_SETTINGS_KEYS);

            if( $api_keys == false ){
                return array('success' => false, 'message' => 'Empty API Keys' );
            }
            if( is_null($api_key) || $api_key != $api_keys['api_key'] ){
                return array('success' => false, 'message' => 'Invalid API Key' );
            }

            if( $this->_validate_hmac($hmac, $data) ){
                return array('success' => true, 'message' => "Valid API keys");
            }else{
                return array('success' => false, 'message' => 'Invalid authorization');
            }
        }
    }

?>