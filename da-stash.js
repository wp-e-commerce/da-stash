/* js */

jQuery(document).ready(function($) {

	function addLoadingSpinner(thetarget) {
		$('.da-stash-loading').remove();
		$(thetarget).prepend('<div class="da-stash-loading"></div>');
	}

	$('.da-stash-connect').click(function (evt) {
		addLoadingSpinner(evt.target);
		evt.preventDefault();
		var width = 975;
		var height = 400;
		var left = (screen.width / 2) - (width / 2);
		var top = (screen.height / 2) - (height / 2);
		window.open(DA_Stash.connect_url, 'da-stash-connect', 'toolbar=no, location=yes, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=' + width + ', height=' + height + ', top=' + top + ', left=' + left);
	});

	$('.da-stash-disconnect').click(function (evt) {
		addLoadingSpinner(evt.target);
		evt.preventDefault();
		$.ajax({
			type: "POST",
			url: DA_Stash.api_url + '&action=disconnect',
			data: {'disconnect': '1'},
			success: function (data, textStatus, jqXHR) {
				if (DA_Stash.onDisconnect) {
					DA_Stash.onDisconnect(data);
				}
			}
		});
	});

});