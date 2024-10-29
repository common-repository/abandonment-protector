<?php

    class AbprotectorOrdersWebhooks{

        private static $instance;
        private $utils = null;
        private $cart_mgr = null;
        private $woo_session = null;

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
            $this->cart_mgr = AbprotectorCartMgr::get_instance();
        }



        function init_webhooks(){
            // just one step before saving the order data
            add_action('woocommerce_checkout_create_order', array($this, '_before_checkout_create_order'));

            // Webhooks para los distintos estatus que vamos a procesar

            // Order received, no payment initiated
            add_action('woocommerce_order_status_pending', array($this, '_woo_order_pending') );
            add_action('woocommerce_order_status_on-hold', array($this, '_woo_order_pending') );
            // Payment received (paid) and stock has been reduced; order is awaiting fulfillment.
            add_action('woocommerce_order_status_processing', array($this, '_woo_order_processing') );
            // Order fulfilled and complete
            add_action('woocommerce_order_status_completed', array($this, '_woo_order_completed') );
            // Canceled by an admin or the customer
            add_action('woocommerce_order_status_cancelled', array($this, '_woo_order_cancelled') );
        }

        function _before_checkout_create_order($order){
            $cart_token = $this->cart_mgr->get_cart_token();
            // new way
            $order->update_meta_data('_abprotector_cart_token', $cart_token);
            // old way
            // update_post_meta()

            // ahora limpiar el token de checkout para que se cree uno nuevo
            $this->cart_mgr->clear_cart_token();
        }


        function _woo_order_pending($order_id){
            $order = wc_get_order( $order_id );
            $order_data = $this->_get_data_from_order($order);

            $url = ABPROTECTOR_API_BASE . "/order_create";
            $this->utils->send_webhook($url, $order_data);
        }

        function _woo_order_processing($order_id){
            $order = wc_get_order( $order_id );
            $order_data = $this->_get_data_from_order($order);

            $url = ABPROTECTOR_API_BASE . "/order_paid";
            $this->utils->send_webhook($url, $order_data);
        }

        function _woo_order_completed($order_id){
            $order = wc_get_order( $order_id );
            $order_data = $this->_get_data_from_order($order);

            $url = ABPROTECTOR_API_BASE . "/order_fulfilled";
            $this->utils->send_webhook($url, $order_data);
        }

        function _woo_order_cancelled($order_id){
            $order = wc_get_order( $order_id );
            // TODO: guardar en los meta la fecha de cancelacion
            $cancel_date = date("c");
            $order->update_meta_data('_abprotector_cancel_date', $cancel_date);

            $order_data = $this->_get_data_from_order($order);


            $url = ABPROTECTOR_API_BASE . "/order_cancelled";
            $this->utils->send_webhook($url, $order_data);
        }


        function _get_data_from_order($order){
            // formatear la info parecido a shopify

            $order_data = array(
                'id' => $order->get_id(),
                'key' => $order->get_order_key(),
                'currency' => $order->get_currency(),
                'discount_total' => $order->get_discount_total(),
                'shipping_total' => $order->get_shipping_total(),
                'subtotal_price' => $order->get_subtotal(),
                'total_price' => $order->get_total(),
                'total_tax' => $order->get_total_tax(),
                'checkout_payment_url' => $order->get_checkout_payment_url(),
                'view_order_url' => $order->get_view_order_url(),
                'woo_status' => $order->get_status(),
                'created_at' => $order->order_date,
                'cancelled_at' => $order->get_meta('_abprotector_cancel_date'),
                'abprotector_cart_token' => $order->get_meta('_abprotector_cart_token')
            );

            // asignar datos extra de estatus
            $order_data = $this->utils->_assign_status_data($order, $order_data);


            // agregar los articulos de la orden
            $order_items = [];

            foreach ( $order->get_items() as $item_id => $order_item ) {
                $item_total = $order_item->get_total();
                $item_qty = $order_item->get_quantity();
                $item_price = $item_qty > 0 ? $item_total/$item_qty : 0;

                $item = array(
                    'product_id' => $order_item->get_product_id(),
                    'variant_id' => $order_item->get_variation_id(),
                    'name' => $order_item->get_name(),
                    'title' => $order_item->get_name(),
                    'quantity' => $item_qty,
                    'total' => $item_total,
                    'price' => $item_price,
                    'line_price' => $item_total,
                    'subtotal' => $order_item->get_subtotal(),
                    'tax' => $order_item->get_subtotal_tax(),
                    'type' => $order_item->get_type()
                );

                $image_url = get_the_post_thumbnail_url( $item['product_id'] );
               
                if( $image_url != false ){
                    $item['image'] = $image_url;
                }

               array_push($order_items, $item);
            }
            $order_data['line_items'] = $order_items;

            // armar la informacion del cliente
            $customer = array(
                'email' => $order->get_billing_email(),
                'accepts_marketing' => true,
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(),
                'currency' => 'USD'
            );

            $billing_address = array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country_code' => $order->get_billing_country(),
                'phone' => $order->get_billing_phone()
            );

            $order_data['customer'] = $customer;
            $order_data['billing_address'] = $billing_address;
            $order_data['email'] = $customer['email'];

            return $order_data;
        }

    }
?>