<?php

    class AbprotectorProductsWebhooks{

        private static $instance;
        private $utils = null;

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
        }



        function init_webhooks(){
            add_action('woocommerce_new_product', array($this, 'on_new_product'));
            add_action('woocommerce_update_product', array($this, 'on_product_updated'));
            add_action('wp_trash_post', array($this, 'post_deleted'));
            add_action('untrashed_post', array($this, 'post_untrashed'));
        }

        function on_new_product($product_id){
            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/product_update', array('product_id' => $product_id, 'cmd' => 'on_new_product') );
        }

        function on_product_updated($product_id){
            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/product_update', array('product_id' => $product_id, 'cmd' => 'on_product_updated') );
        }

        function post_deleted($post_id){
            // obtener post y ver si es de un producto
            if( get_post_type($post_id) != 'product' ){
                return;
            }

            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/product_delete', array('product_id' => $post_id) );
        }
        function post_untrashed($post_id){
            // obtener post y ver si es de un producto
            if( get_post_type($post_id) != 'product' ){
                return;
            }
            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/product_update', array('product_id' => $post_id) );
        }

    }
?>