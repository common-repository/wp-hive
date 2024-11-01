(function () {
	var data = {
		'action'  : 'wphive',
		'task'    : 'pvtrack',
		'url'     : document.URL,
		'referer' : document.referrer
	};
	jQuery.post(WPHive.ajaxurl, data);
})();
