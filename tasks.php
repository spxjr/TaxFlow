<?php
require_once 'db.php';

/* ══════════════════════════════════════════
   HELPER FUNCTIONS (defined first, before any include)
══════════════════════════════════════════ */
function task_due_class(string $due): string {
    if (!$due) return 'on-track';
    $days = (strtotime($due) - strtotime(date('Y-m-d'))) / 86400;
    if ($days < 0) return 'overdue';
    if ($days < 1) return 'due-soon';
    if ($days <= 3) return 'due-soon';
    return 'on-track';
}

function task_due_label(string $due): string {
    if (!$due) return '';
    $days = (strtotime($due) - strtotime(date('Y-m-d'))) / 86400;
    if ($days < -1) return 'Overdue ' . abs((int)$days) . 'd';
    if ($days < 0)  return 'Was yesterday';
    if ($days < 1)  return 'Due today';
    if ($days < 2)  return 'Due tomorrow';
    if ($days <= 7) return 'Due ' . date('D', strtotime($due));
    return 'Due ' . date('M j', strtotime($due));
}

/* ══════════════════════════════════════════
   CHECK TASKS TABLE EXISTS
══════════════════════════════════════════ */
$tasks_table_exists = false;
try {
    db()->query('SELECT 1 FROM tasks LIMIT 1');
    $tasks_table_exists = true;
} catch (Exception $e) {
    $tasks_table_exists = false;
}

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS (only if table exists)
══════════════════════════════════════════ */
$action = $_POST['action'] ?? '';

if ($tasks_table_exists) {

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            db()->prepare(
                'INSERT INTO tasks (title, notes, client_id, return_id, assignee_id, priority, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $title,
                trim($_POST['notes'] ?? '') ?: null,
                !empty($_POST['client_id'])   ? (int)$_POST['client_id']   : null,
                !empty($_POST['return_id'])   ? (int)$_POST['return_id']   : null,
                !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null,
                $_POST['priority'] ?? 'medium',
                !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            ]);
        }
        header('Location: tasks.php?msg=added'); exit;
    }

    if ($action === 'toggle' && !empty($_POST['id'])) {
        $cur = db()->prepare('SELECT status FROM tasks WHERE id=?');
        $cur->execute([(int)$_POST['id']]);
        $s = $cur->fetchColumn();
        $new = ($s === 'done') ? 'open' : 'done';
        $done_at = ($new === 'done') ? date('Y-m-d H:i:s') : null;
        db()->prepare('UPDATE tasks SET status=?, done_at=?, updated_at=NOW() WHERE id=?')
            ->execute([$new, $done_at, (int)$_POST['id']]);
        header('Location: tasks.php'); exit;
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        db()->prepare('UPDATE tasks SET status="cancelled" WHERE id=?')->execute([(int)$_POST['id']]);
        header('Location: tasks.php?msg=deleted'); exit;
    }
}

/* ══════════════════════════════════════════
   FILTERS
══════════════════════════════════════════ */
$filter_status   = $_GET['status']   ?? 'open';
$filter_priority = $_GET['priority'] ?? '';
$filter_assignee = (int)($_GET['assignee'] ?? 0);
$filter_client   = (int)($_GET['client']   ?? 0);
$search          = trim($_GET['q'] ?? '');

/* ══════════════════════════════════════════
   LOAD DATA
══════════════════════════════════════════ */
if (!$tasks_table_exists) {
    $tasks       = [];
    $done_tasks  = [];
    $groups      = ['urgent'=>[],'high'=>[],'medium'=>[],'low'=>[]];
    $stats       = ['open_count'=>0,'done_count'=>0,'urgent_count'=>0,'overdue_count'=>0,'due_today_count'=>0];
    $staff_list  = [];
    $clients_list= [];
    $blocked     = [];
} else {
    /* Build WHERE clause */
    $where  = ["t.status != 'cancelled'"];
    $params = [];

    if ($filter_status === 'open') {
        $where[] = "t.status IN('open','in_progress')";
    } elseif ($filter_status === 'done') {
        $where[] = "t.status = 'done'";
    }

    if ($filter_priority !== '') { $where[] = 't.priority = ?'; $params[] = $filter_priority; }
    if ($filter_assignee > 0)    { $where[] = 't.assignee_id = ?'; $params[] = $filter_assignee; }
    if ($filter_client > 0)      { $where[] = 't.client_id = ?'; $params[] = $filter_client; }
    if ($search !== '')          { $where[] = 't.title LIKE ?'; $params[] = '%' . $search . '%'; }

    $wsql = implode(' AND ', $where);

    $stmt = db()->prepare("
        SELECT t.*,
               c.display_name  AS client_name,
               c.avatar_color,
               c.initials      AS client_init,
               r.form_type,
               s.name          AS assignee_name,
               s.initials      AS assignee_init,
               s.color         AS assignee_color
        FROM tasks t
        LEFT JOIN clients c     ON c.id = t.client_id
        LEFT JOIN tax_returns r ON r.id = t.return_id
        LEFT JOIN staff s       ON s.id = t.assignee_id
        WHERE $wsql
        ORDER BY
            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
            t.due_date ASC,
            t.created_at DESC
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    /* Group by priority */
    $groups     = ['urgent'=>[],'high'=>[],'medium'=>[],'low'=>[]];
    $done_tasks = [];
    foreach ($tasks as $t) {
        if ($t['status'] === 'done') {
            $done_tasks[] = $t;
        } else {
            $pri = $t['priority'];
            if (isset($groups[$pri])) $groups[$pri][] = $t;
            else $groups['medium'][] = $t;
        }
    }

    /* Stats */
    $stats = db()->query("
        SELECT
            SUM(status IN('open','in_progress'))                                          AS open_count,
            SUM(status = 'done')                                                          AS done_count,
            SUM(status IN('open','in_progress') AND priority IN('urgent','high'))         AS urgent_count,
            SUM(status IN('open','in_progress') AND due_date < CURDATE())                 AS overdue_count,
            SUM(status IN('open','in_progress') AND due_date = CURDATE())                 AS due_today_count
        FROM tasks
        WHERE status != 'cancelled'
    ")->fetch();

    $staff_list   = db()->query('SELECT * FROM staff WHERE active=1 ORDER BY name')->fetchAll();
    $clients_list = db()->query("SELECT id, display_name FROM clients WHERE status='active' ORDER BY display_name")->fetchAll();

    $blocked = db()->query("
        SELECT ch.label, c.display_name AS client_name, r.form_type, r.id AS return_id
        FROM return_checklist ch
        JOIN tax_returns r ON r.id = ch.return_id
        JOIN clients c     ON c.id = r.client_id
        WHERE ch.status = 'blocked' AND r.tax_year = 2025
        ORDER BY c.display_name
        LIMIT 5
    ")->fetchAll();
}

/* ══════════════════════════════════════════
   RENDER
══════════════════════════════════════════ */
$page_title = 'Tasks';
$active_nav = 'tasks';
$topbar_btn  = '+ New Task';
$topbar_href = '#';
include 'header.php';
?>

<?php if (!$tasks_table_exists): ?>
<div style="background:#fff3d4;border:1px solid #c8922a;color:#7a5800;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;line-height:1.7;">
  <strong>&#9888; One-time setup required</strong><br>
  The <code>tasks</code> table has not been created yet. Please run
  <strong>taxflow_tasks_table.sql</strong> in phpMyAdmin (SQL tab), then refresh this page.
</div>
<?php endif; ?>

<?php if (!empty($_GET['msg'])): ?>
<div style="background:#daeade;border:1px solid #3d5a47;color:#2a5c35;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;">
  <?php echo $_GET['msg']==='added' ? 'Task added.' : 'Task removed.'; ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <div class="page-heading">Tasks</div>
    <div class="page-heading-sub">
      <?php echo (int)$stats['open_count']; ?> open &middot;
      <?php echo (int)$stats['overdue_count']; ?> overdue &middot;
      <?php echo (int)$stats['done_count']; ?> completed
    </div>
  </div>
  <div class="page-actions">
    <button class="topbar-btn btn-primary" onclick="document.getElementById('modal-add-task').style.display='flex'">+ New Task</button>
  </div>
</div>

<!-- Stat strip -->
<div class="stat-strip" style="margin-bottom:20px;">
  <div class="stat-strip-item">
    <div class="stat-strip-val"><?php echo (int)$stats['open_count']; ?></div>
    <div class="stat-strip-lbl">Open Tasks</div>
  </div>
  <div class="stat-strip-item">
    <div class="stat-strip-val" style="color:#b84c2e;"><?php echo (int)$stats['overdue_count']; ?></div>
    <div class="stat-strip-lbl">Overdue</div>
    <?php if ((int)$stats['overdue_count'] > 0): ?>
    <div class="stat-strip-change down">&#9650; Needs attention</div>
    <?php endif; ?>
  </div>
  <div class="stat-strip-item">
    <div class="stat-strip-val" style="color:#c8922a;"><?php echo (int)$stats['due_today_count']; ?></div>
    <div class="stat-strip-lbl">Due Today</div>
  </div>
  <div class="stat-strip-item">
    <div class="stat-strip-val" style="color:#4e7260;"><?php echo (int)$stats['done_count']; ?></div>
    <div class="stat-strip-lbl">Completed</div>
    <div class="stat-strip-change up">&#8593; This season</div>
  </div>
</div>

<!-- Filters -->
<form method="get" action="tasks.php">
<div class="toolbar" style="margin-bottom:20px;">
  <div class="toolbar-search">
    <svg width="13" height="13" viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
    <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Search tasks...">
  </div>
  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="open"  <?php echo $filter_status==='open' ?'selected':''; ?>>Open Tasks</option>
    <option value="done"  <?php echo $filter_status==='done' ?'selected':''; ?>>Completed</option>
    <option value=""      <?php echo $filter_status===''     ?'selected':''; ?>>All Tasks</option>
  </select>
  <select class="filter-select" name="priority" onchange="this.form.submit()">
    <option value="">All Priorities</option>
    <option value="urgent" <?php echo $filter_priority==='urgent'?'selected':''; ?>>&#128308; Urgent</option>
    <option value="high"   <?php echo $filter_priority==='high'  ?'selected':''; ?>>&#128992; High</option>
    <option value="medium" <?php echo $filter_priority==='medium'?'selected':''; ?>>&#128994; Medium</option>
    <option value="low"    <?php echo $filter_priority==='low'   ?'selected':''; ?>>&#9898; Low</option>
  </select>
  <select class="filter-select" name="assignee" onchange="this.form.submit()">
    <option value="">All Assignees</option>
    <?php foreach ($staff_list as $st): ?>
    <option value="<?php echo $st['id']; ?>" <?php echo $filter_assignee===(int)$st['id']?'selected':''; ?>><?php echo h($st['name']); ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-outline" style="padding:7px 14px;">Filter</button>
  <?php if ($filter_status!=='open' || $filter_priority || $filter_assignee || $filter_client || $search): ?>
  <a href="tasks.php" class="btn-outline" style="padding:7px 14px;color:#b84c2e;">Clear</a>
  <?php endif; ?>
</div>
</form>

<!-- Quick-add task bar -->
<?php if ($tasks_table_exists): ?>
<form method="post" action="tasks.php" style="margin-bottom:20px;">
  <input type="hidden" name="action" value="add">
  <div class="add-task-form">
    <div class="task-card-check" style="border-color:#d4cec2;cursor:default;color:#8a8070;">+</div>
    <input class="add-task-input" name="title" placeholder="Add a task &mdash; press Enter to save..." required>
    <select name="priority" class="filter-select" style="font-size:11.5px;padding:5px 26px 5px 9px;">
      <option value="urgent">&#128308; Urgent</option>
      <option value="high">&#128992; High</option>
      <option value="medium" selected>&#128994; Medium</option>
      <option value="low">&#9898; Low</option>
    </select>
    <input type="date" name="due_date" style="padding:6px 10px;border:1px solid #d4cec2;border-radius:6px;font-size:12.5px;font-family:inherit;color:#0f1117;background:#fffdf8;outline:none;">
    <button type="submit" class="topbar-btn btn-primary" style="padding:7px 14px;font-size:12.5px;">Add</button>
  </div>
</form>
<?php endif; ?>

<!-- Tasks layout -->
<div class="tasks-layout">

  <!-- ── TASK LIST ── -->
  <div>
    <?php
    $priority_meta = [
      'urgent' => ['label'=>'Urgent',        'dot'=>'dot-urgent'],
      'high'   => ['label'=>'High Priority',  'dot'=>'dot-high'],
      'medium' => ['label'=>'Medium',         'dot'=>'dot-medium'],
      'low'    => ['label'=>'Low Priority',   'dot'=>'dot-low'],
    ];

    $any_open = false;
    foreach ($groups as $items) { if (!empty($items)) { $any_open = true; break; } }

    if (!$any_open && empty($done_tasks)):
    ?>
    <div class="panel" style="padding:48px;text-align:center;">
      <div style="font-size:32px;margin-bottom:12px;">&#9989;</div>
      <div style="font-family:'DM Serif Display',Georgia,serif;font-size:20px;color:#0f1117;margin-bottom:6px;">All caught up!</div>
      <div style="font-size:13px;color:#8a8070;">No tasks match your current filters.</div>
      <a href="tasks.php" style="display:inline-block;margin-top:14px;color:#c8922a;font-weight:600;font-size:13px;">View all tasks</a>
    </div>
    <?php else: ?>

    <?php foreach ($groups as $pri => $items):
      if (empty($items)) continue;
      $meta = $priority_meta[$pri] ?? ['label'=>ucfirst($pri),'dot'=>'dot-medium'];
    ?>
    <div class="task-group">
      <div class="task-group-header">
        <div class="task-priority-dot <?php echo h($meta['dot']); ?>"></div>
        <div class="task-group-title"><?php echo h($meta['label']); ?></div>
        <div class="task-group-count"><?php echo count($items); ?></div>
      </div>
      <div class="task-list">
        <?php foreach ($items as $task): ?>
        <div class="task-card">
          <form method="post" action="tasks.php" style="display:contents;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
            <button type="submit" class="task-card-check <?php echo $pri==='urgent'?'urgent':''; ?>" title="Mark done">&nbsp;</button>
          </form>
          <div class="task-card-body">
            <div class="task-card-title"><?php echo h($task['title']); ?></div>
            <?php if ($task['notes']): ?>
            <div style="font-size:12px;color:#8a8070;margin-top:3px;line-height:1.4;"><?php echo h(mb_substr($task['notes'],0,100)) . (mb_strlen($task['notes'])>100?'&hellip;':''); ?></div>
            <?php endif; ?>
            <div class="task-card-meta">
              <span class="priority-badge priority-<?php echo h($pri); ?>"><?php echo h(ucfirst($pri)); ?></span>
              <?php if ($task['client_name']): ?>
              <a href="client-detail.php?id=<?php echo (int)$task['client_id']; ?>" class="task-card-client" style="color:#c8922a;text-decoration:none;"><?php echo h($task['client_name']); ?></a>
              <?php endif; ?>
              <?php if ($task['form_type']): ?>
              <span class="form-badge" style="font-size:10px;padding:1px 6px;"><?php echo h($task['form_type']); ?></span>
              <?php endif; ?>
              <?php if ($task['due_date']): ?>
              <span class="task-card-due <?php echo task_due_class($task['due_date']); ?>"><?php echo task_due_label($task['due_date']); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="task-card-right">
            <?php if ($task['assignee_init']): ?>
            <div class="task-assignee-avatar" style="background:<?php echo h($task['assignee_color']??'#3d5a47'); ?>;" title="<?php echo h($task['assignee_name']??''); ?>"><?php echo h($task['assignee_init']); ?></div>
            <?php endif; ?>
            <form method="post" action="tasks.php" style="display:contents;" onsubmit="return confirm('Remove this task?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
              <button type="submit" style="background:none;border:none;cursor:pointer;color:#d4cec2;font-size:14px;line-height:1;padding:0;" title="Remove">&#10005;</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Completed tasks -->
    <?php if (!empty($done_tasks) && ($filter_status === '' || $filter_status === 'done')): ?>
    <div class="task-group">
      <div class="task-group-header" onclick="var d=document.getElementById('done-list');d.style.display=d.style.display==='none'?'flex':'none';" style="cursor:pointer;">
        <div class="task-priority-dot" style="background:#3d5a47;"></div>
        <div class="task-group-title">Completed</div>
        <div class="task-group-count"><?php echo count($done_tasks); ?></div>
        <span style="font-family:'DM Mono','Courier New',monospace;font-size:10px;color:#8a8070;">toggle</span>
      </div>
      <div class="task-list" id="done-list" style="display:<?php echo $filter_status==='done'?'flex':'none'; ?>;">
        <?php foreach ($done_tasks as $task): ?>
        <div class="task-card done-card">
          <form method="post" action="tasks.php" style="display:contents;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
            <button type="submit" class="task-card-check done" title="Mark open">&#10003;</button>
          </form>
          <div class="task-card-body">
            <div class="task-card-title done"><?php echo h($task['title']); ?></div>
            <div class="task-card-meta">
              <?php if ($task['client_name']): ?><span class="task-card-client"><?php echo h($task['client_name']); ?></span><?php endif; ?>
              <?php if ($task['done_at']): ?><span class="task-card-due on-track">Done <?php echo date('M j', strtotime($task['done_at'])); ?></span><?php endif; ?>
            </div>
          </div>
          <div class="task-card-right">
            <?php if ($task['assignee_init']): ?>
            <div class="task-assignee-avatar" style="background:<?php echo h($task['assignee_color']??'#3d5a47'); ?>;"><?php echo h($task['assignee_init']); ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; /* end any_open check */ ?>
  </div><!-- /.task-list-col -->

  <!-- ── SIDEBAR ── -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Summary -->
    <div class="panel">
      <div class="panel-header"><div class="panel-title">Summary</div></div>
      <div>
        <div class="task-sidebar-stat">
          <div class="task-sidebar-label">Open</div>
          <div class="task-sidebar-val"><?php echo (int)$stats['open_count']; ?></div>
        </div>
        <div class="task-sidebar-stat">
          <div class="task-sidebar-label" style="color:#b84c2e;">Overdue</div>
          <div class="task-sidebar-val" style="color:#b84c2e;"><?php echo (int)$stats['overdue_count']; ?></div>
        </div>
        <div class="task-sidebar-stat">
          <div class="task-sidebar-label" style="color:#c8922a;">Due Today</div>
          <div class="task-sidebar-val" style="color:#c8922a;"><?php echo (int)$stats['due_today_count']; ?></div>
        </div>
        <div class="task-sidebar-stat">
          <div class="task-sidebar-label">Urgent &amp; High</div>
          <div class="task-sidebar-val" style="color:#b84c2e;"><?php echo (int)$stats['urgent_count']; ?></div>
        </div>
        <div class="task-sidebar-stat">
          <div class="task-sidebar-label" style="color:#4e7260;">Completed</div>
          <div class="task-sidebar-val" style="color:#4e7260;"><?php echo (int)$stats['done_count']; ?></div>
        </div>
      </div>
    </div>

    <!-- Blocked checklist items -->
    <?php if (!empty($blocked)): ?>
    <div class="panel">
      <div class="panel-header">
        <div><div class="panel-title">Blocked Items</div><div class="panel-subtitle">RETURN CHECKLIST</div></div>
      </div>
      <div class="doc-list">
        <?php foreach ($blocked as $b): ?>
        <a href="return-detail.php?id=<?php echo (int)$b['return_id']; ?>" class="doc-row" style="text-decoration:none;">
          <div class="doc-icon misc" style="background:#f5dbd4;">&#9888;</div>
          <div class="doc-info">
            <div class="doc-name" style="color:#b84c2e;"><?php echo h($b['label']); ?></div>
            <div class="doc-meta"><?php echo h($b['client_name']); ?> &middot; <?php echo h($b['form_type']); ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- By assignee -->
    <?php if (!empty($staff_list)): ?>
    <div class="panel">
      <div class="panel-header"><div class="panel-title">By Assignee</div></div>
      <div>
        <?php foreach ($staff_list as $st):
          $cnt_stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_id=? AND status IN('open','in_progress')");
          $cnt_stmt->execute([(int)$st['id']]);
          $cnt = (int)$cnt_stmt->fetchColumn();
        ?>
        <a href="tasks.php?assignee=<?php echo $st['id']; ?>" class="task-sidebar-stat" style="text-decoration:none;display:flex;align-items:center;">
          <div style="display:flex;align-items:center;gap:8px;flex:1;">
            <div class="task-assignee-avatar" style="background:<?php echo h($st['color']); ?>;"><?php echo h($st['initials']); ?></div>
            <div class="task-sidebar-label" style="color:#0f1117;"><?php echo h($st['name']); ?></div>
          </div>
          <div class="task-group-count"><?php echo $cnt; ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.sidebar -->
</div><!-- /.tasks-layout -->

<!-- NEW TASK MODAL -->
<div id="modal-add-task" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fffdf8;border-radius:12px;width:100%;max-width:520px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,0.2);">
    <div style="padding:18px 22px;border-bottom:1px solid #d4cec2;display:flex;align-items:center;justify-content:space-between;">
      <span style="font-family:'DM Serif Display',Georgia,serif;font-size:18px;">New Task</span>
      <button onclick="document.getElementById('modal-add-task').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#8a8070;line-height:1;">&times;</button>
    </div>
    <form method="post" action="tasks.php" style="padding:20px 22px;display:flex;flex-direction:column;gap:13px;">
      <input type="hidden" name="action" value="add">
      <div>
        <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Title *</label>
        <input name="title" required placeholder="What needs to be done?" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:14px;font-family:inherit;outline:none;background:#fffdf8;">
      </div>
      <div>
        <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Notes</label>
        <textarea name="notes" rows="2" placeholder="Optional details..." style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;resize:vertical;"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Priority</label>
          <select name="priority" class="filter-select" style="width:100%;">
            <option value="urgent">&#128308; Urgent</option>
            <option value="high">&#128992; High</option>
            <option value="medium" selected>&#128994; Medium</option>
            <option value="low">&#9898; Low</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Due Date</label>
          <input name="due_date" type="date" style="width:100%;padding:8px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Assignee</label>
          <select name="assignee_id" class="filter-select" style="width:100%;">
            <option value="">— None —</option>
            <?php foreach ($staff_list as $st): ?><option value="<?php echo $st['id']; ?>"><?php echo h($st['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Client</label>
          <select name="client_id" class="filter-select" style="width:100%;">
            <option value="">— None —</option>
            <?php foreach ($clients_list as $cl): ?><option value="<?php echo $cl['id']; ?>"><?php echo h($cl['display_name']); ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:4px;">
        <button type="button" onclick="document.getElementById('modal-add-task').style.display='none'" class="btn-outline">Cancel</button>
        <button type="submit" class="topbar-btn btn-primary">Create Task</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
