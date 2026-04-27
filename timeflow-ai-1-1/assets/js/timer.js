jQuery(function ($) {
  const controls = $('.tf-controls[data-task-id]').first();
  if (!controls.length || !$('#tf-start-timer').length) {
    return;
  }

  const taskId = parseInt(controls.data('task-id'), 10) || 0;
  const messageEl = $('#tf-message');

  function showMessage(text, type) {
    if (!messageEl.length) return;
    messageEl.removeClass('is-success is-error').addClass(type === 'error' ? 'is-error' : 'is-success').text(text);
  }

  function postAction(action, extraData) {
    return $.post(tfTimer.ajaxUrl, Object.assign({
      action: action,
      nonce: tfTimer.nonce,
      task_id: taskId
    }, extraData || {}));
  }

  $('#tf-start-timer').on('click', function () {
    postAction('tf_start_timer').done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to start timer.', 'error');
        return;
      }
      showMessage(resp.data.message, 'success');
      $('#tf-start-timer').prop('disabled', true);
      $('#tf-stop-timer').prop('disabled', false);
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });

  $('#tf-stop-timer').on('click', function () {
    postAction('tf_stop_timer').done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to stop timer.', 'error');
        return;
      }
      const data = resp.data.data;
      $('#tf-total-time-display').text(data.formatted_total);
      showMessage(resp.data.message, 'success');
      $('#tf-start-timer').prop('disabled', false);
      $('#tf-stop-timer').prop('disabled', true);
      window.location.reload();
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });

  $('#tf-add-time').on('click', function () {
    const minutes = parseInt($('#tf-manual-minutes').val(), 10);
    if (!minutes || minutes < 1) {
      showMessage('Please enter minutes greater than 0.', 'error');
      return;
    }

    postAction('tf_add_manual_time', { minutes: minutes }).done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to add time.', 'error');
        return;
      }
      const data = resp.data.data;
      $('#tf-total-time-display').text(data.formatted_total);
      showMessage(resp.data.message, 'success');
      $('#tf-manual-minutes').val('');
      window.location.reload();
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });
});
