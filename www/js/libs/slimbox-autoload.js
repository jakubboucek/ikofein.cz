var slimboxConfig = window.slimboxConfig || {};

// AUTOLOAD CODE BLOCK (MAY BE CHANGED OR REMOVED)
if (!/android|iphone|ipod|series60|symbian|windows ce|blackberry/i.test(navigator.userAgent)) {
	jQuery(function($) {
		$("a[data-rel^='lightbox']").slimbox(slimboxConfig, null, function(el) {
			var thisrel = $(this).attr('data-rel');
			var elrel = $(el).attr('data-rel');
			return (this == el) || ((thisrel.length > 8) && (thisrel == elrel));
		});
	});
}