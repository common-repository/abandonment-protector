<?php

    class AbprotectorAutoApplyCoupons{

        function init(){
            add_action('woocommerce_before_checkout_form', array($this, '_check_auto_apply_coupon') ); // Cart
            add_action('woocommerce_before_cart', array($this, '_check_auto_apply_coupon') ); // Checkout
        }

        function _check_auto_apply_coupon(){
            if( !isset($_GET['discount']) ){
                return;
            }

            if( !empty($coupon) ){
                return;
            }

            global $woocommerce;
            $cart = $woocommerce->cart;
            $coupon_code = filter_input(INPUT_GET, 'discount', FILTER_SANITIZE_STRING);

            // checar si el cupon ya fue aplicado
            if( $this->_check_in_array(strtolower($coupon_code), $cart->applied_coupons) ){
                return;
            }

            // dejar que woocommerce muestre el mensaje de error
            if( !$this->is_coupon_valid($coupon_code) ){
                printf("<div class='woocommerce-error'>%s</div>", $this->is_coupon_valid($coupon_code, true));
                return;
            }

            $resp = $cart->add_discount($coupon_code);

            if( $this->_check_in_array(strtolower($coupon_code), $cart->applied_coupons) ){
                printf("<div class='woocommerce-message'>%s Applied successfully</div>", $coupon_code);
            }

        }

        function _check_in_array($item, $array) {
            if( !is_array($array) ){
                $array = explode(',', $array);
            }
            if(in_array($item, $array, true) !== false) return true; return false;
        }

        function is_coupon_valid($coupon_code, $show_error = false){
            $coupon = new WC_Coupon($coupon_code);   
            $discounts = new WC_Discounts(WC()->cart);
            $status = $discounts->is_coupon_valid($coupon);

            if( is_wp_error($status) ){
                if( $show_error )
                    return $status->get_error_message();
                else
                    return false;
            }else{
                return true;
            }
        }

    }

?>