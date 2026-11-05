<?php
// admin_demographics.php — Analytics & Insight: Client Demographics

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
$min_resp      = max(1, (int)($_GET['min_resp'] ?? 3));

$range_days = match ($range) {
    '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365, 'all' => null, default => 90,
};

$now_ts     = time();
$cur_end    = date('Y-m-d H:i:s', $now_ts);
$cur_start  = $range_days !== null ? date('Y-m-d H:i:s', $now_ts - ($range_days * 86400)) : '1970-01-01 00:00:00';
$prev_end   = $cur_start;
$prev_start = $range_days !== null ? date('Y-m-d H:i:s', $now_ts - ($range_days * 2 * 86400)) : null;

$extra = '';
if ($office_filter > 0) $extra .= " AND f.office_id = " . (int)$office_filter;

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------
function csat_from_row(array $r): float {
    $rated = (int)($r['rated'] ?? 0);
    return $rated > 0 ? round(((int)$r['satisfied'] / $rated) * 100, 1) : 0.0;
}

function fetch_segment(mysqli $conn, string $col, string $extra, ?string $start, ?string $end): array {
    if ($start === null) return [];
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
    if (!$stmt) return [];
    $stmt->bind_param("ss", $start, $end);
    if (!$stmt->execute()) { $stmt->close(); return []; }
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['segment']] = $r;
    $stmt->close();
    return $out;
}

// ---------------------------------------------------------------------------
// OVERALL KPI
// ---------------------------------------------------------------------------
$kpi_res = $conn->prepare("
    SELECT
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
    FROM arta_feedback f
    WHERE f.is_archived = 0
      AND f.submission_timestamp >= ?
      AND f.submission_timestamp <  ?
      $extra
");
$overall_csat = 0.0;
$total_responses = 0;
if ($kpi_res) {
    $kpi_res->bind_param("ss", $cur_start, $cur_end);
    $kpi_res->execute();
    $kr = $kpi_res->get_result()->fetch_assoc();
    $kpi_res->close();
    $total_responses = (int)($kr['total_responses'] ?? 0);
    $overall_csat = csat_from_row($kr ?: []);
}

// ---------------------------------------------------------------------------
// FETCH SEGMENTS (current + previous)
// ---------------------------------------------------------------------------
$segment_defs = [
    'age_group' => [
        'label' => 'Age Group', 'icon_key' => 'age',
        'order' => ['19 or lower', '20-34', '35-49', '50-64', '65 or higher'],
    ],
    'sex' => [
        'label' => 'Sex', 'icon_key' => 'sex',
        'order' => ['Male', 'Female', 'Prefer not to say'],
    ],
    'client_type' => [
        'label' => 'Client Type', 'icon_key' => 'client',
        'order' => ['Citizen', 'Business', 'Government'],
    ],
    'region' => [
        'label' => 'Region', 'icon_key' => 'region',
        'order' => null, // sorted by volume
    ],
];

$segments = [];
foreach ($segment_defs as $col => $meta) {
    $cur  = fetch_segment($conn, $col, $extra, $cur_start, $cur_end);
    $prev = fetch_segment($conn, $col, $extra, $prev_start, $prev_end);

    $rows = [];
    foreach ($cur as $seg => $row) {
        if ((int)$row['responses'] < $min_resp) continue;
        $csat = csat_from_row($row);
        $prev_row = $prev[$seg] ?? null;
        $prev_csat = $prev_row ? csat_from_row($prev_row) : null;
        $rows[] = [
            'segment' => $seg,
            'csat' => $csat,
            'responses' => (int)$row['responses'],
            'delta' => $prev_csat !== null ? round($csat - $prev_csat, 1) : null,
        ];
    }

    // Sort
    if ($meta['order'] !== null) {
        // Natural order for age, sex, client type
        usort($rows, function($a, $b) use ($meta) {
            $ai = array_search($a['segment'], $meta['order'], true);
            $bi = array_search($b['segment'], $meta['order'], true);
            $ai = $ai === false ? 999 : $ai;
            $bi = $bi === false ? 999 : $bi;
            return $ai <=> $bi;
        });
    } else {
        // Region: by volume
        usort($rows, fn($a, $b) => $b['responses'] <=> $a['responses']);
    }

    $segments[$col] = [
        'label' => $meta['label'],
        'order' => $meta['order'],
        'data'  => $rows,
    ];
}

// ---------------------------------------------------------------------------
// KPI DERIVATIONS
// ---------------------------------------------------------------------------

// Most active segment (by volume) across age group
$most_active_segment = ['segment' => '—', 'responses' => 0, 'csat' => 0.0, 'type' => 'Age Group'];
foreach ($segments as $col => $meta) {
    foreach ($meta['data'] as $row) {
        if ($row['responses'] > $most_active_segment['responses']) {
            $most_active_segment = array_merge($row, ['type' => $meta['label']]);
        }
    }
}

// Best-served segment (highest CSAT, min responses threshold already applied)
$best_segment = null;
foreach ($segments as $col => $meta) {
    foreach ($meta['data'] as $row) {
        if ($best_segment === null || $row['csat'] > $best_segment['csat']) {
            $best_segment = array_merge($row, ['type' => $meta['label']]);
        }
    }
}
if ($best_segment === null) {
    $best_segment = ['segment' => '—', 'csat' => 0.0, 'responses' => 0, 'type' => '—', 'delta' => null];
}

// Equity gap: highest CSAT minus lowest CSAT across all segments with min_resp
$all_flat = [];
foreach ($segments as $col => $meta) {
    foreach ($meta['data'] as $row) {
        $all_flat[] = array_merge($row, ['type' => $meta['label'], 'type_key' => $col]);
    }
}
$equity_gap = 0.0;
$equity_leader = null;
$equity_trailer = null;
if (count($all_flat) >= 2) {
    $sorted = $all_flat;
    usort($sorted, fn($a, $b) => $b['csat'] <=> $a['csat']);
    $equity_leader  = $sorted[0];
    $equity_trailer = $sorted[count($sorted) - 1];
    $equity_gap = round($equity_leader['csat'] - $equity_trailer['csat'], 1);
}

// Equity watchlist: segments with CSAT below overall by 5+ points
$equity_watchlist = array_values(array_filter($all_flat, fn($s) => $s['csat'] < ($overall_csat - 5)));
usort($equity_watchlist, fn($a, $b) => $a['csat'] <=> $b['csat']);

// ---------------------------------------------------------------------------
// REGION ABBREVIATION HELPER (for chart labels)
// ---------------------------------------------------------------------------
function abbreviate_region(string $r): string {
    $r = trim($r);
    if (stripos($r, ' - ') !== false) {
        return trim(explode(' - ', $r, 2)[0]);
    }
    if (stripos($r, ' Region') === 0) {
        return trim(explode(' ', $r, 2)[0]);
    }
    return mb_strimwidth($r, 0, 20, '…');
}

// ---------------------------------------------------------------------------
// OFFICES FOR FILTER
// ---------------------------------------------------------------------------
$filter_offices = [];
$fo_res = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($fo_res) while ($r = $fo_res->fetch_assoc()) $filter_offices[] = $r;

// ---------------------------------------------------------------------------
// PAYLOAD FOR JS CHARTS
// ---------------------------------------------------------------------------
$chart_payload = [
    'age' => [
        'labels'   => array_map(fn($r) => $r['segment'], $segments['age_group']['data']),
        'csat'     => array_map(fn($r) => $r['csat'],   $segments['age_group']['data']),
        'volume'   => array_map(fn($r) => $r['responses'], $segments['age_group']['data']),
    ],
    'sex' => [
        'labels'   => array_map(fn($r) => $r['segment'], $segments['sex']['data']),
        'csat'     => array_map(fn($r) => $r['csat'],   $segments['sex']['data']),
        'volume'   => array_map(fn($r) => $r['responses'], $segments['sex']['data']),
    ],
    'client' => [
        'labels'   => array_map(fn($r) => $r['segment'], $segments['client_type']['data']),
        'csat'     => array_map(fn($r) => $r['csat'],   $segments['client_type']['data']),
        'volume'   => array_map(fn($r) => $r['responses'], $segments['client_type']['data']),
    ],
    'region' => [
        'labels'   => array_map(fn($r) => abbreviate_region($r['segment']), $segments['region']['data']),
        'full'     => array_map(fn($r) => $r['segment'], $segments['region']['data']),
        'csat'     => array_map(fn($r) => $r['csat'],   $segments['region']['data']),
        'volume'   => array_map(fn($r) => $r['responses'], $segments['region']['data']),
    ],
    'overall_csat' => $overall_csat,
];

// Page metadata
$page_title    = 'Client Demographics';
$page_crumb    = 'Administration / Demographics';
$page_subtitle = 'Satisfaction equity across age, sex, client type, and region';
$unread_notifications = 5;
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
    top: var(--header-height); 
    z-index: 10;               
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

        /* ===== KPI ===== */
        .kpi-grid {
            display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
            gap:12px; margin-bottom:12px;
        }
        .kpi-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            position:relative; overflow:hidden;
            display:flex; flex-direction:column; gap:8px; min-width:0;
        }
        .kpi-card::before {
            content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:linear-gradient(180deg, var(--maroon), var(--maroon-dark));
        }
        .kpi-card.volume::before   { background:linear-gradient(180deg,#2563EB,#1D4ED8); }
        .kpi-card.best::before     { background:linear-gradient(180deg,#10B981,#047857); }
        .kpi-card.active::before   { background:linear-gradient(180deg,#8B5CF6,#6D28D9); }
        .kpi-card.gap::before      { background:linear-gradient(180deg,#F59E0B,#B45309); }

        .kpi-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .kpi-label {
            font-size:11px; font-weight:800; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:0.7px;
        }
        .kpi-icon {
            width:30px; height:30px; border-radius:8px;
            display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .kpi-icon svg { width:16px; height:16px; }
        .kpi-icon.blue    { background:#EFF6FF; color:#1D4ED8; }
        .kpi-icon.green   { background:#ECFDF5; color:#047857; }
        .kpi-icon.purple  { background:#F5F3FF; color:#6D28D9; }
        .kpi-icon.orange  { background:#FFF7ED; color:#C2410C; }
        .kpi-icon.maroon  { background:#FEF2F2; color:var(--maroon); }

        .kpi-value {
            font-size:28px; font-weight:800; color:var(--text-heading);
            line-height:1; letter-spacing:-0.8px;
            display:flex; align-items:baseline; gap:6px;
            flex-wrap:wrap;
        }
        .kpi-value .unit { font-size:15px; font-weight:700; color:var(--text-muted); }
        .kpi-value.segmented { font-size:17px; letter-spacing:-0.3px; line-height:1.2; }

        .kpi-sub {
            font-size:11.5px; color:var(--text-muted); font-weight:600;
            display:flex; align-items:center; gap:5px; line-height:1.4;
        }
        .kpi-sub .dot {
            display:inline-block; width:6px; height:6px; border-radius:50%;
            background:currentColor; flex-shrink:0;
        }

        /* ===== CHART GRID ===== */
        .charts-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;
        }
        .chart-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            min-width:0;
            display:flex; flex-direction:column;
        }
        .chart-card.full { grid-column: 1 / -1; }
        .chart-head {
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px; margin-bottom:14px;
        }
        .chart-head h3 { font-size:14px; font-weight:800; color:var(--text-heading); margin:0; line-height:1.2; }
        .chart-head p { font-size:11.5px; color:var(--text-muted); margin:3px 0 0; }
        .chart-canvas-wrap { position:relative; height:260px; min-width:0; }
        .chart-canvas-wrap.tall { height:300px; }

        /* ===== PANEL / TABLE ===== */
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

        /* Watchlist */
        .watchlist {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
            gap:10px;
        }
        .watchlist-item {
            background:#FAFBFC; border:1px solid var(--border);
            border-left:3px solid var(--warning);
            border-radius:10px; padding:12px 14px;
            display:flex; flex-direction:column; gap:6px;
        }
        .watchlist-item.critical { border-left-color:var(--danger); background:#FEF7F7; }
        .watchlist-item.warning  { border-left-color:var(--warning); background:#FFFBF4; }
        .watchlist-item .wl-head {
            display:flex; align-items:center; justify-content:space-between;
            gap:8px; flex-wrap:wrap;
        }
        .watchlist-item .wl-type {
            font-size:10px; font-weight:800; text-transform:uppercase;
            letter-spacing:0.6px; color:var(--text-muted);
        }
        .watchlist-item .wl-name {
            font-size:13px; font-weight:700; color:var(--text-heading);
        }
        .watchlist-item .wl-metric {
            display:flex; align-items:baseline; gap:8px;
        }
        .watchlist-item .wl-value {
            font-size:22px; font-weight:800; color:var(--text-heading);
            line-height:1; letter-spacing:-0.5px;
        }
        .watchlist-item.critical .wl-value { color:var(--danger); }
        .watchlist-item.warning  .wl-value { color:#B45309; }
        .watchlist-item .wl-gap {
            font-size:11.5px; font-weight:700; color:var(--danger);
            margin-left:auto;
        }
        .watchlist-item .wl-sub {
            font-size:11px; color:var(--text-muted); font-weight:600;
        }

        /* Tab nav */
        .tab-nav {
            display:flex; gap:4px; padding:4px;
            background:#F8FAFC; border:1px solid var(--border);
            border-radius:10px; margin-bottom:14px;
            overflow-x:auto; scrollbar-width:none;
        }
        .tab-nav::-webkit-scrollbar { display:none; }
        .tab-btn {
            flex:1; min-width:120px; padding:9px 14px;
            border:none; background:transparent; color:var(--text-muted);
            font-family:inherit; font-size:12px; font-weight:700;
            cursor:pointer; border-radius:7px;
            display:inline-flex; align-items:center; justify-content:center;
            gap:6px; white-space:nowrap;
            transition:all 0.18s;
        }
        .tab-btn:hover { background:#FFFFFF; color:var(--text-heading); }
        .tab-btn.active { background:var(--surface); color:var(--maroon); box-shadow:0 1px 3px rgba(0,0,0,0.06); }
        .tab-count {
            background:#E2E8F0; color:#475569;
            font-size:10.5px; font-weight:800;
            padding:1px 6px; border-radius:8px;
        }
        .tab-btn.active .tab-count { background:#FECACA; color:var(--maroon); }

        /* Ranking table */
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

        .entity-name { display:flex; flex-direction:column; gap:1px; min-width:0; }
        .entity-name .primary { font-weight:700; color:var(--text-heading); font-size:12.5px; line-height:1.25; }
        .entity-name .secondary { font-size:11px; color:var(--text-muted); line-height:1.3; }

        .metric-bar { display:flex; align-items:center; gap:10px; min-width:140px; }
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
            padding:2px 7px; border-radius:5px; white-space:nowrap;
        }
        .delta-up   { background:#ECFDF5; color:#047857; }
        .delta-down { background:#FEE2E2; color:#B91C1C; }
        .delta-flat { background:#F1F5F9; color:var(--text-muted); }

        .badge-mini {
            display:inline-flex; align-items:center;
            font-size:10.5px; font-weight:700;
            padding:3px 8px; border-radius:5px; white-space:nowrap;
        }
        .badge-mini.ok     { background:#ECFDF5; color:#047857; }
        .badge-mini.warn   { background:#FEF3C7; color:#B45309; }
        .badge-mini.danger { background:#FEE2E2; color:#B91C1C; }
        .badge-mini.neutral{ background:#F1F5F9; color:var(--text-body); }

        .empty-state { text-align:center; color:var(--text-muted); padding:50px 20px; }
        .empty-state svg { width:44px; height:44px; color:#CBD5E1; margin-bottom:10px; }
        .empty-state h4 { color:var(--text-heading); font-size:15px; margin:0 0 4px; }
        .empty-state p { font-size:12.5px; margin:0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
            .charts-grid { grid-template-columns:1fr; }
        }
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction:column; align-items:stretch; padding:14px 16px; }
            .filter-controls { width:100%; }
            .select-live-filter, .btn-reset { flex:1; min-width:0; }
            .chart-canvas-wrap { height:230px; }
            .chart-canvas-wrap.tall { height:260px; }
            .rank-table td, .rank-table th { padding:10px 8px; font-size:11.5px; }
            .metric-bar { min-width:100px; gap:6px; }
            .metric-value { min-width:42px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size:17px; }
            .toolbar-title p { font-size:11.5px; }
            .kpi-grid { gap:8px; }
            .kpi-card { padding:14px; border-radius:12px; }
            .kpi-value { font-size:22px; }
            .kpi-value.segmented { font-size:15px; }
            .kpi-label { font-size:10px; }
            .kpi-icon { width:26px; height:26px; }
            .kpi-icon svg { width:14px; height:14px; }
            .chart-card, .panel-card { padding:14px; }
            .chart-canvas-wrap { height:200px; }
            .rank-table .hide-sm { display:none; }
            .watchlist { grid-template-columns:1fr; }
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
                    <h2>Client Demographics</h2>
                    <p>Satisfaction equity across age, sex, client type, and region</p>
                </div>

                <form method="GET" action="admin_demographics.php" class="filter-controls">
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

                    <select name="min_resp" class="select-live-filter" onchange="this.form.submit()">
                        <option value="1" <?php echo $min_resp === 1 ? 'selected' : ''; ?>>Min 1 response</option>
                        <option value="3" <?php echo $min_resp === 3 ? 'selected' : ''; ?>>Min 3 responses</option>
                        <option value="5" <?php echo $min_resp === 5 ? 'selected' : ''; ?>>Min 5 responses</option>
                        <option value="10"<?php echo $min_resp === 10 ? 'selected' : ''; ?>>Min 10 responses</option>
                    </select>

                    <?php if ($range !== '90d' || $office_filter > 0 || $min_resp !== 3): ?>
                        <a href="admin_demographics.php" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- ============= KPI ============= -->
            <section class="kpi-grid">
                <div class="kpi-card volume">
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
                        <span class="dot" style="background:var(--info);"></span>
                        Overall CSAT <?php echo number_format($overall_csat, 1); ?>%
                    </div>
                </div>

                <div class="kpi-card active">
                    <div class="kpi-head">
                        <span class="kpi-label">Most Active Segment</span>
                        <span class="kpi-icon purple">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value segmented"><?php echo htmlspecialchars($most_active_segment['segment']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:#8B5CF6;"></span>
                        <?php echo htmlspecialchars($most_active_segment['type']); ?> ·
                        <?php echo number_format($most_active_segment['responses']); ?> responses ·
                        <?php echo number_format((float)$most_active_segment['csat'], 1); ?>% CSAT
                    </div>
                </div>

                <div class="kpi-card best">
                    <div class="kpi-head">
                        <span class="kpi-label">Best Served Segment</span>
                        <span class="kpi-icon green">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value segmented"><?php echo htmlspecialchars($best_segment['segment']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--success);"></span>
                        <?php echo htmlspecialchars($best_segment['type']); ?> ·
                        <?php echo number_format((float)$best_segment['csat'], 1); ?>% CSAT ·
                        <?php echo number_format((int)$best_segment['responses']); ?> responses
                    </div>
                </div>

                <div class="kpi-card gap">
                    <div class="kpi-head">
                        <span class="kpi-label">Equity Gap</span>
                        <span class="kpi-icon orange">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php echo number_format($equity_gap, 1); ?><span class="unit">pts</span>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--warning);"></span>
                        <?php if ($equity_leader && $equity_trailer): ?>
                            <?php echo htmlspecialchars(mb_strimwidth($equity_leader['segment'], 0, 20, '…')); ?>
                            vs <?php echo htmlspecialchars(mb_strimwidth($equity_trailer['segment'], 0, 20, '…')); ?>
                        <?php else: ?>
                            Insufficient segments to compare
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ============= CHARTS ============= -->
            <section class="charts-grid">
                <div class="chart-card full">
                    <div class="chart-head">
                        <div>
                            <h3>Age Group Satisfaction</h3>
                            <p>CSAT % and response volume by age bracket — reveals if young or older clients are underserved</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap tall">
                        <canvas id="ageChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>Sex Distribution</h3>
                            <p>Response share and satisfaction by sex</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="sexChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h3>Client Type</h3>
                            <p>ARTA client classification breakdown</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="clientChart"></canvas>
                    </div>
                </div>

                <div class="chart-card full">
                    <div class="chart-head">
                        <div>
                            <h3>Regional Reach</h3>
                            <p>Response volume and CSAT by Philippine region — useful for geographic equity analysis</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap tall">
                        <canvas id="regionChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- ============= EQUITY WATCHLIST ============= -->
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <h3>Equity Watchlist</h3>
                        <p>Demographic segments scoring more than 5 points below the overall CSAT of <?php echo number_format($overall_csat, 1); ?>%</p>
                    </div>
                    <?php if (!empty($equity_watchlist)): ?>
                        <span class="badge-mini danger"><?php echo count($equity_watchlist); ?> segment<?php echo count($equity_watchlist) === 1 ? '' : 's'; ?> flagged</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($equity_watchlist)): ?>
                    <div class="watchlist">
                        <?php foreach ($equity_watchlist as $w): ?>
                            <?php
                                $gap = round($overall_csat - $w['csat'], 1);
                                $level = $gap >= 15 ? 'critical' : 'warning';
                            ?>
                            <div class="watchlist-item <?php echo $level; ?>">
                                <div class="wl-head">
                                    <span class="wl-type"><?php echo htmlspecialchars($w['type']); ?></span>
                                    <?php if ($w['delta'] !== null): ?>
                                        <span class="delta-chip delta-<?php echo $w['delta'] >= 0.5 ? 'up' : ($w['delta'] <= -0.5 ? 'down' : 'flat'); ?>">
                                            <?php if ($w['delta'] >= 0.5): ?>▲<?php elseif ($w['delta'] <= -0.5): ?>▼<?php endif; ?>
                                            <?php echo ($w['delta'] > 0 ? '+' : '') . number_format($w['delta'], 1); ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="wl-name"><?php echo htmlspecialchars(mb_strimwidth($w['segment'], 0, 40, '…')); ?></span>
                                <div class="wl-metric">
                                    <span class="wl-value"><?php echo number_format($w['csat'], 1); ?>%</span>
                                    <span class="wl-gap">−<?php echo number_format($gap, 1); ?> pts</span>
                                </div>
                                <div class="wl-sub">
                                    <?php echo number_format((int)$w['responses']); ?> responses
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h4>No equity concerns detected</h4>
                        <p>Every demographic segment scores within 5 points of the overall CSAT.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============= FULL BREAKDOWN ============= -->
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <h3>Full Segment Breakdown</h3>
                        <p>Every demographic dimension with CSAT, volume, and period-over-period trend</p>
                    </div>
                </div>

                <nav class="tab-nav" id="tabNav">
                    <?php $first = true; foreach ($segment_defs as $col => $meta): ?>
                        <button class="tab-btn <?php echo $first ? 'active' : ''; ?>"
                                data-tab="<?php echo htmlspecialchars($col); ?>"
                                onclick="switchTab('<?php echo htmlspecialchars($col); ?>')">
                            <?php echo htmlspecialchars($meta['label']); ?>
                            <span class="tab-count"><?php echo count($segments[$col]['data']); ?></span>
                        </button>
                    <?php $first = false; endforeach; ?>
                </nav>

                <?php $first = true; foreach ($segment_defs as $col => $meta): ?>
                    <div class="panel-tab-content" id="tabPanel-<?php echo htmlspecialchars($col); ?>" <?php echo $first ? '' : 'style="display:none;"'; ?>>
                        <?php if (!empty($segments[$col]['data'])): ?>
                            <div style="overflow-x:auto;">
                                <table class="rank-table">
                                    <thead>
                                        <tr>
                                            <th style="width:56px;">#</th>
                                            <th><?php echo htmlspecialchars($meta['label']); ?></th>
                                            <th style="width:200px;">CSAT</th>
                                            <th style="width:100px;" class="hide-sm">Trend</th>
                                            <th style="width:120px;">Responses</th>
                                            <th style="width:140px;" class="hide-sm">vs. Overall</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($segments[$col]['data'] as $i => $row): ?>
                                            <?php
                                                $pos = $i + 1;
                                                $trend = $row['delta'] !== null ? ($row['delta'] >= 0.5 ? 'up' : ($row['delta'] <= -0.5 ? 'down' : 'flat')) : 'flat';
                                                $trend_label = $row['delta'] !== null ? (($row['delta'] > 0 ? '+' : '') . number_format($row['delta'], 1) . '%') : '—';
                                                $barClass = $row['csat'] >= 80 ? 'green' : ($row['csat'] >= 60 ? 'blue' : ($row['csat'] >= 40 ? 'warn' : 'danger'));

                                                $vs_overall = round($row['csat'] - $overall_csat, 1);
                                                if ($vs_overall > 2)      { $vs_class = 'ok';     $vs_label = '+' . number_format($vs_overall, 1); }
                                                elseif ($vs_overall < -5) { $vs_class = 'danger'; $vs_label = number_format($vs_overall, 1); }
                                                elseif ($vs_overall < -2) { $vs_class = 'warn';   $vs_label = number_format($vs_overall, 1); }
                                                else                      { $vs_class = 'neutral';$vs_label = ($vs_overall > 0 ? '+' : '') . number_format($vs_overall, 1); }
                                            ?>
                                            <tr>
                                                <td><span style="font-weight:800; color:var(--text-muted); font-size:11.5px;"><?php echo $pos; ?></span></td>
                                                <td>
                                                    <div class="entity-name">
                                                        <span class="primary"><?php echo htmlspecialchars($row['segment']); ?></span>
                                                        <span class="secondary"><?php echo htmlspecialchars($meta['label']); ?></span>
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
                                                    <span class="delta-chip delta-<?php echo $trend; ?>">
                                                        <?php if ($trend === 'up'): ?>▲<?php elseif ($trend === 'down'): ?>▼<?php endif; ?>
                                                        <?php echo htmlspecialchars($trend_label); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($row['responses']); ?></td>
                                                <td class="hide-sm">
                                                    <span class="badge-mini <?php echo $vs_class; ?>">
                                                        <?php echo htmlspecialchars($vs_label); ?> pts
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
                                <h4>No data for this dimension</h4>
                                <p>No segments meet the minimum response threshold (<?php echo $min_resp; ?>).</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php $first = false; endforeach; ?>
            </section>

        </main>
    </div>

    <script>
        const PAYLOAD = <?php echo json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const OVERALL_CSAT = <?php echo json_encode($overall_csat); ?>;

        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#64748B';

        const MAROON  = '#7A0000';
        const SUCCESS = '#10B981';
        const WARNING = '#F59E0B';
        const DANGER  = '#EF4444';
        const INFO    = '#2563EB';
        const PURPLE  = '#8B5CF6';

        const gridStyle = { color: '#F1F5F9', drawTicks: false };
        const tooltipStyle = {
            backgroundColor:'#0F172A', titleColor:'#FFFFFF', bodyColor:'#E2E8F0',
            padding:10, cornerRadius:8,
            titleFont:{weight:'700',size:12}, bodyFont:{size:12},
        };

        function csatBarColor(v) {
            if (v >= 80) return SUCCESS;
            if (v >= 60) return '#3B82F6';
            if (v >= 40) return WARNING;
            return DANGER;
        }

        // ============================================================
        // AGE GROUP — combo bar: CSAT % + volume reference
        // ============================================================
        (function() {
            const el = document.getElementById('ageChart');
            if (!el || !PAYLOAD.age.labels.length) return;

            new Chart(el, {
                data: {
                    labels: PAYLOAD.age.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'CSAT %',
                            data: PAYLOAD.age.csat,
                            backgroundColor: PAYLOAD.age.csat.map(csatBarColor),
                            borderRadius: 8,
                            maxBarThickness: 52,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: 'Overall CSAT',
                            data: PAYLOAD.age.labels.map(() => OVERALL_CSAT),
                            borderColor: MAROON,
                            borderWidth: 1.5,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            fill: false,
                            yAxisID: 'y',
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode:'index', intersect:false },
                    plugins: {
                        legend: {
                            position: 'top', align: 'end',
                            labels: { boxWidth:10, boxHeight:10, usePointStyle:true, pointStyle:'circle', padding:14, font:{weight:'600',size:11} }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                afterBody: (items) => {
                                    const i = items[0].dataIndex;
                                    return 'Responses: ' + PAYLOAD.age.volume[i];
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display:false }, ticks: { font:{weight:'600'} } },
                        y: {
                            beginAtZero:true, max:100,
                            grid: gridStyle,
                            ticks: { callback: v => v + '%', padding:6 },
                        }
                    }
                }
            });
        })();

        // ============================================================
        // SEX — doughnut
        // ============================================================
        (function() {
            const el = document.getElementById('sexChart');
            if (!el || !PAYLOAD.sex.labels.length) return;

            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: PAYLOAD.sex.labels,
                    datasets: [{
                        data: PAYLOAD.sex.volume,
                        backgroundColor: [MAROON, INFO, WARNING],
                        borderWidth: 3, borderColor: '#FFFFFF',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, cutout:'62%',
                    plugins: {
                        legend: {
                            position:'bottom',
                            labels: { boxWidth:10, boxHeight:10, usePointStyle:true, pointStyle:'circle', padding:12, font:{weight:'600',size:11} }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                label: (c) => {
                                    const i = c.dataIndex;
                                    return ` ${c.label}: ${c.parsed} responses · ${PAYLOAD.sex.csat[i]}% CSAT`;
                                }
                            }
                        }
                    }
                }
            });
        })();

        // ============================================================
        // CLIENT TYPE — doughnut
        // ============================================================
        (function() {
            const el = document.getElementById('clientChart');
            if (!el || !PAYLOAD.client.labels.length) return;

            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: PAYLOAD.client.labels,
                    datasets: [{
                        data: PAYLOAD.client.volume,
                        backgroundColor: [MAROON, INFO, WARNING],
                        borderWidth: 3, borderColor: '#FFFFFF',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, cutout:'62%',
                    plugins: {
                        legend: {
                            position:'bottom',
                            labels: { boxWidth:10, boxHeight:10, usePointStyle:true, pointStyle:'circle', padding:12, font:{weight:'600',size:11} }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                label: (c) => {
                                    const i = c.dataIndex;
                                    return ` ${c.label}: ${c.parsed} responses · ${PAYLOAD.client.csat[i]}% CSAT`;
                                }
                            }
                        }
                    }
                }
            });
        })();

        // ============================================================
        // REGION — horizontal bar with dual metric
        // ============================================================
        (function() {
            const el = document.getElementById('regionChart');
            if (!el || !PAYLOAD.region.labels.length) return;

            new Chart(el, {
                data: {
                    labels: PAYLOAD.region.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'CSAT %',
                            data: PAYLOAD.region.csat,
                            backgroundColor: PAYLOAD.region.csat.map(csatBarColor),
                            borderRadius: 6,
                            barThickness: 22,
                            yAxisID: 'y1',
                        },
                        {
                            type: 'bar',
                            label: 'Volume',
                            data: PAYLOAD.region.volume,
                            backgroundColor: 'rgba(37,99,235,0.15)',
                            borderColor: 'rgba(37,99,235,0.4)',
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 22,
                            yAxisID: 'y2',
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode:'index', intersect:false },
                    plugins: {
                        legend: {
                            position:'top', align:'end',
                            labels: { boxWidth:10, boxHeight:10, usePointStyle:true, pointStyle:'circle', padding:14, font:{weight:'600',size:11} }
                        },
                        tooltip: {
                            ...tooltipStyle,
                            callbacks: {
                                title: (items) => {
                                    const i = items[0].dataIndex;
                                    return PAYLOAD.region.full[i];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero:true,
                            grid: gridStyle,
                            ticks: { padding:6 },
                        },
                        y1: {
                            position: 'top',
                            beginAtZero: true, max: 100,
                            grid: { display:false },
                            ticks: { callback: v => v + '%', font:{weight:'600'}, padding:6 },
                        },
                        y2: {
                            position: 'bottom',
                            beginAtZero: true,
                            grid: { display:false },
                            ticks: { font:{weight:'600'}, padding:6 },
                        }
                    }
                }
            });
        })();

        // ============================================================
        // TAB SWITCHER
        // ============================================================
        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === name);
            });
            document.querySelectorAll('.panel-tab-content').forEach(panel => {
                panel.style.display = (panel.id === 'tabPanel-' + name) ? '' : 'none';
            });
        }
    </script>
</body>
</html>