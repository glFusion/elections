/*
 * Utility javascript functions to abstrace UI elements from the
 * underlying framework.
 */

/* Display a popup notification */
var Elections = (function() {
	return {
		// Display a notification popup for a short time.
		notify: function(message, status='', timeout=1500) {
			if (status == 'success') {
				var icon = "<i class='uk-icon uk-icon-check'></i>&nbsp;";
			} else if (status == 'warning') {
				var icon = '<i class="uk-icon uk-icon-exclamation-triangle"></i>&nbsp';
			} else {
				var icon = '';
			}
			if (typeof UIkit.notify === 'function') {
				// uikit v2 theme
	            UIkit.notify(icon + message, {timeout: timeout});
			} else if (typeof UIkit.notification === 'function') {
		        // uikit v3 theme
				UIkit.notification({
		            message: icon + message,
				    timeout: timeout,
		            status: status,
				});
		    } else {
				alert(message);
			}
		}
	};
})();

