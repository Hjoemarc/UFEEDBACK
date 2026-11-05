<?php
// admin_analytics.php — Feedback Analytics & Trends Dashboard

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='background:#fee2e2;color:#991b1b;padding:24px;margin:20px;font-family:sans-serif;border-radius:8px;border:1px solid #f87171;'>";
        echo "<h3 style='margin-top:0;'>PHP Fatal Error Caught</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " on line <strong>" . $error['line'] . "</strong></p>";
        echo "</div>";
    }
});

session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    // Log the unauthorized attempt
    if (isset($conn) && $conn instanceof mysqli) {
        $uid  = $_SESSION['user_id'] ?? 'GUEST';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $page = basename($_SERVER['PHP_SELF']);
        $reason = "Unauthorized access attempt to {$page}";
        $stmt = $conn->prepare("INSERT INTO suspicious_activity (username, ip_address, reason) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $uid, $ip, $reason);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

// ---------------------------------------------------------------------------
// FILTERS (GET)
// ---------------------------------------------------------------------------
$range = $_GET['range'] ?? '90d';
$office_filter = (int)($_GET['office_id'] ?? 0);
$client_filter = trim($_GET['client_type'] ?? '');

$range_days = match ($range) {
    '30d'   => 30,
    '90d'   => 90,
    '180d'  => 180,
    '365d'  => 365,
    'all'   => null,
    default => 90,
};

// Build a WHERE fragment that's reusable across all aggregations
$where = ["f.is_archived = 0"];
$params = [];
$types  = '';

if ($range_days !== null) {
    $where[] = "f.submission_timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $range_days;
    $types   .= 'i';
}
if ($office_filter > 0) {
    $where[] = "f.office_id = ?";
    $params[] = $office_filter;
    $types   .= 'i';
}
if ($client_filter !== '' && in_array($client_filter, ['Citizen','Business','Government'], true)) {
    $where[] = "f.client_type = ?";
    $params[] = $client_filter;
    $types   .= 's';
}

$WHERE_SQL = implode(' AND ', $where);

/**
 * Run a prepared SELECT with the current filter set.
 */
function run_filtered(mysqli $conn, string $sql_tail, string $WHERE_SQL, array $params, string $types) {
    $sql = "SELECT " . $sql_tail . " FROM arta_feedback f WHERE " . $WHERE_SQL;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $res = $stmt->get_result();
    $stmt->close();
    return $res;
}

// ---------------------------------------------------------------------------
// KPI: overall CSAT + Derived NPS + total responses + SLA breach
// ---------------------------------------------------------------------------

// Per-response average of non-zero SQD scores, then classify
$kpi_res = run_filtered(
    $conn,
    "COUNT(*) AS total_responses,
     SUM(CASE WHEN (sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0) = 0
              THEN 1 ELSE 0 END) AS empty_responses,
     SUM(CASE WHEN ((sqd1_responsiveness+sqd2_reliability+sqd3_access_facilities+sqd4_communication+sqd5_costs+sqd6_integrity+sqd7_assurance+sqd8_outcome) /
                    NULLIF((sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0), 0)
                   ) >= 4.5 THEN 1 ELSE 0 END) AS promoters,
     SUM(CASE WHEN ((sqd1_responsiveness+sqd2_reliability+sqd3_access_facilities+sqd4_communication+sqd5_costs+sqd6_integrity+sqd7_assurance+sqd8_outcome) /
                    NULLIF((sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0), 0)
                   ) >= 3.5
               AND ((sqd1_responsiveness+sqd2_reliability+sqd3_access_facilities+sqd4_communication+sqd5_costs+sqd6_integrity+sqd7_assurance+sqd8_outcome) /
                    NULLIF((sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0), 0)
                   ) <  4.5 THEN 1 ELSE 0 END) AS passives,
     SUM(CASE WHEN (sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0) > 0
               AND ((sqd1_responsiveness+sqd2_reliability+sqd3_access_facilities+sqd4_communication+sqd5_costs+sqd6_integrity+sqd7_assurance+sqd8_outcome) /
                    NULLIF((sqd1_responsiveness>0)+(sqd2_reliability>0)+(sqd3_access_facilities>0)+(sqd4_communication>0)+(sqd5_costs>0)+(sqd6_integrity>0)+(sqd7_assurance>0)+(sqd8_outcome>0), 0)
                   ) <  3.5 THEN 1 ELSE 0 END) AS detractors,
     SUM(CASE WHEN sqd1_responsiveness >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd2_reliability >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd3_access_facilities >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd4_communication >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd5_costs >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd6_integrity >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd7_assurance >= 4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd8_outcome >= 4 THEN 1 ELSE 0 END) AS satisfied_ratings,
     SUM(CASE WHEN sqd1_responsiveness > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd2_reliability > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd3_access_facilities > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd4_communication > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd5_costs > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd6_integrity > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd7_assurance > 0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd8_outcome > 0 THEN 1 ELSE 0 END) AS total_ratings",
    $WHERE_SQL, $params, $types
);

$kpi = [
    'total_responses' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0,
    'satisfied_ratings' => 0, 'total_ratings' => 0,
];
if ($kpi_res) {
    $kpi = array_merge($kpi, $kpi_res->fetch_assoc() ?: []);
}

$csat = ($kpi['total_ratings'] > 0)
    ? round(($kpi['satisfied_ratings'] / $kpi['total_ratings']) * 100, 1)
    : 0.0;

$scorable = (int)$kpi['promoters'] + (int)$kpi['passives'] + (int)$kpi['detractors'];
$nps = ($scorable > 0)
    ? round((((int)$kpi['promoters'] - (int)$kpi['detractors']) / $scorable) * 100, 1)
    : 0.0;

$total_responses = (int)$kpi['total_responses'];

// SLA breach rate — only for feedback with start_date set
$breach_res = $conn->query("
    SELECT
        COUNT(*) AS eligible,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 1 ELSE 0 END) AS breaches
    FROM arta_feedback f
    JOIN services s ON s.office_id = f.office_id AND s.service_name = f.service_accessed
    WHERE $WHERE_SQL
      AND f.service_start_date IS NOT NULL
      AND f.transaction_date IS NOT NULL
");
$breach_rate = 0.0;
$breach_count = 0;
$breach_eligible = 0;
if ($breach_res) {
    $br = $breach_res->fetch_assoc();
    $breach_eligible = (int)($br['eligible'] ?? 0);
    $breach_count    = (int)($br['breaches'] ?? 0);
    $breach_rate     = $breach_eligible > 0 ? round(($breach_count / $breach_eligible) * 100, 1) : 0.0;
}

// ---------------------------------------------------------------------------
// CHART 1: CSAT trend (weekly buckets)
// ---------------------------------------------------------------------------
$csat_trend = ['labels' => [], 'csat' => [], 'responses' => []];
$trend_res = run_filtered(
    $conn,
    "DATE_FORMAT(f.submission_timestamp, '%x-W%v') AS wk,
     MIN(DATE(f.submission_timestamp)) AS wk_start,
     COUNT(*) AS total_responses,
     SUM(CASE WHEN sqd1_responsiveness>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd2_reliability>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd3_access_facilities>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd4_communication>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd5_costs>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd6_integrity>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd7_assurance>=4 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd8_outcome>=4 THEN 1 ELSE 0 END) AS satisfied,
     SUM(CASE WHEN sqd1_responsiveness>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd2_reliability>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd3_access_facilities>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd4_communication>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd5_costs>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd6_integrity>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd7_assurance>0 THEN 1 ELSE 0 END) +
     SUM(CASE WHEN sqd8_outcome>0 THEN 1 ELSE 0 END) AS rated
     GROUP BY wk ORDER BY wk_start ASC",
    $WHERE_SQL, $params, $types
);
if ($trend_res) {
    while ($r = $trend_res->fetch_assoc()) {
        $rated = (int)$r['rated'];
        $csat_trend['labels'][]    = date('M j', strtotime($r['wk_start']));
        $csat_trend['csat'][]      = $rated > 0 ? round(((int)$r['satisfied'] / $rated) * 100, 1) : 0;
        $csat_trend['responses'][] = (int)$r['total_responses'];
    }
}

// ---------------------------------------------------------------------------
// CHART 2: SQD dimension breakdown (average per dimension)
// ---------------------------------------------------------------------------
$sqd_res = run_filtered(
    $conn,
    "AVG(NULLIF(sqd1_responsiveness,0)) AS sqd1, AVG(NULLIF(sqd2_reliability,0)) AS sqd2,
     AVG(NULLIF(sqd3_access_facilities,0)) AS sqd3, AVG(NULLIF(sqd4_communication,0)) AS sqd4,
     AVG(NULLIF(sqd5_costs,0)) AS sqd5, AVG(NULLIF(sqd6_integrity,0)) AS sqd6,
     AVG(NULLIF(sqd7_assurance,0)) AS sqd7, AVG(NULLIF(sqd8_outcome,0)) AS sqd8",
    $WHERE_SQL, $params, $types
);
$sqd = [
    'Responsiveness'    => 0, 'Reliability'       => 0,
    'Access & Facilities'=>0, 'Communication'     => 0,
    'Costs'             => 0, 'Integrity'         => 0,
    'Assurance'         => 0, 'Outcome'           => 0,
];
if ($sqd_res && ($sr = $sqd_res->fetch_assoc())) {
    $sqd['Responsiveness']     = round((float)($sr['sqd1'] ?? 0), 2);
    $sqd['Reliability']        = round((float)($sr['sqd2'] ?? 0), 2);
    $sqd['Access & Facilities']= round((float)($sr['sqd3'] ?? 0), 2);
    $sqd['Communication']      = round((float)($sr['sqd4'] ?? 0), 2);
    $sqd['Costs']              = round((float)($sr['sqd5'] ?? 0), 2);
    $sqd['Integrity']          = round((float)($sr['sqd6'] ?? 0), 2);
    $sqd['Assurance']          = round((float)($sr['sqd7'] ?? 0), 2);
    $sqd['Outcome']            = round((float)($sr['sqd8'] ?? 0), 2);
}

// ---------------------------------------------------------------------------
// CHART 3: Client type composition
// ---------------------------------------------------------------------------
$client_res = run_filtered(
    $conn,
    "client_type, COUNT(*) AS total GROUP BY client_type",
    $WHERE_SQL, $params, $types
);
$client_comp = ['Citizen' => 0, 'Business' => 0, 'Government' => 0];
if ($client_res) {
    while ($r = $client_res->fetch_assoc()) {
        $client_comp[$r['client_type']] = (int)$r['total'];
    }
}

// ---------------------------------------------------------------------------
// TOP PERFORMING OFFICES (leaderboard)
// ---------------------------------------------------------------------------
$office_leaderboard = [];
$lead_res = $conn->query("
    SELECT
        o.id, o.office_name, o.office_type,
        COUNT(f.feedback_id) AS response_count,
        SUM(CASE WHEN f.sqd1_responsiveness>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd2_reliability>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd3_access_facilities>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd4_communication>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd5_costs>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd6_integrity>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd7_assurance>=4 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd8_outcome>=4 THEN 1 ELSE 0 END) AS satisfied,
        SUM(CASE WHEN f.sqd1_responsiveness>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd2_reliability>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd3_access_facilities>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd4_communication>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd5_costs>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd6_integrity>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd7_assurance>0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN f.sqd8_outcome>0 THEN 1 ELSE 0 END) AS rated
    FROM offices o
    LEFT JOIN arta_feedback f ON f.office_id = o.id AND f.is_archived = 0
        " . ($range_days !== null ? "AND f.submission_timestamp >= DATE_SUB(NOW(), INTERVAL " . (int)$range_days . " DAY)" : "") . "
        " . ($client_filter !== '' && in_array($client_filter, ['Citizen','Business','Government'], true) ? "AND f.client_type = '" . $conn->real_escape_string($client_filter) . "'" : "") . "
    GROUP BY o.id, o.office_name, o.office_type
    HAVING response_count > 0
    ORDER BY (satisfied / NULLIF(rated, 0)) DESC, response_count DESC
    LIMIT 8
");
if ($lead_res instanceof mysqli_result) {
    while ($r = $lead_res->fetch_assoc()) {
        $rated = (int)$r['rated'];
        $r['csat'] = $rated > 0 ? round(((int)$r['satisfied'] / $rated) * 100, 1) : 0;
        $office_leaderboard[] = $r;
    }
}

// ---------------------------------------------------------------------------
// BOTTLENECK WATCHLIST (services exceeding mandated processing time)
// ---------------------------------------------------------------------------
$bottlenecks = [];
$bn_res = $conn->query("
    SELECT
        s.id, s.service_name, s.processing_time_days, s.classification,
        o.office_name,
        COUNT(f.feedback_id) AS response_count,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 1 ELSE 0 END) AS breach_count,
        AVG(DATEDIFF(f.transaction_date, f.service_start_date)) AS avg_actual_days
    FROM services s
    JOIN offices o ON s.office_id = o.id
    JOIN arta_feedback f ON f.office_id = s.office_id AND f.service_accessed = s.service_name
    WHERE f.is_archived = 0
      AND f.service_start_date IS NOT NULL
      AND f.transaction_date IS NOT NULL
      " . ($range_days !== null ? "AND f.submission_timestamp >= DATE_SUB(NOW(), INTERVAL " . (int)$range_days . " DAY)" : "") . "
      " . ($office_filter > 0 ? "AND f.office_id = " . (int)$office_filter : "") . "
      " . ($client_filter !== '' && in_array($client_filter, ['Citizen','Business','Government'], true) ? "AND f.client_type = '" . $conn->real_escape_string($client_filter) . "'" : "") . "
    GROUP BY s.id, s.service_name, s.processing_time_days, s.classification, o.office_name
    HAVING breach_count > 0
    ORDER BY breach_count DESC, (breach_count / response_count) DESC
    LIMIT 8
");
if ($bn_res instanceof mysqli_result) {
    while ($r = $bn_res->fetch_assoc()) {
        $r['breach_rate'] = ((int)$r['response_count'] > 0)
            ? round(((int)$r['breach_count'] / (int)$r['response_count']) * 100, 1)
            : 0;
        $bottlenecks[] = $r;
    }
}

// ---------------------------------------------------------------------------
// RECENT LOW-SCORE FEEDBACK (verbatims)
// ---------------------------------------------------------------------------
$recent_negatives = [];
$neg_res = $conn->query("
    SELECT
        f.feedback_id, f.service_accessed, f.client_type, f.submission_timestamp,
        f.comments_recommendations,
        f.sqd1_responsiveness, f.sqd2_reliability, f.sqd3_access_facilities,
        f.sqd4_communication, f.sqd5_costs, f.sqd6_integrity,
        f.sqd7_assurance, f.sqd8_outcome,
        o.office_name
    FROM arta_feedback f
    LEFT JOIN offices o ON f.office_id = o.id
    WHERE $WHERE_SQL
      AND (f.sqd1_responsiveness <= 2 OR f.sqd2_reliability <= 2 OR f.sqd3_access_facilities <= 2 OR f.sqd4_communication <= 2
        OR f.sqd5_costs <= 2 OR f.sqd6_integrity <= 2 OR f.sqd7_assurance <= 2 OR f.sqd8_outcome <= 2)
      AND f.comments_recommendations IS NOT NULL
      AND TRIM(f.comments_recommendations) <> ''
    ORDER BY f.submission_timestamp DESC
    LIMIT 6
");
if ($neg_res instanceof mysqli_result) {
    while ($r = $neg_res->fetch_assoc()) $recent_negatives[] = $r;
}

// ---------------------------------------------------------------------------
// Offices list (for filter dropdown)
// ---------------------------------------------------------------------------
$offices = [];
$o_res = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($o_res instanceof mysqli_result) {
    while ($r = $o_res->fetch_assoc()) $offices[] = $r;
}

// Page metadata
$page_title    = 'Feedback Analytics & Trends';
$page_crumb    = 'Administration / Analytics';
$page_subtitle = 'Real-time CSAT, NPS, and bottleneck intelligence';
$unread_notifications = 5;

// Encode datasets for JS
$JSON = [
    'csat_trend'    => $csat_trend,
    'nps'           => ['promoters' => (int)$kpi['promoters'], 'passives' => (int)$kpi['passives'], 'detractors' => (int)$kpi['detractors']],
    'sqd'           => $sqd,
    'client_comp'   => $client_comp,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --maroon: #7A0000; --maroon-dark: #580000; --gold: #F2A900;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --info: #2563EB;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width: 0; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden;
            transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1), width 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .admin-container {
            width: 100%; max-width: 100%;
            padding: calc(var(--header-height) + 20px) 24px 24px;
            flex: 1; display: flex; flex-direction: column;
        }

        /* ================ TOOLBAR ================ */
      .toolbar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    
    /* ADD THESE 4 LINES FOR THE STICKY EFFECT */
    position: -webkit-sticky;
    position: sticky;
    top: var(--header-height); 
    z-index: 10;               
}
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }

        .filter-controls {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .select-live-filter, .input-live-search {
            padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 12.5px; background: #F8FAFC; color: var(--text-heading);
            outline: none; font-family: inherit;
            transition: border-color 0.18s, background 0.18s;
        }
        .select-live-filter:focus, .input-live-search:focus { border-color: var(--maroon); background: #FFFFFF; }

        .btn-primary {
            background: var(--maroon); color: #FFF; border: none;
            padding: 10px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            font-family: inherit; transition: background 0.18s;
            text-decoration: none;
        }
        .btn-primary:hover { background: var(--maroon-dark); }

        /* ================ KPI GRID ================ */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 12px; margin-bottom: 12px;
        }
        .kpi-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 8px;
            min-width: 0;
        }
        .kpi-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: var(--maroon);
        }
        .kpi-card.csat::before   { background: linear-gradient(180deg, #10B981, #047857); }
        .kpi-card.nps::before    { background: linear-gradient(180deg, #7A0000, #580000); }
        .kpi-card.resp::before   { background: linear-gradient(180deg, #2563EB, #1D4ED8); }
        .kpi-card.breach::before { background: linear-gradient(180deg, #F59E0B, #B45309); }

        .kpi-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px;
        }
        .kpi-label {
            font-size: 11px; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.7px;
        }
        .kpi-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .kpi-icon svg { width: 16px; height: 16px; }
        .kpi-icon.green  { background:#ECFDF5; color:#047857; }
        .kpi-icon.maroon { background:#FEF2F2; color: var(--maroon); }
        .kpi-icon.blue   { background:#EFF6FF; color:#1D4ED8; }
        .kpi-icon.orange { background:#FFF7ED; color:#C2410C; }

        .kpi-value {
            font-size: 30px; font-weight: 800; color: var(--text-heading);
            line-height: 1; letter-spacing: -0.8px; display: flex; align-items: baseline; gap: 4px;
        }
        .kpi-unit { font-size: 15px; font-weight: 700; color: var(--text-muted); }

        .kpi-sub {
            font-size: 11.5px; color: var(--text-muted); font-weight: 600;
            display: flex; align-items: center; gap: 5px;
        }
        .kpi-sub .dot {
            display: inline-block; width: 6px; height: 6px; border-radius: 50%;
            background: currentColor; flex-shrink: 0;
        }

        /* ================ CHART GRID ================ */
        .charts-row {
            display: grid; gap: 12px; margin-bottom: 12px;
        }
        .charts-row.primary { grid-template-columns: 1.5fr 1fr; }
        .charts-row.secondary { grid-template-columns: 1fr 1fr; }

        .chart-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            min-width: 0;
            display: flex; flex-direction: column;
        }
        .chart-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 12px; margin-bottom: 14px;
        }
        .chart-head h3 {
            font-size: 14px; font-weight: 800; color: var(--text-heading);
            margin: 0; line-height: 1.2;
        }
        .chart-head p {
            font-size: 11.5px; color: var(--text-muted); margin: 3px 0 0;
        }
        .chart-canvas-wrap {
            position: relative;
            height: 260px;
            min-width: 0;
        }
        .chart-canvas-wrap.tall { height: 320px; }

        /* ================ TABLES ================ */
        .tables-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
            margin-bottom: 12px;
        }
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            min-width: 0;
        }
        .panel-card .chart-head { margin-bottom: 12px; }

        .mini-table {
            width: 100%; border-collapse: collapse; font-size: 12.5px;
        }
        .mini-table th {
            background: #F8FAFC; color: var(--text-muted); font-weight: 700;
            font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.4px;
            padding: 9px 10px; text-align: left;
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 2;
        }
        .mini-table td {
            padding: 11px 10px; border-bottom: 1px solid #F1F5F9;
            color: var(--text-body); vertical-align: middle;
        }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-table .rank {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 6px;
            background: #F1F5F9; color: var(--text-body);
            font-size: 11px; font-weight: 800; flex-shrink: 0;
        }
        .mini-table .rank.top1 { background:#FEF3C7; color:#B45309; }
        .mini-table .rank.top2 { background:#E2E8F0; color:#475569; }
        .mini-table .rank.top3 { background:#FED7AA; color:#C2410C; }

        .name-cell {
            display: flex; align-items: center; gap: 10px; min-width: 0;
        }
        .name-cell .text {
            min-width: 0;
        }
        .name-cell .text .primary {
            font-weight: 700; color: var(--text-heading);
            display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 220px;
        }
        .name-cell .text .secondary {
            font-size: 11px; color: var(--text-muted);
            display: block; margin-top: 1px;
        }

        .bar-track {
            position: relative; height: 6px; background: #F1F5F9;
            border-radius: 3px; overflow: hidden; min-width: 60px;
        }
        .bar-fill {
            position: absolute; left: 0; top: 0; bottom: 0;
            background: linear-gradient(90deg, #10B981, #047857);
            border-radius: 3px; transition: width 0.5s;
        }
        .bar-fill.warn   { background: linear-gradient(90deg, #F59E0B, #B45309); }
        .bar-fill.danger { background: linear-gradient(90deg, #EF4444, #B91C1C); }

        .score-inline {
            display: flex; align-items: center; gap: 8px; min-width: 120px;
        }
        .score-value {
            font-weight: 800; color: var(--text-heading);
            font-size: 12.5px; min-width: 46px; text-align: right;
        }

        .badge-mini {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10.5px; font-weight: 700;
            padding: 2px 7px; border-radius: 5px;
            white-space: nowrap;
        }
        .badge-mini.danger { background: #FEE2E2; color: #B91C1C; }
        .badge-mini.warn   { background: #FEF3C7; color: #B45309; }
        .badge-mini.ok     { background: #ECFDF5; color: #047857; }
        .badge-mini.neutral{ background: #F1F5F9; color: var(--text-body); }

        /* ================ VERBATIMS ================ */
        .verbatim-list { display: flex; flex-direction: column; gap: 10px; }
        .verbatim {
            padding: 12px 14px; border: 1px solid var(--border);
            border-radius: 10px; background: #FAFBFC;
            display: flex; flex-direction: column; gap: 6px;
        }
        .verbatim-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; flex-wrap: wrap;
        }
        .verbatim-head .office {
            font-size: 12px; font-weight: 800; color: var(--text-heading);
        }
        .verbatim-head .meta {
            font-size: 10.5px; color: var(--text-muted); font-weight: 600;
        }
        .verbatim-body {
            font-size: 12.5px; color: var(--text-body); line-height: 1.5;
            border-left: 3px solid var(--border); padding-left: 10px;
            font-style: italic;
        }

        /* ================ EMPTY STATE ================ */
        .empty-state {
            text-align: center; color: var(--text-muted);
            padding: 40px 20px;
        }
        .empty-state svg {
            width: 40px; height: 40px; color: #CBD5E1; margin-bottom: 8px;
        }
        .empty-state p { font-size: 12.5px; margin: 0; }

        /* ================ RESPONSIVE ================ */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .charts-row.primary { grid-template-columns: 1fr; }
            .tables-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .filter-controls { width: 100%; }
            .select-live-filter, .input-live-search, .btn-primary { flex: 1; min-width: 0; }
            .charts-row.secondary { grid-template-columns: 1fr; }
            .chart-canvas-wrap { height: 220px; }
            .chart-canvas-wrap.tall { height: 260px; }
            .mini-table .name-cell .text .primary { max-width: 140px; }
            .kpi-value { font-size: 26px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .kpi-grid { gap: 8px; }
            .kpi-card { padding: 14px; border-radius: 12px; }
            .kpi-value { font-size: 22px; }
            .kpi-label { font-size: 10px; }
            .kpi-icon { width: 26px; height: 26px; }
            .kpi-icon svg { width: 14px; height: 14px; }
            .chart-card, .panel-card { padding: 14px; }
            .chart-canvas-wrap { height: 200px; }
            .mini-table td, .mini-table th { padding: 9px 8px; font-size: 11.5px; }
            .mini-table .name-cell .text .primary { max-width: 100px; font-size: 11.5px; }
            .score-inline { min-width: 90px; gap: 5px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

    <?php
    $sidebar_path = __DIR__ . '/admin_sidebar.php';
    if (file_exists($sidebar_path)) require_once $sidebar_path;
    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) require_once $header_path;
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <!-- ============= TOOLBAR / FILTERS ============= -->
            <section class="toolbar">
                <div class="toolbar-title">
                    <h2>Feedback Analytics &amp; Trends</h2>
                    <p>Real-time CSAT, Derived NPS, and bottleneck intelligence across all university services</p>
                </div>

                <form method="GET" action="admin_analytics.php" class="filter-controls" id="filterForm">
                    <select name="range" class="select-live-filter" onchange="this.form.submit()">
                        <option value="30d"  <?php echo $range === '30d'  ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90d"  <?php echo $range === '90d'  ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="180d" <?php echo $range === '180d' ? 'selected' : ''; ?>>Last 6 months</option>
                        <option value="365d" <?php echo $range === '365d' ? 'selected' : ''; ?>>Last 12 months</option>
                        <option value="all"  <?php echo $range === 'all'  ? 'selected' : ''; ?>>All time</option>
                    </select>

                    <select name="office_id" class="select-live-filter" onchange="this.form.submit()">
                        <option value="0">All Offices</option>
                        <?php foreach ($offices as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>" <?php echo $office_filter === (int)$o['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['office_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="client_type" class="select-live-filter" onchange="this.form.submit()">
                        <option value="">All Client Types</option>
                        <option value="Citizen"    <?php echo $client_filter === 'Citizen' ? 'selected' : ''; ?>>Citizen</option>
                        <option value="Business"   <?php echo $client_filter === 'Business' ? 'selected' : ''; ?>>Business</option>
                        <option value="Government" <?php echo $client_filter === 'Government' ? 'selected' : ''; ?>>Government</option>
                    </select>

                    <?php if ($range !== '90d' || $office_filter > 0 || $client_filter !== ''): ?>
                        <a href="admin_analytics.php" class="btn-primary" style="background:#FFFFFF; color: var(--text-heading); border:1.5px solid var(--border);">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- ============= KPI GRID ============= -->
            <section class="kpi-grid">
                <div class="kpi-card csat">
                    <div class="kpi-head">
                        <span class="kpi-label">Overall CSAT</span>
                        <span class="kpi-icon green">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php echo number_format($csat, 1); ?><span class="kpi-unit">%</span>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background: var(--success);"></span>
                        <?php echo number_format((int)$kpi['satisfied_ratings']); ?> of
                        <?php echo number_format((int)$kpi['total_ratings']); ?> ratings scored ≥ 4
                    </div>
                </div>

                <div class="kpi-card nps">
                    <div class="kpi-head">
                        <span class="kpi-label">Derived NPS</span>
                        <span class="kpi-icon maroon">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 12h4l3-7 4 14 3-7h4"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value" style="color: <?php
                        echo $nps >= 30 ? 'var(--success)' : ($nps >= 0 ? 'var(--text-heading)' : 'var(--danger)');
                    ?>;">
                        <?php echo ($nps > 0 ? '+' : '') . number_format($nps, 1); ?>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background: var(--maroon);"></span>
                        <?php echo (int)$kpi['promoters']; ?> promoters · <?php echo (int)$kpi['detractors']; ?> detractors
                    </div>
                </div>

                <div class="kpi-card resp">
                    <div class="kpi-head">
                        <span class="kpi-label">Total Responses</span>
                        <span class="kpi-icon blue">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($total_responses); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background: var(--info);"></span>
                        <?php echo $range_days !== null ? 'Last ' . $range_days . ' days' : 'All time'; ?>
                        <?php if ($office_filter > 0): ?> · office filtered<?php endif; ?>
                    </div>
                </div>

                <div class="kpi-card breach">
                    <div class="kpi-head">
                        <span class="kpi-label">SLA Breach Rate</span>
                        <span class="kpi-icon orange">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value" style="color: <?php
                        echo $breach_rate >= 20 ? 'var(--danger)' : ($breach_rate > 0 ? 'var(--warning)' : 'var(--success)');
                    ?>;">
                        <?php echo number_format($breach_rate, 1); ?><span class="kpi-unit">%</span>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background: var(--warning);"></span>
                        <?php echo number_format($breach_count); ?> of <?php echo number_format($breach_eligible); ?> eligible transactions
                    </div>
                </div>
            </section>

            <!-- ============= CHART ROW 1 ============= -->
            <section class="charts-row primary">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>CSAT Trend</h3>
                            <p>Weekly satisfaction rate and response volume</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap tall">
                        <canvas id="csatTrendChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>Derived NPS Breakdown</h3>
                            <p>Promoter / Passive / Detractor distribution</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap tall">
                        <canvas id="npsChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- ============= CHART ROW 2 ============= -->
            <section class="charts-row secondary">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>SQD Dimension Averages</h3>
                            <p>Mean score (1–5) across the eight ARTA service quality dimensions</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="sqdChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>Client Composition</h3>
                            <p>Response distribution by ARTA client classification</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="clientChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- ============= TABLES ROW ============= -->
            <section class="tables-row">

                <!-- Office Leaderboard -->
                <div class="panel-card">
                    <div class="chart-head">
                        <div>
                            <h3>Top Performing Offices</h3>
                            <p>Ranked by CSAT for the selected period</p>
                        </div>
                    </div>

                    <?php if (!empty($office_leaderboard)): ?>
                        <div style="overflow-x:auto;">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th style="width:36px;">#</th>
                                        <th>Office</th>
                                        <th style="width:140px;">CSAT</th>
                                        <th style="width:70px; text-align:right;">Resp.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($office_leaderboard as $i => $o): ?>
                                        <tr>
                                            <td>
                                                <span class="rank <?php echo $i === 0 ? 'top1' : ($i === 1 ? 'top2' : ($i === 2 ? 'top3' : '')); ?>">
                                                    <?php echo $i + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="name-cell">
                                                    <div class="text">
                                                        <span class="primary"><?php echo htmlspecialchars($o['office_name']); ?></span>
                                                        <span class="secondary"><?php echo htmlspecialchars($o['office_type'] ?? ''); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="score-inline">
                                                    <div class="bar-track" style="flex:1;">
                                                        <div class="bar-fill" style="width: <?php echo max(2, (float)$o['csat']); ?>%;"></div>
                                                    </div>
                                                    <span class="score-value"><?php echo number_format((float)$o['csat'], 1); ?>%</span>
                                                </div>
                                            </td>
                                            <td style="text-align:right;">
                                                <span class="badge-mini neutral"><?php echo (int)$o['response_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>No feedback data available for the current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bottleneck Watchlist -->
                <div class="panel-card">
                    <div class="chart-head">
                        <div>
                            <h3>Bottleneck Watchlist</h3>
                            <p>Services exceeding mandated ARTA processing time</p>
                        </div>
                    </div>

                    <?php if (!empty($bottlenecks)): ?>
                        <div style="overflow-x:auto;">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th style="width:110px;">Actual vs SLA</th>
                                        <th style="width:90px; text-align:right;">Breach</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bottlenecks as $b): ?>
                                        <tr>
                                            <td>
                                                <div class="name-cell">
                                                    <div class="text">
                                                        <span class="primary"><?php echo htmlspecialchars($b['service_name']); ?></span>
                                                        <span class="secondary"><?php echo htmlspecialchars($b['office_name']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="score-inline">
                                                    <div class="bar-track" style="flex:1;">
                                                        <div class="bar-fill <?php echo $b['breach_rate'] >= 50 ? 'danger' : 'warn'; ?>"
                                                             style="width: <?php echo max(4, min(100, (float)$b['breach_rate'])); ?>%;"></div>
                                                    </div>
                                                    <span class="score-value" style="font-size:11.5px;">
                                                        <?php echo number_format((float)$b['avg_actual_days'], 1); ?>d
                                                        <span style="color:var(--text-muted); font-weight:600;">
                                                            / <?php echo (int)$b['processing_time_days']; ?>d
                                                        </span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td style="text-align:right;">
                                                <span class="badge-mini <?php echo $b['breach_rate'] >= 50 ? 'danger' : 'warn'; ?>">
                                                    <?php echo number_format((float)$b['breach_rate'], 0); ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>No SLA breaches detected. All chartered services within standard.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============= VERBATIMS ============= -->
            <section class="panel-card" style="margin-bottom: 24px;">
                <div class="chart-head">
                    <div>
                        <h3>Recent Low-Score Feedback</h3>
                        <p>Verbatim comments attached to any SQD rating of 1 or 2</p>
                    </div>
                </div>

                <?php if (!empty($recent_negatives)): ?>
                    <div class="verbatim-list">
                        <?php foreach ($recent_negatives as $v): ?>
                            <div class="verbatim">
                                <div class="verbatim-head">
                                    <span class="office">
                                        #<?php echo (int)$v['feedback_id']; ?> · <?php echo htmlspecialchars($v['office_name'] ?? '—'); ?>
                                    </span>
                                    <span class="meta">
                                        <?php echo htmlspecialchars($v['client_type']); ?> ·
                                        <?php echo htmlspecialchars(date('M j, Y', strtotime($v['submission_timestamp']))); ?> ·
                                        <?php echo htmlspecialchars($v['service_accessed']); ?>
                                    </span>
                                </div>
                                <div class="verbatim-body">
                                    "<?php echo htmlspecialchars($v['comments_recommendations']); ?>"
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p>No low-score verbatim comments in the selected period.</p>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <script>
        const DATA = <?php echo json_encode($JSON, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        // Global defaults
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#64748B';

        const MAROON = '#7A0000';
        const MAROON_LIGHT = 'rgba(122, 0, 0, 0.08)';
        const SUCCESS = '#10B981';
        const DANGER = '#EF4444';
        const WARNING = '#F59E0B';
        const INFO = '#2563EB';

        const gridStyle = {
            color: '#F1F5F9',
            drawTicks: false,
        };
        const tooltipStyle = {
            backgroundColor: '#0F172A',
            titleColor: '#FFFFFF',
            bodyColor: '#E2E8F0',
            padding: 10,
            cornerRadius: 8,
            titleFont: { weight: '700', size: 12 },
            bodyFont: { size: 12 },
        };

        /* ============================================================
           CSAT TREND — line with dual axis (rate + volume)
           ============================================================ */
        (function() {
            const ctx = document.getElementById('csatTrendChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: DATA.csat_trend.labels.length ? DATA.csat_trend.labels : ['No data'],
                    datasets: [
                        {
                            label: 'CSAT %',
                            data: DATA.csat_trend.csat.length ? DATA.csat_trend.csat : [0],
                            borderColor: MAROON,
                            backgroundColor: MAROON_LIGHT,
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: MAROON,
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Responses',
                            data: DATA.csat_trend.responses.length ? DATA.csat_trend.responses : [0],
                            type: 'bar',
                            backgroundColor: 'rgba(37, 99, 235, 0.15)',
                            borderColor: 'rgba(37, 99, 235, 0.35)',
                            borderWidth: 1,
                            borderRadius: 4,
                            maxBarThickness: 24,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 10, boxHeight: 10, usePointStyle: true,
                                pointStyle: 'circle', padding: 14,
                                font: { weight: '600', size: 11 },
                            }
                        },
                        tooltip: tooltipStyle,
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                        y: {
                            position: 'left',
                            beginAtZero: true, max: 100,
                            grid: gridStyle,
                            ticks: { callback: v => v + '%', padding: 8 },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { padding: 8 },
                        }
                    }
                }
            });
        })();

        /* ============================================================
           NPS BREAKDOWN — doughnut
           ============================================================ */
        (function() {
            const ctx = document.getElementById('npsChart');
            if (!ctx) return;
            const p = DATA.nps.promoters || 0;
            const n = DATA.nps.passives  || 0;
            const d = DATA.nps.detractors|| 0;
            const total = p + n + d;
            const empty = total === 0;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: empty ? ['No responses'] : ['Promoters', 'Passives', 'Detractors'],
                    datasets: [{
                        data: empty ? [1] : [p, n, d],
                        backgroundColor: empty
                            ? ['#F1F5F9']
                            : [SUCCESS, WARNING, DANGER],
                        borderWidth: 3,
                        borderColor: '#FFFFFF',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10, boxHeight: 10, usePointStyle: true,
                                pointStyle: 'circle', padding: 14,
                                font: { weight: '600', size: 11 },
                            }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                label: (c) => {
                                    if (empty) return 'No responses';
                                    const val = c.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0';
                                    return ` ${c.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw(chart) {
                        const { ctx, chartArea: { left, right, top, bottom } } = chart;
                        const cx = (left + right) / 2;
                        const cy = (top + bottom) / 2;
                        const nps = <?php echo json_encode($nps); ?>;
                        const label = empty ? '—' : (nps > 0 ? '+' + nps.toFixed(1) : nps.toFixed(1));

                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.fillStyle = '#0F172A';
                        ctx.font = '800 24px "Plus Jakarta Sans", sans-serif';
                        ctx.fillText(label, cx, cy + 4);
                        ctx.fillStyle = '#94A3B8';
                        ctx.font = '700 10px "Plus Jakarta Sans", sans-serif';
                        ctx.fillText('NPS', cx, cy + 22);
                        ctx.restore();
                    }
                }]
            });
        })();

        /* ============================================================
           SQD DIMENSIONS — horizontal bar
           ============================================================ */
        (function() {
            const ctx = document.getElementById('sqdChart');
            if (!ctx) return;
            const labels = Object.keys(DATA.sqd);
            const values = Object.values(DATA.sqd).map(v => Number(v));

            // Colour the bars according to score
            const colors = values.map(v => {
                if (v >= 4.5) return SUCCESS;
                if (v >= 3.5) return '#3B82F6';
                if (v >= 2.5) return WARNING;
                return DANGER;
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Avg score',
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        barThickness: 18,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                label: (c) => ` Avg: ${Number(c.parsed.x).toFixed(2)} / 5`
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true, max: 5,
                            grid: gridStyle,
                            ticks: { stepSize: 1, padding: 6 },
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { weight: '600' }, padding: 8 },
                        }
                    }
                }
            });
        })();

        /* ============================================================
           CLIENT COMPOSITION — doughnut
           ============================================================ */
        (function() {
            const ctx = document.getElementById('clientChart');
            if (!ctx) return;
            const labels = Object.keys(DATA.client_comp);
            const values = Object.values(DATA.client_comp).map(v => Number(v));
            const total = values.reduce((a, b) => a + b, 0);
            const empty = total === 0;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: empty ? ['No responses'] : labels,
                    datasets: [{
                        data: empty ? [1] : values,
                        backgroundColor: empty
                            ? ['#F1F5F9']
                            : [MAROON, INFO, WARNING],
                        borderWidth: 3,
                        borderColor: '#FFFFFF',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10, boxHeight: 10, usePointStyle: true,
                                pointStyle: 'circle', padding: 14,
                                font: { weight: '600', size: 11 },
                            }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                label: (c) => {
                                    if (empty) return 'No responses';
                                    const val = c.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0';
                                    return ` ${c.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>