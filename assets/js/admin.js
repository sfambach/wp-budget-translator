(function ($) {
	'use strict';

	function headers() {
		return {
			'Content-Type': 'application/json',
			'X-WP-Nonce': btAdmin.nonce
		};
	}

	/**
	 * Grow a textarea so its full content is visible.
	 *
	 * @param {HTMLTextAreaElement|jQuery} el Textarea.
	 */
	function autosizeTextarea(el) {
		var node = el && el.jquery ? el[0] : el;
		if (!node || node.tagName !== 'TEXTAREA') {
			return;
		}
		node.style.height = 'auto';
		node.style.height = Math.max(node.scrollHeight, 48) + 'px';
	}

	/**
	 * Autosize source + translation in a review row to the same height.
	 *
	 * @param {jQuery} $row Table row.
	 */
	function autosizeRow($row) {
		var $areas = $row.find('.bt-source-text, .bt-translated');
		if (!$areas.length) {
			return;
		}
		$areas.each(function () {
			this.style.height = 'auto';
		});
		var max = 48;
		$areas.each(function () {
			max = Math.max(max, this.scrollHeight);
		});
		$areas.each(function () {
			this.style.height = max + 'px';
		});
	}

	function autosizeAll() {
		$('.bt-review-table tbody tr').each(function () {
			autosizeRow($(this));
		});
		$('#bt-focus-source, #bt-focus-translated').each(function () {
			autosizeTextarea(this);
		});
	}

	function notify(msg, isError) {
		var $n = $('<div class="notice is-dismissible"><p></p></div>');
		$n.addClass(isError ? 'notice-error' : 'notice-success');
		$n.find('p').text(msg);
		$('.bt-admin h1').first().after($n);
		if (window.wp && wp.a11y && wp.a11y.speak) {
			wp.a11y.speak(msg);
		}
		setTimeout(function () {
			$n.fadeOut(200, function () {
				$(this).remove();
			});
		}, 4000);
	}

	$(document).on('click', '.bt-save-row', function () {
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var source = $row.find('.bt-source-text').val();
		var text = $row.find('.bt-translated').val();

		$.ajax({
			url: btAdmin.restUrl + 'translations/' + id,
			method: 'POST',
			headers: headers(),
			data: JSON.stringify({ source_text: source, translated_text: text, status: 'edited' }),
			success: function (res) {
				$row.find('.bt-status').text(res.status || 'edited');
				var msg = btAdmin.i18n.saved;
				if (res.source_changed && res.propagated) {
					msg += ' (posts: ' + (res.propagated.posts || 0) + ', menus: ' + (res.propagated.menus || 0) + ')';
				}
				notify(msg);
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
				notify(msg, true);
			}
		});
	});

	$(document).on('click', '.bt-confirm-row', function () {
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var source = $row.find('.bt-source-text').val();
		var text = $row.find('.bt-translated').val();

		$.ajax({
			url: btAdmin.restUrl + 'translations/' + id,
			method: 'POST',
			headers: headers(),
			data: JSON.stringify({ source_text: source, translated_text: text, status: 'confirmed' }),
			success: function (res) {
				$row.find('.bt-status').text('confirmed');
				var msg = btAdmin.i18n.confirmed;
				if (res.source_changed && res.propagated) {
					msg += ' (posts: ' + (res.propagated.posts || 0) + ', menus: ' + (res.propagated.menus || 0) + ')';
				}
				notify(msg);
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
				notify(msg, true);
			}
		});
	});

	$(document).on('click', '.bt-retranslate-row', function () {
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: btAdmin.restUrl + 'translations/' + id + '/retranslate',
			method: 'POST',
			headers: headers(),
			success: function (res) {
				if (res.translated_text) {
					$row.find('.bt-translated').val(res.translated_text);
					autosizeRow($row);
				}
				$row.find('.bt-status').text(res.status || 'auto');
				notify(btAdmin.i18n.retranslated);
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
				notify(msg, true);
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	$('#bt-check-all').on('change', function () {
		$('.bt-row-check').prop('checked', $(this).is(':checked'));
	});

	$('#bt-confirm-selected').on('click', function () {
		var ids = $('.bt-row-check:checked').map(function () {
			return parseInt($(this).val(), 10);
		}).get();

		if (!ids.length) {
			return;
		}

		$.ajax({
			url: btAdmin.restUrl + 'translations/confirm',
			method: 'POST',
			headers: headers(),
			data: JSON.stringify({ ids: ids }),
			success: function () {
				ids.forEach(function (id) {
					$('tr[data-id="' + id + '"] .bt-status').text('confirmed');
				});
				notify(btAdmin.i18n.confirmed);
			},
			error: function () {
				notify(btAdmin.i18n.error, true);
			}
		});
	});

	function refreshJobStatus(data) {
		var $el = $('#bt-job-status');
		if (!$el.length || !data) {
			return;
		}
		var done = parseInt(data.done, 10) || 0;
		var total = parseInt(data.total, 10) || 0;
		var state = data.state || '-';
		var pct = total > 0 ? Math.round((done / total) * 100) : 0;
		$el.attr('data-state', state);
		var label = (btAdmin.i18n.progress || 'Site queue: %1$d / %2$d (%3$s%%)')
			.replace('%1$d', String(done))
			.replace('%2$d', String(total))
			.replace('%3$s', String(pct));
		var note = btAdmin.i18n.queueNote
			? '<br /><span class="description">' + btAdmin.i18n.queueNote + '</span>'
			: '';
		$el.html(
			'<p>' + label + ' — ' + state + note + '</p>' +
			'<div class="bt-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '">' +
			'<span class="bt-progress__bar" style="width:' + pct + '%"></span></div>'
		);
	}

	$('#bt-process-job').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);
		$.ajax({
			url: btAdmin.restUrl + 'job/process',
			method: 'POST',
			headers: headers(),
			success: function (data) {
				refreshJobStatus(data);
				if (data && data.state === 'done') {
					notify(btAdmin.i18n.done);
				} else {
					notify(btAdmin.i18n.running);
				}
			},
			error: function () {
				notify(btAdmin.i18n.error, true);
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// Auto-poll while job running on settings page.
	(function pollJob() {
		var $el = $('#bt-job-status');
		if (!$el.length || $el.data('state') !== 'running') {
			return;
		}
		$.ajax({
			url: btAdmin.restUrl + 'job/process',
			method: 'POST',
			headers: headers(),
			success: function (data) {
				refreshJobStatus(data);
				if (data && data.state === 'running') {
					setTimeout(pollJob, 1500);
				} else if (data && data.state === 'done') {
					notify(btAdmin.i18n.done);
				}
			}
		});
	})();
	// Focus (single item) review mode.
	(function () {
		var $card = $('#bt-focus-card');
		if (!$card.length) {
			return;
		}

		var lang = String($('.bt-focus').data('lang') || '');

		function focusUrl(id) {
			var url = btAdmin.focusUrl + (btAdmin.focusUrl.indexOf('?') >= 0 ? '&' : '?') + 'bt_id=' + encodeURIComponent(id);
			if (lang) {
				url += '&bt_lang=' + encodeURIComponent(lang);
			}
			return url;
		}

		function goNext(nextId) {
			if (nextId) {
				window.location.href = focusUrl(nextId);
				return;
			}
			notify(btAdmin.i18n.allDone);
			window.setTimeout(function () {
				var url = btAdmin.focusUrl;
				if (lang) {
					url += (url.indexOf('?') >= 0 ? '&' : '?') + 'bt_lang=' + encodeURIComponent(lang);
				}
				window.location.href = url;
			}, 500);
		}

		function payload(status) {
			return JSON.stringify({
				source_text: $('#bt-focus-source').val(),
				translated_text: $('#bt-focus-translated').val(),
				status: status,
				lang: lang
			});
		}

		$('.bt-focus-save').on('click', function () {
			var id = $card.data('id');
			$.ajax({
				url: btAdmin.restUrl + 'translations/' + id,
				method: 'POST',
				headers: headers(),
				data: payload('edited'),
				success: function (res) {
					$card.find('.bt-status').text(res.status || 'edited');
					notify(btAdmin.i18n.saved);
				},
				error: function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
					notify(msg, true);
				}
			});
		});

		$('.bt-focus-confirm').on('click', function () {
			var id = $card.data('id');
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.ajax({
				url: btAdmin.restUrl + 'translations/' + id,
				method: 'POST',
				headers: headers(),
				data: payload('confirmed'),
				success: function (res) {
					notify(btAdmin.i18n.confirmed);
					goNext(res.next_id || null);
				},
				error: function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
					notify(msg, true);
					$btn.prop('disabled', false);
				}
			});
		});

		$('.bt-focus-retranslate').on('click', function () {
			var id = $card.data('id');
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.ajax({
				url: btAdmin.restUrl + 'translations/' + id + '/retranslate',
				method: 'POST',
				headers: headers(),
				success: function (res) {
					if (res.translated_text) {
						$('#bt-focus-translated').val(res.translated_text);
						autosizeTextarea($('#bt-focus-translated'));
					}
					$card.find('.bt-status').text(res.status || 'auto');
					notify(btAdmin.i18n.retranslated);
				},
				error: function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : btAdmin.i18n.error;
					notify(msg, true);
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});

		$(document).on('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
				e.preventDefault();
				$('.bt-focus-confirm').trigger('click');
			}
		});
	})();

	$(document).on('input', '.bt-review-table .bt-source-text, .bt-review-table .bt-translated', function () {
		autosizeRow($(this).closest('tr'));
	});

	$(document).on('input', '#bt-focus-source, #bt-focus-translated', function () {
		autosizeTextarea(this);
	});

	$(autosizeAll);
	$(window).on('load', autosizeAll);
})(jQuery);
