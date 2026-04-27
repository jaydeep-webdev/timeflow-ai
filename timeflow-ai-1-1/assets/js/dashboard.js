jQuery(function ($) {
  const state = {
    data: null
  };

  function formatDuration(totalSeconds) {
    const seconds = Math.max(0, parseInt(totalSeconds || 0, 10));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}h ${m}m ${s}s`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
  }

  function formatTime(unixTs) {
    if (!unixTs) return '—';
    const d = new Date(unixTs * 1000);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  function setMessage(msg, type) {
    $('#tf-dashboard-message').removeClass('is-success is-error').addClass(type === 'error' ? 'is-error' : 'is-success').text(msg || '');
  }

  function fetchDashboard() {
    return $.get(tfDashboard.restUrl);
  }

  function ajaxAction(action, extra) {
    return $.post(tfDashboard.ajaxUrl, Object.assign({ action: action, nonce: tfDashboard.nonce }, extra || {}));
  }

  function renderQuickSelectors(data) {
    const projectSelect = $('#tf-quick-project');
    const taskSelect = $('#tf-quick-task');

    projectSelect.empty();
    taskSelect.empty();

    projectSelect.append('<option value="">Select project</option>');
    data.projects.forEach((p) => {
      projectSelect.append(`<option value="${p.id}">${p.title}</option>`);
    });

    const selectedProjectId = parseInt(projectSelect.val(), 10) || 0;
    renderTaskOptions(selectedProjectId || (data.projects[0] ? data.projects[0].id : 0));
  }

  function renderTaskOptions(projectId) {
    const taskSelect = $('#tf-quick-task');
    const data = state.data;
    taskSelect.empty();
    taskSelect.append('<option value="">Select task</option>');
    if (!data) return;

    data.tasks.filter((t) => parseInt(t.project_id, 10) === parseInt(projectId, 10)).forEach((t) => {
      taskSelect.append(`<option value="${t.id}">${t.title}</option>`);
    });
  }

  function renderProjects(data) {
    const body = $('#tf-projects-body');
    body.empty();
    if (!data.projects.length) {
      body.append('<tr><td colspan="3">No projects found.</td></tr>');
      return;
    }

    data.projects.forEach((p) => {
      body.append(`<tr><td>${p.title}</td><td>${p.task_count}</td><td>${formatDuration(p.total_time)}</td></tr>`);
    });
  }

  function renderTasks(data) {
    const body = $('#tf-tasks-body');
    body.empty();
    if (!data.tasks.length) {
      body.append('<tr><td colspan="4">No tasks found.</td></tr>');
      return;
    }

    data.tasks.slice(0, 10).forEach((t) => {
      body.append(`<tr>
        <td>${t.title}</td>
        <td>${t.project || '—'}</td>
        <td>${formatDuration(t.total_time)}</td>
        <td><button class="button tf-task-start" data-task-id="${t.id}">Start</button></td>
      </tr>`);
    });
  }

  function renderLogs(data) {
    const body = $('#tf-logs-body');
    body.empty();
    if (!data.logs.length) {
      body.append('<tr><td colspan="4">No logs yet.</td></tr>');
      return;
    }

    data.logs.forEach((log) => {
      body.append(`<tr><td>${log.project || '—'}</td><td>${log.task || '—'}</td><td>${formatDuration(log.duration)}</td><td>${log.type}</td></tr>`);
    });
  }

  function renderStats(data) {
    $('#tf-stat-projects').text(data.total_projects);
    $('#tf-stat-tasks').text(data.total_tasks);
    $('#tf-stat-total-time').text(formatDuration(data.total_time));
    $('#tf-stat-today-time').text(formatDuration(data.today_time));
  }

  function renderActive(data) {
    $('#tf-active-task').text(data.active_task ? data.active_task.title : '—');
    $('#tf-active-project').text(data.active_project ? data.active_project.title : '—');
    $('#tf-active-start').text(formatTime(data.timer_start));

    const hasActive = !!data.active_task;
    $('#tf-dashboard-start').prop('disabled', hasActive);
    $('#tf-dashboard-stop').prop('disabled', !hasActive);
    $('#tf-dashboard-add-time').prop('disabled', !hasActive);
  }

  function renderAll(data) {
    state.data = data;
    renderStats(data);
    renderActive(data);
    renderQuickSelectors(data);
    renderProjects(data);
    renderTasks(data);
    renderLogs(data);

    if (data.projects.length && !$('#tf-quick-project').val()) {
      $('#tf-quick-project').val(String(data.projects[0].id));
      renderTaskOptions(data.projects[0].id);
    }
  }

  function loadDashboard() {
    fetchDashboard().done(function (data) {
      renderAll(data);
    }).fail(function () {
      setMessage('Failed to load dashboard data.', 'error');
    });
  }

  $('#tf-quick-project').on('change', function () {
    renderTaskOptions($(this).val());
  });

  $('#tf-dashboard-start').on('click', function () {
    const taskId = parseInt($('#tf-quick-task').val(), 10);
    if (!taskId) {
      setMessage('Please select a project and task first.', 'error');
      return;
    }

    ajaxAction('tf_start_timer', { task_id: taskId }).done(function (resp) {
      if (!resp.success) {
        setMessage(resp.data.message || 'Unable to start timer.', 'error');
        return;
      }
      setMessage(resp.data.message, 'success');
      loadDashboard();
    }).fail(function () {
      setMessage('Request failed.', 'error');
    });
  });

  $('#tf-dashboard-stop').on('click', function () {
    ajaxAction('tf_stop_timer').done(function (resp) {
      if (!resp.success) {
        setMessage(resp.data.message || 'Unable to stop timer.', 'error');
        return;
      }
      setMessage(resp.data.message, 'success');
      loadDashboard();
    }).fail(function () {
      setMessage('Request failed.', 'error');
    });
  });

  $('#tf-dashboard-add-time').on('click', function () {
    const minutes = parseInt($('#tf-dashboard-manual-minutes').val(), 10);
    if (!minutes || minutes < 1) {
      setMessage('Enter minutes greater than 0.', 'error');
      return;
    }

    const taskId = state.data && state.data.active_task ? parseInt(state.data.active_task.id, 10) : 0;
    if (!taskId) {
      setMessage('No active task to add manual time to.', 'error');
      return;
    }

    ajaxAction('tf_add_manual_time', { task_id: taskId, minutes: minutes }).done(function (resp) {
      if (!resp.success) {
        setMessage(resp.data.message || 'Unable to add manual time.', 'error');
        return;
      }
      $('#tf-dashboard-manual-minutes').val('');
      setMessage(resp.data.message, 'success');
      loadDashboard();
    }).fail(function () {
      setMessage('Request failed.', 'error');
    });
  });

  $(document).on('click', '.tf-task-start', function () {
    const taskId = parseInt($(this).data('task-id'), 10);
    if (!taskId) return;

    ajaxAction('tf_start_timer', { task_id: taskId }).done(function (resp) {
      if (!resp.success) {
        setMessage(resp.data.message || 'Unable to start timer.', 'error');
        return;
      }
      setMessage(resp.data.message, 'success');
      loadDashboard();
    }).fail(function () {
      setMessage('Request failed.', 'error');
    });
  });

  loadDashboard();
});
