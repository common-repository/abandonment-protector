jQuery(document).ready(function(){
    AbprotectorTracking.trackCustomerData();
    AbprotectorGdprMgr.init();
    console.log("-- abprotector ready");
});


var AbprotectorGdprMgr = {
    _ppId: '_chppchkgdpr',
    init: function(){
        if( AbprotectorTrackingVars.accepted_policy == 'false' ){
            return;
        }
        // buscar el popup especial
        if( typeof _chpmgr != 'object' || (typeof _chpmgr == 'object' && _chpmgr.popups.length == 0) ){
            jQuery(document).on("chpmgr_popups_ready", function(){
                _initGdprPopup();
            });
            return;
        }

        _initGdprPopup();

        function _initGdprPopup(){
            var special_popup = _chpmgr.getPopupDataById(AbprotectorGdprMgr._ppId);

            if( !special_popup ){
                console.warn('--> special chpp popup not found');
                return false;
            }
            _chpmgr._popupShowMgr._showMagnific({ ref: AbprotectorGdprMgr._ppId, invocation: 'manual' });
            AbprotectorGdprMgr._addPopupEvents();
        }
    },
    _addPopupEvents: function(){
        var _this = this;

        jQuery(document).off('chp_pp_closed').on('chp_pp_closed', function(e, data){
            if( data.ref != AbprotectorGdprMgr._ppId ){
                return;
            }

            jQuery(document).off('chp_pp_closed');

            if( AbprotectorTrackingVars.accepted_policy == "empty" ){
                _this._sendSaveResponse({
                    accepted_policy: 'false',
                    accepted_gdpr: 'false'
                });
            }
        });

        jQuery("#"+_this._ppId).find(".chp_link.snd_frm").off("click").on("click", function(e){
            e.preventDefault();

            var valid = _checkValidForm();
            if( valid ){
                _setDataToCheckoutForm();
                _this._sendSaveResponse({
                    accepted_policy: 'true',
                    accepted_gdpr: jQuery("#"+_this._ppId).find(".chp_chk_accept_marketing").first().prop("checked")
                });
            }
        });

        function _setDataToCheckoutForm(){
            // colocar los valores del popup en el formulario de checkout
            var name = jQuery("#"+AbprotectorGdprMgr._ppId).find(".chp_textbox[name='name']").val();
            var email = jQuery("#"+AbprotectorGdprMgr._ppId).find(".chp_textbox[name='email']").val();
            var last_name = jQuery("#"+AbprotectorGdprMgr._ppId).find(".chp_textbox[name='last_name']").val();

            jQuery('#billing_last_name').val(last_name);
            jQuery('#billing_first_name').val(name);
            jQuery('#billing_email').val(email);
        }


        function _checkValidForm(){
            var invalid = 0;

            // si no hay un solo item no enviar
            if( jQuery("#" + _this._ppId).find(".chp_textbox:visible[name='email']").length == 0 ){
                return false;
            }

            jQuery("#" + _this._ppId).find(".chp_textbox:visible[name='email']").each(function(){
                var control = jQuery(this);
                if( control.val() == "" ){
                    control.addClass("with_error");
                    control.focus();
                    invalid++;
                }
            });

            if( invalid > 0 ){
                return false;
            }

            // ahora checar el checkbox
            if( !jQuery("#" + _this._ppId).find(".chp_chk_policy").is(":checked") ){
                jQuery("#" + _this._ppId).find(".chp_checkbox_error_msg").show();
                return false;
            }

            return true;
        }
    },
    _sendSaveResponse: function(args){
        var data = {
            action: 'save_abprotector_gpdr_response',
            abtoken: AbprotectorTrackingVars._nonce_gdpr,
            accepted_policy: args.accepted_policy,
            accepted_gdpr: args.accepted_gdpr
        };

        jQuery.post(AbprotectorTrackingVars.ajaxurl, data, function(resp){
            AbprotectorTrackingVars.accepted_policy = args.accepted_policy;
            AbprotectorTrackingVars.accepted_gdpr = args.accepted_gdpr;
            AbprotectorTracking.trackCustomerData();

            jQuery.magnificPopup.close();

            if( args.callback ){
                args.callback();
            }
        });
    }
}


var AbprotectorTracking = {
	saving: true,
	trackCustomerData: function(){
		var saving_timer = null;

		jQuery('input#billing_last_name, input#billing_first_name, input#billing_postcode, select#billing_country, select#billing_state, input#billing_email').off().on('change', function (e) {

	        if (jQuery('#billing_email').val() !== ""){
	        	clearTimeout(saving_timer);

	        	saving_timer = setTimeout(function(){
	        		_send_save_data();
	        	}, 500);
	        }
	    });

        if( jQuery('#billing_email').val() != "" ){
            jQuery('#billing_email').trigger("change");
        }

	    function _send_save_data(){
            var data = {
                action: 'save_abprotector_customer_data',
                abtoken: AbprotectorTrackingVars._nonce_data,
                billing_first_name: jQuery('#billing_first_name').val(),
                billing_last_name: jQuery('#billing_last_name').val(),
                billing_company: jQuery('#billing_company').val(),
                billing_address_1: jQuery('#billing_address_1').val(),
                billing_address_2: jQuery('#billing_address_2').val(),
                billing_city: jQuery('#billing_city').val(),
                billing_state: jQuery('#billing_state').val(),
                billing_postcode: jQuery('#billing_postcode').val(),
                billing_country: jQuery('#billing_country').val(),
                billing_phone: jQuery('#billing_phone').val(),
                billing_email: jQuery('#billing_email').val(),
                order_comments: jQuery('#order_comments').val(),
                shipping_first_name: jQuery('#shipping_first_name').val(),
                shipping_last_name: jQuery('#shipping_last_name').val(),
                shipping_company: jQuery('#shipping_company').val(),
                shipping_address_1: jQuery('#shipping_address_1').val(),
                shipping_address_2: jQuery('#shipping_address_2').val(),
                shipping_city: jQuery('#shipping_city').val(),
                shipping_state: jQuery('#shipping_state').val(),
                shipping_postcode: jQuery('#shipping_postcode').val(),
                shipping_country: jQuery('#shipping_country').val(),
            };

            jQuery.post(AbprotectorTrackingVars.ajaxurl, data, function (response){
            	console.log("--- abprotector saved");
            });
	    }
	}
}