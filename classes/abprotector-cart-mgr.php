<?php
    class AbprotectorCartMgr{
        private static $instance;
        private $woo_session = null;
        protected $cart_token_key = "abprotector_user_cart_token",
                  $cart_token_key_db = "_abprotector_user_cart_token",
                  $cart_creation_date_key = "abprotector_user_cart_creation_date",
                  $cart_creation_date_key_db = "_abprotector_user_cart_creation_date";

        public static function get_instance() {
            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        function __construct(){
            $this->woo_session = AbprotectorWooSession::get_instance();
            $this->utils = AbprotectorUtils::get_instance();
            $this->register_cart_redirect_endpoint();
        }

        // ------------------ crear endpoint para redirigir al carrito/checkout ------------------

        function register_cart_redirect_endpoint(){
            // site.com/wp-json/abprotector/cart-check
            add_action( 'rest_api_init', function(){
                register_rest_route( 'abprotector', '/cart-check',
                    array(
                        'methods' => 'GET', 
                        'callback' => array($this, 'cart_check_callback')
                    )
                );
            });
        }

        function cart_check_callback($request){
            $params = esc_url_raw($_SERVER['QUERY_STRING']);
            $url_with_params = wc_get_checkout_url();

            if( isset($params) ){
                $params = $this->utils->remove_url_protocol($params);
                $url_with_params = $url_with_params."?".$params;
            }
            wp_redirect($url_with_params);
            exit;
        }

        function get_abprotector_cart_url(){
            $url = get_bloginfo('url');
            $url = $url . "/wp-json/abprotector/cart-check";

            return $url;
        }

        // -------------------------------------

        function get_data_for_webhook(){
            // retorna la informacion del carrito actual formateada para mandar a webhook
            $customer_data = $this->utils->get_logged_customer_data();
            if( is_null($customer_data) ){
                $customer_data = array();
            }
            $order_data = $this->get_current_cart_data();

            $order_data['email'] = $customer_data['email'];
            $order_data['customer'] = $customer_data['customer'];
            $order_data['billing_address'] = $customer_data['billing_address'];

            return $order_data;
        }

        // ---------- obtener la informacion del carrito actual --------------

        function get_current_cart_data(){
            if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
                wc_load_cart();
            }

            $cart = WC()->cart;

            if( is_null($cart) ){
                $cart = new WC_Cart();
            }

            $cart->calculate_totals();

            $cart_token = $this->get_cart_token();
            $creation_date = $this->get_creation_date();
            $checkout_url = $this->get_abprotector_cart_url() . "?chpabp_crt_tk=" . $cart_token;

            $cart_data = array(
                'cart_token' => $cart_token,
                'created_at' => $creation_date,
                'total_discounts' => $cart->get_discount_total(),
                'shipping_total' => $cart->get_shipping_total(),
                'subtotal_price' => $cart->get_subtotal(),
                'currency' => get_option('woocommerce_currency'),
                'total_price' => $cart->get_total('number'),
                'total_tax' => $cart->get_total_tax(),
                'abandoned_checkout_url' => $checkout_url
            );
            $order_items = array();

            // $cart->get_customer() // instance of WC_Customer
            // $cart_item['data'] es una instancia de WC_Product

            foreach ( $cart->get_cart() as $cart_item ){
               $item = array(
                    'key' => $cart_item['key'],
                    'product_id' => $cart_item['product_id'],
                    'variant_id' => $cart_item['variation_id'],
                    'title' => $cart_item['data']->get_title(),
                    'quantity' => $cart_item['quantity'],
                    'subtotal' => $cart_item['line_subtotal'],
                    'total' => $cart_item['line_total'],
                    'tax' => $cart_item['line_subtotal_tax'],
                    'line_price' => $cart_item['line_total'],
                    'price' => $cart_item['data']->get_price()
                );

               $image_url = get_the_post_thumbnail_url( $cart_item['product_id'] );
               
               if( $image_url != false ){
                    $item['image'] = $image_url;
               }

               $order_items[] = $item;
           }
           $cart_data['line_items'] = $order_items;

           return $cart_data;
        }

        function get_cart_token(){
            // obtiene el token o genera uno y lo retorna
            $cart_token = $this->_read_cart_token();

            if( empty($cart_token) ){
                $cart_token = $this->_generateCartToken();
                $this->set_cart_token($cart_token);
            }

            if( isset( WC()->session ) ){
                // guardar aqui para consultar despues cuando se haya convertido a orden (si se convierte).
                WC()->session->set('abp_session_cart_token', $cart_token);
            }

            return $cart_token;
        }

        function get_creation_date(){
            // obtiene el token o genera uno y lo retorna
            $creation_date = $this->_read_creation_date();

            if( empty($creation_date) ){
                $creation_date = date("c");
                $this->set_creation_date($creation_date);
            }
            return $creation_date;
        }

        // --------------------------------------------------------------------------

        function _generateCartToken(){
            $data = openssl_random_pseudo_bytes( 16 );
            $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
            $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

            return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
        }

        function _read_cart_token($user_id = null){
            // lee el token del carrito de la sesion o de los meta del usuario

            if( $user_id == null ){
                $user_id = get_current_user_id();
            }
            if( !empty($user_id) ){
                return get_user_meta($user_id, $this->cart_token_key_db, true);
            } else {
                return $this->woo_session->getValue($this->cart_token_key);
            }
        }

        function _read_creation_date($user_id = null){
            // lee el token del carrito de la sesion o de los meta del usuario

            if( $user_id == null ){
                $user_id = get_current_user_id();
            }
            if( !empty($user_id) ){
                return get_user_meta($user_id, $this->cart_creation_date_key_db, true);
            } else {
                return $this->woo_session->getValue($this->cart_creation_date_key);
            }
        }

        function set_cart_token($cart_token, $user_id = null){
            // checar si hay usuario en sesion para obtener el token anterior
            if( $user_id == null ){
                $user_id = get_current_user_id();
            }

            if( !empty($user_id) ){
                update_user_meta($user_id, $this->cart_token_key_db, sanitize_text_field($cart_token));
            }else{
                $this->woo_session->setValue($this->cart_token_key, sanitize_text_field($cart_token));
            }
        }

        function set_creation_date($creation_date, $user_id = null){
            $old_creation_date = $this->woo_session->getValue($this->cart_creation_date_key);

            if( empty($old_creation_date) ){
                $this->woo_session->setValue($this->cart_creation_date_key, $creation_date);
                
                if (!empty($user_id) || $user_id = get_current_user_id()) {
                    update_user_meta($user_id, $this->cart_creation_date_key_db, $creation_date);
                }
            }
        }

        function clear_cart_token($user_id = null){
            if( $user_id == null ){
                $user_id = get_current_user_id();
            }

            $this->woo_session->removeValue($this->cart_token_key);
            update_user_meta($user_id, $this->cart_token_key_db, null);
        }

    }
?>