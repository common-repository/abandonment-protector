<?php
	if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ){
		exit();
	}

	define( 'ABPROTECTOR_POPUPS_SCRIPTS_KEY', 'abprotector_popup_scripts');
	define( 'ABPROTECTOR_SESSION_KEY', 'abprotector_user_session_key');
	define( 'ABPROTECTOR_SETTINGS_KEYS', 'abprotector_keys');

	include_once plugin_dir_path( __FILE__ ) . 'classes/abprotector-utils.php';

	// mandar webhook a chilliapps
	$url = "http://ko.chilliapps.com/webhooks/woocommerce/uninstall";

	try {
		$utils = AbprotectorUtils::get_instance();
		$utils->send_webhook($url, array());
	} catch (Exception $e) {};


	// borrar las opciones que se generaron
	delete_option(ABPROTECTOR_SETTINGS_KEYS);
	delete_option(ABPROTECTOR_POPUPS_SCRIPTS_KEY);
?>