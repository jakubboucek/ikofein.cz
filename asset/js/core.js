(function($){
	const date = new Date();
	const lang = $('html').attr('lang');
	date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
	const expires = "; expires=" + date.toUTCString();
	document.cookie = "lang=" + lang + expires + "; path=/";
})(jQuery);