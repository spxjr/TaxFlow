<?php
require_once 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'delete' && !empty($_POST['id'])) {
    db()->prepare('UPDATE clients SET status = "archived" WHERE id = ?')->execute([(int)$_POST['id']]);
    header('Location: clients.php?msg=deleted'); exit;
}

if ($action === 'add') {
    $name = trim($_POST['display_name'] ?? '');
    if ($name !== '') {
        $stmt = db()->prepare('INSERT INTO clients (display_name,initials,entity_type,email,ein,assignee_id,client_since,status) VALUES (?,?,?,?,?,?,YEAR(NOW()),"active")');
        $stmt->execute([$name, make_initials($name), $_POST['entity_type']??'individual', trim($_POST['email']??''), trim($_POST['ein']??''), (int)($_POST['assignee_id']??1)]);
        header('Location: client-detail.php?id='.db()->lastInsertId().'&msg=created'); exit;
    }
}

$search  = trim($_GET['q'] ?? '');
$entity  = $_GET['entity'] ?? '';
$asgn    = (int)($_GET['assignee'] ?? 0);
$sort    = $_GET['sort'] ?? 'name';
$view    = $_GET['view'] ?? 'grid';
$page    = max(1,(int)($_GET['page']??1));
$per     = 9;

$where = ["c.status != 'archived'"]; $params = [];
if ($search!==''){$where[]='(c.display_name LIKE ? OR c.ein LIKE ? OR c.email LIKE ?)'; $like='%'.$search.'%'; $params=array_merge($params,[$like,$like,$like]);}
if ($entity!==''){$where[]='c.entity_type=?';$params[]=$entity;}
if ($asgn>0){$where[]='c.assignee_id=?';$params[]=$asgn;}
$wsql = implode(' AND ',$where);
$osql = match($sort){'revenue'=>'c.revenue_est DESC','updated'=>'c.updated_at DESC',default=>'c.display_name ASC'};

$total=(int)db()->prepare("SELECT COUNT(*) FROM clients c WHERE $wsql")->execute($params) ? db()->prepare("SELECT COUNT(*) FROM clients c WHERE $wsql")->execute($params) : 0;
$cs=db()->prepare("SELECT COUNT(*) FROM clients c WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetchColumn();
$pages=max(1,(int)ceil($total/$per)); $offset=($page-1)*$per;

$stmt=db()->prepare("SELECT c.*,s.name AS aname,s.initials AS ainit,s.color AS acolor,(SELECT r.status FROM tax_returns r WHERE r.client_id=c.id AND r.tax_year=2025 LIMIT 1) AS rstatus,(SELECT SUM(i.amount) FROM invoices i WHERE i.client_id=c.id AND YEAR(i.issued_date)=2026) AS fees,(SELECT COUNT(*) FROM documents d WHERE d.client_id=c.id AND d.status='missing') AS missing FROM clients c LEFT JOIN staff s ON s.id=c.assignee_id WHERE $wsql ORDER BY $osql LIMIT $per OFFSET $offset");
$stmt->execute($params); $clients=$stmt->fetchAll();

$ss=db()->query("SELECT COUNT(*) AS tot,COALESCE((SELECT COUNT(*) FROM tax_returns WHERE status='filed' AND tax_year=2025),0) AS filed,COALESCE((SELECT COUNT(*) FROM documents WHERE status='missing'),0) AS miss,COALESCE((SELECT SUM(amount) FROM invoices WHERE YEAR(issued_date)=2026),0) AS rev FROM clients WHERE status='active'")->fetch();
$staff=db()->query('SELECT * FROM staff WHERE active=1 ORDER BY name')->fetchAll();

$page_title='All Clients'; $active_nav='clients';
include 'header.php';
?>

<?php if(!empty($_GET['msg'])): ?><div style="background:#daeade;border:1px solid #3d5a47;color:#2a5c35;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;"><?= $_GET['msg']==='deleted'?'Client archived.':($_GET['msg']==='created'?'Client created.':'') ?></div><?php endif; ?>

<div class="page-header">
  <div><div class="page-heading">All Clients</div><div class="page-heading-sub"><?= (int)$ss['tot'] ?> active &middot; 2026 Season</div></div>
  <div class="page-actions">
    <button class="btn-outline" onclick="document.getElementById('modal-add').style.display='flex'">+ New Client</button>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['tot'] ?></div><div class="stat-strip-lbl">Clients</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['filed'] ?></div><div class="stat-strip-lbl">Returns Filed</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val"><?= (int)$ss['miss'] ?></div><div class="stat-strip-lbl">Docs Needed</div></div>
  <div class="stat-strip-item"><div class="stat-strip-val">$<?= number_format((float)$ss['rev']/1000,0) ?>k</div><div class="stat-strip-lbl">Revenue YTD</div></div>
</div>

<form method="get" action="clients.php">
<div class="toolbar">
  <div class="toolbar-search">
    <svg width="13" height="13" viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by name, EIN, email...">
  </div>
  <select class="filter-select" name="entity" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach(['individual'=>'Individual','s_corp'=>'S-Corp','c_corp'=>'C-Corp','llc'=>'LLC','partnership'=>'Partnership','trust'=>'Trust'] as $v=>$l): ?>
    <option value="<?= $v ?>" <?= $entity===$v?'selected':'' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select class="filter-select" name="assignee" onchange="this.form.submit()">
    <option value="">All Assignees</option>
    <?php foreach($staff as $st): ?><option value="<?= $st['id'] ?>" <?= $asgn===(int)$st['id']?'selected':'' ?>><?= h($st['name']) ?></option><?php endforeach; ?>
  </select>
  <select class="filter-select" name="sort" onchange="this.form.submit()">
    <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A–Z</option>
    <option value="revenue" <?= $sort==='revenue'?'selected':'' ?>>Revenue ↓</option>
    <option value="updated" <?= $sort==='updated'?'selected':'' ?>>Last Active</option>
  </select>
  <input type="hidden" name="view" value="<?= h($view) ?>">
  <button type="submit" class="btn-outline" style="padding:7px 14px;">Search</button>
  <div class="view-toggle">
    <a class="view-btn <?= $view==='grid'?'active':'' ?>" href="?<?= h(http_build_query(array_merge($_GET,['view'=>'grid']))) ?>" title="Grid"><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 1h5v5H1V1zm7 0h5v5H8V1zM1 8h5v5H1V8zm7 0h5v5H8V8z"/></svg></a>
    <a class="view-btn <?= $view==='list'?'active':'' ?>" href="?<?= h(http_build_query(array_merge($_GET,['view'=>'list']))) ?>" title="List"><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 2h12v2H1V2zm0 4h12v2H1V6zm0 4h12v2H1v-2z"/></svg></a>
  </div>
</div>
</form>

<?php if(empty($clients)): ?>

<div class="panel" style="padding:40px;text-align:center;color:#8a8070;">No clients found. <a href="clients.php" style="color:#c8922a;">Clear filters</a></div>
<?php elseif($view==='list'): ?>
<div class="panel">
  <div class="table-wrapper">
    <table class="client-table">
      <thead><tr><th>Client</th><th>Type</th><th>EIN / SSN</th><th>Status</th><th>Assignee</th><th>Fee YTD</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php foreach($clients as $c): ?>
      <tr onclick="window.location='client-detail.php?id=<?= $c['id'] ?>'" style="cursor:pointer;">
        <td><div class="client-name-cell"><div class="client-avatar" style="background:<?= h($c['avatar_color']) ?>;"><?= h($c['initials']?:make_initials($c['display_name'])) ?></div><div><div class="client-name"><?= h($c['display_name']) ?></div><div class="client-type"><?= h(entity_label($c['entity_type'])) ?></div></div></div></td>
        <td><span class="segment-pill <?= entity_pill_class($c['entity_type']) ?>"><?= entity_label($c['entity_type']) ?></span></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:12px;color:#8a8070;"><?= $c['ein']?h($c['ein']):'***-**-'.h($c['ssn_last4']) ?></td>
        <td><?php if($c['rstatus']): ?><span class="status-pill <?= status_pill_class($c['rstatus']) ?>"><?= status_label($c['rstatus']) ?></span><?php else: ?><span style="color:#8a8070;font-size:12px;">—</span><?php endif; ?></td>
        <td><div class="assignee"><div class="assignee-dot" style="background:<?= h($c['acolor']??'#3d5a47') ?>;"><?= h($c['ainit']??'?') ?></div><?= h($c['aname']??'') ?></div></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:13px;color:#4e7260;"><?= $c['fees']?'$'.number_format((float)$c['fees']):'—' ?></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:11.5px;color:#8a8070;"><?= date('M j',strtotime($c['updated_at'])) ?></td>
        <td onclick="event.stopPropagation()">
          <a href="client-detail.php?id=<?= $c['id'] ?>" class="btn-outline" style="padding:4px 10px;font-size:11px;">View</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Archive this client?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="btn-outline" style="padding:4px 10px;font-size:11px;color:#b84c2e;">Archive</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <div class="pagination-info">Showing <?= $offset+1 ?>–<?= min($offset+$per,$total) ?> of <?= $total ?></div>
    <div class="pagination-controls">
      <?php if($page>1): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">&#8592;</a><?php else: ?><button class="page-btn" disabled>&#8592;</button><?php endif; ?>
      <?php for($p=max(1,$page-2);$p<=min($pages,$page+2);$p++): ?><a class="page-btn <?= $p===$page?'active':'' ?>" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$p]))) ?>"><?= $p ?></a><?php endfor; ?>
      <?php if($page<$pages): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">&#8594;</a><?php else: ?><button class="page-btn" disabled>&#8594;</button><?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="client-grid">
<?php foreach($clients as $c): ?>
<a href="client-detail.php?id=<?= $c['id'] ?>" class="client-card" style="text-decoration:none;color:inherit;">
  <div class="client-card-top">
    <div><div class="client-card-name"><?= h($c['display_name']) ?></div><div class="client-card-type"><?= h(entity_label($c['entity_type'])) ?></div></div>
    <div class="client-card-avatar" style="background:<?= h($c['avatar_color']) ?>;"><?= h($c['initials']?:make_initials($c['display_name'])) ?></div>
  </div>
  <div class="client-card-meta">
    <?php if($c['rstatus']): ?><span class="status-pill <?= status_pill_class($c['rstatus']) ?>"><?= status_label($c['rstatus']) ?></span><?php endif; ?>
    <span class="segment-pill <?= entity_pill_class($c['entity_type']) ?>"><?= entity_label($c['entity_type']) ?></span>
  </div>
  <div style="margin-top:8px;font-size:12px;color:#8a8070;font-family:'DM Mono','Courier New',monospace;"><?= $c['ein']?'EIN: '.h($c['ein']):'SSN: ***-**-'.h($c['ssn_last4']) ?> &middot; <?= h($c['ainit']??'') ?></div>
  <?php if($c['missing']>0): ?><div style="margin-top:5px;font-size:11.5px;color:#b84c2e;font-family:'DM Mono','Courier New',monospace;">&#9888; <?= (int)$c['missing'] ?> doc<?= $c['missing']>1?'s':'' ?> needed</div><?php endif; ?>
  <div class="client-card-footer">
    <div class="client-card-amount"><?= $c['fees']?'$'.number_format((float)$c['fees']):'—' ?></div>
    <div class="client-card-date"><?= date('M j',strtotime($c['updated_at'])) ?></div>
  </div>
</a>
<?php endforeach; ?>
</div>
<div class="panel" style="margin-top:0;">
  <div class="pagination">
    <div class="pagination-info">Showing <?= $offset+1 ?>–<?= min($offset+$per,$total) ?> of <?= $total ?></div>
    <div class="pagination-controls">
      <?php if($page>1): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">&#8592;</a><?php else: ?><button class="page-btn" disabled>&#8592;</button><?php endif; ?>
      <?php for($p=max(1,$page-2);$p<=min($pages,$page+2);$p++): ?><a class="page-btn <?= $p===$page?'active':'' ?>" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$p]))) ?>"><?= $p ?></a><?php endfor; ?>
      <?php if($page<$pages): ?><a class="page-btn" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">&#8594;</a><?php else: ?><button class="page-btn" disabled>&#8594;</button><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- NEW CLIENT MODAL -->

<div id="modal-add" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fffdf8;border-radius:12px;width:100%;max-width:500px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,0.2);">
    <div style="padding:18px 22px;border-bottom:1px solid #d4cec2;display:flex;align-items:center;justify-content:space-between;">
      <span style="font-family:'DM Serif Display',Georgia,serif;font-size:18px;">New Client</span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#8a8070;line-height:1;">&times;</button>
    </div>
    <form method="post" action="clients.php" style="padding:20px 22px;display:flex;flex-direction:column;gap:13px;">
      <input type="hidden" name="action" value="add">
      <div>
        <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Name *</label>
        <input name="display_name" required placeholder="e.g. Henderson & Walsh LLC" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:14px;font-family:inherit;outline:none;background:#fffdf8;">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Entity Type</label>
          <select name="entity_type" class="filter-select" style="width:100%;">
            <option value="individual">Individual</option>
            <option value="s_corp">S-Corporation</option>
            <option value="c_corp">C-Corporation</option>
            <option value="llc">LLC</option>
            <option value="partnership">Partnership</option>
            <option value="trust">Trust / Estate</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Assignee</label>
          <select name="assignee_id" class="filter-select" style="width:100%;">
            <?php foreach($staff as $st): ?><option value="<?= $st['id'] ?>"><?= h($st['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">Email</label>
          <input name="email" type="email" placeholder="client@example.com" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:14px;font-family:inherit;outline:none;background:#fffdf8;">
        </div>
        <div>
          <label style="display:block;font-family:'DM Mono','Courier New',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#8a8070;margin-bottom:5px;">EIN / Tax ID</label>
          <input name="ein" placeholder="XX-XXXXXXX" style="width:100%;padding:9px 12px;border:1px solid #d4cec2;border-radius:7px;font-size:14px;font-family:inherit;outline:none;background:#fffdf8;">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:4px;">
        <button type="button" onclick="document.getElementById('modal-add').style.display='none'" class="btn-outline">Cancel</button>
        <button type="submit" class="topbar-btn btn-primary">Create Client</button>
      </div>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>