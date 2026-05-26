<?php
require_once 'db.php';

/* ── Date range filter ── */
$year      = (int)($_GET['year'] ?? 2026);
$tax_year  = (int)($_GET['tax_year'] ?? 2025);

/* ── Key financial metrics ── */
$metrics = db()->prepare("
    SELECT
        COALESCE(SUM(i.amount), 0)                                         AS revenue_total,
        COALESCE(SUM(CASE WHEN i.status='paid'   THEN i.amount END), 0)   AS revenue_collected,
        COALESCE(SUM(CASE WHEN i.status='unpaid' THEN i.amount END), 0)   AS revenue_outstanding,
        COUNT(DISTINCT i.client_id)                                        AS billed_clients,
        COUNT(i.id)                                                        AS invoice_count,
        COALESCE(AVG(i.amount), 0)                                         AS avg_invoice
    FROM invoices i
    WHERE YEAR(i.issued_date) = ?
")->execute([$year]) ? null : null;

$m = db()->prepare("SELECT COALESCE(SUM(i.amount),0) AS rev_total, COALESCE(SUM(CASE WHEN i.status='paid' THEN i.amount END),0) AS rev_coll, COALESCE(SUM(CASE WHEN i.status='unpaid' THEN i.amount END),0) AS rev_out, COUNT(DISTINCT i.client_id) AS billed, COUNT(i.id) AS inv_count, COALESCE(AVG(i.amount),0) AS avg_inv FROM invoices i WHERE YEAR(i.issued_date)=?");
$m->execute([$year]); $metrics = $m->fetch();

/* ── Monthly revenue ── */
$monthly = db()->prepare("
    SELECT MONTH(issued_date) AS mo, MONTHNAME(issued_date) AS mon_name,
           SUM(amount) AS total, SUM(CASE WHEN status='paid' THEN amount END) AS collected
    FROM invoices
    WHERE YEAR(issued_date) = ?
    GROUP BY MONTH(issued_date), MONTHNAME(issued_date)
    ORDER BY mo
");
$monthly->execute([$year]); $monthly_data = $monthly->fetchAll();

/* Build full 12-month array */
$months_full = [];
for ($i = 1; $i <= 12; $i++) {
    $months_full[$i] = ['mo'=>$i,'mon_name'=>date('M',mktime(0,0,0,$i,1)),'total'=>0,'collected'=>0];
}
foreach ($monthly_data as $row) {
    $months_full[(int)$row['mo']] = $row;
}
$max_monthly = max(1, max(array_column($months_full, 'total')));

/* ── Returns by status ── */
$ret_status = db()->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM tax_returns WHERE tax_year=?
    GROUP BY status ORDER BY cnt DESC
");
$ret_status->execute([$tax_year]); $ret_by_status = $ret_status->fetchAll();
$total_returns = array_sum(array_column($ret_by_status, 'cnt'));

/* ── Returns by form type ── */
$ret_form = db()->prepare("
    SELECT form_type, COUNT(*) AS cnt, COALESCE(SUM(fee),0) AS fees
    FROM tax_returns WHERE tax_year=?
    GROUP BY form_type ORDER BY cnt DESC
");
$ret_form->execute([$tax_year]); $ret_by_form = $ret_form->fetchAll();

/* ── Clients by entity type ── */
$cli_type = db()->query("
    SELECT entity_type, COUNT(*) AS cnt
    FROM clients WHERE status='active'
    GROUP BY entity_type ORDER BY cnt DESC
");
$cli_by_type = $cli_type->fetchAll();
$total_clients = array_sum(array_column($cli_by_type, 'cnt'));

/* ── Top clients by revenue ── */
$top_clients = db()->prepare("
    SELECT c.display_name, c.avatar_color, c.initials, c.entity_type,
           SUM(i.amount) AS total_fees,
           COUNT(DISTINCT r.id) AS return_count
    FROM clients c
    JOIN invoices i ON i.client_id = c.id AND YEAR(i.issued_date) = ?
    LEFT JOIN tax_returns r ON r.client_id = c.id AND r.tax_year = ?
    GROUP BY c.id, c.display_name, c.avatar_color, c.initials, c.entity_type
    ORDER BY total_fees DESC
    LIMIT 8
");
$top_clients->execute([$year, $tax_year]); $top = $top_clients->fetchAll();
$max_fee = max(1, (float)($top[0]['total_fees'] ?? 1));

/* ── Filing completion ── */
$filing = db()->prepare("
    SELECT
        SUM(status='filed')        AS filed,
        SUM(status='in_review')    AS in_review,
        SUM(status='in_progress')  AS in_progress,
        SUM(status='awaiting_docs')AS awaiting,
        SUM(status='not_started')  AS not_started,
        SUM(status='extension')    AS extension,
        COUNT(*)                   AS total
    FROM tax_returns WHERE tax_year=?
");
$filing->execute([$tax_year]); $fl = $filing->fetch();

/* ── Donut chart helpers ── */
$status_colors = [
    'filed'        => '#3d5a47',
    'in_review'    => '#c8922a',
    'in_progress'  => '#2a6496',
    'awaiting_docs'=> '#b84c2e',
    'not_started'  => '#d4cec2',
    'extension'    => '#7c4d8a',
];

$entity_colors = [
    'individual'  => '#2a6496',
    's_corp'      => '#c8922a',
    'c_corp'      => '#b84c2e',
    'llc'         => '#3d5a47',
    'partnership' => '#7c4d8a',
    'trust'       => '#4a7c6e',
    'nonprofit'   => '#4a5568',
];

/* Donut SVG builder */
function donut_svg(array $slices, float $total, int $size = 120, int $thickness = 22): string {
    if ($total <= 0) return '';
    $r = ($size / 2) - ($thickness / 2);
    $cx = $size / 2; $cy = $size / 2;
    $circum = 2 * M_PI * $r;
    $offset = -0.25 * $circum; // start at 12 o'clock

    $svg = '<svg width="'.$size.'" height="'.$size.'" class="donut-svg" viewBox="0 0 '.$size.' '.$size.'">';
    // Background ring
    $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="#ede9df" stroke-width="'.$thickness.'"/>';

    foreach ($slices as $s) {
        $pct   = $s['val'] / $total;
        $dash  = $pct * $circum;
        $gap   = $circum - $dash;
        $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none"'
             . ' stroke="'.$s['color'].'" stroke-width="'.$thickness.'"'
             . ' stroke-dasharray="'.round($dash,2).' '.round($gap,2).'"'
             . ' stroke-dashoffset="'.round($offset,2).'"'
             . ' stroke-linecap="butt"/>';
        $offset -= $dash;
    }

    // Centre label
    $pct_done = $total > 0 ? round($slices[0]['val'] / $total * 100) : 0;
    $svg .= '<text x="'.$cx.'" y="'.($cy-4).'" text-anchor="middle" font-family="DM Serif Display,Georgia,serif" font-size="'.($size/5).'" fill="#0f1117">'.$pct_done.'%</text>';
    $svg .= '<text x="'.$cx.'" y="'.($cy+12).'" text-anchor="middle" font-family="DM Mono,Courier New,monospace" font-size="7" fill="#8a8070" letter-spacing="1">OF TARGET</text>';
    $svg .= '</svg>';
    return $svg;
}

$page_title = 'Reports';
$active_nav = 'reports';
$topbar_btn = '&#8681; Export PDF';
$topbar_href = '#';
include 'header.php';
?>

<!-- Header -->

<div class="page-header">
  <div>
    <div class="page-heading">Reports</div>
    <div class="page-heading-sub">
      <?= $year ?> Revenue &middot; <?= $tax_year ?> Tax Year
    </div>
  </div>
  <div class="page-actions">
    <!-- Year picker -->
    <form method="get" action="reports.php" style="display:flex;align-items:center;gap:8px;">
      <select name="year" class="filter-select" onchange="this.form.submit()">
        <?php foreach ([2026,2025,2024] as $y): ?>
        <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?> Revenue</option>
        <?php endforeach; ?>
      </select>
      <select name="tax_year" class="filter-select" onchange="this.form.submit()">
        <?php foreach ([2025,2024,2023] as $ty): ?>
        <option value="<?= $ty ?>" <?= $tax_year===$ty?'selected':'' ?>><?= $ty ?> Returns</option>
        <?php endforeach; ?>
      </select>
    </form>
    <button class="btn-outline export-btn" onclick="window.print()">&#128196; Print Report</button>
  </div>
</div>

<!-- ── KPI Tiles ── -->

<div class="reports-grid-3" style="grid-template-columns:repeat(3,1fr);">
  <div class="metric-tile t-gold">
    <div class="metric-tile-val">$<?= number_format((float)$metrics['rev_total']) ?></div>
    <div class="metric-tile-label">Total Billed <?= $year ?></div>
    <div class="metric-tile-change up">&#8593; <?= number_format((float)$metrics['inv_count']) ?> invoices</div>
  </div>
  <div class="metric-tile t-moss">
    <div class="metric-tile-val">$<?= number_format((float)$metrics['rev_coll']) ?></div>
    <div class="metric-tile-label">Revenue Collected</div>
    <div class="metric-tile-change up" style="color:#4e7260;">
      <?= $metrics['rev_total']>0 ? round($metrics['rev_coll']/$metrics['rev_total']*100).'% collection rate' : '—' ?>
    </div>
  </div>
  <div class="metric-tile t-rust">
    <div class="metric-tile-val">$<?= number_format((float)$metrics['rev_out']) ?></div>
    <div class="metric-tile-label">Outstanding AR</div>
    <?php if ((float)$metrics['rev_out'] > 0): ?>
    <div class="metric-tile-change down">&#9650; Awaiting payment</div>
    <?php endif; ?>
  </div>
</div>

<div class="reports-grid-3" style="grid-template-columns:repeat(3,1fr);margin-top:0;">
  <div class="metric-tile t-slate">
    <div class="metric-tile-val"><?= (int)$fl['total'] ?></div>
    <div class="metric-tile-label">Total Returns <?= $tax_year ?></div>
    <div class="metric-tile-change up">&#8593; <?= (int)$fl['filed'] ?> filed</div>
  </div>
  <div class="metric-tile t-moss">
    <div class="metric-tile-val"><?= $fl['total']>0?round($fl['filed']/$fl['total']*100):0 ?>%</div>
    <div class="metric-tile-label">Filing Completion</div>
    <div class="metric-tile-change <?= ($fl['total']>0&&$fl['filed']/$fl['total']>.5)?'up':'warn' ?>">
      <?= (int)$fl['filed'] ?> of <?= (int)$fl['total'] ?> returns
    </div>
  </div>
  <div class="metric-tile t-purple">
    <div class="metric-tile-val">$<?= number_format((float)$metrics['avg_inv']) ?></div>
    <div class="metric-tile-label">Avg. Invoice Value</div>
    <div class="metric-tile-change up">&#8593; Per engagement</div>
  </div>
</div>

<!-- ── Revenue by Month ── -->

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-header">
    <div>
      <div class="panel-title">Revenue by Month</div>
      <div class="panel-subtitle"><?= $year ?> &middot; BILLED VS COLLECTED</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:#8a8070;font-family:'DM Mono','Courier New',monospace;">
        <div style="width:10px;height:10px;background:#c8922a;border-radius:2px;"></div> Billed
      </div>
      <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:#8a8070;font-family:'DM Mono','Courier New',monospace;">
        <div style="width:10px;height:10px;background:#3d5a47;border-radius:2px;"></div> Collected
      </div>
    </div>
  </div>
  <div class="month-bars">
    <?php foreach ($months_full as $mo): ?>
    <?php $pct = $max_monthly > 0 ? round((float)$mo['total'] / $max_monthly * 100) : 0;
          $col_pct = $mo['total'] > 0 ? round((float)$mo['collected'] / (float)$mo['total'] * 100) : 0;
          $is_current = (int)$mo['mo'] === (int)date('n') && $year === (int)date('Y');
    ?>
    <div class="month-bar-row">
      <div class="month-bar-label"><?= $mo['mon_name'] ?></div>
      <div style="flex:1;position:relative;">
        <div class="month-bar-track">
          <!-- Billed bar -->
          <div class="month-bar-fill <?= $is_current?'current':'' ?>" style="width:<?= $pct ?>%;opacity:0.35;"></div>
        </div>
        <!-- Collected bar overlaid -->
        <?php if ($mo['collected'] > 0): ?>
        <div class="month-bar-track" style="margin-top:3px;">
          <div class="month-bar-fill" style="width:<?= round((float)$mo['collected']/$max_monthly*100) ?>%;background:#3d5a47;"></div>
        </div>
        <?php endif; ?>
      </div>
      <div class="month-bar-val"><?= $mo['total']>0?'$'.number_format((float)$mo['total']/1000,1).'k':'' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Two-column: filing status donut + clients donut ── -->

<div class="reports-grid">

  <!-- Filing status -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Returns by Status</div><div class="panel-subtitle"><?= $tax_year ?> TAX YEAR</div></div>
      <a href="returns.php" class="panel-action">View all &rarr;</a>
    </div>
    <div class="donut-wrap">
      <?php
      $donut_slices = [];
      $status_order = ['filed','in_review','in_progress','awaiting_docs','extension','not_started'];
      foreach ($status_order as $sk) {
          $val = (int)($fl[$sk==='awaiting_docs'?'awaiting':($sk==='not_started'?'not_started':$sk)] ?? 0);
          if ($val > 0) $donut_slices[] = ['val'=>$val,'color'=>$status_colors[$sk]??'#d4cec2'];
      }
      echo donut_svg($donut_slices, (int)$fl['total']);
      ?>
      <div class="donut-legend">
        <?php foreach ($status_order as $sk):
          $map_key = $sk==='awaiting_docs'?'awaiting':($sk==='not_started'?'not_started':$sk);
          $val = (int)($fl[$map_key] ?? 0);
          if ($val === 0) continue;
          $pct = $fl['total']>0?round($val/$fl['total']*100):0;
        ?>
        <div class="donut-legend-item">
          <div class="donut-swatch" style="background:<?= $status_colors[$sk]??'#d4cec2' ?>;"></div>
          <div class="donut-legend-label"><?= status_label($sk) ?></div>
          <div class="donut-legend-val"><?= $val ?></div>
          <div class="donut-legend-pct"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Clients by entity type -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Clients by Type</div><div class="panel-subtitle">ACTIVE CLIENT MIX</div></div>
      <a href="clients.php" class="panel-action">View all &rarr;</a>
    </div>
    <div class="donut-wrap">
      <?php
      $type_slices = [];
      foreach ($cli_by_type as $ct) {
          $type_slices[] = ['val'=>(int)$ct['cnt'],'color'=>$entity_colors[$ct['entity_type']]??'#d4cec2'];
      }
      echo donut_svg($type_slices, $total_clients);
      ?>
      <div class="donut-legend">
        <?php foreach ($cli_by_type as $ct):
          $pct = $total_clients>0?round($ct['cnt']/$total_clients*100):0;
        ?>
        <div class="donut-legend-item">
          <div class="donut-swatch" style="background:<?= $entity_colors[$ct['entity_type']]??'#d4cec2' ?>;"></div>
          <div class="donut-legend-label"><?= entity_label($ct['entity_type']) ?></div>
          <div class="donut-legend-val"><?= $ct['cnt'] ?></div>
          <div class="donut-legend-pct"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Revenue by Form Type + Top Clients ── -->

<div class="reports-grid">

  <!-- Returns by form type -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Returns by Form Type</div><div class="panel-subtitle"><?= $tax_year ?> TAX YEAR</div></div>
    </div>
    <div class="hbar-list">
      <?php
      $max_form = max(1, (int)($ret_by_form[0]['cnt'] ?? 1));
      foreach ($ret_by_form as $rf):
        $pct = round($rf['cnt'] / $max_form * 100);
        $colors = ['1040'=>'#2a6496','1120-S'=>'#c8922a','1065'=>'#3d5a47','1120'=>'#b84c2e','1041'=>'#7c4d8a'];
        $color = $colors[$rf['form_type']] ?? '#4a5568';
      ?>
      <div class="hbar-row">
        <div class="hbar-row-top">
          <div class="hbar-label">Form <?= h($rf['form_type']) ?></div>
          <div class="hbar-val">$<?= number_format((float)$rf['fees']) ?> &mdash; <?= $rf['cnt'] ?> returns</div>
        </div>
        <div class="hbar-track">
          <div class="hbar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($ret_by_form)): ?>
      <div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">No return data for <?= $tax_year ?>.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top clients by revenue -->

  <div class="panel">
    <div class="panel-header">
      <div><div class="panel-title">Top Clients by Revenue</div><div class="panel-subtitle"><?= $year ?> BILLED</div></div>
    </div>
    <div class="hbar-list">
      <?php foreach ($top as $tc):
        $pct = round((float)$tc['total_fees'] / $max_fee * 100);
      ?>
      <div class="hbar-row">
        <div class="hbar-row-top">
          <div class="hbar-label" style="display:flex;align-items:center;gap:8px;">
            <div class="client-avatar" style="width:22px;height:22px;font-size:9px;background:<?= h($tc['avatar_color']) ?>;"><?= h($tc['initials']?:make_initials($tc['display_name'])) ?></div>
            <?= h($tc['display_name']) ?>
          </div>
          <div class="hbar-val">$<?= number_format((float)$tc['total_fees']) ?></div>
        </div>
        <div class="hbar-track">
          <div class="hbar-fill" style="width:<?= $pct ?>%;background:#c8922a;"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($top)): ?>
      <div style="padding:24px;text-align:center;color:#8a8070;font-size:13px;">No invoice data for <?= $year ?>.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Full invoice/returns table ── -->

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-header">
    <div><div class="panel-title">Invoice Summary</div><div class="panel-subtitle"><?= $year ?> &middot; ALL CLIENTS</div></div>
    <button class="export-btn" onclick="window.print()">&#128196; Export</button>
  </div>
  <div class="table-wrapper">
    <table class="report-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Invoice Ref</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Issued</th>
          <th>Paid</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $inv_all = db()->prepare("
          SELECT i.*, c.display_name, c.avatar_color, c.initials, c.entity_type
          FROM invoices i
          JOIN clients c ON c.id = i.client_id
          WHERE YEAR(i.issued_date) = ?
          ORDER BY i.issued_date DESC
      ");
      $inv_all->execute([$year]);
      $all_inv = $inv_all->fetchAll();
      foreach ($all_inv as $inv):
      ?>
      <tr>
        <td>
          <div class="client-name-cell">
            <div class="client-avatar" style="background:<?= h($inv['avatar_color']) ?>;"><?= h($inv['initials']?:make_initials($inv['display_name'])) ?></div>
            <div>
              <div class="client-name"><?= h($inv['display_name']) ?></div>
              <div class="client-type"><?= entity_label($inv['entity_type']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:12px;color:#8a8070;"><?= h($inv['invoice_ref']) ?></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:13px;font-weight:600;">$<?= number_format((float)$inv['amount']) ?></td>
        <td>
          <span class="status-pill <?= $inv['status']==='paid'?'pill-filed':'pill-pending' ?>">
            <?= ucfirst($inv['status']) ?>
          </span>
        </td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:11.5px;color:#8a8070;"><?= date('M j, Y',strtotime($inv['issued_date'])) ?></td>
        <td style="font-family:'DM Mono','Courier New',monospace;font-size:11.5px;color:#4e7260;"><?= $inv['paid_date']?date('M j, Y',strtotime($inv['paid_date'])):'—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($all_inv)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:#8a8070;">No invoices for <?= $year ?>.</td></tr><?php endif; ?>
      </tbody>
      <tfoot>
        <tr style="background:#faf8f4;">
          <td colspan="2" style="padding:11px 22px;font-weight:600;font-size:13px;">Totals</td>
          <td style="padding:11px 22px;font-family:'DM Mono','Courier New',monospace;font-size:13px;font-weight:700;color:#0f1117;">$<?= number_format((float)$metrics['rev_total']) ?></td>
          <td style="padding:11px 22px;">
            <span style="font-family:'DM Mono','Courier New',monospace;font-size:11px;color:#4e7260;">$<?= number_format((float)$metrics['rev_coll']) ?> paid</span>
          </td>
          <td colspan="2" style="padding:11px 22px;font-family:'DM Mono','Courier New',monospace;font-size:11px;color:#b84c2e;">$<?= number_format((float)$metrics['rev_out']) ?> outstanding</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>