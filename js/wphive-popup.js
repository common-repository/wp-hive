function wphive_load_leaddata( email ) {

	jQuery('div#wphive-popup').dialog( "open" );
	jQuery('div#wphive-popup').html('<b>Loading data... Please wait...</b>');

	var data = {
		'action' : 'wphive_admin',
		'task'   : 'load_leaddata',
		'email'  : email
	};

	jQuery.post(ajaxurl, data, function(response) {
		jQuery('div#wphive-popup').html(response);
	});

}

jQuery(document).ready(function($){
    jQuery( "div#wphive-popup" ).dialog({
        dialogClass : 'wp-dialog',
        autoOpen : false,
        closeOnEscape : true,
        height : 600,
        width : 1200,
        modal : true
    });
});
