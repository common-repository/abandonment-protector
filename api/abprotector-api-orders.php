<?php
	class AbprotectorApiOrders{
		private $utils = null;

		function __construct(){
            $this->utils = AbprotectorUtils::get_instance();
        }

        function init_reponses_formatter(){
			// --- formatear la orden cuando se consulta por API
			add_filter('woocommerce_rest_prepare_shop_order_object', array($this, '_api_find_order_response'), 20, 3);
        }

        function _api_find_order_response($response, $object, $request){
            $order_data = $response->get_data();
            $order_data = $this->utils->_assign_status_data($object, $order_data);


            return $order_data;
        }

	}
?>