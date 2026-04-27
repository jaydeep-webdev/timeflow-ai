jQuery(function ($) {
  const state = { data: null };
  const projectSelect = document.getElementById('tf-quick-project');
  const taskSelect = document.getElementById('tf-quick-task');
  const startBtn = document.getElementById('tf-dashboard-start');
  const stopBtn = document.getElementById('tf-dashboard-stop');

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

  function postAction(payload) {
    return fetch(tfDashboard.actionRestUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': tfDashboard.restNonce || ''
      },
      body: JSON.stringify(payload)
    }).then((res) => res.json());
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
      body.append(`<tr><td>${t.title}</td><td>${t.project || 'Standalone'}</td><td>${formatDuration(t.total_time)}</td><td><button class="button tf-task-start" data-task-id="${t.id}">Start</button></td></tr>`);
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
    projectSelect.innerHTML = '';

    if (!data.projects.length) {
      projectSelect.innerHTML = '<option value="">Create a project first</option>';
      projectSelect.disabled = true;
      taskSelect.innerHTML = '<option value="">Select project first</option>';
      taskSelect.disabled = true;
      return;
    }

    projectSelect.innerHTML = '<option value="">Select project first</option>';
    data.projects.forEach((project) => {
      const opt = document.createElement('option');
      opt.value = String(project.id);
      opt.textContent = project.title;
      projectSelect.appendChild(opt);
    });
    projectSelect.disabled = false;
    taskSelect.innerHTML = '<option value="">Select project first</option>';
    taskSelect.disabled = true;
  }

  function bindTaskDropdownForProject(projectId) {
    taskSelect.innerHTML = '<option value="">Select Task</option>';

    if (!projectId) {
      taskSelect.disabled = true;
      taskSelect.innerHTML = '<option value="">Select project first</option>';
      return;
    }

    const filtered = state.data.tasks.filter((t) => String(t.project_id) === String(projectId));

    if (!filtered.length) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.text = 'No tasks found';
      taskSelect.appendChild(opt);
      taskSelect.disabled = true;
      setMessage('No tasks found for this project', 'error');
      return;
    }

    filtered.forEach((task) => {
      const opt = document.createElement('option');
      opt.value = String(task.id);
      opt.text = task.title;
      taskSelect.appendChild(opt);
    });

    taskSelect.disabled = false;
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

  projectSelect.addEventListener('change', function () {
    bindTaskDropdownForProject(this.value);
  });

  startBtn.addEventListener('click', function () {
    const taskId = taskSelect.value;
    const projectId = projectSelect.value;

    if (!taskId) {
      setMessage('Please select a project and task', 'error');
      return;
    }

    postAction({ action: 'START_TIMER', task_id: taskId, project_id: projectId }).then((res) => {
      if (!res.success) {
        setMessage(res.message || 'Unable to start timer.', 'error');
        return;
      }
      loadDashboard();
    });
  });

  stopBtn.addEventListener('click', function () {
    postAction({ action: 'STOP_TIMER' }).then((res) => {
      if (!res.success) {
        setMessage(res.message || 'Unable to stop timer.', 'error');
        return;
      }
      loadDashboard();
    });
  });

  $('#tf-dashboard-add-time').on('click', function () {
    const minutes = parseInt($('#tf-dashboard-manual-minutes').val(), 10);
    const activeTaskId = state.data && state.data.active_task ? parseInt(state.data.active_task.id, 10) : 0;

    if (!minutes || minutes < 1) {
      setMessage('Enter minutes greater than 0.', 'error');
      return;
    }

    if (!activeTaskId) {
      setMessage('Please select a project and task', 'error');
      return;
    }

    postAction({ action: 'ADD_TIME', task_id: activeTaskId, minutes: minutes }).then((res) => {
      if (!res.success) {
        setMessage(res.message || 'Unable to add time.', 'error');
        return;
      }
      $('#tf-dashboard-manual-minutes').val('');
      loadDashboard();
    });
  });

  $(document).on('click', '.tf-task-start', function () {
    const taskId = parseInt($(this).data('task-id'), 10) || 0;
    if (!taskId) {
      setMessage('Please select a project and task', 'error');
      return;
    }

    postAction({ action: 'START_TIMER', task_id: taskId }).then((res) => {
      if (!res.success) {
        setMessage(res.message || 'Unable to start timer.', 'error');
        return;
      }
      loadDashboard();
    });
  });

  loadDashboard();
});
