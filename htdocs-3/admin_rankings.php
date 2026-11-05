<?php
// admin_rankings.php — Analytics & Insight: Rankings Leaderboard

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
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

// ---------------------------------------------------------------------------
// FILTERS
// ---------------------------------------------------------------------------
$range         = $_GET['range'] ?? '90d';
$office_filter = (int)($_GET['office_id'] ?? 0);
$client_filter = trim($_GET['client_type'] ?? '');
$min_resp      = max(1, (int)($_GET['min_resp'] ?? 3));

$range_days = match ($range) {
    '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365, 'all' => null, default => 90,
};

// Period bounds
$now_ts       = time();
$cur_end      = date('Y-m-d H:i:s', $now_ts);
$cur_start    = $range_days !== null ? date('Y-m-d H:i:s', $now_ts - ($range_days * 86400)) : '1970-01-01 00:00:00';
$prev_end     = $cur_start;
$prev_start   = $range_days !== null ? date('Y-m-d H:i:s', $now_ts - ($range_days * 2 * 86400)) : null;

// Extra WHERE
$extra = '';
if ($office_filter > 0) $extra .= " AND f.office_id = " . (int)$office_filter;
if ($client_filter !== '' && in_array($client_filter, ['Citizen','Business','Government'], true)) {
    $extra .= " AND f.client_type = '" . $conn->real_escape_string($client_filter) . "'";
}

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------
function csat_from_row(array $r): float {
    $rated = (int)($r['rated'] ?? 0);
    return $rated > 0 ? round(((int)$r['satisfied'] / $rated) * 100, 1) : 0.0;
}
function trend_arrow(float $delta): array {
    if ($delta >= 0.5)  return ['up', '+' . number_format($delta, 1)];
    if ($delta <= -0.5) return ['down', number_format($delta, 1)];
    return ['flat', '—'];
}

/**
 * Generic entity ranking fetcher.
 * Groups arta_feedback by the given SQL expression and computes CSAT.
 */
function fetch_entity_csat(mysqli $conn, string $group_by, string $joins, string $extra, ?string $start, ?string $end): array {
    if ($start === null) return [];
    $sql = "
        SELECT
            $group_by AS entity_id,
            COUNT(*) AS responses,
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
        FROM arta_feedback f
        $joins
        WHERE f.is_archived = 0
          AND f.submission_timestamp >= ?
          AND f.submission_timestamp <  ?
          $extra
        GROUP BY entity_id
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("fetch_entity_csat prepare failed: " . $conn->error);
        return [];
    }
    $stmt->bind_param("ss", $start, $end);
    if (!$stmt->execute()) { 
        error_log("fetch_entity_csat execute failed: " . $stmt->error);
        $stmt->close(); 
        return []; 
    }
    $res = $stmt->get_result();
    $out = [];
    // Removed the (int) cast so service strings like "8||Enrollment" do not collide
    while ($r = $res->fetch_assoc()) $out[$r['entity_id']] = $r;
    $stmt->close();
    return $out;
}

// ---------------------------------------------------------------------------
// 1. OFFICE RANKINGS
// ---------------------------------------------------------------------------
$cur_offices = fetch_entity_csat($conn, 'f.office_id', '', $extra, $cur_start, $cur_end);
$prev_offices = fetch_entity_csat($conn, 'f.office_id', '', $extra, $prev_start, $prev_end);

$office_names = [];
$of_res = $conn->query("SELECT id, office_name, office_type FROM offices");
if ($of_res) while ($r = $of_res->fetch_assoc()) $office_names[(int)$r['id']] = $r;

// Office breach rate (current period only, needs service SLA join)
$office_breach = [];
$ob_res = $conn->query("
    SELECT f.office_id,
           COUNT(*) AS eligible,
           SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 1 ELSE 0 END) AS breaches
    FROM arta_feedback f
    JOIN services s ON s.office_id = f.office_id AND s.service_name = f.service_accessed
    WHERE f.is_archived = 0
      AND f.service_start_date IS NOT NULL
      AND f.transaction_date IS NOT NULL
      AND f.submission_timestamp >= '" . $conn->real_escape_string($cur_start) . "'
      AND f.submission_timestamp <  '" . $conn->real_escape_string($cur_end) . "'
      $extra
    GROUP BY f.office_id
");
if ($ob_res) while ($r = $ob_res->fetch_assoc()) $office_breach[(int)$r['office_id']] = $r;

$office_rankings = [];
foreach ($cur_offices as $oid => $cur) {
    if ((int)$cur['responses'] < $min_resp) continue;
    $csat_cur = csat_from_row($cur);
    $prev = $prev_offices[$oid] ?? null;
    $csat_prev = $prev ? csat_from_row($prev) : null;
    $delta = $csat_prev !== null ? round($csat_cur - $csat_prev, 1) : null;

    $br = $office_breach[$oid] ?? null;
    $breach_rate = ($br && (int)$br['eligible'] > 0)
        ? round(((int)$br['breaches'] / (int)$br['eligible']) * 100, 1) : null;

    $office_rankings[] = [
        'id' => $oid,
        'name' => $office_names[$oid]['office_name'] ?? 'Office #' . $oid,
        'type' => $office_names[$oid]['office_type'] ?? '',
        'csat' => $csat_cur,
        'responses' => (int)$cur['responses'],
        'delta' => $delta,
        'breach_rate' => $breach_rate,
    ];
}
usort($office_rankings, fn($a, $b) => $b['csat'] <=> $a['csat'] ?: $b['responses'] <=> $a['responses']);

// ---------------------------------------------------------------------------
// 2. SERVICE RANKINGS
// ---------------------------------------------------------------------------
$cur_services = fetch_entity_csat(
    $conn,
    'CONCAT(f.office_id, "||", f.service_accessed)',
    '', $extra, $cur_start, $cur_end
);
$prev_services = fetch_entity_csat(
    $conn,
    'CONCAT(f.office_id, "||", f.service_accessed)',
    '', $extra, $prev_start, $prev_end
);

// Map "office_id||service_name" → details
$service_details = [];
$sd_res = $conn->query("
    SELECT id, office_id, service_name, classification, processing_time_days
    FROM services
");
if ($sd_res) while ($r = $sd_res->fetch_assoc()) {
    $key = $r['office_id'] . '||' . $r['service_name'];
    $service_details[$key] = $r;
}

// Service breach + avg actual duration
$service_breach = [];
$sb_res = $conn->query("
    SELECT CONCAT(f.office_id, '||', f.service_accessed) AS skey,
           COUNT(*) AS eligible,
           SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 1 ELSE 0 END) AS breaches,
           AVG(DATEDIFF(f.transaction_date, f.service_start_date)) AS avg_days
    FROM arta_feedback f
    JOIN services s ON s.office_id = f.office_id AND s.service_name = f.service_accessed
    WHERE f.is_archived = 0
      AND f.service_start_date IS NOT NULL
      AND f.transaction_date IS NOT NULL
      AND f.submission_timestamp >= '" . $conn->real_escape_string($cur_start) . "'
      AND f.submission_timestamp <  '" . $conn->real_escape_string($cur_end) . "'
      $extra
    GROUP BY skey
");
if ($sb_res) while ($r = $sb_res->fetch_assoc()) $service_breach[$r['skey']] = $r;

$service_rankings = [];
foreach ($cur_services as $key => $cur) {
    if ((int)$cur['responses'] < $min_resp) continue;
    $parts = explode('||', (string)$key, 2);
    $office_id = (int)($parts[0] ?? 0);
    $svc_name = $parts[1] ?? '';
    $details = $service_details[$key] ?? null;

    $csat_cur = csat_from_row($cur);
    $prev = $prev_services[$key] ?? null;
    $csat_prev = $prev ? csat_from_row($prev) : null;
    $delta = $csat_prev !== null ? round($csat_cur - $csat_prev, 1) : null;

    $br = $service_breach[$key] ?? null;
    $breach_rate = ($br && (int)$br['eligible'] > 0)
        ? round(((int)$br['breaches'] / (int)$br['eligible']) * 100, 1) : null;
    $avg_days = $br ? round((float)$br['avg_days'], 1) : null;

    $service_rankings[] = [
        'office_id' => $office_id,
        'office_name' => $office_names[$office_id]['office_name'] ?? '—',
        'service_name' => $svc_name,
        'classification' => $details['classification'] ?? '—',
        'sla_days' => $details ? (int)$details['processing_time_days'] : null,
        'csat' => $csat_cur,
        'responses' => (int)$cur['responses'],
        'delta' => $delta,
        'breach_rate' => $breach_rate,
        'avg_days' => $avg_days,
    ];
}
usort($service_rankings, fn($a, $b) => $b['csat'] <=> $a['csat'] ?: $b['responses'] <=> $a['responses']);

// ---------------------------------------------------------------------------
// 3. RESOLVER RANKINGS (from action_plans assigned to users)
// ---------------------------------------------------------------------------
$resolver_rankings = [];
$rr_res = $conn->query("
    SELECT
        u.user_id, u.full_name, u.internal_user_type,
        COUNT(ap.id) AS assigned,
        SUM(CASE WHEN ap.status = 'Resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN ap.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN ap.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        AVG(CASE WHEN ap.status = 'Resolved' AND ap.resolved_at IS NOT NULL
                 THEN TIMESTAMPDIFF(HOUR, ap.created_at, ap.resolved_at)
            END) AS avg_hours_resolve
    FROM users u
    JOIN action_plans ap ON ap.assigned_staff_id = u.user_id
    WHERE ap.created_at >= '" . $conn->real_escape_string($cur_start) . "'
    GROUP BY u.user_id, u.full_name, u.internal_user_type
    HAVING assigned > 0
");
if ($rr_res instanceof mysqli_result) {
    while ($r = $rr_res->fetch_assoc()) {
        $assigned = (int)$r['assigned'];
        $resolved = (int)$r['resolved'];
        $r['resolution_rate'] = $assigned > 0 ? round(($resolved / $assigned) * 100, 1) : 0;
        $r['avg_days_resolve'] = $r['avg_hours_resolve'] !== null ? round((float)$r['avg_hours_resolve'] / 24, 1) : null;
        $resolver_rankings[] = $r;
    }
}
// Sort: resolution rate desc, then avg days asc (nulls last), then resolved count desc
usort($resolver_rankings, function($a, $b) {
    if ($b['resolution_rate'] !== $a['resolution_rate']) return $b['resolution_rate'] <=> $a['resolution_rate'];
    $ad = $a['avg_days_resolve'] ?? PHP_FLOAT_MAX;
    $bd = $b['avg_days_resolve'] ?? PHP_FLOAT_MAX;
    if ($ad !== $bd) return $ad <=> $bd;
    return $b['resolved'] <=> $a['resolved'];
});

// ---------------------------------------------------------------------------
// 4. DEMOGRAPHIC SEGMENTS
// ---------------------------------------------------------------------------
function fetch_segment(mysqli $conn, string $col, string $extra, string $start, string $end): array {
    $sql = "
        SELECT
            $col AS segment,
            COUNT(*) AS responses,
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
        FROM arta_feedback f
        WHERE f.is_archived = 0
          AND f.submission_timestamp >= ?
          AND f.submission_timestamp <  ?
          $extra
          AND $col IS NOT NULL
          AND $col <> ''
        GROUP BY segment
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("fetch_segment prepare failed: " . $conn->error);
        return [];
    }
    $stmt->bind_param("ss", $start, $end);
    if (!$stmt->execute()) { 
        error_log("fetch_segment execute failed: " . $stmt->error);
        $stmt->close(); 
        return []; 
    }
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['segment']] = $r;
    $stmt->close();
    return $out;
}

$segments = [
    'age_group' => ['label' => 'Age Group',   'data' => []],
    'sex'       => ['label' => 'Sex',         'data' => []],
    'client_type' => ['label' => 'Client Type','data' => []],
    'region'    => ['label' => 'Region',      'data' => []],
];

foreach (['age_group', 'sex', 'client_type', 'region'] as $col) {
    $cur = fetch_segment($conn, $col, $extra, $cur_start, $cur_end);
    $prev = $prev_start !== null ? fetch_segment($conn, $col, $extra, $prev_start, $prev_end) : [];
    foreach ($cur as $seg => $row) {
        if ((int)$row['responses'] < $min_resp) continue;
        $csat = csat_from_row($row);
        $prev_row = $prev[$seg] ?? null;
        $prev_csat = $prev_row ? csat_from_row($prev_row) : null;
        $segments[$col]['data'][] = [
            'segment' => $seg,
            'csat' => $csat,
            'responses' => (int)$row['responses'],
            'delta' => $prev_csat !== null ? round($csat - $prev_csat, 1) : null,
        ];
    }
    usort($segments[$col]['data'], fn($a, $b) => $b['csat'] <=> $a['csat'] ?: $b['responses'] <=> $a['responses']);
}

// ---------------------------------------------------------------------------
// "Most Improved" (biggest delta)
// ---------------------------------------------------------------------------
$most_improved = array_values(array_filter($office_rankings, fn($o) => $o['delta'] !== null));
usort($most_improved, fn($a, $b) => $b['delta'] <=> $a['delta']);
$most_improved = array_slice($most_improved, 0, 1);

// ---------------------------------------------------------------------------
// Offices for filter
// ---------------------------------------------------------------------------
$filter_offices = [];
$fo_res = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($fo_res) while ($r = $fo_res->fetch_assoc()) $filter_offices[] = $r;

// Page metadata
$page_title    = 'Rankings & Insights';
$page_crumb    = 'Administration / Rankings';
$page_subtitle = 'Performance leaderboards across offices, services, and resolvers';
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
            --maroon: #7A0000; --maroon-dark: #580000; --gold: #F2A900;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --info: #2563EB;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width:100%; height:100%; margin:0; padding:0; overflow-x:hidden; }
        * { box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; -webkit-text-size-adjust:100%; }
        body { background-color:var(--bg); color:var(--text-body); display:flex; min-height:100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width:0; display:flex; flex-direction:column; min-height:100vh; overflow-x:hidden;
            transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1), width 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .admin-container {
            width:100%; max-width:100%;
            padding: calc(var(--header-height) + 20px) 24px 24px;
            flex:1; display:flex; flex-direction:column;
        }

        /* ===== TOOLBAR ===== */
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
    top: var(--header-height); /* Sticks it exactly under your top navigation */
    z-index: 10;               /* Keeps it above the scrolling content below */
}
        .toolbar-title h2 { font-size:19px; font-weight:800; color:var(--text-heading); margin:0; }
        .toolbar-title p { font-size:12px; color:var(--text-muted); margin:2px 0 0; }
        .filter-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .select-live-filter {
            padding:9px 12px; border:1.5px solid var(--border); border-radius:8px;
            font-size:12.5px; background:#F8FAFC; color:var(--text-heading);
            outline:none; font-family:inherit; transition:border-color 0.18s, background 0.18s;
        }
        .select-live-filter:focus { border-color:var(--maroon); background:#FFFFFF; }
        .btn-reset {
            background:#FFFFFF; color:var(--text-heading);
            border:1.5px solid var(--border); padding:9px 14px;
            border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; text-decoration:none; font-family:inherit;
            transition: background 0.18s, border-color 0.18s;
        }
        .btn-reset:hover { background:#F1F5F9; border-color:var(--border-strong); }

        /* ===== PODIUM ===== */
        .podium-grid {
            display:grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap:12px; margin-bottom:12px;
        }
        .podium-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            position:relative; overflow:hidden; min-width:0;
            display:flex; flex-direction:column; gap:10px;
        }
        .podium-card.rank-1 { border-color:#FDE68A; background:linear-gradient(180deg,#FFFDF5 0%,#FFFFFF 60%); }
        .podium-card.rank-2 { border-color:#E2E8F0; background:linear-gradient(180deg,#FAFBFC 0%,#FFFFFF 60%); }
        .podium-card.rank-3 { border-color:#FED7AA; background:linear-gradient(180deg,#FFF8F1 0%,#FFFFFF 60%); }

        .podium-card::before {
            content:''; position:absolute; left:0; top:0; bottom:0;
            width:5px;
        }
        .podium-card.rank-1::before { background:linear-gradient(180deg,#F2A900,#B45309); }
        .podium-card.rank-2::before { background:linear-gradient(180deg,#94A3B8,#475569); }
        .podium-card.rank-3::before { background:linear-gradient(180deg,#F97316,#C2410C); }

        .podium-header {
            display:flex; align-items:center; gap:12px;
        }
        .medal {
            width:42px; height:42px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:16px; font-weight:800; color:#FFFFFF;
            flex-shrink:0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .medal.gold   { background:linear-gradient(135deg,#FBBF24,#B45309); }
        .medal.silver { background:linear-gradient(135deg,#94A3B8,#475569); }
        .medal.bronze { background:linear-gradient(135deg,#FB923C,#9A3412); }

        .podium-title { min-width:0; flex:1; }
        .podium-title .name {
            font-size:13px; font-weight:800; color:var(--text-heading);
            line-height:1.25; word-wrap:break-word;
        }
        .podium-title .sub {
            font-size:11px; color:var(--text-muted); margin-top:2px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }

        .podium-metric {
            display:flex; align-items:baseline; gap:6px;
        }
        .podium-metric .value {
            font-size:30px; font-weight:800; color:var(--text-heading);
            letter-spacing:-0.8px; line-height:1;
        }
        .podium-metric .unit { font-size:14px; font-weight:700; color:var(--text-muted); }
        .podium-metric .delta {
            margin-left:auto; font-size:11px; font-weight:700;
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 7px; border-radius:5px;
        }
        .delta-up   { background:#ECFDF5; color:#047857; }
        .delta-down { background:#FEE2E2; color:#B91C1C; }
        .delta-flat { background:#F1F5F9; color:var(--text-muted); }

        .podium-sub {
            font-size:11.5px; color:var(--text-muted); font-weight:600;
            padding-top:8px; border-top:1px dashed var(--border);
            display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;
        }

        /* ===== TAB NAV ===== */
        .tab-nav {
            display:flex; gap:4px; padding:4px;
            background:var(--surface); border:1px solid var(--border);
            border-radius:10px; margin-bottom:12px; overflow-x:auto;
            scrollbar-width:none;
        }
        .tab-nav::-webkit-scrollbar { display:none; }
        .tab-btn {
            flex:1; min-width:150px; padding:10px 14px;
            border:none; background:transparent; color:var(--text-muted);
            font-family:inherit; font-size:12.5px; font-weight:700;
            cursor:pointer; border-radius:7px;
            display:inline-flex; align-items:center; justify-content:center;
            gap:8px; white-space:nowrap;
            transition:all 0.18s;
        }
        .tab-btn:hover { background:#F1F5F9; color:var(--text-heading); }
        .tab-btn.active { background:var(--maroon); color:#FFFFFF; box-shadow:0 2px 6px rgba(122,0,0,0.18); }

        /* ===== PANEL ===== */
        .panel-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            margin-bottom:12px;
        }
        .panel-card.panel-hidden { display:none; }

        .panel-head {
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px; margin-bottom:14px;
        }
        .panel-head h3 { font-size:14px; font-weight:800; color:var(--text-heading); margin:0; }
        .panel-head p { font-size:11.5px; color:var(--text-muted); margin:3px 0 0; }

        /* ===== RANKING TABLE ===== */
        .rank-table { width:100%; border-collapse:collapse; font-size:12.5px; }
        .rank-table thead th {
            background:#F8FAFC; color:var(--text-muted); font-weight:700;
            font-size:10.5px; text-transform:uppercase; letter-spacing:0.4px;
            padding:10px 12px; text-align:left;
            border-bottom:1px solid var(--border);
            position:sticky; top:0; z-index:2;
        }
        .rank-table td {
            padding:12px; border-bottom:1px solid #F1F5F9;
            vertical-align:middle; color:var(--text-body);
        }
        .rank-table tr:last-child td { border-bottom:none; }
        .rank-table tr:hover td { background:#FAFBFC; }

        .rank-pos {
            display:inline-flex; align-items:center; justify-content:center;
            width:28px; height:28px; border-radius:8px;
            font-size:11.5px; font-weight:800;
            background:#F1F5F9; color:var(--text-body);
        }
        .rank-pos.r1 { background:linear-gradient(135deg,#FEF3C7,#FDE68A); color:#B45309; }
        .rank-pos.r2 { background:linear-gradient(135deg,#E2E8F0,#CBD5E1); color:#475569; }
        .rank-pos.r3 { background:linear-gradient(135deg,#FED7AA,#FDBA74); color:#9A3412; }

        .entity-name { display:flex; flex-direction:column; gap:1px; min-width:0; }
        .entity-name .primary {
            font-weight:700; color:var(--text-heading); font-size:12.5px;
            line-height:1.25;
        }
        .entity-name .secondary {
            font-size:11px; color:var(--text-muted); line-height:1.3;
        }

        .metric-bar {
            display:flex; align-items:center; gap:10px; min-width:140px;
        }
        .bar-track {
            position:relative; height:7px; background:#F1F5F9;
            border-radius:4px; overflow:hidden; flex:1; min-width:60px;
        }
        .bar-fill {
            position:absolute; left:0; top:0; bottom:0;
            border-radius:4px; transition: width 0.4s;
        }
        .bar-fill.green  { background:linear-gradient(90deg,#10B981,#047857); }
        .bar-fill.blue   { background:linear-gradient(90deg,#3B82F6,#1D4ED8); }
        .bar-fill.warn   { background:linear-gradient(90deg,#F59E0B,#B45309); }
        .bar-fill.danger { background:linear-gradient(90deg,#EF4444,#B91C1C); }

        .metric-value {
            font-weight:800; color:var(--text-heading);
            font-size:12.5px; min-width:48px; text-align:right;
        }

        .delta-chip {
            display:inline-flex; align-items:center; gap:3px;
            font-size:11px; font-weight:700;
            padding:2px 7px; border-radius:5px;
            white-space:nowrap;
        }
        .badge-mini {
            display:inline-flex; align-items:center;
            font-size:10.5px; font-weight:700;
            padding:3px 8px; border-radius:5px; white-space:nowrap;
        }
        .badge-mini.ok     { background:#ECFDF5; color:#047857; }
        .badge-mini.warn   { background:#FEF3C7; color:#B45309; }
        .badge-mini.danger { background:#FEE2E2; color:#B91C1C; }
        .badge-mini.neutral{ background:#F1F5F9; color:var(--text-body); }

        .empty-state {
            text-align:center; color:var(--text-muted); padding:50px 20px;
        }
        .empty-state svg { width:44px; height:44px; color:#CBD5E1; margin-bottom:10px; }
        .empty-state h4 { color:var(--text-heading); font-size:15px; margin:0 0 4px; }
        .empty-state p { font-size:12.5px; margin:0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .podium-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
        }
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction:column; align-items:stretch; padding:14px 16px; }
            .filter-controls { width:100%; }
            .select-live-filter, .btn-reset { flex:1; min-width:0; }
            .podium-grid { grid-template-columns:1fr; }
            .podium-card { padding:14px; }
            .panel-card { padding:14px; }
            .rank-table td, .rank-table th { padding:10px 8px; font-size:11.5px; }
            .metric-bar { min-width:100px; gap:6px; }
            .metric-value { min-width:42px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size:17px; }
            .toolbar-title p { font-size:11.5px; }
            .podium-metric .value { font-size:24px; }
            .medal { width:36px; height:36px; font-size:14px; }
            .rank-table .hide-sm { display:none; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition:none !important; animation:none !important; }
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

            <!-- ============= TOOLBAR ============= -->
            <section class="toolbar">
                <div class="toolbar-title">
                    <h2>Rankings &amp; Insights</h2>
                    <p>Performance leaderboards across offices, services, resolvers, and client segments</p>
                </div>

                <form method="GET" action="admin_rankings.php" class="filter-controls">
                    <select name="range" class="select-live-filter" onchange="this.form.submit()">
                        <option value="30d"  <?php echo $range === '30d'  ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90d"  <?php echo $range === '90d'  ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="180d" <?php echo $range === '180d' ? 'selected' : ''; ?>>Last 6 months</option>
                        <option value="365d" <?php echo $range === '365d' ? 'selected' : ''; ?>>Last 12 months</option>
                        <option value="all"  <?php echo $range === 'all'  ? 'selected' : ''; ?>>All time</option>
                    </select>

                    <select name="office_id" class="select-live-filter" onchange="this.form.submit()">
                        <option value="0">All Offices</option>
                        <?php foreach ($filter_offices as $o): ?>
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

                    <select name="min_resp" class="select-live-filter" onchange="this.form.submit()">
                        <option value="1" <?php echo $min_resp === 1 ? 'selected' : ''; ?>>Min 1 response</option>
                        <option value="3" <?php echo $min_resp === 3 ? 'selected' : ''; ?>>Min 3 responses</option>
                        <option value="5" <?php echo $min_resp === 5 ? 'selected' : ''; ?>>Min 5 responses</option>
                        <option value="10"<?php echo $min_resp === 10 ? 'selected' : ''; ?>>Min 10 responses</option>
                    </select>

                    <?php if ($range !== '90d' || $office_filter > 0 || $client_filter !== '' || $min_resp !== 3): ?>
                        <a href="admin_rankings.php" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- ============= PODIUM — Top 3 Offices ============= -->
            <?php if (count($office_rankings) >= 1): ?>
                <section class="podium-grid">
                    <?php
                    $top3 = array_slice($office_rankings, 0, 3);
                    $labels = ['1st', '2nd', '3rd'];
                    foreach ($top3 as $i => $o):
                        $rank = $i + 1;
                        $trend = $o['delta'] !== null ? trend_arrow($o['delta']) : ['flat', '—'];
                    ?>
                        <div class="podium-card rank-<?php echo $rank; ?>">
                            <div class="podium-header">
                                <div class="medal <?php echo ['gold','silver','bronze'][$i]; ?>">
                                    <?php echo $rank; ?>
                                </div>
                                <div class="podium-title">
                                    <div class="name"><?php echo htmlspecialchars($o['name']); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($o['type'] ?: 'Office'); ?> ·
                                        <?php echo number_format($o['responses']); ?> response<?php echo $o['responses'] === 1 ? '' : 's'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="podium-metric">
                                <span class="value"><?php echo number_format($o['csat'], 1); ?></span>
                                <span class="unit">% CSAT</span>
                                <span class="delta delta-<?php echo $trend[0]; ?>">
                                    <?php if ($trend[0] === 'up'): ?>
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    <?php elseif ($trend[0] === 'down'): ?>
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($trend[1]); ?>
                                </span>
                            </div>

                            <div class="podium-sub">
                                <span>
                                    <?php if ($o['breach_rate'] !== null): ?>
                                        SLA breach: <strong><?php echo number_format($o['breach_rate'], 1); ?>%</strong>
                                    <?php else: ?>
                                        No SLA data
                                    <?php endif; ?>
                                </span>
                                <span><?php echo $labels[$i]; ?> place</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <!-- ============= TABS ============= -->
            <nav class="tab-nav" id="tabNav">
                <button class="tab-btn active" data-tab="offices" onclick="switchTab('offices')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01"/>
                    </svg>
                    Offices
                </button>
                <button class="tab-btn" data-tab="services" onclick="switchTab('services')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Services
                </button>
                <button class="tab-btn" data-tab="resolvers" onclick="switchTab('resolvers')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Resolvers
                </button>
                <button class="tab-btn" data-tab="segments" onclick="switchTab('segments')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M12 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Client Segments
                </button>
            </nav>

            <!-- ============= TAB 1: OFFICES ============= -->
            <section class="panel-card" id="tabPanel-offices">
                <div class="panel-head">
                    <div>
                        <h3>Office Leaderboard</h3>
                        <p>Ranked by CSAT for the selected period — compliance and volume included</p>
                    </div>
                </div>

                <?php if (!empty($office_rankings)): ?>
                    <div style="overflow-x:auto;">
                        <table class="rank-table">
                            <thead>
                                <tr>
                                    <th style="width:56px;">Rank</th>
                                    <th>Office</th>
                                    <th style="width:200px;">CSAT</th>
                                    <th style="width:100px;" class="hide-sm">Trend</th>
                                    <th style="width:120px;">Responses</th>
                                    <th style="width:130px;" class="hide-sm">SLA Breach</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($office_rankings as $i => $o): ?>
                                    <?php
                                        $pos = $i + 1;
                                        $rclass = $pos === 1 ? 'r1' : ($pos === 2 ? 'r2' : ($pos === 3 ? 'r3' : ''));
                                        $trend = $o['delta'] !== null ? trend_arrow($o['delta']) : ['flat', '—'];
                                        $barClass = $o['csat'] >= 80 ? 'green' : ($o['csat'] >= 60 ? 'blue' : ($o['csat'] >= 40 ? 'warn' : 'danger'));
                                    ?>
                                    <tr>
                                        <td><span class="rank-pos <?php echo $rclass; ?>"><?php echo $pos; ?></span></td>
                                        <td>
                                            <div class="entity-name">
                                                <span class="primary"><?php echo htmlspecialchars($o['name']); ?></span>
                                                <span class="secondary"><?php echo htmlspecialchars($o['type'] ?: '—'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-bar">
                                                <div class="bar-track">
                                                    <div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo max(2, $o['csat']); ?>%;"></div>
                                                </div>
                                                <span class="metric-value"><?php echo number_format($o['csat'], 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td class="hide-sm">
                                            <span class="delta-chip delta-<?php echo $trend[0]; ?>">
                                                <?php if ($trend[0] === 'up'): ?>▲<?php elseif ($trend[0] === 'down'): ?>▼<?php endif; ?>
                                                <?php echo htmlspecialchars($trend[1]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($o['responses']); ?></td>
                                        <td class="hide-sm">
                                            <?php if ($o['breach_rate'] === null): ?>
                                                <span class="badge-mini neutral">No data</span>
                                            <?php elseif ($o['breach_rate'] == 0): ?>
                                                <span class="badge-mini ok">0.0%</span>
                                            <?php elseif ($o['breach_rate'] < 20): ?>
                                                <span class="badge-mini warn"><?php echo number_format($o['breach_rate'], 1); ?>%</span>
                                            <?php else: ?>
                                                <span class="badge-mini danger"><?php echo number_format($o['breach_rate'], 1); ?>%</span>
                                            <?php endif; ?>
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
                        <h4>No office rankings</h4>
                        <p>No offices meet the minimum response threshold (<?php echo $min_resp; ?>). Lower it in the filters above.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============= TAB 2: SERVICES ============= -->
            <section class="panel-card panel-hidden" id="tabPanel-services">
                <div class="panel-head">
                    <div>
                        <h3>Service Leaderboard</h3>
                        <p>Per-service CSAT and actual vs. mandated ARTA processing time</p>
                    </div>
                </div>

                <?php if (!empty($service_rankings)): ?>
                    <div style="overflow-x:auto;">
                        <table class="rank-table">
                            <thead>
                                <tr>
                                    <th style="width:56px;">Rank</th>
                                    <th>Service</th>
                                    <th style="width:180px;">CSAT</th>
                                    <th style="width:100px;" class="hide-sm">Trend</th>
                                    <th style="width:110px;">Actual / SLA</th>
                                    <th style="width:120px;">Responses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($service_rankings as $i => $s): ?>
                                    <?php
                                        $pos = $i + 1;
                                        $rclass = $pos === 1 ? 'r1' : ($pos === 2 ? 'r2' : ($pos === 3 ? 'r3' : ''));
                                        $trend = $s['delta'] !== null ? trend_arrow($s['delta']) : ['flat', '—'];
                                        $barClass = $s['csat'] >= 80 ? 'green' : ($s['csat'] >= 60 ? 'blue' : ($s['csat'] >= 40 ? 'warn' : 'danger'));
                                        $sla_display = $s['sla_days'] !== null ? $s['sla_days'] . 'd' : '—';
                                        $actual_display = $s['avg_days'] !== null ? number_format($s['avg_days'], 1) . 'd' : '—';
                                        $is_breach = $s['avg_days'] !== null && $s['sla_days'] !== null && $s['avg_days'] > $s['sla_days'];
                                    ?>
                                    <tr>
                                        <td><span class="rank-pos <?php echo $rclass; ?>"><?php echo $pos; ?></span></td>
                                        <td>
                                            <div class="entity-name">
                                                <span class="primary"><?php echo htmlspecialchars($s['service_name']); ?></span>
                                                <span class="secondary"><?php echo htmlspecialchars($s['office_name']); ?> · <?php echo htmlspecialchars($s['classification']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-bar">
                                                <div class="bar-track">
                                                    <div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo max(2, $s['csat']); ?>%;"></div>
                                                </div>
                                                <span class="metric-value"><?php echo number_format($s['csat'], 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td class="hide-sm">
                                            <span class="delta-chip delta-<?php echo $trend[0]; ?>">
                                                <?php if ($trend[0] === 'up'): ?>▲<?php elseif ($trend[0] === 'down'): ?>▼<?php endif; ?>
                                                <?php echo htmlspecialchars($trend[1]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-mini <?php echo $is_breach ? 'danger' : 'ok'; ?>">
                                                <?php echo $actual_display; ?> / <?php echo $sla_display; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($s['responses']); ?></td>
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
                        <h4>No service rankings</h4>
                        <p>No services meet the minimum response threshold (<?php echo $min_resp; ?>).</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============= TAB 3: RESOLVERS ============= -->
            <section class="panel-card panel-hidden" id="tabPanel-resolvers">
                <div class="panel-head">
                    <div>
                        <h3>Resolver Leaderboard</h3>
                        <p>Staff members ranked by corrective-action ticket resolution rate</p>
                    </div>
                </div>

                <?php if (!empty($resolver_rankings)): ?>
                    <div style="overflow-x:auto;">
                        <table class="rank-table">
                            <thead>
                                <tr>
                                    <th style="width:56px;">Rank</th>
                                    <th>Staff Member</th>
                                    <th style="width:180px;">Resolution Rate</th>
                                    <th style="width:120px;">Assigned</th>
                                    <th style="width:100px;" class="hide-sm">Resolved</th>
                                    <th style="width:130px;" class="hide-sm">Avg. Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resolver_rankings as $i => $r): ?>
                                    <?php
                                        $pos = $i + 1;
                                        $rclass = $pos === 1 ? 'r1' : ($pos === 2 ? 'r2' : ($pos === 3 ? 'r3' : ''));
                                        $rate = (float)$r['resolution_rate'];
                                        $barClass = $rate >= 80 ? 'green' : ($rate >= 50 ? 'blue' : ($rate >= 25 ? 'warn' : 'danger'));
                                    ?>
                                    <tr>
                                        <td><span class="rank-pos <?php echo $rclass; ?>"><?php echo $pos; ?></span></td>
                                        <td>
                                            <div class="entity-name">
                                                <span class="primary"><?php echo htmlspecialchars($r['full_name']); ?></span>
                                                <span class="secondary">
                                                    <?php echo htmlspecialchars($r['user_id']); ?> ·
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $r['internal_user_type']))); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-bar">
                                                <div class="bar-track">
                                                    <div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo max(2, $rate); ?>%;"></div>
                                                </div>
                                                <span class="metric-value"><?php echo number_format($rate, 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-mini neutral"><?php echo (int)$r['assigned']; ?></span>
                                        </td>
                                        <td class="hide-sm">
                                            <span class="badge-mini ok"><?php echo (int)$r['resolved']; ?></span>
                                        </td>
                                        <td class="hide-sm">
                                            <?php if ($r['avg_days_resolve'] !== null): ?>
                                                <span class="badge-mini <?php echo $r['avg_days_resolve'] <= 3 ? 'ok' : ($r['avg_days_resolve'] <= 7 ? 'warn' : 'danger'); ?>">
                                                    <?php echo number_format($r['avg_days_resolve'], 1); ?>d
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-mini neutral">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <h4>No resolver data yet</h4>
                        <p>Assign corrective-action tickets in the Feedback Resolutions module to populate this leaderboard.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============= TAB 4: CLIENT SEGMENTS ============= -->
            <section class="panel-card panel-hidden" id="tabPanel-segments">
                <div class="panel-head">
                    <div>
                        <h3>Client Segment Satisfaction</h3>
                        <p>CSAT breakdown by demographic — reveals where experience gaps exist</p>
                    </div>
                </div>

                <?php
                $has_segment_data = false;
                foreach ($segments as $s) { if (!empty($s['data'])) { $has_segment_data = true; break; } }
                ?>

                <?php if ($has_segment_data): ?>
                    <div style="display:grid; gap:20px;">
                        <?php foreach ($segments as $key => $meta): ?>
                            <?php if (empty($meta['data'])) continue; ?>
                            <div>
                                <div style="font-size:11px; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:var(--maroon); margin-bottom:10px; padding-bottom:6px; border-bottom:1px dashed #FECACA;">
                                    <?php echo htmlspecialchars($meta['label']); ?>
                                </div>
                                <div style="overflow-x:auto;">
                                    <table class="rank-table">
                                        <thead>
                                            <tr>
                                                <th style="width:56px;">#</th>
                                                <th>Segment</th>
                                                <th style="width:200px;">CSAT</th>
                                                <th style="width:100px;" class="hide-sm">Trend</th>
                                                <th style="width:120px;">Responses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($meta['data'] as $i => $row): ?>
                                                <?php
                                                    $pos = $i + 1;
                                                    $rclass = $pos === 1 ? 'r1' : ($pos === 2 ? 'r2' : ($pos === 3 ? 'r3' : ''));
                                                    $trend = $row['delta'] !== null ? trend_arrow($row['delta']) : ['flat', '—'];
                                                    $barClass = $row['csat'] >= 80 ? 'green' : ($row['csat'] >= 60 ? 'blue' : ($row['csat'] >= 40 ? 'warn' : 'danger'));
                                                ?>
                                                <tr>
                                                    <td><span class="rank-pos <?php echo $rclass; ?>"><?php echo $pos; ?></span></td>
                                                    <td>
                                                        <div class="entity-name">
                                                            <span class="primary"><?php echo htmlspecialchars($row['segment']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-bar">
                                                            <div class="bar-track">
                                                                <div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo max(2, $row['csat']); ?>%;"></div>
                                                            </div>
                                                            <span class="metric-value"><?php echo number_format($row['csat'], 1); ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td class="hide-sm">
                                                        <span class="delta-chip delta-<?php echo $trend[0]; ?>">
                                                            <?php if ($trend[0] === 'up'): ?>▲<?php elseif ($trend[0] === 'down'): ?>▼<?php endif; ?>
                                                            <?php echo htmlspecialchars($trend[1]); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($row['responses']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M12 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <h4>No segment data</h4>
                        <p>No demographic segments meet the minimum response threshold (<?php echo $min_resp; ?>).</p>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <script>
        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === name);
            });
            document.querySelectorAll('.panel-card[id^="tabPanel-"]').forEach(panel => {
                panel.classList.toggle('panel-hidden', panel.id !== 'tabPanel-' + name);
            });
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState({}, '', url);
            } catch(e) {}
        }

        // Restore active tab from URL
        (function() {
            const url = new URL(window.location.href);
            const tab = url.searchParams.get('tab');
            if (tab && document.querySelector('.tab-btn[data-tab="' + tab + '"]')) {
                switchTab(tab);
            }
        })();
    </script>
</body>
</html>