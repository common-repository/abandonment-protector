<?php

    class AbprotectorSetup {
        
        public function __construct(){
            // Activation hook.
            register_activation_hook( ABPROTECTOR_FILE, array( $this, 'plugin_was_activated' ) );
        }

        function plugin_was_activated(){
            // crear las tablas en la base de datos para guardar los datos de recuperacion de checkouts
            $this->_create_cart_abandonment_tables();
        }

        public function _create_cart_abandonment_tables(){
            // crea la tabla para guardar los checkouts abandonados y poderlos restaurar mediante un token parametro (chpabp_crt_tk)
            global $wpdb;

            $cart_abandonment_db = $wpdb->prefix . ABPROTECTOR_CARTS_TABLE;
            $charset_collate     = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $cart_abandonment_db (
                id BIGINT(20) NOT NULL AUTO_INCREMENT,
                email VARCHAR(100),
                cart_content LONGTEXT,
                cart_total DECIMAL(10,2),
                cart_token VARCHAR(60) NOT NULL,
                extra_data LONGTEXT,
                order_status ENUM( 'normal','abandoned','completed','lost') NOT NULL DEFAULT 'normal',
                coupon_code VARCHAR(50),
                PRIMARY KEY  (`id`, `cart_token`),
                UNIQUE KEY `cart_token_UNIQUE` (`cart_token`)
            ) $charset_collate;\n";

            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }
    }
?>