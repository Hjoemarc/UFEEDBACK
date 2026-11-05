<?php
/* ============================================================
   admin_reports.php — Comprehensive ARTA Feedback Analytics
   ============================================================ */

/* ---------- 1. Diagnostics ---------- */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$GLOBALS['__fatal_shown'] = false;

set_exception_handler(function (Throwable $e) {
    $GLOBALS['__fatal_shown'] = true;
    if (!headers_sent()) http_response_code(500);
    echo "<div style='background:#fee2e2;color:#991b1b;padding:24px;margin:20px;font-family:sans-serif;border-radius:8px;border:1px solid #f87171;'>";
    echo "<h3 style='margin-top:0;'>Uncaught " . htmlspecialchars(get_class($e)) . "</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " on line <strong>" . (int)$e->getLine() . "</strong></p>";
    echo "</div>";
});

register_shutdown_function(function () {
    if (!empty($GLOBALS['__fatal_shown'])) return;
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo "<div style='background:#fee2e2;color:#991b1b;padding:24px;margin:20px;font-family:sans-serif;border-radius:8px;border:1px solid #f87171;'>";
        echo "<h3 style='margin-top:0;'>PHP Fatal Error Caught</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " on line <strong>" . (int)$error['line'] . "</strong></p>";
        echo "</div>";
    }
});

/* ---------- 2. Auth ---------- */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    header("Location: student_dashboard.php"); exit();
}

/* ---------- 3. DB ---------- */
$db_file = __DIR__ . '/db_connection.php';
if (!is_file($db_file)) { http_response_code(500); die("Missing db_connection.php"); }
require_once $db_file;
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); die("db_connection.php must expose \$conn"); }
if ($conn->connect_errno) { http_response_code(500); die("MySQL connection failed: " . htmlspecialchars($conn->connect_error)); }

mysqli_report(MYSQLI_REPORT_OFF);
@$conn->set_charset('utf8mb4');

/* ---------- 4. Schema helpers ---------- */
function csm_table_exists(mysqli $c, string $t): bool {
    $t = $c->real_escape_string($t);
    $r = @$c->query("SHOW TABLES LIKE '{$t}'");
    return ($r instanceof mysqli_result && $r->num_rows > 0);
}
function csm_table_columns(mysqli $c, string $t): array {
    $cols = [];
    $r = @$c->query("SHOW COLUMNS FROM `{$t}`");
    if ($r instanceof mysqli_result) { while ($row = $r->fetch_assoc()) $cols[$row['Field']] = true; $r->free(); }
    return $cols;
}
function run_q(mysqli $c, string $sql, string $types = '', array $params = []): array {
    if ($types === '' || empty($params)) {
        $r = @$c->query($sql);
        if (!$r instanceof mysqli_result) return [];
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        $r->free();
        return $rows;
    }
    $stmt = @$c->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res instanceof mysqli_result) while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}
function arta_rating(?float $m): array {
    if ($m === null) return ['—', 'rating-na'];
    if ($m >= 4.21) return ['Outstanding', 'rating-outstanding'];
    if ($m >= 3.41) return ['Very Satisfactory', 'rating-vs'];
    if ($m >= 2.61) return ['Satisfactory', 'rating-s'];
    if ($m >= 1.81) return ['Unsatisfactory', 'rating-us'];
    if ($m > 0)     return ['Poor', 'rating-p'];
    return ['No Rating', 'rating-na'];
}
function fmt_num($n, int $d = 2): string {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, $d);
}

$has_offices  = csm_table_exists($conn, 'offices');
$has_feedback = csm_table_exists($conn, 'arta_feedback');
$has_services = csm_table_exists($conn, 'services');
$has_users    = csm_table_exists($conn, 'users');

$sqd_cols = [
    'sqd1_responsiveness'    => 'Responsiveness',
    'sqd2_reliability'       => 'Reliability',
    'sqd3_access_facilities' => 'Access & Facilities',
    'sqd4_communication'     => 'Communication',
    'sqd5_costs'             => 'Costs',
    'sqd6_integrity'         => 'Integrity',
    'sqd7_assurance'         => 'Assurance',
    'sqd8_outcome'           => 'Outcome',
];

$fb_cols = $has_feedback ? csm_table_columns($conn, 'arta_feedback') : [];
$feedback_ready = $has_feedback && isset($fb_cols['feedback_id'], $fb_cols['office_id'], $fb_cols['service_accessed']);
if ($feedback_ready) {
    foreach (array_keys($sqd_cols) as $c) {
        if (!isset($fb_cols[$c])) { $feedback_ready = false; break; }
    }
}

$date_col = null;
foreach (['submitted_at','created_at','feedback_date','date_submitted','timestamp','date_created','submission_timestamp'] as $c) {
    if (isset($fb_cols[$c])) { $date_col = $c; break; }
}

/* ---------- 5. Reference lists ---------- */
$offices_list = [];
if ($has_offices) {
    $r = @$conn->query("SELECT id, office_name, office_type FROM offices ORDER BY office_name ASC");
    if ($r instanceof mysqli_result) { while ($row = $r->fetch_assoc()) $offices_list[] = $row; $r->free(); }
}
$services_list = [];
if ($has_services && $has_offices) {
    $r = @$conn->query("SELECT s.id, s.service_name, s.office_id, o.office_name FROM services s LEFT JOIN offices o ON s.office_id = o.id ORDER BY o.office_name ASC, s.service_name ASC");
    if ($r instanceof mysqli_result) { while ($row = $r->fetch_assoc()) $services_list[] = $row; $r->free(); }
}

/* ---------- 6. Filters ---------- */
$filter_from    = trim($_GET['date_from'] ?? '');
$filter_to      = trim($_GET['date_to'] ?? '');
$filter_office  = (int)($_GET['office_id'] ?? 0);
$filter_service = (int)($_GET['service_id'] ?? 0);
$filter_client  = trim($_GET['client_type'] ?? '');
$filter_preset  = trim($_GET['preset'] ?? '');

if ($filter_preset !== '' && $filter_from === '' && $filter_to === '') {
    $today = new DateTime('now');
    switch ($filter_preset) {
        case '7d':   $filter_from = (clone $today)->modify('-7 days')->format('Y-m-d');  break;
        case '30d':  $filter_from = (clone $today)->modify('-30 days')->format('Y-m-d'); break;
        case 'tm':   $filter_from = $today->format('Y-m-01'); $filter_to = $today->format('Y-m-t'); break;
        case 'lm':   $filter_from = (clone $today)->modify('first day of last month')->format('Y-m-d');
                     $filter_to   = (clone $today)->modify('last day of last month')->format('Y-m-d'); break;
        case 'ty':   $filter_from = $today->format('Y-01-01'); $filter_to = $today->format('Y-12-31'); break;
        case 'all':  $filter_from = ''; $filter_to = ''; break;
    }
}

$where_sql = "1=1";
$params = [];
$types  = '';

if ($feedback_ready) {
    if ($date_col && $filter_from !== '') { $where_sql .= " AND DATE(f.`{$date_col}`) >= ?"; $params[] = $filter_from; $types .= 's'; }
    if ($date_col && $filter_to   !== '') { $where_sql .= " AND DATE(f.`{$date_col}`) <= ?"; $params[] = $filter_to;   $types .= 's'; }
    if ($filter_office > 0) { $where_sql .= " AND f.office_id = ?"; $params[] = $filter_office; $types .= 'i'; }
    if ($filter_client !== '' && isset($fb_cols['client_type'])) {
        $where_sql .= " AND f.client_type = ?"; $params[] = $filter_client; $types .= 's';
    }
    if ($filter_service > 0) {
        foreach ($services_list as $s) {
            if ((int)$s['id'] === $filter_service) {
                $where_sql .= " AND f.office_id = ? AND BINARY TRIM(f.service_accessed) = BINARY TRIM(?)";
                $params[] = (int)$s['office_id']; $types .= 'i';
                $params[] = $s['service_name'];   $types .= 's';
                break;
            }
        }
    }
}

/* Count active filters for the mobile badge */
$active_filters = 0;
if ($filter_preset !== '')   $active_filters++;
if ($filter_from !== '')     $active_filters++;
if ($filter_to !== '')       $active_filters++;
if ($filter_office > 0)      $active_filters++;
if ($filter_service > 0)     $active_filters++;
if ($filter_client !== '')   $active_filters++;

/* ---------- 7. Aggregations ---------- */
$overall      = ['total' => 0, 'mean' => null, 'positive' => 0, 'negative' => 0, 'valid' => 0];
$sqd_means    = [];
$office_rows  = [];
$service_rows = [];
$monthly      = [];
$distribution = ['Outstanding' => 0, 'Very Satisfactory' => 0, 'Satisfactory' => 0, 'Unsatisfactory' => 0, 'Poor' => 0];

$demographics = [
    'client_type' => [],
    'age_group'   => [],
    'sex'         => [],
    'region'      => [],
    'account'     => ['Guest' => 0, 'Authenticated' => 0],
];

$cc_analysis = [
    'cc1' => ['saw' => 0, 'knew' => 0, 'learned' => 0, 'none' => 0, 'aware_rate' => 0],
    'cc2' => ['easy' => 0, 'somewhat' => 0, 'difficult' => 0, 'not_visible' => 0, 'na' => 0, 'visible_rate' => 0],
    'cc3' => ['helped_much' => 0, 'somewhat' => 0, 'did_not' => 0, 'na' => 0, 'helpful_rate' => 0],
];

$low_rating_rows = [];
$recent_comments = [];

if ($feedback_ready) {
    $sum_parts = $cnt_parts = [];
    foreach (array_keys($sqd_cols) as $c) {
        $sum_parts[] = "COALESCE(NULLIF(f.`{$c}`, 0), 0)";
        $cnt_parts[] = "CASE WHEN f.`{$c}` > 0 THEN 1 ELSE 0 END";
    }
    $sum_sql = implode(' + ', $sum_parts);
    $cnt_sql = implode(' + ', $cnt_parts);
    $mean_expr = "AVG( ({$sum_sql}) / NULLIF(({$cnt_sql}), 0) )";
    $row_mean_expr = "( ({$sum_sql}) / NULLIF(({$cnt_sql}), 0) )";

    // ---- Overall KPIs ----
    $res = run_q($conn,
        "SELECT
            COUNT(f.feedback_id) AS total,
            ROUND({$mean_expr}, 3) AS mean,
            SUM({$cnt_sql}) AS valid,
            SUM(CASE WHEN f.sqd1_responsiveness >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd2_reliability >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd3_access_facilities >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd4_communication >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd5_costs >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd6_integrity >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd7_assurance >= 4 THEN 1 ELSE 0 END +
                CASE WHEN f.sqd8_outcome >= 4 THEN 1 ELSE 0 END) AS positive,
            SUM(CASE WHEN f.sqd1_responsiveness IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd2_reliability IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd3_access_facilities IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd4_communication IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd5_costs IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd6_integrity IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd7_assurance IN (1,2) THEN 1 ELSE 0 END +
                CASE WHEN f.sqd8_outcome IN (1,2) THEN 1 ELSE 0 END) AS negative
         FROM arta_feedback f WHERE {$where_sql}",
        $types, $params);
    if (!empty($res[0])) {
        $overall['total']    = (int)$res[0]['total'];
        $overall['mean']     = ($res[0]['mean'] !== null) ? (float)$res[0]['mean'] : null;
        $overall['valid']    = (int)($res[0]['valid'] ?? 0);
        $overall['positive'] = (int)($res[0]['positive'] ?? 0);
        $overall['negative'] = (int)($res[0]['negative'] ?? 0);
    }

    $satisfaction_rate = $overall['valid'] > 0 ? round(($overall['positive'] / $overall['valid']) * 100, 1) : 0.0;

    // ---- Per SQD ----
    foreach ($sqd_cols as $col => $label) {
        $res = run_q($conn,
            "SELECT ROUND(AVG(NULLIF(f.`{$col}`, 0)), 3) AS mean,
                    COUNT(NULLIF(f.`{$col}`, 0)) AS cnt
             FROM arta_feedback f WHERE {$where_sql}",
            $types, $params);
        $sqd_means[$col] = [
            'label' => $label,
            'mean'  => (!empty($res[0]) && $res[0]['mean'] !== null) ? (float)$res[0]['mean'] : null,
            'count' => !empty($res[0]) ? (int)$res[0]['cnt'] : 0,
        ];
    }

    // ---- Per office ----
    $office_rows = run_q($conn,
        "SELECT o.id, o.office_name, o.office_type,
                COUNT(f.feedback_id) AS total,
                ROUND({$mean_expr}, 3) AS mean
         FROM arta_feedback f
         JOIN offices o ON f.office_id = o.id
         WHERE {$where_sql}
         GROUP BY o.id, o.office_name, o.office_type
         ORDER BY mean DESC, total DESC",
        $types, $params);

    // ---- Per service ----
    $service_rows = run_q($conn,
        "SELECT TRIM(f.service_accessed) AS service_name,
                o.office_name, o.office_type,
                COUNT(f.feedback_id) AS total,
                ROUND({$mean_expr}, 3) AS mean
         FROM arta_feedback f
         JOIN offices o ON f.office_id = o.id
         WHERE {$where_sql} AND TRIM(f.service_accessed) <> ''
         GROUP BY TRIM(f.service_accessed), o.office_name, o.office_type
         ORDER BY mean DESC, total DESC",
        $types, $params);

    // ---- Monthly trend ----
    if ($date_col) {
        $monthly = run_q($conn,
            "SELECT DATE_FORMAT(f.`{$date_col}`, '%Y-%m') AS ym,
                    COUNT(f.feedback_id) AS total,
                    ROUND({$mean_expr}, 3) AS mean
             FROM arta_feedback f
             WHERE {$where_sql}
             GROUP BY ym
             ORDER BY ym ASC",
            $types, $params);
    }

    // ---- Rating distribution ----
    $rows = run_q($conn,
        "SELECT {$row_mean_expr} AS row_mean FROM arta_feedback f WHERE {$where_sql}",
        $types, $params);
    foreach ($rows as $r) {
        if ($r['row_mean'] === null) continue;
        $m = (float)$r['row_mean'];
        if ($m >= 4.21)      $distribution['Outstanding']++;
        elseif ($m >= 3.41)  $distribution['Very Satisfactory']++;
        elseif ($m >= 2.61)  $distribution['Satisfactory']++;
        elseif ($m >= 1.81)  $distribution['Unsatisfactory']++;
        else                 $distribution['Poor']++;
    }

    // ---- Demographics ----
    if (isset($fb_cols['client_type'])) {
        $r = run_q($conn, "SELECT f.client_type AS k, COUNT(*) AS c FROM arta_feedback f WHERE {$where_sql} GROUP BY f.client_type ORDER BY c DESC", $types, $params);
        foreach ($r as $row) $demographics['client_type'][$row['k'] ?: 'Unspecified'] = (int)$row['c'];
    }
    if (isset($fb_cols['age_group'])) {
        $r = run_q($conn, "SELECT f.age_group AS k, COUNT(*) AS c FROM arta_feedback f WHERE {$where_sql} GROUP BY f.age_group ORDER BY c DESC", $types, $params);
        foreach ($r as $row) $demographics['age_group'][$row['k'] ?: 'Unspecified'] = (int)$row['c'];
    }
    if (isset($fb_cols['sex'])) {
        $r = run_q($conn, "SELECT f.sex AS k, COUNT(*) AS c FROM arta_feedback f WHERE {$where_sql} GROUP BY f.sex ORDER BY c DESC", $types, $params);
        foreach ($r as $row) $demographics['sex'][$row['k'] ?: 'Unspecified'] = (int)$row['c'];
    }
    if (isset($fb_cols['region'])) {
        $r = run_q($conn, "SELECT f.region AS k, COUNT(*) AS c FROM arta_feedback f WHERE {$where_sql} GROUP BY f.region ORDER BY c DESC LIMIT 8", $types, $params);
        foreach ($r as $row) $demographics['region'][$row['k'] ?: 'Unspecified'] = (int)$row['c'];
    }
    if (isset($fb_cols['user_id'])) {
        $r = run_q($conn, "SELECT
                SUM(CASE WHEN f.user_id IS NULL OR f.user_id = '' THEN 1 ELSE 0 END) AS guests,
                SUM(CASE WHEN f.user_id IS NOT NULL AND f.user_id <> '' THEN 1 ELSE 0 END) AS auths
             FROM arta_feedback f WHERE {$where_sql}", $types, $params);
        if (!empty($r[0])) {
            $demographics['account']['Guest']         = (int)$r[0]['guests'];
            $demographics['account']['Authenticated'] = (int)$r[0]['auths'];
        }
    }

    // ---- Citizen's Charter ----
    if (isset($fb_cols['cc1'], $fb_cols['cc2'], $fb_cols['cc3'])) {
        $r = run_q($conn,
            "SELECT
                SUM(CASE WHEN f.cc1 = 1 THEN 1 ELSE 0 END) AS cc1_saw,
                SUM(CASE WHEN f.cc1 = 2 THEN 1 ELSE 0 END) AS cc1_knew,
                SUM(CASE WHEN f.cc1 = 3 THEN 1 ELSE 0 END) AS cc1_learned,
                SUM(CASE WHEN f.cc1 = 4 THEN 1 ELSE 0 END) AS cc1_none,
                SUM(CASE WHEN f.cc2 = 1 THEN 1 ELSE 0 END) AS cc2_easy,
                SUM(CASE WHEN f.cc2 = 2 THEN 1 ELSE 0 END) AS cc2_somewhat,
                SUM(CASE WHEN f.cc2 = 3 THEN 1 ELSE 0 END) AS cc2_difficult,
                SUM(CASE WHEN f.cc2 = 4 THEN 1 ELSE 0 END) AS cc2_not_visible,
                SUM(CASE WHEN f.cc2 = 5 THEN 1 ELSE 0 END) AS cc2_na,
                SUM(CASE WHEN f.cc3 = 1 THEN 1 ELSE 0 END) AS cc3_helped_much,
                SUM(CASE WHEN f.cc3 = 2 THEN 1 ELSE 0 END) AS cc3_somewhat,
                SUM(CASE WHEN f.cc3 = 3 THEN 1 ELSE 0 END) AS cc3_did_not,
                SUM(CASE WHEN f.cc3 = 4 THEN 1 ELSE 0 END) AS cc3_na
             FROM arta_feedback f WHERE {$where_sql}",
            $types, $params);
        if (!empty($r[0])) {
            $cc = $r[0];
            $cc_analysis['cc1']['saw']     = (int)$cc['cc1_saw'];
            $cc_analysis['cc1']['knew']    = (int)$cc['cc1_knew'];
            $cc_analysis['cc1']['learned'] = (int)$cc['cc1_learned'];
            $cc_analysis['cc1']['none']    = (int)$cc['cc1_none'];
            $cc1_total = $cc_analysis['cc1']['saw'] + $cc_analysis['cc1']['knew'] + $cc_analysis['cc1']['learned'] + $cc_analysis['cc1']['none'];
            $cc_analysis['cc1']['aware_rate'] = $cc1_total > 0
                ? round((($cc_analysis['cc1']['saw'] + $cc_analysis['cc1']['knew'] + $cc_analysis['cc1']['learned']) / $cc1_total) * 100, 1)
                : 0;

            $cc_analysis['cc2']['easy']        = (int)$cc['cc2_easy'];
            $cc_analysis['cc2']['somewhat']    = (int)$cc['cc2_somewhat'];
            $cc_analysis['cc2']['difficult']   = (int)$cc['cc2_difficult'];
            $cc_analysis['cc2']['not_visible'] = (int)$cc['cc2_not_visible'];
            $cc_analysis['cc2']['na']          = (int)$cc['cc2_na'];
            $cc2_total = $cc_analysis['cc2']['easy'] + $cc_analysis['cc2']['somewhat'] + $cc_analysis['cc2']['difficult'] + $cc_analysis['cc2']['not_visible'];
            $cc_analysis['cc2']['visible_rate'] = $cc2_total > 0
                ? round((($cc_analysis['cc2']['easy'] + $cc_analysis['cc2']['somewhat']) / $cc2_total) * 100, 1)
                : 0;

            $cc_analysis['cc3']['helped_much'] = (int)$cc['cc3_helped_much'];
            $cc_analysis['cc3']['somewhat']    = (int)$cc['cc3_somewhat'];
            $cc_analysis['cc3']['did_not']     = (int)$cc['cc3_did_not'];
            $cc_analysis['cc3']['na']          = (int)$cc['cc3_na'];
            $cc3_total = $cc_analysis['cc3']['helped_much'] + $cc_analysis['cc3']['somewhat'] + $cc_analysis['cc3']['did_not'];
            $cc_analysis['cc3']['helpful_rate'] = $cc3_total > 0
                ? round((($cc_analysis['cc3']['helped_much'] + $cc_analysis['cc3']['somewhat']) / $cc3_total) * 100, 1)
                : 0;
        }
    }

    // ---- Low-rating rows ----
    $low_rating_rows = run_q($conn,
        "SELECT f.feedback_id, {$row_mean_expr} AS row_mean,
                TRIM(f.service_accessed) AS service_accessed,
                f.client_type, f.comments_recommendations,
                f.`{$date_col}` AS submitted_at,
                o.office_name
         FROM arta_feedback f
         LEFT JOIN offices o ON f.office_id = o.id
         WHERE {$where_sql} AND ({$row_mean_expr}) <= 2.0
         ORDER BY row_mean ASC, f.`{$date_col}` DESC
         LIMIT 15",
        $types, $params);

    // ---- Recent comments ----
    if (isset($fb_cols['comments_recommendations'])) {
        $recent_comments = run_q($conn,
            "SELECT f.feedback_id, {$row_mean_expr} AS row_mean,
                    TRIM(f.service_accessed) AS service_accessed,
                    f.client_type, f.comments_recommendations,
                    f.`{$date_col}` AS submitted_at,
                    o.office_name
             FROM arta_feedback f
             LEFT JOIN offices o ON f.office_id = o.id
             WHERE {$where_sql}
               AND f.comments_recommendations IS NOT NULL
               AND TRIM(f.comments_recommendations) <> ''
             ORDER BY f.`{$date_col}` DESC
             LIMIT 10",
            $types, $params);
    }
}

$satisfaction_rate = $overall['valid'] > 0 ? round(($overall['positive'] / $overall['valid']) * 100, 1) : 0.0;
$dissatisfaction_rate = $overall['valid'] > 0 ? round(($overall['negative'] / $overall['valid']) * 100, 1) : 0.0;

$offices_reporting = count($office_rows);
$services_eval     = count($service_rows);
$top_office        = $office_rows[0] ?? null;
$worst_office      = !empty($office_rows) ? end($office_rows) : null;

// Filter summary
$filter_parts = [];
if ($filter_from !== '' || $filter_to !== '') {
    $filter_parts[] = 'Period: ' . ($filter_from !== '' ? $filter_from : 'beginning') . ' → ' . ($filter_to !== '' ? $filter_to : 'today');
} else {
    $filter_parts[] = 'Period: All Time';
}
if ($filter_office > 0) {
    foreach ($offices_list as $o) if ((int)$o['id'] === $filter_office) { $filter_parts[] = 'Office: ' . $o['office_name']; break; }
}
if ($filter_service > 0) {
    foreach ($services_list as $s) if ((int)$s['id'] === $filter_service) { $filter_parts[] = 'Service: ' . $s['service_name']; break; }
}
if ($filter_client !== '') $filter_parts[] = 'Client: ' . $filter_client;
$filter_summary = implode('  ·  ', $filter_parts);

// Page metadata for admin_header.php
$page_title    = 'ARTA Reports & Analytics';
$page_crumb    = 'ARTA Compliance / MC 2022-05 Reports';
$page_subtitle = 'Comprehensive performance analysis and audit reporting';
$unread_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --maroon: #7A0000;
        --maroon-dark: #580000;
        --gold: #F2A900;
        --gold-light: #FFF8E6;
        --bg: #F8FAFC;
        --surface: #FFFFFF;
        --border: #E2E8F0;
        --border-strong: #CBD5E1;
        --text-heading: #0F172A;
        --text-body: #475569;
        --text-muted: #94A3B8;
        --success: #10B981;
        --success-bg: #ECFDF5;
        --danger: #EF4444;
        --danger-bg: #FEF2F2;
        --warning: #F59E0B;
        --info: #2563EB;

        --sidebar-width: 272px;
        --header-height: 72px;
    }

    html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
    * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
    body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

    /* ============================================================
       MAIN SHELL
       ============================================================ */
    .admin-main-wrap {
        margin-left: var(--sidebar-width);
        width: calc(100% - var(--sidebar-width));
        min-width: 0;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        overflow-x: hidden;
        transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .admin-container {
        width: 100%; max-width: 100%;
        padding: calc(var(--header-height) + 20px) 24px 40px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .fixed-header-deck {
        position: sticky;
        top: var(--header-height);
        z-index: 800;
        background: var(--bg);
        padding-top: 4px;
        padding-bottom: 12px;
    }

    /* ============================================================
       TOOLBAR
       ============================================================ */
    .toolbar {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); letter-spacing: -0.4px; margin: 0; }
    .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
    .toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    .btn-primary {
        background: var(--maroon);
        color: #FFFFFF;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.18s, transform 0.12s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        font-family: inherit;
    }
    .btn-primary:hover { background: var(--maroon-dark); }
    .btn-primary:active { transform: scale(0.98); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

    .btn-ghost {
        background: #FFFFFF;
        color: var(--text-heading);
        border: 1.5px solid var(--border);
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.18s, border-color 0.18s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        text-decoration: none;
        font-family: inherit;
    }
    .btn-ghost:hover { background: #F1F5F9; border-color: var(--border-strong); }

    /* ============================================================
       FILTER PANEL
       ============================================================ */
    .filter-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0;
        overflow: hidden;
    }

    .filter-toggle {
        display: none;
        width: 100%;
        padding: 12px 14px;
        background: transparent;
        border: none;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-heading);
        cursor: pointer;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        text-align: left;
        transition: background 0.18s ease;
    }
    .filter-toggle:hover { background: #F8FAFC; }

    .filter-toggle-left {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }
    .filter-toggle-icon {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        background: #FEF2F2;
        color: var(--maroon);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .filter-toggle-icon svg { width: 14px; height: 14px; }

    .filter-toggle-badge {
        font-size: 9.5px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 999px;
        background: var(--maroon);
        color: #FFFFFF;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .filter-toggle-chev {
        width: 16px;
        height: 16px;
        color: var(--text-muted);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
    }
    .filter-panel.open .filter-toggle-chev { transform: rotate(180deg); }

    .filter-body {
        padding: 12px 14px;
    }

    .filter-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .filter-controls label {
        font-size: 10.5px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .filter-controls input[type="date"],
    .filter-controls select {
        padding: 9px 10px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 12.5px;
        background: #F8FAFC;
        color: var(--text-heading);
        font-weight: 500;
        outline: none;
        transition: border-color 0.18s, background 0.18s;
        max-width: 200px;
        font-family: inherit;
    }
    .filter-controls input[type="date"]:focus,
    .filter-controls select:focus {
        border-color: var(--maroon);
        background: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(122, 0, 0, 0.06);
    }

    .filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .filter-actions .btn-primary,
    .filter-actions .btn-ghost {
        padding: 9px 14px;
    }

    /* ============================================================
       SECTION HEADERS
       ============================================================ */
    .section-label {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 26px 0 14px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: var(--text-muted);
    }
    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, var(--border-strong) 0%, transparent 100%);
    }
    .section-label .num {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--maroon);
        color: #FFFFFF;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10.5px;
        font-weight: 800;
        flex-shrink: 0;
    }

    /* ============================================================
       KPI CARDS
       ============================================================ */
    .kpi-grid {
        display: grid;
        /* Default to 3 columns for desktop, 6 cards total */
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
    .kpi-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 18px 16px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        position: relative;
        overflow: hidden;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: var(--maroon);
    }
    .kpi-card.kpi-gold::before { background: var(--gold); }
    .kpi-card.kpi-green::before { background: var(--success); }
    .kpi-card.kpi-blue::before { background: var(--info); }
    .kpi-card.kpi-red::before { background: var(--danger); }

    .kpi-label {
        font-size: 10.5px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 8px;
        line-height: 1.2;
    }
    .kpi-value {
        font-size: 26px;
        font-weight: 800;
        color: var(--text-heading);
        line-height: 1.1;
        letter-spacing: -0.6px;
        display: flex;
        align-items: baseline;
        gap: 8px;
        flex-wrap: wrap;
        word-break: break-word;
    }
    .kpi-value.kpi-value-sm { font-size: 15px; letter-spacing: -0.2px; line-height: 1.3; }
    .kpi-value small { font-size: 13px; font-weight: 600; color: var(--text-muted); }
    .kpi-sub {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 6px;
        font-weight: 600;
        line-height: 1.4;
    }

    .rating-badge {
        display: inline-block;
        font-size: 10.5px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        white-space: nowrap;
        letter-spacing: 0.2px;
    }
    .rating-outstanding { background: #ECFDF5; color: #059669; }
    .rating-vs { background: #EFF6FF; color: #2563EB; }
    .rating-s { background: #FFF8E6; color: #B45309; }
    .rating-us { background: #FFF7ED; color: #EA580C; }
    .rating-p { background: #FEF2F2; color: #DC2626; }
    .rating-na { background: #F1F5F9; color: #64748B; }

    /* ============================================================
       CHART / PANEL CARDS
       ============================================================ */
    .chart-grid {
        display: grid;
        grid-template-columns: 1.55fr 1fr;
        gap: 12px;
    }

    .chart-card, .panel-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        margin-bottom: 12px;
        min-width: 0; /* Prevents table overflow */
    }
    .chart-card h3, .panel-header h3 {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-heading);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }
    .chart-card h3 { margin-bottom: 12px; }
    .chart-card h3 span, .panel-header p {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: none;
        letter-spacing: 0;
    }
    .chart-wrap { position: relative; height: 260px; }
    .chart-wrap-sm { position: relative; height: 240px; }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* ============================================================
       MINI PANELS
       ============================================================ */
    .mini-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .mini-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        margin-bottom: 12px;
        min-width: 0;
    }

    /* CC Cards */
    .cc-mini-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .cc-mini {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px;
        min-width: 0;
    }
    .cc-mini-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--maroon);
        margin-bottom: 10px;
        border-bottom: 1px dashed #FECACA;
        padding-bottom: 6px;
    }
    .cc-mini-big {
        font-size: 22px;
        font-weight: 800;
        color: var(--text-heading);
        line-height: 1.1;
        margin-bottom: 8px;
    }
    .cc-mini-big small {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        display: block;
        margin-top: 2px;
    }
    .cc-mini-rows { display: flex; flex-direction: column; gap: 5px; }
    .cc-mini-row {
        display: flex;
        justify-content: space-between;
        font-size: 11.5px;
        color: var(--text-body);
        gap: 6px;
    }
    .cc-mini-row strong { color: var(--text-heading); }

    /* ============================================================
       TABLES
       ============================================================ */
    .table-scroll-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
        -webkit-overflow-scrolling: touch;
    }
    .table-scroll-wrap::-webkit-scrollbar { height: 6px; width: 6px; }
    .table-scroll-wrap::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }

    table { width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left; min-width: 620px; }
    th {
        background-color: #F1F5F9;
        color: var(--text-muted);
        font-weight: 700;
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        text-transform: uppercase;
        font-size: 10.5px;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        color: var(--text-body);
        vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background-color: #FAFAFA; }

    .rank-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px; height: 22px;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 800;
        background: #F1F5F9;
        color: var(--text-body);
    }
    .rank-pill.rank-1 { background: #FEF3C7; color: #92400E; }
    .rank-pill.rank-2 { background: #E2E8F0; color: #334155; }
    .rank-pill.rank-3 { background: #FFEDD5; color: #9A3412; }

    .score-bar {
        display: inline-block;
        height: 6px;
        border-radius: 3px;
        background: #E2E8F0;
        vertical-align: middle;
        margin-right: 6px;
        width: 60px;
        position: relative;
        overflow: hidden;
    }
    .score-bar > span {
        display: block;
        height: 100%;
        background: var(--success);
        border-radius: 3px;
    }
    .score-bar.sb-med > span { background: var(--gold); }
    .score-bar.sb-low > span { background: var(--danger); }

    .low-row { background: #FEF7F7; }
    .low-row:hover td { background: #FEEEEE; }

    .empty-state {
        text-align: center;
        color: var(--text-muted);
        padding: 32px 20px;
        font-size: 13px;
        font-weight: 500;
    }

    /* ============================================================
       PRINT
       ============================================================ */
    @media print {
        body { background: #FFF; }
        .admin-main-wrap { margin-left: 0 !important; width: 100% !important; }
        .fixed-header-deck, .toolbar-actions, .filter-panel,
        .admin-top-bar, #adminSidebar, .admin-sidebar,
        .sidebar-overlay, .admin-header { display: none !important; }
        .admin-container { padding: 0 !important; }
        .chart-card, .panel-card, .kpi-card, .mini-panel { break-inside: avoid; box-shadow: none; }
    }

    /* ============================================================
       RESPONSIVE
       ============================================================ */
    @media (max-width: 1024px) {
        .admin-main-wrap { margin-left: 0; width: 100%; }
        .chart-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .admin-container { padding: calc(var(--header-height) + 12px) 14px 30px; }

        .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
        .toolbar-actions { width: 100%; gap: 8px; }
        .toolbar-actions .btn-primary,
        .toolbar-actions .btn-ghost { flex: 1; justify-content: center; }

        .filter-toggle { display: flex; }
        .filter-body { display: none; border-top: 1px solid var(--border); }
        .filter-panel.open .filter-body { display: block; }

        .filter-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .filter-controls label {
            margin-bottom: -4px;
        }
        .filter-controls input[type="date"],
        .filter-controls select {
            width: 100%;
            max-width: none;
            height: 44px;
            font-size: 16px; /* prevent iOS zoom */
        }

        .filter-actions {
            width: 100%;
            gap: 8px;
            margin-top: 4px;
        }
        .filter-actions .btn-primary,
        .filter-actions .btn-ghost {
            flex: 1;
            justify-content: center;
            padding: 11px 14px;
        }

        .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .mini-grid { grid-template-columns: 1fr; }
        .cc-mini-grid { grid-template-columns: 1fr; }
        .chart-wrap { height: 220px; }
        .chart-wrap-sm { height: 200px; }
    }

    @media (max-width: 480px) {
        :root { --header-height: 66px; }
        .admin-container { padding: calc(var(--header-height) + 10px) 10px 24px; }
        .toolbar-title h2 { font-size: 17px; }
        .toolbar-title p { font-size: 11.5px; }
        
        .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .kpi-card { padding: 12px 10px; min-height: 90px; border-radius: 10px; }
        .kpi-label { font-size: 9px; margin-bottom: 4px; }
        .kpi-value { font-size: 18px; letter-spacing: -0.5px; }
        .kpi-value.kpi-value-sm { font-size: 12px; }
        .kpi-value small { font-size: 10px; }
        .kpi-sub { font-size: 10px; margin-top: 4px; }
        
        .panel-card, .chart-card, .mini-panel { padding: 14px 12px; }
        .section-label { font-size: 10px; letter-spacing: 1px; }
        
        .chart-wrap { height: 200px; }
        .chart-wrap-sm { height: 180px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .admin-main-wrap, .filter-toggle-chev { transition: none !important; }
    }
</style>
</head>
<body>

    <?php
    // Sidebar first
    $sidebar_path = __DIR__ . '/admin_sidebar.php';
    if (file_exists($sidebar_path)) require_once $sidebar_path;

    // Header second
    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) require_once $header_path;
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>ARTA Feedback Reports &amp; Analytics</h2>
                        <p id="filterSummaryText"><?php echo htmlspecialchars($filter_summary); ?></p>
                    </div>
                    <div class="toolbar-actions">
                        <button type="button" class="btn-ghost" onclick="window.print()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                            Print
                        </button>
                        <button type="button" class="btn-primary" id="btnExportPDF" onclick="exportPDF()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 4h16v16H4z"/></svg>
                            Export PDF
                        </button>
                    </div>
                </section>

                <section class="filter-panel" id="filterPanel">
                    <button type="button" class="filter-toggle" onclick="toggleFilterPanel()" aria-expanded="false" aria-controls="filterBody">
                        <span class="filter-toggle-left">
                            <span class="filter-toggle-icon">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 4h18M6 12h12M10 20h4"/>
                                </svg>
                            </span>
                            <span>Search Filters</span>
                            <?php if ($active_filters > 0): ?>
                                <span class="filter-toggle-badge"><?php echo (int)$active_filters; ?> active</span>
                            <?php endif; ?>
                        </span>
                        <svg class="filter-toggle-chev" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>

                    <div class="filter-body" id="filterBody">
                        <form method="GET" action="admin_reports.php" class="filter-controls" id="filterForm">
                            <label for="preset">Period</label>
                            <select name="preset" id="preset">
                                <option value="">Custom</option>
                                <option value="7d"  <?php echo $filter_preset === '7d'  ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30d" <?php echo $filter_preset === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="tm"  <?php echo $filter_preset === 'tm'  ? 'selected' : ''; ?>>This Month</option>
                                <option value="lm"  <?php echo $filter_preset === 'lm'  ? 'selected' : ''; ?>>Last Month</option>
                                <option value="ty"  <?php echo $filter_preset === 'ty'  ? 'selected' : ''; ?>>This Year</option>
                                <option value="all" <?php echo $filter_preset === 'all' ? 'selected' : ''; ?>>All Time</option>
                            </select>

                            <label for="date_from">From</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_from); ?>" <?php echo !$date_col ? 'disabled' : ''; ?>>

                            <label for="date_to">To</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_to); ?>" <?php echo !$date_col ? 'disabled' : ''; ?>>

                            <label for="office_id">Office</label>
                            <select name="office_id" id="office_id">
                                <option value="0">All Offices</option>
                                <?php foreach ($offices_list as $o): ?>
                                    <option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_office === (int)$o['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['office_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="service_id">Service</label>
                            <select name="service_id" id="service_id">
                                <option value="0">All Services</option>
                                <?php foreach ($services_list as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo $filter_service === (int)$s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['service_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="client_type">Client</label>
                            <select name="client_type" id="client_type">
                                <option value="">All Clients</option>
                                <option value="Citizen"    <?php echo $filter_client === 'Citizen'    ? 'selected' : ''; ?>>Citizen</option>
                                <option value="Business"   <?php echo $filter_client === 'Business'   ? 'selected' : ''; ?>>Business</option>
                                <option value="Government" <?php echo $filter_client === 'Government' ? 'selected' : ''; ?>>Government</option>
                            </select>

                            <div class="filter-actions">
                                <button type="submit" class="btn-primary">Apply</button>
                                <a href="admin_reports.php" class="btn-ghost">Reset</a>
                            </div>
                        </form>
                    </div>
                </section>
            </div>

            <?php if (!$feedback_ready): ?>
                <div style="background:#FFF8E6;border:1px solid #FDE68A;color:#92400E;padding:14px 16px;border-radius:8px;font-size:13px;margin-bottom:12px;">
                    <strong>Feedback data unavailable.</strong> The <code>arta_feedback</code> table (or one of its SQD columns) is missing on this database. Report metrics will be zero until it is created.
                </div>
            <?php endif; ?>

            <!-- ============================================================
                 SECTION 1: EXECUTIVE SUMMARY
                 ============================================================ -->
            <div class="section-label"><span class="num">1</span><span>Executive Summary</span></div>

            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Feedback Responses</div>
                    <div class="kpi-value"><?php echo number_format($overall['total']); ?></div>
                    <div class="kpi-sub"><?php echo $offices_reporting; ?> offices · <?php echo $services_eval; ?> services</div>
                </div>

                <div class="kpi-card kpi-gold">
                    <div class="kpi-label">Overall Mean Score</div>
                    <?php $rr = arta_rating($overall['mean']); ?>
                    <div class="kpi-value">
                        <?php echo fmt_num($overall['mean'], 2); ?>
                        <?php if ($overall['mean'] !== null): ?>
                            <span class="rating-badge <?php echo $rr[1]; ?>"><?php echo htmlspecialchars($rr[0]); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-sub">Based on 5-point SQD scale</div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-label">Net Satisfaction</div>
                    <div class="kpi-value"><?php echo number_format($satisfaction_rate, 1); ?><small>%</small></div>
                    <div class="kpi-sub"><?php echo number_format($overall['positive']); ?> positive ratings</div>
                </div>

                <div class="kpi-card kpi-red">
                    <div class="kpi-label">Dissatisfaction</div>
                    <div class="kpi-value" style="color: <?php echo $dissatisfaction_rate > 10 ? 'var(--danger)' : 'var(--text-heading)'; ?>;">
                        <?php echo number_format($dissatisfaction_rate, 1); ?><small>%</small>
                    </div>
                    <div class="kpi-sub"><?php echo number_format($overall['negative']); ?> negative flags</div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-label">Top Office</div>
                    <?php if ($top_office): ?>
                        <?php $tr = arta_rating((float)$top_office['mean']); ?>
                        <div class="kpi-value kpi-value-sm"><?php echo htmlspecialchars($top_office['office_name']); ?></div>
                        <div class="kpi-sub">★ <?php echo fmt_num($top_office['mean'], 2); ?> · <?php echo htmlspecialchars($tr[0]); ?></div>
                    <?php else: ?>
                        <div class="kpi-value kpi-value-sm">—</div>
                        <div class="kpi-sub">No feedback yet</div>
                    <?php endif; ?>
                </div>

                <div class="kpi-card kpi-blue">
                    <div class="kpi-label">Needs Improvement</div>
                    <?php if ($worst_office && $worst_office !== $top_office): ?>
                        <?php $wr = arta_rating((float)$worst_office['mean']); ?>
                        <div class="kpi-value kpi-value-sm"><?php echo htmlspecialchars($worst_office['office_name']); ?></div>
                        <div class="kpi-sub">★ <?php echo fmt_num($worst_office['mean'], 2); ?> · <?php echo htmlspecialchars($wr[0]); ?></div>
                    <?php else: ?>
                        <div class="kpi-value kpi-value-sm">—</div>
                        <div class="kpi-sub">Insufficient data</div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 2: SERVICE QUALITY DIMENSIONS
                 ============================================================ -->
            <div class="section-label"><span class="num">2</span><span>Service Quality Dimensions</span></div>

            <section class="chart-grid">
                <div class="chart-card">
                    <h3>SQD Dimension Performance <span>Scale: 1.00 – 5.00</span></h3>
                    <div class="chart-wrap"><canvas id="sqdChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>Rating Distribution <span><?php echo number_format($overall['total']); ?> submissions</span></h3>
                    <div class="chart-wrap"><canvas id="distChart"></canvas></div>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>SQD Dimension Breakdown</h3>
                    <p>Mean score, response count, and ARTA rating</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:52px;">#</th>
                                <th>Dimension</th>
                                <th style="width:110px;">Responses</th>
                                <th style="width:200px;">Mean Score</th>
                                <th style="width:170px;">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; foreach ($sqd_means as $col => $d):
                                $i++;
                                $m = $d['mean'];
                                $r = arta_rating($m);
                                $pct = $m !== null ? max(0, min(100, ($m / 5) * 100)) : 0;
                                $barClass = $m === null ? 'sb-low' : ($m >= 4.0 ? '' : ($m >= 3.0 ? 'sb-med' : 'sb-low'));
                            ?>
                                <tr>
                                    <td><span class="rank-pill"><?php echo $i; ?></span></td>
                                    <td><strong style="color: var(--text-heading);"><?php echo htmlspecialchars($d['label']); ?></strong></td>
                                    <td><?php echo number_format($d['count']); ?></td>
                                    <td>
                                        <span class="score-bar <?php echo $barClass; ?>"><span style="width: <?php echo $pct; ?>%;"></span></span>
                                        <strong style="color: var(--text-heading);"><?php echo fmt_num($m, 2); ?></strong>
                                    </td>
                                    <td><span class="rating-badge <?php echo $r[1]; ?>"><?php echo htmlspecialchars($r[0]); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 3: CITIZEN'S CHARTER COMPLIANCE
                 ============================================================ -->
            <div class="section-label"><span class="num">3</span><span>Citizen's Charter Compliance</span></div>

            <section class="cc-mini-grid">
                <div class="cc-mini">
                    <div class="cc-mini-title">CC1 · Awareness of Charter</div>
                    <div class="cc-mini-big">
                        <?php echo number_format($cc_analysis['cc1']['aware_rate'], 1); ?>%
                        <small>Clients who know or saw the Charter</small>
                    </div>
                    <div class="cc-mini-rows">
                        <div class="cc-mini-row"><span>1. Saw office's CC</span><strong><?php echo number_format($cc_analysis['cc1']['saw']); ?></strong></div>
                        <div class="cc-mini-row"><span>2. Knew CC, did not see</span><strong><?php echo number_format($cc_analysis['cc1']['knew']); ?></strong></div>
                        <div class="cc-mini-row"><span>3. Learned upon visit</span><strong><?php echo number_format($cc_analysis['cc1']['learned']); ?></strong></div>
                        <div class="cc-mini-row"><span>4. Did not know / see</span><strong><?php echo number_format($cc_analysis['cc1']['none']); ?></strong></div>
                    </div>
                </div>

                <div class="cc-mini">
                    <div class="cc-mini-title">CC2 · Charter Visibility</div>
                    <div class="cc-mini-big">
                        <?php echo number_format($cc_analysis['cc2']['visible_rate'], 1); ?>%
                        <small>Rated "Easy" or "Somewhat easy" to see</small>
                    </div>
                    <div class="cc-mini-rows">
                        <div class="cc-mini-row"><span>Easy to see</span><strong><?php echo number_format($cc_analysis['cc2']['easy']); ?></strong></div>
                        <div class="cc-mini-row"><span>Somewhat easy</span><strong><?php echo number_format($cc_analysis['cc2']['somewhat']); ?></strong></div>
                        <div class="cc-mini-row"><span>Difficult to see</span><strong><?php echo number_format($cc_analysis['cc2']['difficult']); ?></strong></div>
                        <div class="cc-mini-row"><span>Not visible</span><strong><?php echo number_format($cc_analysis['cc2']['not_visible']); ?></strong></div>
                        <div class="cc-mini-row"><span>N/A</span><strong><?php echo number_format($cc_analysis['cc2']['na']); ?></strong></div>
                    </div>
                </div>

                <div class="cc-mini">
                    <div class="cc-mini-title">CC3 · Charter Helpfulness</div>
                    <div class="cc-mini-big">
                        <?php echo number_format($cc_analysis['cc3']['helpful_rate'], 1); ?>%
                        <small>Rated "Helped very much" or "Somewhat helped"</small>
                    </div>
                    <div class="cc-mini-rows">
                        <div class="cc-mini-row"><span>Helped very much</span><strong><?php echo number_format($cc_analysis['cc3']['helped_much']); ?></strong></div>
                        <div class="cc-mini-row"><span>Somewhat helped</span><strong><?php echo number_format($cc_analysis['cc3']['somewhat']); ?></strong></div>
                        <div class="cc-mini-row"><span>Did not help</span><strong><?php echo number_format($cc_analysis['cc3']['did_not']); ?></strong></div>
                        <div class="cc-mini-row"><span>N/A</span><strong><?php echo number_format($cc_analysis['cc3']['na']); ?></strong></div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 4: DEMOGRAPHIC ANALYTICS
                 ============================================================ -->
            <div class="section-label"><span class="num">4</span><span>Demographic Analytics</span></div>

            <section class="mini-grid">
                <div class="mini-panel">
                    <div class="panel-header"><h3>Client Type Distribution</h3></div>
                    <div class="chart-wrap-sm"><canvas id="clientChart"></canvas></div>
                </div>

                <div class="mini-panel">
                    <div class="panel-header"><h3>Age Group Distribution</h3></div>
                    <div class="chart-wrap-sm"><canvas id="ageChart"></canvas></div>
                </div>

                <div class="mini-panel">
                    <div class="panel-header"><h3>Sex Distribution</h3></div>
                    <div class="chart-wrap-sm"><canvas id="sexChart"></canvas></div>
                </div>

                <div class="mini-panel">
                    <div class="panel-header"><h3>Account Routing</h3></div>
                    <div class="chart-wrap-sm"><canvas id="accountChart"></canvas></div>
                </div>
            </section>

            <?php if (!empty($demographics['region'])): ?>
            <section class="panel-card">
                <div class="panel-header">
                    <h3>Top Regions of Respondents</h3>
                    <p>Ranked by submission volume</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:52px;">#</th>
                                <th>Region</th>
                                <th style="width:140px;">Responses</th>
                                <th style="width:180px;">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $region_total = array_sum($demographics['region']);
                            $ri = 0;
                            foreach ($demographics['region'] as $region => $count):
                                $ri++;
                                $share = $region_total > 0 ? round(($count / $region_total) * 100, 1) : 0;
                                $rClass = $ri <= 3 ? 'rank-' . $ri : '';
                            ?>
                                <tr>
                                    <td><span class="rank-pill <?php echo $rClass; ?>"><?php echo $ri; ?></span></td>
                                    <td><strong style="color: var(--text-heading);"><?php echo htmlspecialchars($region); ?></strong></td>
                                    <td><?php echo number_format($count); ?></td>
                                    <td>
                                        <span class="score-bar"><span style="width: <?php echo $share; ?>%;"></span></span>
                                        <strong style="color: var(--text-heading);"><?php echo $share; ?>%</strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <!-- ============================================================
                 SECTION 5: PERFORMANCE BREAKDOWN
                 ============================================================ -->
            <div class="section-label"><span class="num">5</span><span>Performance Breakdown</span></div>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>Office Performance</h3>
                    <p><?php echo $offices_reporting; ?> office<?php echo $offices_reporting === 1 ? '' : 's'; ?> with feedback</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:56px;">Rank</th>
                                <th>Office / College Unit</th>
                                <th style="width:150px;">Classification</th>
                                <th style="width:110px;">Responses</th>
                                <th style="width:180px;">Mean Score</th>
                                <th style="width:150px;">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($office_rows)): ?>
                                <?php $rank = 0; foreach ($office_rows as $row):
                                    $rank++;
                                    $m = (float)$row['mean'];
                                    $r = arta_rating($m);
                                    $pct = max(0, min(100, ($m / 5) * 100));
                                    $barClass = $m >= 4.0 ? '' : ($m >= 3.0 ? 'sb-med' : 'sb-low');
                                    $rankClass = $rank <= 3 ? 'rank-' . $rank : '';
                                ?>
                                    <tr>
                                        <td><span class="rank-pill <?php echo $rankClass; ?>"><?php echo $rank; ?></span></td>
                                        <td><strong style="color: var(--text-heading);"><?php echo htmlspecialchars($row['office_name']); ?></strong></td>
                                        <td><span style="font-size:11px; font-weight:700; color:var(--text-muted);"><?php echo htmlspecialchars($row['office_type'] ?? 'Unassigned'); ?></span></td>
                                        <td><?php echo number_format((int)$row['total']); ?></td>
                                        <td>
                                            <span class="score-bar <?php echo $barClass; ?>"><span style="width: <?php echo $pct; ?>%;"></span></span>
                                            <strong style="color: var(--text-heading);"><?php echo fmt_num($m, 2); ?></strong>
                                        </td>
                                        <td><span class="rating-badge <?php echo $r[1]; ?>"><?php echo htmlspecialchars($r[0]); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-state">No office feedback available for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>Service Performance</h3>
                    <p><?php echo $services_eval; ?> service<?php echo $services_eval === 1 ? '' : 's'; ?> evaluated</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:56px;">Rank</th>
                                <th>Service Designation</th>
                                <th style="width:200px;">Assigned Office</th>
                                <th style="width:110px;">Responses</th>
                                <th style="width:180px;">Mean Score</th>
                                <th style="width:150px;">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($service_rows)): ?>
                                <?php $rank = 0; foreach ($service_rows as $row):
                                    $rank++;
                                    $m = (float)$row['mean'];
                                    $r = arta_rating($m);
                                    $pct = max(0, min(100, ($m / 5) * 100));
                                    $barClass = $m >= 4.0 ? '' : ($m >= 3.0 ? 'sb-med' : 'sb-low');
                                    $rankClass = $rank <= 3 ? 'rank-' . $rank : '';
                                ?>
                                    <tr>
                                        <td><span class="rank-pill <?php echo $rankClass; ?>"><?php echo $rank; ?></span></td>
                                        <td><strong style="color: var(--text-heading);"><?php echo htmlspecialchars($row['service_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['office_name']); ?></td>
                                        <td><?php echo number_format((int)$row['total']); ?></td>
                                        <td>
                                            <span class="score-bar <?php echo $barClass; ?>"><span style="width: <?php echo $pct; ?>%;"></span></span>
                                            <strong style="color: var(--text-heading);"><?php echo fmt_num($m, 2); ?></strong>
                                        </td>
                                        <td><span class="rating-badge <?php echo $r[1]; ?>"><?php echo htmlspecialchars($r[0]); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-state">No service feedback available for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 6: TRENDS OVER TIME
                 ============================================================ -->
            <?php if ($date_col && !empty($monthly)): ?>
            <div class="section-label"><span class="num">6</span><span>Trends Over Time</span></div>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>Monthly Feedback Trend</h3>
                    <p>Volume and mean score per month</p>
                </div>
                <div class="chart-wrap-sm"><canvas id="trendChart"></canvas></div>
            </section>
            <?php endif; ?>

            <!-- ============================================================
                 SECTION 7: LOW-RATING ALERTS
                 ============================================================ -->
            <div class="section-label"><span class="num">7</span><span>Low-Rating Alerts</span></div>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>Feedback Requiring Attention</h3>
                    <p>Submissions with mean score ≤ 2.00</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:130px;">Date</th>
                                <th>Office &amp; Service</th>
                                <th style="width:100px;">Client</th>
                                <th style="width:80px;">Mean</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($low_rating_rows)): ?>
                                <?php foreach ($low_rating_rows as $lr):
                                    $m = (float)$lr['row_mean'];
                                ?>
                                    <tr class="low-row">
                                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                            <?php echo $lr['submitted_at'] ? date('M d, Y', strtotime($lr['submitted_at'])) : '—'; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--text-heading);"><?php echo htmlspecialchars($lr['office_name'] ?? 'Unassigned'); ?></strong><br>
                                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($lr['service_accessed']); ?></small>
                                        </td>
                                        <td><span style="font-size: 11.5px; font-weight: 600;"><?php echo htmlspecialchars($lr['client_type'] ?? '—'); ?></span></td>
                                        <td><span class="rating-badge rating-p">★ <?php echo number_format($m, 2); ?></span></td>
                                        <td style="line-height: 1.45; font-size: 12px;">
                                            <?php if (!empty($lr['comments_recommendations'])): ?>
                                                "<?php echo htmlspecialchars($lr['comments_recommendations']); ?>"
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">No comments provided.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state">No low-rated feedback in this period. All submissions met the ≥ 2.00 threshold.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 8: RECENT VERBATIM COMMENTS
                 ============================================================ -->
            <div class="section-label"><span class="num">8</span><span>Recent Verbatim Comments</span></div>

            <section class="panel-card">
                <div class="panel-header">
                    <h3>Latest Client Remarks</h3>
                    <p>Most recent 10 submissions with written comments</p>
                </div>
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:130px;">Date</th>
                                <th>Office &amp; Service</th>
                                <th style="width:100px;">Client</th>
                                <th style="width:80px;">Mean</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_comments)): ?>
                                <?php foreach ($recent_comments as $c):
                                    $m = (float)$c['row_mean'];
                                ?>
                                    <tr>
                                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                            <?php echo $c['submitted_at'] ? date('M d, Y', strtotime($c['submitted_at'])) : '—'; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--text-heading);"><?php echo htmlspecialchars($c['office_name'] ?? 'Unassigned'); ?></strong><br>
                                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($c['service_accessed']); ?></small>
                                        </td>
                                        <td><span style="font-size: 11.5px; font-weight: 600;"><?php echo htmlspecialchars($c['client_type'] ?? '—'); ?></span></td>
                                        <td>
                                            <?php $r = arta_rating($m); ?>
                                            <span class="rating-badge <?php echo $r[1]; ?>">★ <?php echo number_format($m, 2); ?></span>
                                        </td>
                                        <td style="line-height: 1.5; font-size: 12px;">
                                            "<?php echo htmlspecialchars($c['comments_recommendations']); ?>"
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state">No written feedback available for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- jsPDF + AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
    /* ============================================================
       COLLAPSIBLE FILTER PANEL (mobile only)
       ============================================================ */
    function toggleFilterPanel() {
        const panel = document.getElementById('filterPanel');
        if (!panel) return;
        const toggle = panel.querySelector('.filter-toggle');
        const isOpen = panel.classList.toggle('open');
        if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    // Auto-open filter panel on mobile if any filter is already active
    document.addEventListener('DOMContentLoaded', () => {
        const panel = document.getElementById('filterPanel');
        if (!panel) return;

        if (window.innerWidth <= 768) {
            const hasActiveFilter = <?php echo $active_filters > 0 ? 'true' : 'false'; ?>;
            if (hasActiveFilter) {
                panel.classList.add('open');
                const toggle = panel.querySelector('.filter-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
            }
        }
    });

    // ---- Data from PHP ----
    const REPORT = <?php echo json_encode([
        'filterSummary'   => $filter_summary,
        'overall'         => $overall,
        'overallRating'   => arta_rating($overall['mean'])[0],
        'satisfaction'    => $satisfaction_rate,
        'dissatisfaction' => $dissatisfaction_rate,
        'sqd'             => $sqd_means,
        'offices'         => $office_rows,
        'services'        => $service_rows,
        'monthly'         => $monthly,
        'distribution'    => $distribution,
        'demographics'    => $demographics,
        'cc'              => $cc_analysis,
        'topOffice'       => $top_office,
        'worstOffice'     => $worst_office,
        'hasDateCol'      => (bool)$date_col,
        'generatedAt'     => date('F j, Y g:i A'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    // ---- Preset auto-submit ----
    const presetEl = document.getElementById('preset');
    if (presetEl) {
        presetEl.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }

    // ---- Palette ----
    const MAROON = '#7A0000';
    const GOLD   = '#F2A900';
    const GREEN  = '#10B981';
    const BLUE   = '#2563EB';
    const RED    = '#EF4444';
    const ORANGE = '#EA580C';
    const GRAY   = '#94A3B8';
    const PALETTE = [MAROON, GOLD, GREEN, BLUE, ORANGE, '#8B5CF6', '#EC4899', '#14B8A6'];

    function colorForMean(m) {
        if (m === null) return GRAY;
        if (m >= 4.21) return GREEN;
        if (m >= 3.41) return BLUE;
        if (m >= 2.61) return GOLD;
        if (m >= 1.81) return ORANGE;
        return RED;
    }

    // ---- 1. SQD bar ----
    (function() {
        const ctx = document.getElementById('sqdChart');
        if (!ctx) return;
        const labels = [], values = [], colors = [];
        Object.values(REPORT.sqd).forEach(d => {
            labels.push(d.label);
            values.push(d.mean !== null ? d.mean : 0);
            colors.push(colorForMean(d.mean));
        });
        new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Mean Score', data: values, backgroundColor: colors, borderRadius: 4, barThickness: 20 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + (c.parsed.x || 0).toFixed(2) + ' / 5.00' } } },
                scales: {
                    x: { min: 0, max: 5, ticks: { stepSize: 1, color: '#94A3B8', font: { size: 10 } }, grid: { color: '#F1F5F9' } },
                    y: { ticks: { color: '#475569', font: { size: 11, weight: '600' } }, grid: { display: false } }
                }
            }
        });
    })();

    // ---- 2. Distribution doughnut ----
    (function() {
        const ctx = document.getElementById('distChart');
        if (!ctx) return;
        const labels = Object.keys(REPORT.distribution);
        const values = Object.values(REPORT.distribution);
        new Chart(ctx, {
            type: 'doughnut',
            data: { labels, datasets: [{ data: values, backgroundColor: [GREEN, BLUE, GOLD, ORANGE, RED], borderWidth: 2, borderColor: '#FFFFFF' }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, weight: '600' }, color: '#475569', padding: 10 } }
                }
            }
        });
    })();

    // ---- 3. Trend ----
    (function() {
        const ctx = document.getElementById('trendChart');
        if (!ctx || !REPORT.hasDateCol) return;
        const labels = REPORT.monthly.map(m => m.ym);
        const totals = REPORT.monthly.map(m => parseInt(m.total, 10));
        const means  = REPORT.monthly.map(m => m.mean !== null ? parseFloat(m.mean) : null);

        new Chart(ctx, {
            data: {
                labels,
                datasets: [
                    { type: 'bar', label: 'Responses', data: totals, backgroundColor: 'rgba(122, 0, 0, 0.15)', borderColor: MAROON, borderWidth: 1, borderRadius: 4, yAxisID: 'y' },
                    { type: 'line', label: 'Mean Score', data: means, borderColor: GOLD, backgroundColor: GOLD, borderWidth: 2.5, tension: 0.35, pointBackgroundColor: GOLD, pointBorderColor: '#FFFFFF', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 11, weight: '600' }, color: '#475569' } } },
                scales: {
                    x: { ticks: { color: '#94A3B8', font: { size: 10 } }, grid: { display: false } },
                    y: { beginAtZero: true, position: 'left', ticks: { color: '#94A3B8', font: { size: 10 } }, grid: { color: '#F1F5F9' } },
                    y1: { beginAtZero: true, max: 5, position: 'right', ticks: { color: '#94A3B8', font: { size: 10 }, stepSize: 1 }, grid: { display: false } }
                }
            }
        });
    })();

    // ---- 4-7. Demographics pies ----
    function buildPie(canvasId, dataObj, colors) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const labels = Object.keys(dataObj);
        const values = Object.values(dataObj);
        if (!labels.length) { labels.push('No data'); values.push(0); }
        new Chart(ctx, {
            type: 'pie',
            data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#FFFFFF' }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, weight: '600' }, color: '#475569', padding: 8 } }
                }
            }
        });
    }

    buildPie('clientChart',  REPORT.demographics.client_type, [MAROON, GOLD, BLUE, GREEN, ORANGE]);
    buildPie('ageChart',     REPORT.demographics.age_group,   PALETTE);
    buildPie('sexChart',     REPORT.demographics.sex,         [BLUE, RED, GRAY]);
    buildPie('accountChart', REPORT.demographics.account,     [GOLD, BLUE]);

    // ---- 8. PDF Export ----
    function exportPDF() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            alert('PDF library failed to load.');
            return;
        }
        const btn = document.getElementById('btnExportPDF');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Generating…';

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();

            function drawHeader(titleText) {
                doc.setFillColor(122, 0, 0);
                doc.rect(0, 0, pageW, 22, 'F');
                doc.setFillColor(242, 169, 0);
                doc.rect(0, 22, pageW, 1.5, 'F');

                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(12);
                doc.text('ZPPSU CSM — ' + titleText, 14, 10);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.text('Generated: ' + REPORT.generatedAt, 14, 16);
                doc.text('Filter: ' + (REPORT.filterSummary || 'All records'), pageW - 14, 10, { align: 'right' });
            }

            function drawFooter() {
                const pageNum = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(8);
                doc.setTextColor(148, 163, 184);
                doc.text('ZPPSU CSM · ARTA MC 2022-05 Report · Page ' + pageNum, pageW / 2, pageH - 8, { align: 'center' });
            }

            function artaLabel(m) {
                m = parseFloat(m);
                if (isNaN(m)) return '—';
                if (m >= 4.21) return 'Outstanding';
                if (m >= 3.41) return 'Very Satisfactory';
                if (m >= 2.61) return 'Satisfactory';
                if (m >= 1.81) return 'Unsatisfactory';
                return 'Poor';
            }

            // ---- Page 1 ----
            drawHeader('ARTA Feedback Analytics Report');

            doc.setTextColor(15, 23, 42);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.text('Executive Summary', 14, 34);

            const overallMean = REPORT.overall.mean !== null ? parseFloat(REPORT.overall.mean).toFixed(2) + '  (' + REPORT.overallRating + ')' : '—';
            doc.autoTable({
                startY: 38,
                head: [['Key Indicator', 'Value']],
                body: [
                    ['Total Feedback Responses', String(REPORT.overall.total)],
                    ['Overall Harmonized Mean', overallMean],
                    ['Net Client Satisfaction', REPORT.satisfaction.toFixed(1) + '%'],
                    ['Dissatisfaction Rate', REPORT.dissatisfaction.toFixed(1) + '%'],
                    ['Offices Reporting', String(REPORT.offices.length)],
                    ['Services Evaluated', String(REPORT.services.length)],
                    ['Top Performing Office', REPORT.topOffice ? REPORT.topOffice.office_name + '  (' + parseFloat(REPORT.topOffice.mean).toFixed(2) + ')' : '—'],
                    ['Needs Improvement', REPORT.worstOffice ? REPORT.worstOffice.office_name + '  (' + parseFloat(REPORT.worstOffice.mean).toFixed(2) + ')' : '—'],
                ],
                theme: 'grid',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 9.5, fontStyle: 'bold' },
                bodyStyles: { fontSize: 9, textColor: [71, 85, 105] },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                columnStyles: { 0: { cellWidth: 70, fontStyle: 'bold', textColor: [15, 23, 42] }, 1: { cellWidth: 'auto' } },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            let y = doc.lastAutoTable.finalY + 8;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(15, 23, 42);
            doc.text('Service Quality Dimensions (SQD)', 14, y);

            const sqdBody = Object.values(REPORT.sqd).map(d => [
                d.label,
                d.mean !== null ? parseFloat(d.mean).toFixed(2) : '—',
                String(d.count),
                artaLabel(d.mean)
            ]);
            doc.autoTable({
                startY: y + 4,
                head: [['SQD Dimension', 'Mean', 'Responses', 'Rating']],
                body: sqdBody,
                theme: 'striped',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 9, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8.5, textColor: [71, 85, 105] },
                columnStyles: {
                    0: { cellWidth: 70, fontStyle: 'bold', textColor: [15, 23, 42] },
                    1: { cellWidth: 24, halign: 'center', fontStyle: 'bold' },
                    2: { cellWidth: 30, halign: 'center' },
                    3: { cellWidth: 'auto' }
                },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            y = doc.lastAutoTable.finalY + 8;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(15, 23, 42);
            doc.text('Citizen\'s Charter Compliance', 14, y);

            doc.autoTable({
                startY: y + 4,
                head: [['Indicator', 'Rate', 'Key Counts']],
                body: [
                    ['CC1 — Awareness of Charter', REPORT.cc.cc1.aware_rate.toFixed(1) + '%', 'Saw: ' + REPORT.cc.cc1.saw + ' · Knew: ' + REPORT.cc.cc1.knew + ' · Learned: ' + REPORT.cc.cc1.learned + ' · None: ' + REPORT.cc.cc1.none],
                    ['CC2 — Visibility', REPORT.cc.cc2.visible_rate.toFixed(1) + '%', 'Easy: ' + REPORT.cc.cc2.easy + ' · Somewhat: ' + REPORT.cc.cc2.somewhat + ' · Difficult: ' + REPORT.cc.cc2.difficult + ' · Not visible: ' + REPORT.cc.cc2.not_visible],
                    ['CC3 — Helpfulness', REPORT.cc.cc3.helpful_rate.toFixed(1) + '%', 'Helped much: ' + REPORT.cc.cc3.helped_much + ' · Somewhat: ' + REPORT.cc.cc3.somewhat + ' · Did not help: ' + REPORT.cc.cc3.did_not],
                ],
                theme: 'grid',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 9, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8.5, textColor: [71, 85, 105] },
                columnStyles: {
                    0: { cellWidth: 55, fontStyle: 'bold', textColor: [15, 23, 42] },
                    1: { cellWidth: 22, halign: 'center', fontStyle: 'bold' },
                    2: { cellWidth: 'auto' }
                },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            // ---- Page 2 ----
            doc.addPage();
            drawHeader('Demographic & Office Performance');

            doc.setTextColor(15, 23, 42);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.text('Client Demographics', 14, 32);

            const demoRows = [];
            Object.entries(REPORT.demographics.client_type).forEach(([k, v]) => demoRows.push(['Client Type', k, v]));
            Object.entries(REPORT.demographics.age_group).forEach(([k, v]) => demoRows.push(['Age Group', k, v]));
            Object.entries(REPORT.demographics.sex).forEach(([k, v]) => demoRows.push(['Sex', k, v]));
            Object.entries(REPORT.demographics.account).forEach(([k, v]) => demoRows.push(['Account Routing', k, v]));
            Object.entries(REPORT.demographics.region).forEach(([k, v]) => demoRows.push(['Region', k, v]));

            doc.autoTable({
                startY: 36,
                head: [['Category', 'Segment', 'Count']],
                body: demoRows.length ? demoRows : [['—', 'No demographic data', '—']],
                theme: 'striped',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 9, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8.5, textColor: [71, 85, 105] },
                columnStyles: {
                    0: { cellWidth: 45, fontStyle: 'bold', textColor: [15, 23, 42] },
                    1: { cellWidth: 'auto' },
                    2: { cellWidth: 25, halign: 'center', fontStyle: 'bold' }
                },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            let y2 = doc.lastAutoTable.finalY + 8;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(15, 23, 42);
            doc.text('Office Performance Ranking', 14, y2);

            const officeBody = REPORT.offices.map((r, i) => [
                String(i + 1),
                r.office_name,
                r.office_type || 'Unassigned',
                String(r.total),
                parseFloat(r.mean).toFixed(2),
                artaLabel(r.mean)
            ]);

            doc.autoTable({
                startY: y2 + 4,
                head: [['#', 'Office / College Unit', 'Classification', 'Resp.', 'Mean', 'Rating']],
                body: officeBody.length ? officeBody : [['—', 'No office data available', '—', '—', '—', '—']],
                theme: 'grid',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 8.5, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8, textColor: [71, 85, 105] },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                columnStyles: {
                    0: { cellWidth: 10, halign: 'center', fontStyle: 'bold' },
                    1: { cellWidth: 62, fontStyle: 'bold', textColor: [15, 23, 42] },
                    2: { cellWidth: 34 },
                    3: { cellWidth: 18, halign: 'center' },
                    4: { cellWidth: 18, halign: 'center', fontStyle: 'bold' },
                    5: { cellWidth: 'auto' }
                },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            // ---- Page 3 ----
            doc.addPage();
            drawHeader('Service Performance Breakdown');

            const serviceBody = REPORT.services.map((r, i) => [
                String(i + 1),
                r.service_name,
                r.office_name,
                String(r.total),
                parseFloat(r.mean).toFixed(2),
                artaLabel(r.mean)
            ]);

            doc.autoTable({
                startY: 32,
                head: [['#', 'Service Designation', 'Assigned Office', 'Resp.', 'Mean', 'Rating']],
                body: serviceBody.length ? serviceBody : [['—', 'No service data available', '—', '—', '—', '—']],
                theme: 'grid',
                headStyles: { fillColor: [122, 0, 0], textColor: 255, fontSize: 8.5, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8, textColor: [71, 85, 105] },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                columnStyles: {
                    0: { cellWidth: 10, halign: 'center', fontStyle: 'bold' },
                    1: { cellWidth: 62, fontStyle: 'bold', textColor: [15, 23, 42] },
                    2: { cellWidth: 38 },
                    3: { cellWidth: 15, halign: 'center' },
                    4: { cellWidth: 15, halign: 'center', fontStyle: 'bold' },
                    5: { cellWidth: 'auto' }
                },
                margin: { left: 14, right: 14 },
                didDrawPage: drawFooter
            });

            const stamp = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            doc.save('ARTA-Report-' + stamp + '.pdf');

        } catch (err) {
            console.error(err);
            alert('PDF export failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
    </script>
</body>
</html>