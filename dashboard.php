<?php
require_once 'db.php';

/* ── KPI stats ── */
$kpi = db()->query("
  SELECT
    (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE YEAR(issued_date)=2026)             AS revenue,
    (SELECT COUNT(*) FROM clients WHERE status='active')                                     AS clients,
    (SELECT COUNT(*) FROM documents WHERE status='missing')                                  AS missing_docs,
    (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025 AND status='filed')                AS filed_week,
    (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025)                                   AS total_returns,
    (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025 AND status='filed')                AS total_filed,
    (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025 AND status NOT IN('filed','archived')) AS total_pending
")->fetch();

/* ── Recent clients (with 2025 return status) ── */
$recent = db()->query("
  SELECT c.*, s.initials AS ainit, s.color AS acolor,
         r.status AS rstatus, r.refund_amount, r.form_type, r.id AS rid
  FROM clients c
  LEFT JOIN staff s ON s.id = c.assignee_id
  LEFT JOIN tax_returns r ON r.client_id = c.id AND r.tax_year = 2025
  WHERE c.status = 'active'
  ORDER BY c.updated_at DESC
  LIMIT 6
")->fetchAll();

/* ── Upcoming deadlines ── */
$deadline_rows = [
    ['date'=>'Apr 15','bar'=>'urgent','name'=>'Individual Returns','form'=>'Form 1040 · Federal','count'=>$kpi['total_pending']],
    ['date'=>'Apr 15','bar'=>'urgent','name'=>'Q1 Est. Payments','form'=>'Form 1040-ES','count'=>null],
    ['date'=>'Jun 15','bar'=>'soon',  'name'=>'Partnership Returns','form'=>'Form 1065 Extension','count'=>null],
    ['date'=>'Sep 15','bar'=>'ok',    'name'=>'Corp. Extensions','form'=>'Form 1120 Extension','count'=>null],
];

/* ── Activity ── */
$activity = db()->query("
  SELECT a.*, c.display_name AS client_name, s.name AS sname
  FROM activity_log a
  JOIN clients c ON c.id = a.client_id
  LEFT JOIN staff s ON s.id = a.staff_id
  ORDER BY a.logged_at DESC
  LIMIT 5
")->fetchAll();

/* ── Tasks (checklist items that are pending/blocked) ── */
$tasks = db()->query("
  SELECT ch.*, c.display_name AS client_name, r.form_type
  FROM return_checklist ch
  JOIN tax_returns r ON r.id = ch.return_id
  JOIN clients c ON c.id = r.client_id
  WHERE ch.status IN('pending','blocked') AND r.tax_year=2025
  ORDER BY ch.status DESC, ch.id ASC
  LIMIT 5
")->fetchAll();

/* ── Pipeline counts by status ── */
$pipeline_raw = db()->query("
  SELECT r.status,
         COUNT(*) AS cnt,
         GROUP_CONCAT(CONCAT(c.display_name,'|',r.form_type,'|',r.id) ORDER BY c.display_name SEPARATOR ';;') AS items
  FROM tax_returns r
  JOIN clients c ON c.id = r.client_id
  WHERE r.tax_year=2025 AND r.status NOT IN('filed','archived')
  GROUP BY r.status
")->fetchAll();

$pipeline = [];
foreach ($pipeline_raw as $row) {
    $pipeline[$row['status']] = $row;
}

$page_title = 'Dashboard';
$active_nav = 'dashboard';
include 'header.php';
?>

<!-- Season Banner -->

<div class="season-banner">
  <div class="banner-text">
    <div class="banner-label">&#9888; Filing Season Active</div>
    <div class="banner-heading">Tax Deadline Approaching</div>
    <div class="banner-sub">April 15 federal deadline &mdash; stay on track</div>
  </div>
  <div class="banner-right">
    <div class="banner-stat">
      <div class="banner-num"><?= (int)$kpi['total_returns'] ?></div>
      <div class="banner-stat-label">Total Returns</div>
    </div>
    <div class="banner-divider"></div>
    <div class="banner-stat">
      <div class="banner-num"><?= (int)$kpi['total_filed'] ?></div>
      <div class="banner-stat-label">Filed</div>
    </div>
    <div class="banner-divider"></div>
    <div class="banner-stat">
      <div class="banner-num"><?= (int)$kpi['total_pending'] ?></div>
      <div class="banner-stat-label">Pending</div>
    </div>
    <div class="deadline-pill">&#9201; Apr 15 Deadline</div>
  </div>
</div>

<!-- KPI Stats -->

<div class="stats-grid">
  <div class="stat-card gold">
    <div class="stat-icon gold-bg">&#128176;</div>
    <div class="stat-label">Revenue YTD</div>
    <div class="stat-value">$<?= number_format((float)$kpi['revenue']/1000,0) ?>k</div>
    <div class="stat-change up">&#8593; 2026 season</div>
  </div>
  <div class="stat-card moss">
    <div class="stat-icon moss-bg">&#128101;</div>
    <div class="stat-label">Active Clients</div>
    <div class="stat-value"><?= (int)$kpi['clients'] ?></div>
    <div class="stat-change up">&#8593; Live data</div>
  </div>
  <div class="stat-card rust">
    <div class="stat-icon rust-bg">&#128203;</div>
    <div class="stat-label">Awaiting Docs</div>
    <div class="stat-value"><?= (int)$kpi['missing_docs'] ?></div>
    <div class="stat-muted">Needs follow-up</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon slate-bg">&#9989;</div>
    <div class="stat-label">Returns Filed</div>
    <div class="stat-value"><?= (int)$kpi['filed_week'] ?></div>
    <div class="stat-change up">&#8593; 2025 tax year</div>
  </div>
</div>

<!-- Main Grid -->

<div class="main-grid">

  <!-- Recent Clients table -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Recent Clients</div><div class="panel-subtitle">ACTIVE THIS SEASON</div></div>
      <a href="clients.php" class="panel-action">View all &rarr;</a>
    </div>
    <div class="table-wrapper">
      <table class="client-table">
        <thead>
          <tr><th>Client</th><th>Return Type</th><th>Status</th><th>Refund / Due</th><th>Updated</th></tr>
        </thead>
        <tbody>
          <?php foreach($recent as $cl): ?>
          <tr onclick="window.location='client-detail.php?id=<?= $cl['id'] ?>'" style="cursor:pointer;">
            <td>
              <div class="client-name-cell">
                <div class="client-avatar" style="background:<?= h($cl['avatar_color']) ?>;"><?= h($cl['initials']?:make_initials($cl['display_name'])) ?></div>
                <div><div class="client-name"><?= h($cl['display_name']) ?></div><div class="client-type"><?= h(strtoupper(str_replace('_',' ',$cl['entity_type']))) ?></div></div>
              </div>
            </td>
            <td style="color:#8a8070;font-size:13px;"><?= $cl['form_type']?h($cl['form_type']):'—' ?></td>
            <td><?php if($cl['rstatus']): ?><span class="status-pill <?= status_pill_class($cl['rstatus']) ?>"><?= status_label($cl['rstatus']) ?></span><?php else: ?><span style="color:#8a8070;font-size:12px;">No return</span><?php endif; ?></td>
            <td><?= $cl['refund_amount']!==null?fmt_money((float)$cl['refund_amount']):'—' ?></td>
            <td style="font-family:'DM Mono','Courier New',monospace;font-size:11.5px;color:#8a8070;"><?= date('M j',strtotime($cl['updated_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:#8a8070;">No clients yet. <a href="clients.php" style="color:#c8922a;">Add one</a></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right column -->

  <div class="right-col">

```
<!-- Deadlines -->
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Upcoming Deadlines</div><div class="panel-subtitle">FEDERAL &amp; STATE</div></div></div>
  <div class="deadline-list">
    <?php foreach($deadline_rows as $dl): ?>
    <div class="deadline-item">
      <div class="deadline-date">
        <div class="deadline-day"><?= explode(' ',$dl['date'])[1] ?></div>
        <div class="deadline-month"><?= explode(' ',$dl['date'])[0] ?></div>
      </div>
      <div class="deadline-bar bar-<?= $dl['bar'] ?>"></div>
      <div class="deadline-info">
        <div class="deadline-name"><?= h($dl['name']) ?></div>
        <div class="deadline-form"><?= h($dl['form']) ?></div>
      </div>
      <?php if($dl['count']!==null): ?>
      <div class="deadline-count <?= $dl['count']<5?'ok':'' ?>"><?= (int)$dl['count'] ?> left</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Revenue chart (static bars, dynamic label) -->
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">Revenue</div><div class="panel-subtitle">MONTHLY 2026</div></div>
    <a href="returns.php" class="panel-action">All Returns &rarr;</a>
  </div>
  <div class="chart-area">
    <div class="chart-bars">
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-secondary" style="height:40%"></div></div><div class="chart-label">Jan</div></div>
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-secondary" style="height:52%"></div></div><div class="chart-label">Feb</div></div>
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-primary" style="height:78%"></div></div><div class="chart-label">Mar</div></div>
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-active" style="height:100%"></div></div><div class="chart-label active">Apr</div></div>
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-secondary" style="height:28%"></div></div><div class="chart-label">May</div></div>
      <div class="chart-col"><div class="bar-wrap"><div class="bar bar-secondary" style="height:16%"></div></div><div class="chart-label">Jun</div></div>
    </div>
    <div class="chart-summary">
      <div class="chart-legend"><div class="legend-dot" style="background:#0f1117"></div> Current</div>
      <div class="chart-legend"><div class="legend-dot" style="background:#c8922a"></div> High</div>
      <div style="margin-left:auto;font-family:'DM Mono','Courier New',monospace;font-size:11px;color:#4e7260;">YTD: $<?= number_format((float)$kpi['revenue']) ?></div>
    </div>
  </div>
</div>
```

  </div><!-- /.right-col -->
</div><!-- /.main-grid -->

<!-- Bottom grid -->

<div class="bottom-grid">

  <!-- Tasks -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Open Tasks</div><div class="panel-subtitle">PENDING CHECKLIST ITEMS</div></div>
      <a href="returns.php" class="panel-action">View Returns &rarr;</a>
    </div>
    <div>
      <?php if(empty($tasks)): ?><div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">All checklist items complete!</div><?php endif; ?>
      <?php foreach($tasks as $task): ?>
      <div class="task-item">
        <div class="task-check <?= $task['status']==='done'?'done':'' ?>"><?= $task['status']==='done'?'&#10003;':'' ?></div>
        <div class="task-content">
          <div class="task-name <?= $task['status']==='done'?'done':'' ?>"><?= h($task['label']) ?></div>
          <div class="task-meta"><?= h($task['client_name']) ?> &middot; <?= h($task['form_type']) ?></div>
        </div>
        <div class="task-priority <?= $task['status']==='blocked'?'p-high':'p-med' ?>"><?= $task['status']==='blocked'?'BLOCKED':'OPEN' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Pipeline -->

  <div class="panel">
    <div class="panel-header"><div><div class="panel-title">Work Pipeline</div><div class="panel-subtitle">BY STAGE</div></div><a href="returns.php" class="panel-action">All &rarr;</a></div>
    <div class="pipeline-cols">
      <?php
      $stages = ['awaiting_docs'=>'Intake','in_progress'=>'Prep','in_review'=>'Review'];
      foreach($stages as $skey => $slabel):
        $stage_data = $pipeline[$skey] ?? null;
        $stage_count = $stage_data ? (int)$stage_data['cnt'] : 0;
        $stage_items = $stage_data && $stage_data['items'] ? explode(';;',$stage_data['items']) : [];
      ?>
      <div class="pipeline-col">
        <div class="pipeline-col-title"><?= $slabel ?> <span class="pipeline-count"><?= $stage_count ?></span></div>
        <?php foreach(array_slice($stage_items,0,2) as $item): ?>
        <?php $parts = explode('|',$item); $iname=$parts[0]??''; $iform=$parts[1]??''; $irid=(int)($parts[2]??0); ?>
        <a href="return-detail.php?id=<?= $irid ?>" class="pipeline-card" style="text-decoration:none;">
          <div class="pipeline-card-name"><?= h(strlen($iname)>18?substr($iname,0,17).'…':$iname) ?></div>
          <div class="pipeline-card-type"><?= h($iform) ?></div>
        </a>
        <?php endforeach; ?>
        <?php if($stage_count === 0): ?><div style="padding:8px 0;font-size:12px;color:#8a8070;">No returns</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Activity -->

  <div class="panel">
    <div class="panel-header"><div><div class="panel-title">Activity Feed</div><div class="panel-subtitle">RECENT UPDATES</div></div></div>
    <div class="activity-feed">
      <?php if(empty($activity)): ?><div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">No activity yet.</div><?php endif; ?>
      <?php foreach($activity as $log): ?>
      <div class="activity-item">
        <div class="activity-dot" style="background:#ede9df;"><?= h($log['icon']) ?></div>
        <div class="activity-text">
          <div class="activity-main"><strong><?= h($log['client_name']) ?></strong> &mdash; <?= h(mb_substr($log['body'],0,80)).(mb_strlen($log['body'])>80?'…':'') ?></div>
          <div class="activity-time"><?= date('M j, g:i A',strtotime($log['logged_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php include 'footer.php'; ?>