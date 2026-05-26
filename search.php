<?php
/**
 * search.php — TaxFlow CRM Global Search API
 * Returns JSON. Called via fetch() from the search bar.
 * Usage: GET search.php?q=henderson
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once 'db.php';

$q = trim($_GET['q'] ?? '');

// Require at least 3 characters
if (mb_strlen($q) < 3) {
    echo json_encode(['results' => [], 'query' => $q]);
    exit;
}

$like  = '%' . $q . '%';
$results = [];

/* ── Clients ── */
$stmt = db()->prepare("
    SELECT
        c.id,
        c.display_name  AS name,
        c.entity_type,
        c.ein,
        c.ssn_last4,
        c.email,
        c.avatar_color,
        c.initials,
        c.status         AS client_status,
        r.status         AS return_status,
        r.form_type,
        r.id             AS return_id
    FROM clients c
    LEFT JOIN tax_returns r ON r.client_id = c.id AND r.tax_year = 2025
    WHERE c.status = 'active'
      AND (
          c.display_name LIKE ?
          OR c.ein        LIKE ?
          OR c.email      LIKE ?
          OR c.contact_name LIKE ?
          OR c.ssn_last4  LIKE ?
      )
    ORDER BY c.display_name ASC
    LIMIT 6
");
$stmt->execute([$like, $like, $like, $like, $like]);
$clients = $stmt->fetchAll();

foreach ($clients as $c) {
    $sub = entity_label($c['entity_type']);
    if ($c['ein'])       $sub .= ' · ' . $c['ein'];
    elseif ($c['ssn_last4']) $sub .= ' · ***-**-' . $c['ssn_last4'];
    if ($c['email'])     $sub .= ' · ' . $c['email'];

    $results[] = [
        'type'    => 'client',
        'id'      => (int)$c['id'],
        'url'     => 'client-detail.php?id=' . (int)$c['id'],
        'name'    => $c['name'],
        'sub'     => $sub,
        'badge'   => $c['return_status'] ? status_label($c['return_status']) : null,
        'badge_class' => $c['return_status'] ? status_pill_class($c['return_status']) : null,
        'avatar'  => $c['initials'] ?: make_initials($c['name']),
        'color'   => $c['avatar_color'] ?: '#3d5a47',
        'icon'    => 'client',
    ];
}

/* ── Tax Returns ── */
$stmt = db()->prepare("
    SELECT
        r.id,
        r.form_type,
        r.tax_year,
        r.status,
        r.return_ref,
        r.due_date,
        r.refund_amount,
        c.display_name  AS client_name,
        c.avatar_color,
        c.initials,
        c.id            AS client_id
    FROM tax_returns r
    JOIN clients c ON c.id = r.client_id
    WHERE c.status = 'active'
      AND (
          c.display_name LIKE ?
          OR r.return_ref LIKE ?
          OR r.form_type  LIKE ?
          OR r.notes      LIKE ?
      )
    ORDER BY r.tax_year DESC, c.display_name ASC
    LIMIT 5
");
$stmt->execute([$like, $like, $like, $like]);
$returns = $stmt->fetchAll();

foreach ($returns as $r) {
    $sub = 'Form ' . $r['form_type'] . ' · ' . $r['tax_year'];
    if ($r['due_date']) $sub .= ' · Due ' . date('M j, Y', strtotime($r['due_date']));

    $results[] = [
        'type'       => 'return',
        'id'         => (int)$r['id'],
        'url'        => 'return-detail.php?id=' . (int)$r['id'],
        'name'       => $r['client_name'] . ' — Form ' . $r['form_type'],
        'sub'        => $sub,
        'badge'      => status_label($r['status']),
        'badge_class'=> status_pill_class($r['status']),
        'avatar'     => $r['initials'] ?: make_initials($r['client_name']),
        'color'      => $r['avatar_color'] ?: '#3d5a47',
        'icon'       => 'return',
    ];
}

/* ── Sort: clients first, then returns, deduplicate by url ── */
$seen = [];
$deduped = [];
foreach ($results as $item) {
    if (!isset($seen[$item['url']])) {
        $seen[$item['url']] = true;
        $deduped[] = $item;
    }
}

// Limit total to 8 results
$deduped = array_slice($deduped, 0, 8);

echo json_encode([
    'results' => $deduped,
    'query'   => $q,
    'count'   => count($deduped),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);