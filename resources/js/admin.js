jQuery(document).ready(function(){
	jQuery(".btn_change_abprotector_keys").click(function(){
		AbprotectorAdmin.showChangeKeysForm();
	});

	jQuery(".btn_cancel_abprotector_keys").click(function(e){
		e.preventDefault();
		AbprotectorAdmin.hideChangeKeysForm();
	});
});

var AbprotectorAdmin = {
	showChangeKeysForm: function(){
		jQuery(".btn_change_keys_wrapper").hide();
		jQuery("#chp_form_keys_container").show();
	},
	hideChangeKeysForm: function(){
		jQuery(".btn_change_keys_wrapper").show();
		jQuery("#chp_form_keys_container").hide();
	}
}