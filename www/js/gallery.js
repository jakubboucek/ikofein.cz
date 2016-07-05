//@koala-prepend "libs/jquery-2.1.1.js"
//@koala-prepend "core.js"
//@koala-prepend "libs/slimbox-2.05.js"

var counterText = "Image {x} of {y}";

if( $('html').is(':lang(cs)') ) {
	counterText = "Obrázek {x} z {y}";
}

slimboxConfig = {
	captionAnimationDuration: 80,
	overlayFadeDuration: 80,
	resizeDuration: 200,
	imageFadeDuration: 80,
	counterText: counterText,
	closeKeys: [27, 88, 67, 90],
	nextKeys: [32, 39, 78, 68],
	previousKeys: [8, 37, 80]
};

//@koala-prepend "libs/slimbox-autoload.js"


