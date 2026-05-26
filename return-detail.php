<?php
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: returns.php'); exit; }

$action = $_POST['action'] ?? '';

/* Toggle checklist item */
if ($action === 'toggle_check' && !empty($_POST['check_id'])) {
    $ci = db()->prepare('SELECT status FROM return_checklist WHERE id=? AND return_id=?');
    $ci->execute([(int)$_POST['check_id'], $id]);
    $cur = $ci->fetchColumn();
    $new = $cur === 'done' ? 'pending' : 'done';
    db()->prepare('UPDATE return_checklist SET status=?,done_at=IF(?,NOW(),NULL) WHERE id=?')->execute([$new,$new==='done',(int)$_POST['check_id']]);
    $done=db()->prepare('SELECT COUNT(*) FROM return_checklist WHERE return_id=? AND status="done"');$done->execute([$id]);$d=(int)$done->fetchColumn();
    $tot=db()->prepare('SELECT COUNT(*) FROM return_checklist WHERE return_id=?');$tot->execute([$id]);$t=(int)$tot->fetchColumn();
    if($t>0)db()->prepare('UPDATE tax_returns SET completion_pct=? WHERE id=?')->execute([round($d/$t*100),$id]);
    header("Location: return-detail.php?id=$id"); exit;
}

/* Add note */
if ($action === 'add_note') {
    $body = trim($_POST['body'] ?? '');
    $r    = db()->prepare('SELECT client_id FROM tax_returns WHERE id=?');$r->execute([$id]);$cid=(int)($r->fetchColumn());
    if ($body && $cid) {
        db()->prepare('INSERT INTO activity_log (client_id,return_id,type,icon,body,staff_id) VALUES (?,?,"note","📝",?,1)')->execute([$cid,$id,$body]);
    }
    header("Location: return-detail.php?id=$id#notes"); exit;
}

/* Update status */
if ($action === 'update_status') {
    $ns = $_POST['new_status'] ?? '';
    $pct = $ns === 'filed' ? 100 : (int)($_POST['pct'] ?? 50);
    $filed = $ns === 'filed' ? date('Y-m-d') : null;
    db()->prepare('UPDATE tax_returns SET status=?,completion_pct=?,filed_date=? WHERE id=?')->execute([$ns,$pct,$filed,$id]);
    // Log it
    $r=db()->prepare('SELECT client_id FROM tax_returns WHERE id=?');$r->execute([$id]);$cid=(int)$r->fetchColumn();
    if($cid)db()->prepare('INSERT INTO activity_log (client_id,return_id,type,icon,body,staff_id) VALUES (?,?,"status_change","📋",?,1)')->execute([$cid,$id,'Status changed to: '.status_label($ns)]);
    header("Location: return-detail.php?id=$id&msg=updated"); exit;
}

/* ── Load return ── */
$stmt = db()->prepare('SELECT r.*,c.display_name,c.initials,c.avatar_color,c.entity_type,c.ein,c.ssn_last4,c.filing_states,c.fiscal_year_end,s.name AS aname,s.initials AS ainit,s.color AS acolor FROM tax_returns r JOIN clients c ON c.id=r.client_id LEFT JOIN staff s ON s.id=r.assignee_id WHERE r.id=?');
$stmt->execute([$id]); $ret = $stmt->fetch();
if (!$ret) { header('Location: returns.php'); exit; }

/* ── Load checklist ── */
$chk=db()->prepare('SELECT ch.*,s.name AS sname FROM return_checklist ch LEFT JOIN staff s ON s.id=ch.assignee_id WHERE ch.return_id=? ORDER BY ch.sort_order');
$chk->execute([$id]); $checklist=$chk->fetchAll();
$done_count = count(array_filter($checklist, fn($i) => $i['status']==='done'));

/* ── Load estimated payments ── */
$ep=db()->prepare('SELECT * FROM estimated_payments WHERE return_id=? ORDER BY quarter');
$ep->execute([$id]); $payments=$ep->fetchAll();

/* ── Load documents ── */
$docs=db()->prepare('SELECT * FROM documents WHERE return_id=? ORDER BY status ASC,uploaded_at DESC');
$docs->execute([$id]); $documents=$docs->fetchAll();

/* ── Load activity ── */
$logs=db()->prepare('SELECT a.*,s.name AS sname FROM activity_log a LEFT JOIN staff s ON s.id=a.staff_id WHERE a.return_id=? ORDER BY a.logged_at DESC LIMIT 20');
$logs->execute([$id]); $activity=$logs->fetchAll();

/* ── Load shareholders / K-1 ── */
$sh=db()->prepare('SELECT * FROM shareholders WHERE return_id=? ORDER BY ownership_pct DESC');
$sh->execute([$id]); $shareholders=$sh->fetchAll();

/* ── Days until due ── */
$days_left = (int)floor((strtotime($ret['due_date']) - time()) / 86400);
$is_overdue = $days_left < 0 && $ret['status'] !== 'filed';

$page_title  = $ret['tax_year'].' Form '.$ret['form_type'];
$active_nav  = 'returns';
$topbar_btn  = $ret['status']==='filed' ? 'Download Filed Copy' : 'E-File Return';
$topbar_href = '#';
include 'header.php';
?>

<?php if(!empty($_GET['msg'])): ?><div style="background:#daeade;border:1px solid #3d5a47;color:#2a5c35;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;">Return updated successfully.</div><?php endif; ?>

<a class="back-link" href="returns.php">
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 3L5 8l5 5"/></svg>
  All Returns
</a>

<!-- Hero -->

<div class="detail-hero accent-gold">
  <div class="client-avatar detail-hero-avatar" style="background:<?= h($ret['avatar_color']) ?>;"><?= h($ret['initials']?:make_initials($ret['display_name'])) ?></div>
  <div class="detail-hero-body">
    <div class="detail-hero-name"><?= h($ret['display_name']) ?> &mdash; Form <?= h($ret['form_type']) ?></div>
    <div class="detail-hero-meta">
      <span class="status-pill <?= status_pill_class($ret['status']) ?>"><?= status_label($ret['status']) ?></span>
      <span class="form-badge" style="font-size:12px;padding:3px 10px;">Tax Year <?= (int)$ret['tax_year'] ?></span>
      <span class="detail-hero-tag">Due: <?= date('M j, Y',strtotime($ret['due_date'])) ?></span>
      <span class="detail-hero-tag">Assignee: <?= h($ret['ainit']??'—') ?></span>
      <?php if($ret['filing_states']): ?><span class="detail-hero-tag"><?= h($ret['filing_states']) ?></span><?php endif; ?>
    </div>
  </div>
  <div class="detail-hero-actions">
    <a href="client-detail.php?id=<?= (int)$ret['client_id'] ?>" class="btn-outline">&#128100; Client File</a>
    <!-- Status update form -->
    <form method="post" action="return-detail.php?id=<?= $id ?>" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="action" value="update_status">
      <select name="new_status" class="filter-select" style="font-size:12px;padding:6px 28px 6px 10px;">
        <?php foreach(['not_started'=>'Not Started','awaiting_docs'=>'Awaiting Docs','in_progress'=>'In Progress','in_review'=>'In Review','filed'=>'Filed','extension'=>'Extension'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $ret['status']===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="topbar-btn btn-primary">Update</button>
    </form>
  </div>
</div>

<!-- Progress bar -->

<div class="panel" style="margin-bottom:20px;padding:20px 24px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div style="flex:1;min-width:180px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a8070;">Completion</span>
        <span style="font-family:'DM Mono','Courier New',monospace;font-size:12px;font-weight:600;color:#c8922a;"><?= (int)$ret['completion_pct'] ?>%</span>
      </div>
      <div class="progress-bar" style="height:8px;"><div class="progress-fill <?= $ret['status']==='filed'?'filed':($ret['status']==='in_review'?'review':($ret['status']==='in_progress'?'progress':'pending')) ?>" style="width:<?= (int)$ret['completion_pct'] ?>%;height:100%;border-radius:4px;"></div></div>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap;">
      <div style="text-align:center;">
        <div style="font-family:'DM Serif Display',Georgia,serif;font-size:22px;color:<?= $is_overdue?'#b84c2e':($ret['status']==='filed'?'#4e7260':'#0f1117') ?>;line-height:1;"><?= $ret['status']==='filed'?'&#10003;':($is_overdue?'!'.$days_left.'d':$days_left.'d') ?></div>
        <div style="font-family:'DM Mono','Courier New',monospace;font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-top:2px;"><?= $ret['status']==='filed'?'Filed':'Days Left' ?></div>
      </div>
      <div style="text-align:center;">
        <div style="font-family:'DM Serif Display',Georgia,serif;font-size:22px;color:<?= (float)$ret['refund_amount']>0?'#4e7260':'#b84c2e' ?>;line-height:1;"><?= $ret['refund_amount']!=0?fmt_money((float)$ret['refund_amount'],false):'—' ?></div>
        <div style="font-family:'DM Mono','Courier New',monospace;font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-top:2px;"><?= (float)$ret['refund_amount']>0?'Refund Est.':'Owed' ?></div>
      </div>
      <div style="text-align:center;">
        <div style="font-family:'DM Serif Display',Georgia,serif;font-size:22px;color:#0f1117;line-height:1;"><?= $done_count ?>/<?= count($checklist) ?></div>
        <div style="font-family:'DM Mono','Courier New',monospace;font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-top:2px;">Steps Done</div>
      </div>
    </div>
  </div>
</div>

<div class="detail-grid">
  <!-- ── LEFT ── -->
  <div class="detail-main">

```
<!-- Tax summary -->
<?php if($ret['gross_revenue'] || $ret['net_income']): ?>
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">Tax Summary</div><div class="panel-subtitle"><?= $ret['status']==='filed'?'FINAL':'PRELIMINARY' ?> FIGURES</div></div>
    <?php if($ret['status']!=='filed'): ?><span style="font-family:'DM Mono','Courier New',monospace;font-size:10px;color:#c8922a;background:#f5e6c8;padding:3px 8px;border-radius:4px;">DRAFT</span><?php endif; ?>
  </div>
  <div class="figures-grid" style="border-radius:0;">
    <div class="figure-cell"><div class="key-figure">$<?= number_format((float)$ret['gross_revenue']) ?></div><div class="key-figure-label">Gross Revenue</div></div>
    <div class="figure-cell"><div class="key-figure">$<?= number_format((float)$ret['total_expenses']) ?></div><div class="key-figure-label">Total Expenses</div></div>
    <div class="figure-cell"><div class="key-figure" style="color:#4e7260;">$<?= number_format((float)$ret['net_income']) ?></div><div class="key-figure-label">Net Income</div></div>
    <?php if($ret['officer_comp']): ?><div class="figure-cell"><div class="key-figure">$<?= number_format((float)$ret['officer_comp']) ?></div><div class="key-figure-label">Officer Comp</div></div><?php endif; ?>
    <div class="figure-cell"><div class="key-figure" style="color:#b84c2e;">$<?= number_format((float)$ret['tax_liability']) ?></div><div class="key-figure-label">Tax Liability</div></div>
    <div class="figure-cell"><div class="key-figure" style="color:<?= (float)$ret['refund_amount']>0?'#4e7260':'#b84c2e' ?>;"><?= fmt_money((float)$ret['refund_amount'],false) ?></div><div class="key-figure-label"><?= (float)$ret['refund_amount']>0?'Refund':'Owed' ?></div></div>
  </div>
</div>
<?php endif; ?>

<!-- Checklist -->
<?php if($checklist): ?>
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">Preparer Checklist</div><div class="panel-subtitle"><?= $done_count ?> OF <?= count($checklist) ?> COMPLETE</div></div>
  </div>
  <form method="post" action="return-detail.php?id=<?= $id ?>">
  <input type="hidden" name="action" value="toggle_check">
  <div class="checklist">
    <?php foreach($checklist as $item): ?>
    <div class="check-row">
      <button type="submit" name="check_id" value="<?= $item['id'] ?>" style="background:none;border:none;padding:0;cursor:pointer;">
        <div class="check-box <?= $item['status']==='done'?'done':($item['status']==='blocked'?'skip':'') ?>"><?= $item['status']==='done'?'&#10003;':($item['status']==='blocked'?'&#8212;':'') ?></div>
      </button>
      <div class="check-label <?= $item['status']==='done'?'done':'' ?>" style="<?= $item['status']==='blocked'?'color:#b84c2e;font-weight:500;':'' ?>"><?= $item['status']==='blocked'?'&#9888; ':'' ?><?= h($item['label']) ?></div>
      <div class="check-who"><?= $item['sname']?h($item['sname']):'' ?><?= $item['done_at']?' &middot; '.date('M j',strtotime($item['done_at'])):'' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  </form>
</div>
<?php endif; ?>

<!-- Notes & Activity -->
<div class="panel" id="notes">
  <div class="panel-header">
    <div><div class="panel-title">Notes &amp; Activity</div><div class="panel-subtitle">RETURN HISTORY</div></div>
  </div>
  <form method="post" action="return-detail.php?id=<?= $id ?>" style="padding:14px 22px;border-bottom:1px solid #d4cec2;display:flex;gap:8px;">
    <input type="hidden" name="action" value="add_note">
    <input name="body" placeholder="Add a return note..." required style="flex:1;padding:8px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;">
    <button type="submit" class="btn-outline" style="padding:8px 14px;">Add</button>
  </form>
  <div class="timeline">
    <?php if(empty($activity)): ?><div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">No activity logged yet.</div><?php endif; ?>
    <?php foreach($activity as $log): ?>
    <div class="timeline-item">
      <div class="timeline-dot" style="background:#ede9df;"><?= h($log['icon']) ?></div>
      <div class="timeline-body">
        <div class="timeline-text"><?= nl2br(h($log['body'])) ?></div>
        <div class="timeline-meta">
          <span class="timeline-time"><?= date('M j, Y g:i A',strtotime($log['logged_at'])) ?></span>
          <?php if($log['sname']): ?><span class="timeline-author">&middot; <?= h($log['sname']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
```

  </div><!-- /.detail-main -->

  <!-- ── RIGHT ── -->

  <div class="detail-side">

```
<!-- Return details -->
<div class="panel">
  <div class="panel-header"><div class="panel-title">Return Details</div></div>
  <div class="info-list">
    <div class="info-row"><div class="info-label">Tax Year</div><div class="info-value"><?= (int)$ret['tax_year'] ?></div></div>
    <div class="info-row"><div class="info-label">Form</div><div class="info-value"><span class="form-badge"><?= h($ret['form_type']) ?></span></div></div>
    <div class="info-row"><div class="info-label">Jurisdiction</div><div class="info-value"><?= h($ret['jurisdiction']) ?></div></div>
    <div class="info-row"><div class="info-label">Due Date</div><div class="info-value"><span class="due-date <?= due_class($ret['due_date'],$ret['status']) ?>"><?= date('M j, Y',strtotime($ret['due_date'])) ?></span></div></div>
    <?php if($ret['filed_date']): ?><div class="info-row"><div class="info-label">Filed</div><div class="info-value" style="color:#4e7260;"><?= date('M j, Y',strtotime($ret['filed_date'])) ?></div></div><?php endif; ?>
    <?php if($ret['extension_date']): ?><div class="info-row"><div class="info-label">Extension</div><div class="info-value"><?= date('M j, Y',strtotime($ret['extension_date'])) ?></div></div><?php endif; ?>
    <div class="info-row"><div class="info-label">Software</div><div class="info-value"><?= h($ret['software']) ?></div></div>
    <div class="info-row"><div class="info-label">Return ID</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12px;"><?= h($ret['return_ref']) ?></div></div>
    <div class="info-row"><div class="info-label">Assignee</div><div class="info-value"><?= h($ret['aname']??'—') ?></div></div>
    <div class="info-row"><div class="info-label">Fee</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;"><?= $ret['fee']?'$'.number_format((float)$ret['fee']):'—' ?></div></div>
  </div>
</div>

<!-- Blocking items -->
<?php $missing_docs = array_filter($documents,fn($d)=>$d['status']==='missing');
      $blocked_chk  = array_filter($checklist, fn($i)=>$i['status']==='blocked'); ?>
<?php if($missing_docs || $blocked_chk): ?>
<div class="note-box">
  <div class="note-box-label">&#9888; Blocking Items</div>
  <?php foreach($blocked_chk as $bi): ?><div style="margin-bottom:4px;">&#8226; <?= h($bi['label']) ?></div><?php endforeach; ?>
  <?php foreach($missing_docs as $md): ?><div style="margin-bottom:4px;">&#8226; <?= h($md['filename']) ?> (document missing)</div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Estimated payments -->
<?php if($payments): ?>
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Estimated Payments</div><div class="panel-subtitle"><?= (int)$ret['tax_year'] ?> PAID-IN</div></div></div>
  <div class="info-list">
    <?php $total_paid = 0; foreach($payments as $p): $total_paid += $p['paid']?$p['amount']:0; ?>
    <div class="info-row">
      <div class="info-label">Q<?= $p['quarter'] ?> <?= date('M j',strtotime($p['due_date'])) ?></div>
      <div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;color:<?= $p['paid']?'#4e7260':'#b84c2e' ?>;">
        $<?= number_format((float)$p['amount']) ?> <?= $p['paid']?'&#10003;':'pending' ?>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="info-row" style="background:#faf8f4;"><div class="info-label" style="font-weight:600;color:#0f1117;">Total Paid</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:13px;font-weight:700;color:#4e7260;">$<?= number_format($total_paid) ?></div></div>
    <?php if($ret['tax_liability']): ?>
    <div class="info-row"><div class="info-label">Tax Due</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;">$<?= number_format((float)$ret['tax_liability']) ?></div></div>
    <div class="info-row" style="background:#faf8f4;"><div class="info-label" style="font-weight:600;color:<?= (float)$ret['refund_amount']>0?'#4e7260':'#b84c2e' ?>;"><?= (float)$ret['refund_amount']>0?'Refund':'Owed' ?></div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:13px;font-weight:700;color:<?= (float)$ret['refund_amount']>0?'#4e7260':'#b84c2e' ?>;"><?= fmt_money((float)$ret['refund_amount'],false) ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Documents -->
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Documents</div></div></div>
  <div class="doc-list">
    <?php if(empty($documents)): ?><div style="padding:16px;text-align:center;color:#8a8070;font-size:13px;">No documents attached.</div><?php endif; ?>
    <?php foreach($documents as $doc): ?>
    <div class="doc-row" style="<?= $doc['status']==='missing'?'opacity:0.6;':'' ?>">
      <div class="doc-icon <?= $doc['file_type']==='pdf'?'pdf':($doc['file_type']==='xlsx'?'xls':'misc') ?>"><?= $doc['file_type']==='pdf'?'&#128196;':($doc['file_type']==='xlsx'?'&#128200;':'&#9888;') ?></div>
      <div class="doc-info">
        <div class="doc-name" style="<?= $doc['status']==='missing'?'color:#b84c2e;':'' ?>"><?= h($doc['filename']) ?></div>
        <div class="doc-meta"><?= $doc['uploaded_at']?date('M j',strtotime($doc['uploaded_at'])):h($doc['notes']?:'Missing') ?><?= $doc['uploaded_by']&&$doc['uploaded_at']?' &middot; '.h($doc['uploaded_by']):'' ?></div>
      </div>
      <div class="doc-size" style="<?= $doc['status']==='missing'?'color:#b84c2e;':'' ?>"><?= $doc['file_size_kb']?number_format($doc['file_size_kb']).' KB':($doc['status']==='missing'?'Needed':'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- K-1 shareholders -->
<?php if($shareholders): ?>
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Schedule K-1</div><div class="panel-subtitle">SHAREHOLDERS / PARTNERS</div></div></div>
  <div class="info-list">
    <?php foreach($shareholders as $sh): ?>
    <div class="info-row">
      <div class="info-label"><?= h($sh['name']) ?></div>
      <div class="info-value"><?= number_format((float)$sh['ownership_pct'],1) ?>% &middot; <span style="font-family:'DM Mono','Courier New',monospace;font-size:12px;color:#4e7260;">$<?= number_format((float)$sh['distribution']) ?></span></div>
    </div>
    <?php endforeach; ?>
    <div class="info-row" style="background:#faf8f4;"><div class="info-label" style="font-weight:600;color:#0f1117;">Total</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;font-weight:700;">$<?= number_format(array_sum(array_column($shareholders,'distribution'))) ?></div></div>
  </div>
</div>
<?php endif; ?>
```

  </div><!-- /.detail-side -->
</div>

<?php include 'footer.php'; ?>