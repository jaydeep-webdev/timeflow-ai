jQuery(function ($) {
  const state = { data: null };

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

  function fetchTasksByProject(projectId) {
    return $.get(tfDashboard.tasksRestUrl, { project_id: projectId });
  }

  function ajaxAction(action, extra) {
    return $.post(tfDashboard.ajaxUrl, Object.assign({ action: action, nonce: tfDashboard.nonce }, extra || {}));
  }

  function renderProjects(data) {
    const body = $('#tf-projects-body');
    body.empty();
    if (!data.projects.length) {
      body.append('<tr><td colspan="3">Create a project first</td></tr>');
      return;
    }

    data.projects.forEach((p) => {
      body.append(`<tr><td>${p.title}</td><td>${p.task_count}</td><td>${formatDuration(p.total_time)}</td></tr>`);
    });
  }

  function renderTasksTable(data) {
    const body = $('#tf-tasks-body');
    body.empty();
    if (!data.tasks.length) {
      body.append('<tr><td colspan="4">No tasks available</td></tr>');
      return;
    }

    data.tasks.slice(0, 10).forEach((t) => {
      body.append(`<tr>
        <td>${t.title}</td>
        <td>${t.project || 'Standalone'}</td>
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
      body.append(`<tr><td>${log.project || 'Standalone'}</td><td>${log.task || '—'}</td><td>${formatDuration(log.duration)}</td><td>${log.type}</td></tr>`);
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
    $('#tf-active-project').text(data.active_project ? data.active_project.title : 'Standalone');
    $('#tf-active-start').text(formatTime(data.timer_start));

    const hasActive = !!data.active_task;
    $('#tf-dashboard-start').prop('disabled', hasActive);
    $('#tf-dashboard-stop').prop('disabled', !hasActive);
    $('#tf-dashboard-add-time').prop('disabled', !hasActive);
  }

  function renderProjectSelector(data) {
    const projectSelect = $('#tf-quick-project');
    projectSelect.empty();

    if (!data.projects.length) {
      projectSelect.append('<option value="">Create a project first</option>').prop('disabled', true);
      $('#tf-quick-task').empty().append('<option value="">Select project first</option>').prop('disabled', true);
      return;
    }

    projectSelect.append('<option value="">Select project first</option>');
    data.projects.forEach((p) => {
      projectSelect.append(`<option value="${p.id}">${p.title}</option>`);
    });
    projectSelect.prop('disabled', false);

    $('#tf-quick-task').empty().append('<option value="">Select project first</option>').prop('disabled', true);
  }

  function renderTasksDropdown(tasks) {
    const taskSelect = $('#tf-quick-task');
    taskSelect.empty();

    if (!tasks.length) {
      taskSelect.append('<option value="">No tasks found for this project</option>').prop('disabled', true);
      setMessage('No tasks found for this project', 'error');
      return;
    }

    taskSelect.append('<option value="">Select task</option>');
    tasks.forEach((t) => {
      taskSelect.append(`<option value="${t.id}">${t.title}</option>`);
    });
    taskSelect.prop('disabled', false);
  }

  function loadProjectTasks(projectId) {
    const taskSelect = $('#tf-quick-task');
    taskSelect.empty();

    if (!projectId) {
      taskSelect.append('<option value="">Select project first</option>').prop('disabled', true);
      return;
    }

    fetchTasksByProject(projectId).done(function (tasks) {
      renderTasksDropdown(tasks || []);
    }).fail(function () {
      taskSelect.append('<option value="">No tasks found for this project</option>').prop('disabled', true);
      setMessage('No tasks found for this project', 'error');
    });
  }

  function renderAll(data) {
    state.data = data;
    renderStats(data);
    renderActive(data);
    renderProjectSelector(data);
    renderProjects(data);
    renderTasksTable(data);
    renderLogs(data);
  }

  function loadDashboard() {
    fetchDashboard().done(function (data) {
      renderAll(data);
    }).fail(function () {
      setMessage('Failed to load dashboard data.', 'error');
    });
  }

  $('#tf-quick-project').on('change', function () {
    const projectId = parseInt($(this).val(), 10) || 0;
    loadProjectTasks(projectId);
  });

  $('#tf-dashboard-start').on('click', function () {
    const projectId = parseInt($('#tf-quick-project').val(), 10) || 0;
    const taskId = parseInt($('#tf-quick-task').val(), 10) || 0;

    if (!projectId || !taskId) {
      setMessage('Please select a project and task', 'error');
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
      setMessage('Please select a project and task', 'error');
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
    const taskId = parseInt($(this).data('task-id'), 10) || 0;
    if (!taskId) {
      setMessage('Please select a project and task', 'error');
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

  loadDashboard();
});
