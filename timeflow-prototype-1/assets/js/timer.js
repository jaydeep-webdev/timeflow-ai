jQuery(function ($) {
  const controls = $('.tf-controls').first();
  if (!controls.length) return;

  const taskId = controls.data('task-id');
  const messageEl = $('#tf-message');
  const startBtn = $('#tf-start-timer');
  const stopBtn = $('#tf-stop-timer');
  const addBtn = $('#tf-add-time');
  const minutesInput = $('#tf-manual-minutes');

  function showMessage(text, type) {
    messageEl.removeClass('is-error is-success').addClass(type === 'error' ? 'is-error' : 'is-success').text(text);
  }

  function addLogRow(type, start, end, duration) {
    const tbody = $('#tf-log-body');
    const empty = tbody.find('td[colspan="4"]');
    if (empty.length) {
      empty.closest('tr').remove();
    }

    const row = `<tr>
      <td>${type}</td>
      <td>${start}</td>
      <td>${end}</td>
      <td>${duration}</td>
    </tr>`;
    tbody.prepend(row);
  }

  function toDisplayTime(unixTs) {
    if (!unixTs) return '—';
    const date = new Date(unixTs * 1000);
    const pad = (v) => String(v).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
  }

  function postAction(action, extraData) {
    return $.post(tfTimer.ajaxUrl, {
      action,
      nonce: tfTimer.nonce,
      task_id: taskId,
      ...extraData
    });
  }

  startBtn.on('click', function () {
    postAction('tf_start_timer').done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to start timer.', 'error');
        return;
      }
      showMessage(resp.data.message, 'success');
      startBtn.prop('disabled', true);
      stopBtn.prop('disabled', false);
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });

  stopBtn.on('click', function () {
    postAction('tf_stop_timer').done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to stop timer.', 'error');
        return;
      }
      const data = resp.data.data;
      $('#tf-total-time-display').text(data.formatted);
      addLogRow('Timer', toDisplayTime(data.started_at), toDisplayTime(data.ended_at), data.duration + 's');
      showMessage(resp.data.message, 'success');
      startBtn.prop('disabled', false);
      stopBtn.prop('disabled', true);
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });

  addBtn.on('click', function () {
    const minutes = parseInt(minutesInput.val(), 10);
    if (!minutes || minutes < 1) {
      showMessage('Please enter minutes greater than 0.', 'error');
      return;
    }

    postAction('tf_add_manual_time', { minutes }).done(function (resp) {
      if (!resp.success) {
        showMessage(resp.data.message || 'Unable to add time.', 'error');
        return;
      }
      const data = resp.data.data;
      $('#tf-total-time-display').text(data.formatted);
      addLogRow('Manual', toDisplayTime(data.logged_at), toDisplayTime(data.logged_at), data.duration + 's');
      showMessage(resp.data.message, 'success');
      minutesInput.val('');
    }).fail(function () {
      showMessage('Request failed.', 'error');
    });
  });
});
