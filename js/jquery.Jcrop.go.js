		$(function(){
			jQuery("#photo_original img").Jcrop({
				onChange: showPreview,
				setSelect: [ 0, 0, $("#photo_original img").attr("width"), $("#photo_original img").attr("height") ],
				onSelect: updateCoords,
				aspectRatio: 1,
				boxWidth: 480,
				boxHeight: 480,
				bgColor: '#000',
				bgOpacity: .4
			});
		});

		function showPreview(coords) {
			var rx = 96 / coords.w;
			var ry = 96 / coords.h;

			var img_width = $("#photo_original img").attr("width");
			var img_height = $("#photo_original img").attr("height");

			$('#photo_preview img').css({
				width: Math.round(rx *img_width) + 'px',
				height: Math.round(ry * img_height) + 'px',
				marginLeft: '-' + Math.round(rx * coords.x) + 'px',
				marginTop: '-' + Math.round(ry * coords.y) + 'px'
			});
		};

		function updateCoords(c) {
			$('#photo_crop_x').val(c.x);
			$('#photo_crop_y').val(c.y);
			$('#photo_crop_w').val(c.w);
			$('#photo_crop_h').val(c.h);
		};

		function checkCoords() {
			if (parseInt($('#photo_crop_w').val())) return true;
			alert('Please select a crop region then press submit.');
			return false;
		};

