<?php

    class AbprotectorCustomersWebhooks{

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
            add_action('user_register', array($this, '_user_registered'));
            add_action('profile_update', array($this, '_user_updated'));
        }

        function _user_registered($user_id){
            $user_data = $this->_get_user_data($user_id);
            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/customers_create', $user_data );
        }

        function _user_updated($user_id){
            $user_data = $this->_get_user_data($user_id);
            $this->utils->send_webhook(ABPROTECTOR_API_BASE.'/customers_update', $user_data );
        }

        function _get_user_data($user_id){
            $user_data = get_userdata($user_id);

            $data = array();
            $data['id'] = $user_id;
            $data['email'] = $user_data->user_email;
            $data['created_at'] = $user_data->user_registered;
            $data['first_name'] = get_user_meta($user_id, 'first_name', true);
            $data['last_name'] = get_user_meta($user_id, 'last_name', true);
            $data['orders_count'] = get_user_meta($user_id, '_order_count', true);
            $data['phone'] = get_user_meta($user_id, 'billing_phone', true);

            $billing_address = array();
            $billing_address['city'] = get_user_meta($user_id, 'billing_city', true);
            $billing_address['country_code'] = get_user_meta($user_id, 'billing_country', true);
            $billing_address['country'] = get_user_meta($user_id, 'billing_country', true);
            $billing_address['province'] = get_user_meta($user_id, 'billing_state', true);
            $billing_address['province_code'] = get_user_meta($user_id, 'billing_state', true);

            $data['billing_address'] = $billing_address;

            return $data;
        }

    }
?>