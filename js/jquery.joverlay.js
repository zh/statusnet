/* Copyright (c) 2009 Alvaro A. Lima Jr http://alvarojunior.com/jquery/joverlay.html
 * Licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * Version: 0.7.1 (JUN 15, 2009)
 * Requires: jQuery 1.3+
 */

(function($) {

	// Global vars
	var isIE6 = $.browser.msie && $.browser.version == 6.0; // =(
	var JOVERLAY_TIMER = null;
	var	JOVERLAY_ELEMENT_PREV = null;

	$.fn.jOverlay = function(options) {

		// Element exist?
		if ( $('#jOverlay').length ) {$.closeOverlay();}

		// Clear Element Prev
		JOVERLAY_ELEMENT_PREV = null;

		// Clear Timer
		if (JOVERLAY_TIMER !== null) {
			clearTimeout( JOVERLAY_TIMER );
		}

		// Set Options
		var options = $.extend({}, $.fn.jOverlay.options, options);

		// private function
		function center(id) {
			if (options.center) {
				$.center(id);
			}
		}

		var element = this.is('*') ? this : '#jOverlayContent';
		var position = isIE6 ? 'absolute' : 'fixed';
		var isImage = /([^\/\\]+)\.(png|gif|jpeg|jpg|bmp)$/i.test( options.url );

		var imgLoading = options.imgLoading ? "<img id='jOverlayLoading' src='"+options.imgLoading+"' style='position:"+position+"; z-index:"+(options.zIndex + 9)+";'/>" : '';

		$('body').prepend(imgLoading + "<div id='jOverlay' />"
			+ "<div id='jOverlayContent' style='position:"+position+"; z-index:"+(options.zIndex + 5)+"; display:none;'/>"
		);

		// Loading Centered
		$('#jOverlayLoading').load(function(){
			center(this);
		});

		//IE 6 FIX
		if ( isIE6 ) {
			$('select').hide();
			$('#jOverlayContent select').show();
		}

		// Overlay Style
		$('#jOverlay').css({
			backgroundColor : options.color,
			position : position,
			top : '0px',
			left : '0px',
			filter : 'alpha(opacity='+ (options.opacity * 100) +')', // IE =(
			opacity : options.opacity, // Good Browser =D
			zIndex : options.zIndex,
			width : !isIE6 ? '100%' : $(window).width() + 'px',
			height : !isIE6 ? '100%' : $(document).height() + 'px'
		}).show();

		// ELEMENT
		if ( this.is('*') ) {

			JOVERLAY_ELEMENT_PREV = this.prev();

			$('#jOverlayContent').html(
				this.show().attr('display', options.autoHide ? 'none' : this.css('display') )
			);
			
			if ( !isImage ) {

				center('#jOverlayContent');

				$('#jOverlayContent').show();
				
				// Execute callback
				if ( !options.url && $.isFunction( options.success ) ) {
					options.success( this );
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

				$('#jOverlayLoading').fadeOut(500);
				$('#jOverlayContent').show();

				// Execute callback
				if ( $.isFunction( options.success ) ) {
					options.success( this );
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
					if ($.isFunction( options.success )) {
						options.success(responseText);
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
					width: $(window).width() + 'px',
					height: $(document).height() + 'px'
				});

				center('#jOverlayContent');

			});

		}

		// Press ESC to close
		$(document).keydown(function(event){
			if (event.keyCode == 27) {
				$.closeOverlay();
			}
		});

		// Click to close
		if ( options.bgClickToClose ) {
			$('#jOverlay').click($.closeOverlay);
		}

		// Timeout (auto-close)
		// time in millis to wait before auto-close
		// set to 0 to disable
		if ( Number(options.timeout) > 0 ) {
			jOverlayTimer = setTimeout( $.closeOverlay, Number(options.timeout) );
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
		return {width:imageWidth, height:imageHeight};
	};

	// Centered Element
	$.center = function(element) {
		var element = $(element);
		var elemWidth = element.width();

		element.css({
			width : elemWidth + 'px',
			marginLeft : '-' + (elemWidth / 2) + 'px',
			marginTop : '-' + element.height() / 2 + 'px',
		 	height : 'auto',
         	top : !isIE6 ? '50%' : $(window).scrollTop() + ($(window).height() / 2) + 'px',
         	left : '50%'
		});
	};

	// Options default
	$.fn.jOverlay.options = {
		method : 'GET',
		data : '',
		url : '',
		color : '#000',
		opacity : '0.6',
		zIndex : 9999,
		center : true,
		imgLoading : '',
		bgClickToClose : true,
		success : null,
		timeout : 0,
		autoHide : true,
		css : {}
	};

	// Close
	$.closeOverlay = function() {

		if (isIE6) { $("select").show(); }

		if ( JOVERLAY_ELEMENT_PREV !== null ) {
			if ( JOVERLAY_ELEMENT_PREV !== null ) {
				var element = $('#jOverlayContent').children();
				JOVERLAY_ELEMENT_PREV.after( element.css('display', element.attr('display') ) );
				element.removeAttr('display');
			}
		}

		$('#jOverlayLoading, #jOverlayContent, #jOverlay').remove();

	};

})(jQuery);