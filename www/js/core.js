(function($){
	var date = new Date();
	var lang = $('html').attr('lang');
	date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
	var expires = "; expires=" + date.toGMTString();
	document.cookie = "lang=" + lang + expires + "; path=/";
})(jQuery);