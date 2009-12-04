/* Copyright (c) 2009 Alvaro A. Lima Jr http://alvarojunior.com/jquery/joverlay.html
 * Licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * Version: 0.8 (OUT 19, 2009)
 * Requires: jQuery 1.3+
 */

(function($) {

	// Global vars
	var isIE6 = $.browser.msie && $.browser.version == 6.0; // =(
	var JOVERLAY_TIMER = null;

	$.fn.jOverlay = function(options) {

		// Element exist?
		if ( $('#jOverlay').length ) {$.closeOverlay();}

		// Clear Timer
		if (JOVERLAY_TIMER !== null) {
			clearTimeout( JOVERLAY_TIMER );
		}

		// Set Options
		var options = $.extend({}, $.fn.jOverlay.options, options || {});

		// success deprecated !!! Use onSuccess
		var onSuccess =  options.onSuccess || options.success;

		var element = this.is('*') ? this : '#jOverlayContent';

		var position = isIE6 ? 'absolute' : 'fixed';

		var isImage = /([^\/\\]+)\.(png|gif|jpeg|jpg|bmp)$/i.test( options.url );

		var imgLoading = options.imgLoading ? "<img id='jOverlayLoading' src='"+options.imgLoading+"' style='position:"+position+"; z-index:"+(options.zIndex + 9)+";'/>" : '';

		// private function
		function center(id) {
			if (options.center) {
				$.center(id);
			} else if( isIE6 ) {
				$.center('#jOverlayContent',{
					'top' : $(window).scrollTop() + 'px',
					'marginLeft' : '',
					'marginTop' : '',
					'left' : ''
				});
			}
		}

		$('body').prepend(imgLoading + "<div id='jOverlay' />"
			+ "<div id='jOverlayContent' style='position:"+position+"; z-index:"+(options.zIndex + 5)+"; display:none;'/>"
		);

		// Cache options
		$('#jOverlayContent').data('options', options);

		// Loading Centered
		$('#jOverlayLoading').load(function() {
			center(this);
		});

		//IE 6 FIX
		if ( isIE6 ) {
			$('select').hide();
			$('#jOverlayContent select').show();
		}

		// Overlay Style
		$('#jOverlay').css({
			'backgroundColor' : options.color,
			'position' : position,
			'top' : '0px',
			'left' : '0px',
			'filter' : 'alpha(opacity='+ (options.opacity * 100) +')', // IE =(
			'opacity' : options.opacity, // Good Browser =D
			'-khtml-opacity' : options.opacity,
			'-moz-opacity' : options.opacity,
			'zIndex' : options.zIndex,
			'width' : !isIE6 ? '100%' : $(window).width() + 'px',
			'height' : !isIE6 ? '100%' : $(document).height() + 'px'
		}).show();

		// INNER HTML
		if ( $.trim(options.html) ) {
			$(element).html(options.html);
		}

		// ELEMENT
		if ( this.is('*') ) {

			$('#jOverlayContent').data('jOverlayElementPrev', this.prev() );

			$('#jOverlayContent').html(
				this.show().data('display', options.autoHide ? 'none' : this.css('display') )
			);

			if ( !isImage ) {

				center('#jOverlayContent');

				$('#jOverlayContent').show();

				// Execute callback
				if ( !options.url && $.isFunction( onSuccess ) ) {
					onSuccess( this );
				}

			}

		}

		// IMAGE
		if ( isImage ) {

			$('<img/>').load(function(){
				var resize = $.resize(this.width, this.height);

				$(this).css({
					width : resize.width,
					height : resize.height
				});

				$( element ).html(this);

				center('#jOverlayContent');
				center('#jOverlayLoading');

				$('#jOverlayLoading').fadeOut(500);
				$('#jOverlayContent').show();

				// Execute callback
				if ( $.isFunction( onSuccess ) ) {
					onSuccess( $(element) );
				}

			}).error(function(){
				alert('Image ('+options.url+') not found.');
				$.closeOverlay();
			}).attr({'src' : options.url, 'alt' : options.url});

		}

		// AJAX
		if ( options.url && !isImage ) {

			$.ajax({
				type: options.method,
				data: options.data,
				url: options.url,
				success: function(responseText) {

					$('#jOverlayLoading').fadeOut(500);

					$( element ).html(responseText).show();

					center('#jOverlayContent');

					// Execute callback
					if ( $.isFunction( onSuccess ) ) {
						onSuccess( responseText );
					}

				},
				error : function() {
					alert('URL ('+options.url+') not found.');
					$.closeOverlay();
				}
			});

		}

		// :(
		if ( isIE6 ) {

			// Window scroll
			$(window).scroll(function(){
				center('#jOverlayContent');
			});

			// Window resize
			$(window).resize(function(){

				$('#jOverlay').css({
					'width' : $(window).width() + 'px',
					'height' : $(document).height() + 'px'
				});

				center('#jOverlayContent');

			});

		}

		// Press ESC to close
		if ( options.closeOnEsc ) {
			$(document).keydown(function(event){
				if ( event.keyCode == 27 ) {
					$.closeOverlay();
				}
			});
		} else {
			$(document).unbind('keydown');
		}

		// Click to close
		if ( options.bgClickToClose ) {
			$('#jOverlay').click($.closeOverlay);
		}

		// Timeout (auto-close)
		// time in millis to wait before auto-close
		// set to 0 to disable
		if ( options.timeout && Number(options.timeout) > 0 ) {
			JOVERLAY_TIMER = window.setTimeout( $.closeOverlay, Number(options.timeout) );
		}

		// ADD CSS
		$('#jOverlayContent').css(options.css || {});

	};

	// Resizing large images - orginal by Christian Montoya.
	// Edited by - Cody Lindley (http://www.codylindley.com) (Thickbox 3.1)
	$.resize = function(imageWidth, imageHeight) {
		var x = $(window).width() - 150;
		var y = $(window).height() - 150;
		if (imageWidth > x) {
			imageHeight = imageHeight * (x / imageWidth);
			imageWidth = x;
			if (imageHeight > y) {
				imageWidth = imageWidth * (y / imageHeight);
				imageHeight = y;
			}
		} else if (imageHeight > y) {
			imageWidth = imageWidth * (y / imageHeight);
			imageHeight = y;
			if (imageWidth > x) {
				imageHeight = imageHeight * (x / imageWidth);
				imageWidth = x;
			}
		}
		return {'width':imageWidth, 'height':imageHeight};
	};

	// Centered Element
	$.center = function(element, css) {
		var element = $(element);
		var elemWidth = element.width();

		element.css($.extend({},{
			'width' : elemWidth + 'px',
			'marginLeft' : '-' + (elemWidth / 2) + 'px',
			'marginTop' : '-' + element.height() / 2 + 'px',
		 	'height' : 'auto',
         	'top' : !isIE6 ? '50%' : $(window).scrollTop() + ($(window).height() / 2) + 'px',
         	'left' : '50%'
		}, css || {}));
	};

	// Options default
	$.fn.jOverlay.options = {
		'method' : 'GET',
		'data' : '',
		'url' : '',
		'color' : '#000',
		'opacity' : '0.6',
		'zIndex' : 9999,
		'center' : true,
		'imgLoading' : '',
		'bgClickToClose' : true,
		'success' : null, // Deprecated : use onSuccess
		'onSuccess' : null,
		'timeout' : 0,
		'autoHide' : true,
		'css' : {},
		'html' : '',
		'closeOnEsc' : true
	};

	// Set default options (GLOBAL)
	// Overiding the default values.
	$.fn.jOverlay.setDefaults = function(options) {
		$.fn.jOverlay.options = $.extend({}, $.fn.jOverlay.options, options || {});
	};

	// Close
	$.closeOverlay = function() {

		var content = $('#jOverlayContent');
		var options = content.data('options');
		var elementPrev = content.data('jOverlayElementPrev');

		// Fix IE6 (SELECT)
		if (isIE6) { $("select").show(); }

		// Restore position
		if ( elementPrev ) {
			var contentChildren = content.children();
			elementPrev.after( contentChildren.css('display', contentChildren.data('display') ) );
			// Clear cache
			contentChildren.removeData('display');
			content.removeData('jOverlayElementPrev');
		}

		// Clear options cache
		content.removeData('options');

		// Remove joverlay elements
		$('#jOverlayLoading, #jOverlayContent, #jOverlay').remove();

	};

})(jQuery);