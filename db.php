<?php
/**
 * db.php — TaxFlow CRM database connection
 * Include at the top of every page that needs DB access:
 *   require_once 'db.php';
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'opbvihjf5mioj6gs_taxflow');
define('DB_USER', 'opbvihjf5mioj6gs_flowtax');
define('DB_PASS', 'wajhar-pewnic-9govWu');
define('DB_CHAR', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In production, log and show generic error — never expose credentials
            error_log('TaxFlow DB error: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;color:#b84c2e;">
                    <strong>Database connection error.</strong> Please contact your administrator.
                 </div>');
        }
    }
    return $pdo;
}

/* ── Helpers ── */

/**
 * Format a decimal as a dollar amount.
 * Positive → green "+$1,234", negative → red "-$1,234"
 */
function fmt_money(float $n, bool $color = true): string {
    $abs  = abs($n);
    $fmt  = '$' . number_format($abs, 0);
    if (!$color) return ($n < 0 ? '-' : '') . $fmt;
    if ($n > 0) return '<span style="color:#4e7260;">+' . $fmt . '</span>';
    if ($n < 0) return '<span style="color:#b84c2e;">-' . $fmt . '</span>';
    return $fmt;
}

/** Entity type → human label */
function entity_label(string $type): string {
    $map = [
        'individual'  => 'Individual',
        's_corp'      => 'S-Corporation',
        'c_corp'      => 'C-Corporation',
        'llc'         => 'LLC',
        'partnership' => 'Partnership',
        'trust'       => 'Trust / Estate',
        'nonprofit'   => 'Non-Profit',
    ];
    return $map[$type] ?? ucfirst($type);
}

/** Entity type → CSS segment pill class */
function entity_pill_class(string $type): string {
    $map = [
        'individual'  => 'seg-individual',
        's_corp'      => 'seg-scorp',
        'c_corp'      => 'seg-ccorp',
        'llc'         => 'seg-llc',
        'partnership' => 'seg-llc',
        'trust'       => 'seg-nonprofit',
        'nonprofit'   => 'seg-nonprofit',
    ];
    return $map[$type] ?? 'seg-individual';
}

/** Status → CSS pill class */
function status_pill_class(string $s): string {
    $map = [
        'not_started'  => 'pill-progress',
        'awaiting_docs'=> 'pill-pending',
        'in_progress'  => 'pill-progress',
        'in_review'    => 'pill-review',
        'filed'        => 'pill-filed',
        'extension'    => 'pill-pending',
        'archived'     => 'pill-pending',
    ];
    return $map[$s] ?? 'pill-progress';
}

/** Status → human label */
function status_label(string $s): string {
    $map = [
        'not_started'  => 'Not Started',
        'awaiting_docs'=> 'Awaiting Docs',
        'in_progress'  => 'In Progress',
        'in_review'    => 'In Review',
        'filed'        => 'Filed',
        'extension'    => 'Extension',
        'archived'     => 'Archived',
    ];
    return $map[$s] ?? ucwords(str_replace('_', ' ', $s));
}

/** Due date → CSS class based on proximity */
function due_class(string $due_date, string $status): string {
    if ($status === 'filed') return 'on-track';
    $days = (strtotime($due_date) - time()) / 86400;
    if ($days < 0)  return 'overdue';
    if ($days < 21) return 'due-soon';
    return 'on-track';
}

/** Two-letter initials from a display name */
function make_initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    if (count($words) >= 2) {
        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

/** Safe HTML output */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}