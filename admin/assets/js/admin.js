/* global jQuery, geocraftAdmin */
(function ($) {
	'use strict';

	// ---------------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------------

	var catRowIndex = 0;
	var tagRowIndex = 0;

	/**
	 * Build the WP category <select> HTML from the geocraftAdmin.wpCategories list.
	 *
	 * @param {string} fieldName  Full name attribute for the select.
	 * @param {number} selectedId Currently selected term_id (0 = none).
	 * @returns {string} HTML string.
	 */
	function buildCategorySelect(fieldName, selectedId) {
		var html = '<select name="' + fieldName + '">';
		html += '<option value="0">— Select category —</option>';
		$.each(geocraftAdmin.wpCategories, function (i, cat) {
			var sel = (cat.id === selectedId) ? ' selected' : '';
			html += '<option value="' + cat.id + '"' + sel + '>' + $('<div>').text(cat.name).html() + '</option>';
		});
		html += '</select>';
		return html;
	}

	/**
	 * Append a new category mapping row to the table body.
	 *
	 * @param {string} geoCat    Pre-filled GeoCraft category name.
	 * @param {number} wpTermId  Pre-selected WP term id.
	 * @param {boolean} autoCreate Whether auto-create is checked.
	 */
	function addCategoryRow(geoCat, wpTermId, autoCreate) {
		var idx    = 'new_' + catRowIndex++;
		var row    = $('<tr class="geocraft-map-row">');
		var prefix = 'geocraft_category_map[' + idx + ']';

		row.append(
			$('<td>').append(
				$('<input>', {
					type:  'text',
					name:  prefix + '[geocraft_cat]',
					value: geoCat || '',
					class: 'regular-text',
				})
			)
		);

		row.append(
			$('<td>').html(buildCategorySelect(prefix + '[wp_term_id]', wpTermId || 0))
		);

		var $checkbox = $('<input>', {
			type:  'checkbox',
			name:  prefix + '[auto_create]',
			value: '1',
		}).prop('checked', !!autoCreate);

		row.append($('<td>').append($checkbox));
		row.append(
			$('<td>').append(
				$('<button>', {
					type:  'button',
					class: 'button button-small geocraft-remove-row',
					text:  geocraftAdmin.i18n.remove,
				})
			)
		);

		$('#geocraft-category-map-body').append(row);
	}

	/**
	 * Append a new content-type tags row to the table body.
	 *
	 * @param {string} contentType Pre-filled content type key.
	 * @param {string} tags        Pre-filled comma-separated tags.
	 */
	function addTagRow(contentType, tags) {
		var idx    = 'new_' + tagRowIndex++;
		var prefix = 'geocraft_content_type[' + idx + ']';
		var row    = $('<tr class="geocraft-tag-row">');

		row.append(
			$('<td>').append(
				$('<input>', {
					type:        'text',
					name:        prefix + '[type]',
					value:       contentType || '',
					class:       'regular-text',
					placeholder: 'e.g. blog_post',
				})
			)
		);

		row.append(
			$('<td>').append(
				$('<input>', {
					type:        'text',
					name:        prefix + '[tags]',
					value:       tags || '',
					class:       'large-text',
					placeholder: 'e.g. news, featured',
				})
			)
		);

		row.append(
			$('<td>').append(
				$('<button>', {
					type:  'button',
					class: 'button button-small geocraft-remove-row',
					text:  geocraftAdmin.i18n.remove,
				})
			)
		);

		$('#geocraft-content-type-body').append(row);
	}

	// ---------------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------------

	$(function () {

		// --- Connection test ---------------------------------------------------

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

		// --- Load GeoCraft categories ------------------------------------------

		$('#geocraft-load-categories').on('click', function () {
			var $loadBtn    = $(this);
			var $loadResult = $('#geocraft-load-cats-result');

			$loadResult.text(geocraftAdmin.i18n.loading).removeClass('is-success is-error');
			$loadBtn.prop('disabled', true);

			$.post(geocraftAdmin.ajaxUrl, {
				action: 'geocraft_fetch_categories',
				nonce:  geocraftAdmin.fetchCatNonce,
			})
			.done(function (response) {
				if (response.success && response.data && response.data.categories) {
					var cats = response.data.categories;

					// For each returned category, add a row if not already present.
					$.each(cats, function (i, cat) {
						var name = cat.name || cat.slug || String(cat);
						addCategoryRow(name, 0, false);
					});

					$loadResult.text('').addClass('is-success');
				} else {
					var msg = response.data && response.data.message ? response.data.message : geocraftAdmin.i18n.loadError;
					$loadResult.text(msg).addClass('is-error');
				}
			})
			.fail(function () {
				$loadResult.text(geocraftAdmin.i18n.loadError).addClass('is-error');
			})
			.always(function () {
				$loadBtn.prop('disabled', false);
			});
		});

		// --- Add row buttons ---------------------------------------------------

		$('#geocraft-add-cat-row').on('click', function () {
			addCategoryRow('', 0, false);
		});

		$('#geocraft-add-tag-row').on('click', function () {
			addTagRow('', '');
		});

		// --- Remove row (delegated) -------------------------------------------

		$(document).on('click', '.geocraft-remove-row', function () {
			$(this).closest('tr').remove();
		});
	});
})(jQuery);
