<?php
    class AbprotectorPopups{

        // ------- cargar el script de popups si hay -------

        function add_abprotector_popups_script(){
            $scripts = get_option('abprotector_popup_scripts');

            if( $scripts == false ){
                return;
            }

            if( function_exists('WC') ){
                // agregar vars de producto

                $script_path = ABPROTECTOR_PLUGIN_URL . '/resources/js/abp_popups_vars.js';
                $version = 1;

                wp_enqueue_script(
                    'abp-popups-vars',
                    $script_path,
                    array( 'jquery' ),
                    $version
                );

                $vars = array("is_product" => is_product());
                if( is_product() ){
                  $vars["id"] = get_the_ID();
                  $vars["path"] = get_page_uri();
                }

                wp_localize_script( 'abp-popups-vars', 'AbpPopupsVars', $vars );
            }

            $scripts = json_decode($scripts);

            // IMPORTANTE: CAMBIAR LA VERSION AL HACER CAMBIOS
            // $version = 1;

            foreach ($scripts as $script_url){
                wp_enqueue_script(
                    'abprotector-popups',
                    $script_url,
                    array( 'jquery' )
                );
            }

        }

    }
?>