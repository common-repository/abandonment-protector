<?php
	class AbprotectorApiEndpoints{

        function init(){
            $this->register_installation_authorization_endpoint();
        	$this->register_cart_info_endpoint();
            $this->register_api_keys_endpoint();
            $this->register_installation_check_endpoint();
            $this->register_check_product_page_endpoint();
        }
        
        // ------ registrar el endpoint instalacion sin Woocommerce --------

        function register_installation_authorization_endpoint(){
            // site.com/wp-json/abprotector/authorize
            add_action( 'rest_api_init', function(){
                register_rest_route( 'abprotector', '/authorize',
                    array(
                        'methods' => 'GET', 
                        'callback' => array($this, 'wp_installation_authorization_callback')
                    )
                );
            });
        }

        function wp_installation_authorization_callback(){
            $callback_url = $this->get_formatted_url(filter_input(INPUT_GET, 'callback_url', FILTER_SANITIZE_STRING));
            $data = wp_unslash( $_REQUEST );

            try {
                $response = wp_remote_post($callback_url, $data);
                
                if( intval( $response['response']['code'] !== 200 ) ){
                    throw new Exception( __( 'An error occurred in the request and the operation couldn\'t be completed. Error code: WCLBN200', 'abandonment_protector' ) );
                }

                wp_redirect(
                    esc_url_raw(
                        add_query_arg(
                            array(
                                'success' => 1,
                                'user_id' => filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_STRING),
                            ), $this->get_formatted_url( filter_input(INPUT_GET, 'return_url', FILTER_SANITIZE_STRING) )
                        )
                    )
                );
                exit;
            }catch( Exception $e ){
                wp_die( sprintf( esc_html__('Error: %s.', 'abandonment_protector'), esc_html( $e->getMessage() ) ), esc_html__('Access denied', 'abandonment_protector'), array('response' => 401 ));
            }
        }

        protected function get_formatted_url( $url ) {
            $url = urldecode( $url );

            if ( ! strstr( $url, '://' ) ) {
                $url = 'https://' . $url;
            }

            return $url;
        }

        // ------ registrar el endpoint para consultar la info del carrito --------

        function register_cart_info_endpoint(){
        	// site.com/wp-json/abprotector/cart_data
	        add_action( 'rest_api_init', function(){
	            register_rest_route( 'abprotector', '/cart_data',
	                array(
	                    'methods' => 'GET', 
	                    'callback' => array($this, 'cart_validation_callback')
	                )
	            );
	        });
        }

        function cart_validation_callback(){
            // preparar y devolver la informacion del carrito
            $cart_mgr = AbprotectorCartMgr::get_instance();
            $cart_data = $cart_mgr->get_current_cart_data();

            // ahora formatear la info del carrito
            $data = array(
                "token" => $cart_data["cart_token"],
                "total_price" => (float)$cart_data["total_price"],
                "items" => $cart_data["line_items"]
            );

            wp_send_json_success($data);
        }

        // -------- registrar endpoint para consultar api keys ----------

        function register_api_keys_endpoint(){
            // site.com/wp-json/abprotector/plugin_status
            add_action( 'rest_api_init', function(){
                register_rest_route( 'abprotector', '/plugin_status',
                    array(
                        'methods' => 'POST', 
                        'callback' => array($this, 'api_keys_callback')
                    )
                );
            });
        }

        function api_keys_callback($request){
            $utils = AbprotectorUtils::get_instance();
            $abprotector_keys = get_option(ABPROTECTOR_SETTINGS_KEYS);
            $keys_already_saved = is_array($abprotector_keys);

            $data = array(
                "abp_version" => ABP_VERSION,
                "woo_enabled" => function_exists('WC')
            );

            $validation = $utils->_validate_request($request, $_POST);
            $data["valid_api_keys"] = $validation["success"];
            $data["api_keys_message"] = $validation["message"];

            if( function_exists( 'WC' ) ){
                $data["cart_url"] = wc_get_cart_url();
                $data["checkout_url"] = wc_get_checkout_url();
            }

            wp_send_json($data);
        }

        // -------- registrar endpoint para validar usuario de instalacion ----------
        function register_installation_check_endpoint(){
            // site.com/wp-json/abprotector/installation
            add_action( 'rest_api_init', function(){
                register_rest_route( 'abprotector', '/installation',
                    array(
                        'methods' => 'POST', 
                        'callback' => array($this, 'installation_check_callback')
                    )
                );
            });
        }

        function installation_check_callback($request){
            $data = array(
                "status" => "valid"
            );

            wp_send_json($data);
        }


        // ----------- checar pagina de producto -------------

        // -------- registrar endpoint para consultar api keys ----------

        function register_check_product_page_endpoint(){
            // site.com/wp-json/abprotector/check_product_page
            add_action( 'rest_api_init', function(){
                register_rest_route( 'abprotector', '/check_product_page',
                    array(
                        'methods' => 'GET', 
                        'callback' => array($this, 'check_product_page_callback')
                    )
                );
            });
        }

        function check_product_page_callback($request){
            $page_slug = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_STRING); // product path
            $post_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING); // product id

            if( isset($page_slug) ){
                $post_id = url_to_postid( $page_slug );
            }

            $page = get_post($post_id);
            $is_product = (get_post_type($page) == "product");

            $data = array( "is_product" => $is_product );

            if( $is_product ){
                $data["title"] = $page->post_title;
                $data["id"] = $page->ID;
            }

            wp_send_json($data);
        }


	}
?>