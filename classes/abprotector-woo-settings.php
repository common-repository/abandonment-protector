<?php

    class AbprotectorWooSettings extends WC_Settings_Page {
        // the magic will go here

        public function __construct(){
            add_action( 'woocommerce_settings_save_general', array( $this, 'woo_settings_changed' ) );
        }

        function woo_settings_changed($data){
            global $current_section;
            $utils = new AbprotectorUtils();

            $site_url = $utils->remove_url_protocol(get_bloginfo('url'));

            $data = array(
                'address1' => WC_Admin_Settings::get_option('woocommerce_store_address'),
                'address2' => WC_Admin_Settings::get_option('woocommerce_store_address_2'),
                'city' => WC_Admin_Settings::get_option('woocommerce_store_city'),
                'country_code' => WC_Admin_Settings::get_option('woocommerce_default_country'),
                'zip' => WC_Admin_Settings::get_option('woocommerce_store_postcode'),
                'currency' => WC_Admin_Settings::get_option('woocommerce_currency'),
                'wordpress_url' => $site_url
            );

            $utils->send_webhook(ABPROTECTOR_API_BASE.'/shop_update', $data);
        }
    }
?>