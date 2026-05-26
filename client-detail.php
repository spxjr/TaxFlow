<?php
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: clients.php'); exit; }

/* ── Actions ── */
$action = $_POST['action'] ?? '';

if ($action === 'add_note') {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        db()->prepare('INSERT INTO activity_log (client_id,return_id,type,icon,body,staff_id,source) VALUES (?,NULL,"note","📝",?,1,"")')->execute([$id, $body]);
    }
    header("Location: client-detail.php?id=$id#activity"); exit;
}

if ($action === 'update_contact') {
    $stmt = db()->prepare('UPDATE clients SET contact_name=?,email=?,phone=?,address_line1=?,city=?,state=?,zip=?,ein=?,updated_at=NOW() WHERE id=?');
    $stmt->execute([trim($_POST['contact_name']??''),trim($_POST['email']??''),trim($_POST['phone']??''),trim($_POST['address_line1']??''),trim($_POST['city']??''),trim($_POST['state']??''),trim($_POST['zip']??''),trim($_POST['ein']??''),$id]);
    header("Location: client-detail.php?id=$id&msg=saved"); exit;
}

if ($action === 'archive') {
    db()->prepare('UPDATE clients SET status="archived" WHERE id=?')->execute([$id]);
    header('Location: clients.php?msg=deleted'); exit;
}

/* ── Load client ── */
$c = db()->prepare('SELECT c.*,s.name AS aname,s.initials AS ainit,s.color AS acolor FROM clients c LEFT JOIN staff s ON s.id=c.assignee_id WHERE c.id=?');
$c->execute([$id]);
$client = $c->fetch();
if (!$client) { header('Location: clients.php'); exit; }

/* ── Load 2025 return ── */
$ret = db()->prepare('SELECT * FROM tax_returns WHERE client_id=? AND tax_year=2025 LIMIT 1');
$ret->execute([$id]); $return = $ret->fetch();

/* ── Load return history ── */
$hist = db()->prepare('SELECT * FROM tax_returns WHERE client_id=? ORDER BY tax_year DESC');
$hist->execute([$id]); $history = $hist->fetchAll();

/* ── Load checklist ── */
$chk = $return ? db()->prepare('SELECT ch.*,s.name AS sname FROM return_checklist ch LEFT JOIN staff s ON s.id=ch.assignee_id WHERE ch.return_id=? ORDER BY ch.sort_order') : null;
if ($chk) { $chk->execute([$return['id']]); $checklist = $chk->fetchAll(); } else { $checklist = []; }

/* ── Toggle checklist item ── */
if ($action === 'toggle_check' && !empty($_POST['check_id']) && $return) {
    $ci = db()->prepare('SELECT status FROM return_checklist WHERE id=? AND return_id=?');
    $ci->execute([(int)$_POST['check_id'], $return['id']]);
    $cur = $ci->fetchColumn();
    $new = $cur === 'done' ? 'pending' : 'done';
    db()->prepare('UPDATE return_checklist SET status=?,done_at=IF(?,NOW(),NULL) WHERE id=?')->execute([$new, $new==='done', (int)$_POST['check_id']]);
    // Recalculate completion %
    $done = db()->prepare('SELECT COUNT(*) FROM return_checklist WHERE return_id=? AND status="done"');
    $done->execute([$return['id']]); $d = (int)$done->fetchColumn();
    $tot  = db()->prepare('SELECT COUNT(*) FROM return_checklist WHERE return_id=?');
    $tot->execute([$return['id']]); $t = (int)$tot->fetchColumn();
    if ($t > 0) db()->prepare('UPDATE tax_returns SET completion_pct=? WHERE id=?')->execute([round($d/$t*100), $return['id']]);
    header("Location: client-detail.php?id=$id"); exit;
}

/* ── Load docs ── */
$docs = db()->prepare('SELECT * FROM documents WHERE client_id=? AND (return_id=? OR return_id IS NULL) ORDER BY status ASC, uploaded_at DESC');
$docs->execute([$id, $return['id'] ?? 0]); $documents = $docs->fetchAll();

/* ── Load activity ── */
$logs = db()->prepare('SELECT a.*,s.name AS sname,s.initials AS sinit,s.color AS scolor FROM activity_log a LEFT JOIN staff s ON s.id=a.staff_id WHERE a.client_id=? ORDER BY a.logged_at DESC LIMIT 20');
$logs->execute([$id]); $activity = $logs->fetchAll();

/* ── Load invoices ── */
$invs = db()->prepare('SELECT * FROM invoices WHERE client_id=? ORDER BY issued_date DESC LIMIT 10');
$invs->execute([$id]); $invoices = $invs->fetchAll();

/* ── Stats ── */
$fees_ytd = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE client_id=? AND YEAR(issued_date)=2026'); $fees_ytd->execute([$id]); $fees = (float)$fees_ytd->fetchColumn();
$ret_count = count($history);
$missing_count = array_sum(array_column($documents, 'status') === array_fill(0, count($documents), 'missing') ? [count($documents)] : array_map(fn($d) => $d['status']==='missing'?1:0, $documents));

$years_client = date('Y') - (int)$client['client_since'];

$page_title  = htmlspecialchars($client['display_name'], ENT_QUOTES);
$active_nav  = 'clients';
$topbar_btn  = '+ New Return';
$topbar_href = 'returns.php';
include 'header.php';
?>

<?php if(!empty($_GET['msg'])): ?><div style="background:#daeade;border:1px solid #3d5a47;color:#2a5c35;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;">Changes saved.</div><?php endif; ?>

<a class="back-link" href="clients.php">
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 3L5 8l5 5"/></svg>
  All Clients
</a>

<div class="detail-hero accent-gold">
  <div class="client-avatar detail-hero-avatar" style="background:<?= h($client['avatar_color']) ?>;"><?= h($client['initials']?:make_initials($client['display_name'])) ?></div>
  <div class="detail-hero-body">
    <div class="detail-hero-name"><?= h($client['display_name']) ?></div>
    <div class="detail-hero-meta">
      <?php if($return): ?><span class="status-pill <?= status_pill_class($return['status']) ?>"><?= status_label($return['status']) ?></span><?php endif; ?>
      <span class="segment-pill <?= entity_pill_class($client['entity_type']) ?>"><?= entity_label($client['entity_type']) ?></span>
      <?php if($client['ein']): ?><span class="detail-hero-tag">EIN: <?= h($client['ein']) ?></span><?php endif; ?>
      <span class="detail-hero-tag">Client since <?= (int)$client['client_since'] ?></span>
      <span class="detail-hero-tag">Assignee: <?= h($client['ainit']??'—') ?></span>
    </div>
  </div>
  <div class="detail-hero-actions">
    <?php if($client['email']): ?><a href="mailto:<?= h($client['email']) ?>" class="btn-outline">&#9993; Email</a><?php endif; ?>
    <?php if($client['phone']): ?><a href="tel:<?= h(preg_replace('/[^\d+]/','',$client['phone'])) ?>" class="btn-outline">&#128222; Call</a><?php endif; ?>
    <?php if($return): ?><a href="return-detail.php?id=<?= $return['id'] ?>" class="topbar-btn btn-primary">View Return &rarr;</a><?php endif; ?>
    <form method="post" style="display:inline;" onsubmit="return confirm('Archive this client?')"><input type="hidden" name="action" value="archive"><button type="submit" class="btn-outline" style="color:#b84c2e;">Archive</button></form>
  </div>
</div>

<!-- Figures -->

<div class="panel" style="margin-bottom:20px;">
  <div class="figures-grid">
    <div class="figure-cell"><div class="key-figure" style="color:#c8922a;">$<?= number_format($fees) ?></div><div class="key-figure-label">Fees YTD</div></div>
    <div class="figure-cell"><div class="key-figure"><?= $ret_count ?></div><div class="key-figure-label">Returns on File</div></div>
    <div class="figure-cell"><div class="key-figure" style="color:<?= $return&&$return['refund_amount']>0?'#4e7260':'#b84c2e' ?>;"><?= $return?fmt_money((float)$return['refund_amount'],false):'—' ?></div><div class="key-figure-label">Refund / Owed</div></div>
    <div class="figure-cell"><div class="key-figure"><?= count($documents) ?></div><div class="key-figure-label">Documents</div></div>
    <div class="figure-cell"><div class="key-figure" style="color:<?= $missing_count>0?'#b84c2e':'#4e7260' ?>;"><?= $missing_count ?></div><div class="key-figure-label">Items Needed</div></div>
    <div class="figure-cell"><div class="key-figure"><?= $years_client ?></div><div class="key-figure-label">Years as Client</div></div>
  </div>
</div>

<div class="detail-grid">
  <!-- ── LEFT ── -->
  <div class="detail-main">

```
<?php if($return): ?>
<!-- Current return -->
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title"><?= (int)$return['tax_year'] ?> Tax Return</div><div class="panel-subtitle">FORM <?= h($return['form_type']) ?> &middot; DUE <?= strtoupper(date('F j, Y',strtotime($return['due_date']))) ?></div></div>
    <a href="return-detail.php?id=<?= $return['id'] ?>" class="panel-action">Open Return &rarr;</a>
  </div>
  <div style="padding:14px 22px;border-bottom:1px solid #d4cec2;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <span class="status-pill <?= status_pill_class($return['status']) ?>"><?= status_label($return['status']) ?></span>
      <span style="font-family:'DM Mono','Courier New',monospace;font-size:11px;color:#8a8070;"><?= (int)$return['completion_pct'] ?>% complete</span>
    </div>
    <div class="progress-bar" style="height:7px;"><div class="progress-fill <?= $return['status']==='filed'?'filed':($return['status']==='in_review'?'review':($return['status']==='in_progress'?'progress':'pending')) ?>" style="width:<?= (int)$return['completion_pct'] ?>%;height:100%;border-radius:3px;"></div></div>
  </div>
  <?php if($checklist): ?>
  <form method="post" action="client-detail.php?id=<?= $id ?>">
  <input type="hidden" name="action" value="toggle_check">
  <div class="checklist">
    <?php foreach($checklist as $item): ?>
    <div class="check-row">
      <button type="submit" name="check_id" value="<?= $item['id'] ?>" style="background:none;border:none;padding:0;cursor:pointer;">
        <div class="check-box <?= $item['status']==='done'?'done':($item['status']==='blocked'?'skip':'') ?>"><?= $item['status']==='done'?'&#10003;':($item['status']==='blocked'?'&#8212;':'') ?></div>
      </button>
      <div class="check-label <?= $item['status']==='done'?'done':'' ?>" style="<?= $item['status']==='blocked'?'color:#b84c2e;font-weight:500;':'' ?>"><?= $item['status']==='blocked'?'&#9888; ':'' ?><?= h($item['label']) ?></div>
      <div class="check-who"><?= $item['sname']?h($item['sname']):($item['done_at']?date('M j',strtotime($item['done_at'])):'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Activity -->
<div class="panel" id="activity">
  <div class="panel-header">
    <div><div class="panel-title">Activity</div><div class="panel-subtitle">RECENT HISTORY</div></div>
  </div>
  <!-- Add note -->
  <form method="post" action="client-detail.php?id=<?= $id ?>" style="padding:14px 22px;border-bottom:1px solid #d4cec2;display:flex;gap:8px;">
    <input type="hidden" name="action" value="add_note">
    <input name="body" placeholder="Add a note..." required style="flex:1;padding:8px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;">
    <button type="submit" class="btn-outline" style="padding:8px 14px;">Add</button>
  </form>
  <div class="timeline">
    <?php if(empty($activity)): ?>
    <div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">No activity yet.</div>
    <?php endif; ?>
    <?php foreach($activity as $log): ?>
    <div class="timeline-item">
      <div class="timeline-dot" style="background:#ede9df;"><?= h($log['icon']) ?></div>
      <div class="timeline-body">
        <div class="timeline-text"><?= nl2br(h($log['body'])) ?></div>
        <div class="timeline-meta">
          <span class="timeline-time"><?= date('M j, Y g:i A', strtotime($log['logged_at'])) ?></span>
          <?php if($log['sname']): ?><span class="timeline-author">&middot; <?= h($log['sname']) ?></span><?php endif; ?>
          <?php if($log['source']): ?><span class="timeline-author">&middot; <?= h($log['source']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Return history -->
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">Return History</div><div class="panel-subtitle">ALL TAX YEARS</div></div>
    <a href="returns.php" class="panel-action">All Returns &rarr;</a>
  </div>
  <div class="table-wrapper">
    <table class="returns-table">
      <thead><tr><th>Tax Year</th><th>Form</th><th>Filed</th><th>Status</th><th>Refund / Owed</th><th>Fee</th></tr></thead>
      <tbody>
      <?php foreach($history as $h_ret): ?>
      <tr onclick="window.location='return-detail.php?id=<?= $h_ret['id'] ?>'" style="cursor:pointer;">
        <td style="font-weight:600;"><?= (int)$h_ret['tax_year'] ?></td>
        <td><span class="form-badge"><?= h($h_ret['form_type']) ?></span></td>
        <td><div class="due-date <?= due_class($h_ret['due_date'],$h_ret['status']) ?>"><?= $h_ret['filed_date']?date('M j, Y',strtotime($h_ret['filed_date'])):'In Progress' ?></div></td>
        <td><span class="status-pill <?= status_pill_class($h_ret['status']) ?>"><?= status_label($h_ret['status']) ?></span></td>
        <td><?= fmt_money((float)$h_ret['refund_amount']) ?></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;"><?= $h_ret['fee']?'$'.number_format((float)$h_ret['fee']):'—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($history)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:#8a8070;">No returns on file.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
```

  </div><!-- /.detail-main -->

  <!-- ── RIGHT ── -->

  <div class="detail-side">

```
<!-- Contact info (editable) -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">Contact Info</div>
    <button class="panel-action" onclick="document.getElementById('edit-contact').style.display=document.getElementById('edit-contact').style.display==='none'?'block':'none'">Edit</button>
  </div>
  <div id="view-contact">
    <div class="info-list">
      <div class="info-row"><div class="info-label">Name</div><div class="info-value"><?= h($client['contact_name']?:$client['display_name']) ?></div></div>
      <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= $client['email']?'<a href="mailto:'.h($client['email']).'">'.h($client['email']).'</a>':'—' ?></div></div>
      <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?= h($client['phone']?:' —') ?></div></div>
      <div class="info-row"><div class="info-label">Address</div><div class="info-value"><?= h($client['address_line1']) ?><?= $client['city']?'<br>'.h($client['city'].', '.$client['state'].' '.$client['zip']):'' ?></div></div>
      <div class="info-row"><div class="info-label">EIN</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;"><?= h($client['ein']?:('***-**-'.$client['ssn_last4'])) ?></div></div>
      <div class="info-row"><div class="info-label">Fiscal Year</div><div class="info-value"><?= h($client['fiscal_year_end']) ?></div></div>
      <div class="info-row"><div class="info-label">State(s)</div><div class="info-value"><?= h($client['filing_states']?:' CA') ?></div></div>
    </div>
  </div>
  <div id="edit-contact" style="display:none;padding:14px 18px;">
    <form method="post" action="client-detail.php?id=<?= $id ?>" style="display:flex;flex-direction:column;gap:10px;">
      <input type="hidden" name="action" value="update_contact">
      <?php foreach([['contact_name','Contact Name'],['email','Email'],['phone','Phone'],['address_line1','Address'],['city','City'],['state','State'],['zip','ZIP'],['ein','EIN']] as [$f,$l]): ?>
      <div>
        <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:9.5px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:3px;"><?= $l ?></label>
        <input name="<?= $f ?>" value="<?= h($client[$f]??'') ?>" style="width:100%;padding:7px 10px;border:1px solid #d4cec2;border-radius:6px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;">
      </div>
      <?php endforeach; ?>
      <button type="submit" class="topbar-btn btn-primary" style="margin-top:4px;">Save Changes</button>
    </form>
  </div>
</div>

<!-- Missing docs alert -->
<?php $missing_docs = array_filter($documents, fn($d) => $d['status']==='missing'); ?>
<?php if($missing_docs): ?>
<div class="note-box">
  <div class="note-box-label">&#9888; Documents Needed</div>
  <?php foreach($missing_docs as $md): ?>
  <div style="margin-bottom:4px;">&#8226; <?= h($md['filename']) ?><?= $md['notes']?' — <em style="color:#8a8070;font-size:12px;">'.h($md['notes']).'</em>':'' ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Documents -->
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Documents</div></div></div>
  <div class="doc-list">
    <?php if(empty($documents)): ?>
    <div style="padding:20px;text-align:center;color:#8a8070;font-size:13px;">No documents on file.</div>
    <?php endif; ?>
    <?php foreach($documents as $doc): ?>
    <div class="doc-row" style="<?= $doc['status']==='missing'?'opacity:0.6;':'' ?>">
      <div class="doc-icon <?= $doc['file_type'] === 'pdf' ? 'pdf' : ($doc['file_type']==='xlsx'?'xls':'misc') ?>"><?= $doc['file_type']==='pdf'?'&#128196;':($doc['file_type']==='xlsx'?'&#128200;':'&#9888;') ?></div>
      <div class="doc-info">
        <div class="doc-name" style="<?= $doc['status']==='missing'?'color:#b84c2e;':'' ?>"><?= h($doc['filename']) ?></div>
        <div class="doc-meta"><?= $doc['uploaded_at']?date('M j',strtotime($doc['uploaded_at'])):($doc['notes']?h($doc['notes']):'Missing') ?><?= $doc['uploaded_by']&&$doc['uploaded_at']?' &middot; '.h($doc['uploaded_by']):'' ?></div>
      </div>
      <div class="doc-size" style="<?= $doc['status']==='missing'?'color:#b84c2e;':'' ?>"><?= $doc['file_size_kb']?number_format($doc['file_size_kb']).' KB':($doc['status']==='missing'?'Needed':'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Business info -->
<div class="panel">
  <div class="panel-header"><div class="panel-title">Business Info</div></div>
  <div class="info-list">
    <div class="info-row"><div class="info-label">Entity</div><div class="info-value"><?= entity_label($client['entity_type']) ?></div></div>
    <?php if($client['industry']): ?><div class="info-row"><div class="info-label">Industry</div><div class="info-value"><?= h($client['industry']) ?></div></div><?php endif; ?>
    <?php if($client['incorporated_year']): ?><div class="info-row"><div class="info-label">Incorporated</div><div class="info-value"><?= (int)$client['incorporated_year'] ?></div></div><?php endif; ?>
    <?php if($client['employees']): ?><div class="info-row"><div class="info-label">Employees</div><div class="info-value"><?= h($client['employees']) ?></div></div><?php endif; ?>
    <?php if($client['revenue_est']): ?><div class="info-row"><div class="info-label">Revenue</div><div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;">$<?= number_format((float)$client['revenue_est']) ?></div></div><?php endif; ?>
    <?php if($client['payroll_provider']): ?><div class="info-row"><div class="info-label">Payroll</div><div class="info-value"><?= h($client['payroll_provider']) ?></div></div><?php endif; ?>
    <?php if($client['accounting_sw']): ?><div class="info-row"><div class="info-label">Accounting</div><div class="info-value"><?= h($client['accounting_sw']) ?></div></div><?php endif; ?>
  </div>
</div>

<!-- Invoices -->
<div class="panel">
  <div class="panel-header"><div><div class="panel-title">Invoices</div></div></div>
  <div class="info-list">
    <?php if(empty($invoices)): ?><div style="padding:16px;color:#8a8070;font-size:13px;">No invoices yet.</div><?php endif; ?>
    <?php foreach($invoices as $inv): ?>
    <div class="info-row">
      <div class="info-label"><?= h($inv['invoice_ref']) ?></div>
      <div class="info-value" style="display:flex;align-items:center;gap:8px;">
        <span style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;">$<?= number_format((float)$inv['amount']) ?></span>
        <span class="status-pill <?= $inv['status']==='paid'?'pill-filed':'pill-pending' ?>" style="font-size:10px;padding:2px 7px;"><?= ucfirst($inv['status']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if($invoices): ?>
    <div class="info-row" style="background:#faf8f4;">
      <div class="info-label" style="font-weight:600;color:#0f1117;">Total Billed</div>
      <div class="info-value" style="font-family:'DM Mono','Courier New',monospace;font-size:13px;font-weight:700;">$<?= number_format(array_sum(array_column($invoices,'amount'))) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
```

  </div><!-- /.detail-side -->
</div>

<?php include 'footer.php'; ?>