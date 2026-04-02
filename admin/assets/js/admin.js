/* global jQuery, geocraftAdmin */
(function ($) {
	'use strict';

	$(function () {
		var $btn    = $('#geocraft-test-connection');
		var $result = $('#geocraft-test-result');
		var $token  = $('#geocraft-api-token');

		// Enable the test button whenever a token value is present.
		$token.on('input', function () {
			$btn.prop('disabled', '' === $(this).val());
		});

		$btn.on('click', function () {
			$result
				.text(geocraftAdmin.i18n.testing)
				.removeClass('is-success is-error');
			$btn.prop('disabled', true);

			$.post(geocraftAdmin.ajaxUrl, {
				action: 'geocraft_test_connection',
				nonce:  geocraftAdmin.testNonce,
			})
			.done(function (response) {
				if (response.success) {
					$result
						.text(geocraftAdmin.i18n.success)
						.addClass('is-success');
				} else {
					$result
						.text(response.data && response.data.message ? response.data.message : geocraftAdmin.i18n.error)
						.addClass('is-error');
				}
			})
			.fail(function () {
				$result
					.text(geocraftAdmin.i18n.error)
					.addClass('is-error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
