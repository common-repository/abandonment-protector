<?php
    // define( 'ABPROTECTOR_POPUPS_SCRIPTS_KEY', ABPROTECTOR_POPUPS_SCRIPTS_KEY);

    // para comunicacion con abprotector (scripts popups)
	class AbprotectorApiPopups{
        private $utils = null;

        function __construct(){
            $this->utils = AbprotectorUtils::get_instance();
        }

        function init(){
        	$this->register_popups_script_endpoints();
        }

        // ------ registrar los endpoints para recibir peticiones desde abprotector --------

        function register_popups_script_endpoints(){
        	// site.com/wp-json/abprotector/register_popups_script

	        add_action( 'rest_api_init', function(){
	            register_rest_route( 'abprotector', '/register_popups_script',
	                array(
	                    'methods' => 'POST', 
	                    'callback' => array($this, 'process_register_popups_script')
	                )
	            );
	        });

	        // site.com/wp-json/abprotector/remove_popups_script
	        add_action( 'rest_api_init', function(){
	            register_rest_route( 'abprotector', '/remove_popups_script',
	                array(
	                    'methods' => 'POST', 
	                    'callback' => array($this, 'process_remove_popups_script')
	                )
	            );
	        });
        }

        function process_register_popups_script($request){
        	$this->_validate_request($request);

        	$new_script_url = esc_url_raw($_POST['script_url']);
        	$replace_all = false;

            if( isset($_POST['replace_all']) ){
                $replace_all = filter_input(INPUT_POST, 'replace_all', FILTER_SANITIZE_STRING) == "true";
            }

        	if( is_null($new_script_url) || $new_script_url == "" ){
        		wp_send_json_success();
        		return;
        	}

        	$scripts = get_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY);

        	if( $scripts == false ){
        		$scripts = [$new_script_url];
        		add_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY, json_encode($scripts));
        	}else{
        		$scripts = json_decode($scripts);

        		if( $replace_all ){
        			$scripts = [$new_script_url];
        		}else{
        			$new_arr = [];
        			$clean_script_url = explode("?", $new_script_url)[0];

        			// quitar los scripts con la misma url que el que se recibe
        			foreach ($scripts as $script){
        				$cls = explode("?", $script)[0];

        				if( $cls != $clean_script_url ){
        					array_push($new_arr, $script);
        				}
        			}

        			// agregar el nuevo que se recibe
        			array_push($new_arr, $new_script_url);

        			$scripts = $new_arr;
        		}

        		update_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY, json_encode($scripts));
        	}

        	return [
	            'success' => true,
	            'message' => 'Script URLs saved :)',
	            'scripts' => $scripts
	        ];
        }

        function process_remove_popups_script(){
            $script_name = NULL;
            if( isset($_POST['script_name']) ){
                $script_name = filter_input(INPUT_POST, 'script_name', FILTER_SANITIZE_URL);
            }

            if( is_null($script_name) || strpos($script_name, ".js") == false ){
                wp_send_json_success();
                return;
            }

        	$scripts = get_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY);

            if( $scripts == false ){
                wp_send_json_success();
                return;
            }

            $scripts = json_decode($scripts);

            if( count($scripts) == 0 ){
                wp_send_json_success();
                return;
            }

            $new_arr = [];

            // quitar los scripts con la misma url que el que se recibe
            foreach ($scripts as $script){
                if( strpos($script, $script_name) == false ){
                    array_push($new_arr, $script);
                }
            }

            update_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY, json_encode($new_arr));

            return [
                'success' => true,
                'message' => 'Script URLs saved :)',
                'scripts' => $new_arr
            ];
        }

        function _validate_request($request){
        	// valida los datos de la peticion
        	$validation = $this->utils->_validate_request($request, $_POST);

        	if( !$validation['success'] ){
        		wp_send_json($validation);
        		return;
        	}
        }

	}
?>