<?php
require_once 'db.php';

/* ── Actions ── */
$action = $_POST['action'] ?? '';

if ($action === 'update_status' && !empty($_POST['id'])) {
    $stmt = db()->prepare('UPDATE tax_returns SET status=?, filed_date=IF(?,NOW(),NULL), completion_pct=? WHERE id=?');
    $new_status = $_POST['status'];
    $stmt->execute([$new_status, $new_status==='filed', $new_status==='filed'?100:(int)($_POST['pct']??50), (int)$_POST['id']]);
    header('Location: returns.php?msg=updated'); exit;
}

if ($action === 'add') {
    $stmt = db()->prepare('INSERT INTO tax_returns (client_id,tax_year,form_type,jurisdiction,status,due_date,assignee_id,fee) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([(int)$_POST['client_id'],2025,$_POST['form_type']??'1040',$_POST['jurisdiction']??'Federal','not_started',$_POST['due_date']??'2026-04-15',(int)($_POST['assignee_id']??1),(float)($_POST['fee']??0)]);
    header('Location: return-detail.php?id='.db()->lastInsertId().'&msg=created'); exit;
}

/* ── Filters ── */
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$form    = $_GET['form'] ?? '';
$asgn    = (int)($_GET['assignee'] ?? 0);
$sort    = $_GET['sort'] ?? 'due';
$page    = max(1,(int)($_GET['page']??1));
$per     = 15;

$where = ['1=1']; $params = [];
if ($search!==''){$where[]='(c.display_name LIKE ? OR r.return_ref LIKE ?)';$like='%'.$search.'%';$params=array_merge($params,[$like,$like]);}
if ($status!==''){$where[]='r.status=?';$params[]=$status;}
if ($form!==''){$where[]='r.form_type=?';$params[]=$form;}
if ($asgn>0){$where[]='r.assignee_id=?';$params[]=$asgn;}
$wsql=implode(' AND ',$where);
$osql=match($sort){'client'=>'c.display_name ASC','status'=>'r.status ASC','refund'=>'r.refund_amount DESC',default=>'r.due_date ASC, r.status ASC'};

$cs=db()->prepare("SELECT COUNT(*) FROM tax_returns r JOIN clients c ON c.id=r.client_id WHERE r.tax_year=2025 AND $wsql");
$cs->execute($params); $total=(int)$cs->fetchColumn();
$pages=max(1,(int)ceil($total/$per)); $offset=($page-1)*$per;

$stmt=db()->prepare("SELECT r.*,c.display_name,c.initials,c.avatar_color,c.entity_type,s.name AS aname,s.initials AS ainit,s.color AS acolor FROM tax_returns r JOIN clients c ON c.id=r.client_id LEFT JOIN staff s ON s.id=r.assignee_id WHERE r.tax_year=2025 AND $wsql ORDER BY $osql LIMIT $per OFFSET $offset");
$stmt->execute($params); $returns=$stmt->fetchAll();

$ss=db()->query("SELECT COUNT(*) AS tot,SUM(status='filed') AS filed,SUM(status IN('in_progress','in_review','awaiting_docs')) AS prog,SUM(status='extension') AS ext FROM tax_returns WHERE tax_year=2025")->fetch();
$staff=db()->query('SELECT * FROM staff WHERE active=1 ORDER BY name')->fetchAll();
$all_clients=db()->query("SELECT id,display_name FROM clients WHERE status='active' ORDER BY display_name")->fetchAll();

$page_title='Returns'; $active_nav='returns';
include 'header.php';
?>

<?php if(!empty($_GET['msg'])): ?><div style="background:#daeade;border:1px solid #3d5a47;color:#2a5c35;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;">Return <?= $_GET['msg']==='updated'?'updated':($_GET['msg']==='created'?'created':'') ?> successfully.</div><?php endif; ?>

<div class="page-header">
  <div><div class="page-heading">Tax Returns</div><div class="page-heading-sub"><?= (int)$ss['filed'] ?> of <?= (int)$ss['tot'] ?> filed &middot; 2025 Tax Year</div></div>
  <div class="page-actions">
    <button class="btn-outline" onclick="document.getElementById('modal-add-return').style.display='flex'">+ New Return</button>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['tot'] ?></div><div class="stat-strip-lbl">Total Returns</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['filed'] ?></div><div class="stat-strip-lbl">Filed</div><div class="stat-strip-change up">&#8593; 2025 season</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['prog'] ?></div><div class="stat-strip-lbl">In Progress</div><div class="stat-strip-change warn">&#9650; Active</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['ext'] ?></div><div class="stat-strip-lbl">Extensions</div></div>
</div>

<form method="get" action="returns.php">
<div class="toolbar">
  <div class="toolbar-search">
    <svg width="13" height="13" viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search client, return ref...">
  </div>
  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <?php foreach(['filed'=>'Filed','in_review'=>'In Review','in_progress'=>'In Progress','awaiting_docs'=>'Awaiting Docs','extension'=>'Extension','not_started'=>'Not Started'] as $v=>$l): ?>
    <option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select class="filter-select" name="form" onchange="this.form.submit()">
    <option value="">All Forms</option>
    <?php foreach(['1040','1120-S','1065','1120','1041'] as $f): ?><option value="<?= $f ?>" <?= $form===$f?'selected':'' ?>>Form <?= $f ?></option><?php endforeach; ?>
  </select>
  <select class="filter-select" name="assignee" onchange="this.form.submit()">
    <option value="">All Assignees</option>
    <?php foreach($staff as $st): ?><option value="<?= $st['id'] ?>" <?= $asgn===(int)$st['id']?'selected':'' ?>><?= h($st['name']) ?></option><?php endforeach; ?>
  </select>
  <select class="filter-select" name="sort" onchange="this.form.submit()">
    <option value="due" <?= $sort==='due'?'selected':'' ?>>Sort: Due Date</option>
    <option value="client" <?= $sort==='client'?'selected':'' ?>>Sort: Client</option>
    <option value="status" <?= $sort==='status'?'selected':'' ?>>Sort: Status</option>
    <option value="refund" <?= $sort==='refund'?'selected':'' ?>>Sort: Refund</option>
  </select>
  <button type="submit" class="btn-outline" style="padding:7px 14px;">Filter</button>
</div>
</form>

<div class="panel">
  <div class="table-wrapper">
    <table class="returns-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Form</th>
          <th>Due Date</th>
          <th>Progress</th>
          <th>Status</th>
          <th>Refund / Owed</th>
          <th>Assignee</th>
          <th>Fee</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($returns as $r): ?>
      <?php $dc=due_class($r['due_date'],$r['status']); ?>
      <tr onclick="window.location='return-detail.php?id=<?= $r['id'] ?>'" style="cursor:pointer;">
        <td>
          <div class="client-name-cell">
            <div class="client-avatar" style="background:<?= h($r['avatar_color']) ?>;"><?= h($r['initials']?:make_initials($r['display_name'])) ?></div>
            <div><div class="client-name"><?= h($r['display_name']) ?></div><div class="client-type"><?= h(strtoupper(str_replace('_',' ',$r['entity_type']))) ?></div></div>
          </div>
        </td>
        <td><span class="form-badge"><?= h($r['form_type']) ?></span></td>
        <td><div class="due-date <?= $dc ?>"><?= date('M j, Y',strtotime($r['due_date'])) ?><?= $dc==='overdue'?' &#9888;':($dc==='on-track'&&$r['status']==='filed'?' &#10003;':'') ?></div></td>
        <td>
          <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill <?= status_pill_class($r['status'])===('pill-filed')?'filed':($r['status']==='in_review'?'review':($r['status']==='in_progress'?'progress':'pending')) ?>" style="width:<?= (int)$r['completion_pct'] ?>%;height:100%;border-radius:3px;"></div></div>
            <div class="progress-pct"><?= (int)$r['completion_pct'] ?>%</div>
          </div>
        </td>
        <td><span class="status-pill <?= status_pill_class($r['status']) ?>"><?= status_label($r['status']) ?></span></td>
        <td><?= fmt_money((float)$r['refund_amount']) ?></td>
        <td><div class="assignee"><div class="assignee-dot" style="background:<?= h($r['acolor']??'#3d5a47') ?>;"><?= h($r['ainit']??'?') ?></div><?= h($r['aname']??'') ?></div></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:12.5px;"><?= $r['fee']?'$'.number_format((float)$r['fee']):'—' ?></td>
        <td onclick="event.stopPropagation()"><a href="return-detail.php?id=<?= $r['id'] ?>" class="btn-outline" style="padding:4px 10px;font-size:11px;">Open</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($returns)): ?><tr><td colspan="9" style="text-align:center;padding:32px;color:#8a8070;">No returns match your filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <div class="pagination-info">Showing <?= $total>0?$offset+1:0 ?>–<?= min($offset+$per,$total) ?> of <?= $total ?> returns</div>
    <div class="pagination-controls">
      <?php if($page>1): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">&#8592;</a><?php else: ?><button class="page-btn" disabled>&#8592;</button><?php endif; ?>
      <?php for($p=max(1,$page-2);$p<=min($pages,$page+2);$p++): ?><a class="page-btn <?= $p===$page?'active':'' ?>" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$p]))) ?>"><?= $p ?></a><?php endfor; ?>
      <?php if($page<$pages): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">&#8594;</a><?php else: ?><button class="page-btn" disabled>&#8594;</button><?php endif; ?>
    </div>
  </div>
</div>

<!-- NEW RETURN MODAL -->

<div id="modal-add-return" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fffdf8;border-radius:12px;width:100%;max-width:480px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,0.2);">
    <div style="padding:18px 22px;border-bottom:1px solid #d4cec2;display:flex;align-items:center;justify-content:space-between;">
      <span style="font-family:'DM Serif Display',Georgia,serif;font-size:18px;">New Return</span>
      <button onclick="document.getElementById('modal-add-return').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#8a8070;line-height:1;">&times;</button>
    </div>
    <form method="post" action="returns.php" style="padding:20px 22px;display:flex;flex-direction:column;gap:13px;">
      <input type="hidden" name="action" value="add">
      <div>
        <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Client *</label>
        <select name="client_id" required class="filter-select" style="width:100%;">
          <option value="">— Select client —</option>
          <?php foreach($all_clients as $cl): ?><option value="<?= $cl['id'] ?>"><?= h($cl['display_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Form Type</label>
          <select name="form_type" class="filter-select" style="width:100%;">
            <?php foreach(['1040','1120-S','1065','1120','1041'] as $f): ?><option value="<?= $f ?>">Form <?= $f ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Due Date</label>
          <input name="due_date" type="date" value="2026-04-15" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:13px;font-family:inherit;outline:none;background:#fffdf8;">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Assignee</label>
          <select name="assignee_id" class="filter-select" style="width:100%;">
            <?php foreach($staff as $st): ?><option value="<?= $st['id'] ?>"><?= h($st['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Fee ($)</label>
          <input name="fee" type="number" min="0" step="50" placeholder="0" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:14px;font-family:inherit;outline:none;background:#fffdf8;">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:4px;">
        <button type="button" onclick="document.getElementById('modal-add-return').style.display='none'" class="btn-outline">Cancel</button>
        <button type="submit" class="topbar-btn btn-primary">Create Return</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>