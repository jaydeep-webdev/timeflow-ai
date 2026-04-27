jQuery(function ($) {
  window.tfData = { projects: [], tasks: [], logs: [] };

  const projectSelect = document.getElementById('tf-quick-project');
  const taskSelect = document.getElementById('tf-quick-task');
  const startBtn = document.getElementById('tf-dashboard-start');
  const stopBtn = document.getElementById('tf-dashboard-stop');

  function formatDuration(seconds) {
    seconds = parseInt(seconds || 0, 10);
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}h ${m}m ${s}s`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
  }

  function setMessage(msg, type) {
    $('#tf-dashboard-message').removeClass('is-success is-error').addClass(type === 'error' ? 'is-error' : 'is-success').text(msg || '');
  }

  function updateStats(data) {
    $('#tf-stat-projects').text(data.total_projects || 0);
    $('#tf-stat-tasks').text(data.total_tasks || 0);
    $('#tf-stat-total-time').text(formatDuration(data.total_time || 0));
    $('#tf-stat-today-time').text(formatDuration(data.today_time || 0));

    $('#tf-active-task').text(data.active_task ? data.active_task.title : '—');
    $('#tf-active-project').text(data.active_project ? data.active_project.title : '—');
    $('#tf-active-start').text(data.timer_start ? new Date(data.timer_start * 1000).toLocaleString() : '—');
  }

  function populateProjects(projects) {
    projectSelect.innerHTML = '<option value="">Select Project</option>';
    projects.forEach((p) => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.text = p.title;
      projectSelect.appendChild(opt);
    });
  }

  function renderProjectsTable(projects) {
    const body = $('#tf-projects-body');
    body.empty();
    if (!projects.length) {
      body.append('<tr><td colspan="3">Create a project first</td></tr>');
      return;
    }
    projects.forEach((p) => {
      body.append(`<tr><td>${p.title}</td><td>${p.task_count || 0}</td><td>${formatDuration(p.total_time || 0)}</td></tr>`);
    });
  }

  function renderTasksTable(tasks) {
    const body = $('#tf-tasks-body');
    body.empty();
    if (!tasks.length) {
      body.append('<tr><td colspan="4">No tasks available</td></tr>');
      return;
    }
    tasks.slice(0, 10).forEach((t) => {
      body.append(`<tr><td>${t.title}</td><td>${t.project || '—'}</td><td>${formatDuration(t.total_time || 0)}</td><td><button class="button tf-task-start" data-task-id="${t.id}">Start</button></td></tr>`);
    });
  }

  function renderLogs(logs) {
    const body = $('#tf-logs-body');
    body.empty();
    if (!logs.length) {
      body.append('<tr><td colspan="4">No logs yet.</td></tr>');
      return;
    }
    logs.forEach((l) => {
      body.append(`<tr><td>${l.project}</td><td>${l.task}</td><td>${formatDuration(l.duration || 0)}</td><td>${l.type}</td></tr>`);
    });
  }

  function loadDashboard() {
    fetch('/wp-json/timeflow/v1/dashboard')
      .then((res) => res.json())
      .then((data) => {
        window.tfData = data;
        populateProjects(data.projects || []);
        updateStats(data);
        renderProjectsTable(data.projects || []);
        renderTasksTable(data.tasks || []);
        renderLogs(data.logs || []);
        setMessage('', 'success');
      })
      .catch(() => {
        setMessage('Failed to load dashboard data', 'error');
      });
  }

  projectSelect.addEventListener('change', function () {
    const projectId = this.value;
    taskSelect.innerHTML = '<option value="">Select Task</option>';

    const tasks = (window.tfData.tasks || []).filter((t) => String(t.project_id) === String(projectId));
    if (tasks.length === 0) {
      const opt = document.createElement('option');
      opt.text = 'No tasks found';
      taskSelect.appendChild(opt);
      return;
    }

    tasks.forEach((t) => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.text = t.title;
      taskSelect.appendChild(opt);
    });
  });

  function callAction(payload) {
    return fetch('/wp-json/timeflow/v1/action', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then((res) => res.json());
  }

  startBtn.onclick = function () {
    const task = taskSelect.value;
    const project = projectSelect.value;

    if (!task) {
      alert('Select task');
      return;
    }

    callAction({ action: 'START_TIMER', task_id: task, project_id: project }).then(() => loadDashboard());
  };

  stopBtn.onclick = function () {
    callAction({ action: 'STOP_TIMER' }).then(() => loadDashboard());
  };

  $('#tf-dashboard-add-time').on('click', function () {
    const minutes = parseInt($('#tf-dashboard-manual-minutes').val(), 10) || 0;
    const activeTask = window.tfData.active_task ? window.tfData.active_task.id : 0;

    if (!minutes || minutes < 1 || !activeTask) {
      return;
    }

    callAction({ action: 'ADD_TIME', task_id: activeTask, minutes: minutes }).then(() => {
      $('#tf-dashboard-manual-minutes').val('');
      loadDashboard();
    });
  });

  $(document).on('click', '.tf-task-start', function () {
    const taskId = $(this).data('task-id');
    callAction({ action: 'START_TIMER', task_id: taskId }).then(() => loadDashboard());
  });

  loadDashboard();
});
