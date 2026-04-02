/**
 * GeoCraft Analytics — front-end beacon.
 *
 * Tracks time-on-page and bounce signals for GeoCraft-published posts.
 * Sends a lightweight POST to wp-admin/admin-ajax.php when the visitor
 * leaves the page (visibilitychange hidden + pagehide fallback).
 *
 * Data injected via wp_localize_script as `geocraftAnalytics`:
 *   ajaxUrl        {string}  admin-ajax.php URL
 *   action         {string}  AJAX action name
 *   nonce          {string}  WP nonce for this post
 *   geocraftPostId {string}  GeoCraft remote post ID
 *   wpPostId       {number}  WordPress post ID
 */
(function () {
	'use strict';

	if (typeof geocraftAnalytics === 'undefined') {
		return;
	}

	var cfg         = geocraftAnalytics;
	var startTime   = Date.now();
	var sent        = false;

	/**
	 * Determine whether this visit qualifies as a bounce.
	 * A bounce is a single-page session where the visitor left in < 10 s.
	 *
	 * @param {number} seconds Seconds spent on page.
	 * @returns {boolean}
	 */
	function isBounce(seconds) {
		return seconds < 10;
	}

	/**
	 * Send beacon data. Uses navigator.sendBeacon when available for
	 * reliability on page unload; falls back to a synchronous XHR.
	 */
	function sendBeacon() {
		if (sent) {
			return;
		}
		sent = true;

		var seconds = Math.round((Date.now() - startTime) / 1000);
		if (seconds <= 0) {
			return;
		}

		var params = new URLSearchParams();
		params.append('action',           cfg.action);
		params.append('nonce',            cfg.nonce);
		params.append('wp_post_id',       String(cfg.wpPostId));
		params.append('geocraft_post_id', cfg.geocraftPostId);
		params.append('time_on_page',     String(seconds));
		params.append('is_bounce',        isBounce(seconds) ? '1' : '0');

		var url = cfg.ajaxUrl;

		if (typeof navigator.sendBeacon === 'function') {
			navigator.sendBeacon(url, params);
			return;
		}

		// Synchronous XHR fallback (blocks unload briefly).
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url, false);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send(params.toString());
		} catch (e) {
			// Best-effort — ignore errors on unload.
		}
	}

	// Primary signal: Page Visibility API (covers tab close, navigation away).
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			sendBeacon();
		}
	});

	// Fallback: pagehide fires on mobile Safari and when bfcache is involved.
	window.addEventListener('pagehide', sendBeacon);
}());
