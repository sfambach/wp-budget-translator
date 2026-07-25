(function ($) {
	'use strict';

	function headers() {
		return {
			'Content-Type': 'application/json',
			'X-WP-Nonce': btAdmin.nonce
		};
	}

	function notify(msg, isError) {
		var $n = $('<div class="notice is-dismissible"><p></p></div>');
		$n.addClass(isError ? 'notice-error' : 'notice-success');
		$n.find('p').text(msg);
		$('.bt-admin h1').first().after($n);
		setTimeout(function () {
			$n.fadeOut(200, function () {
				$(this).remove();
			});
		}, 2500);
	}

	$(document).on('click', '.bt-save-row', function () {
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var text = $row.find('.bt-translated').val();

		$.ajax({
			url: btAdmin.restUrl + 'translations/' + id,
			method: 'POST',
			headers: headers(),
			data: JSON.stringify({ translated_text: text, status: 'edited' }),
			success: function (res) {
				$row.find('.bt-status').text(res.status || 'edited');
				notify(btAdmin.i18n.saved);
			},
			error: function () {
				notify(btAdmin.i18n.error, true);
			}
		});
	});

	$(document).on('click', '.bt-confirm-row', function () {
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var text = $row.find('.bt-translated').val();

		$.ajax({
			url: btAdmin.restUrl + 'translations/' + id,
			method: 'POST',
			headers: headers(),
			data: JSON.stringify({ translated_text: text, status: 'confirmed' }),
			success: function () {
				$row.find('.bt-status').text('confirmed');
				notify(btAdmin.i18n.confirmed);
			},
			error: function () {
				notify(btAdmin.i18n.error, true);
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
		var done = data.done || 0;
		var total = data.total || 0;
		var state = data.state || '-';
		$el.attr('data-state', state);
		$el.html('<p>Progress: ' + done + ' / ' + total + ' (' + state + ')</p>');
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
})(jQuery);
