<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// 1. Authentication & Role Gate
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
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

$admin_name = $_SESSION['full_name'] ?? 'Administrator';

// 2. Filter Handler
$selected_office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
$where_sql = $selected_office_id > 0 ? " WHERE f.office_id = $selected_office_id" : "";

// 3. Fetch Offices for Filter Dropdown
$offices_list = [];
$offices_res = $conn->query("SELECT id, office_name, office_type FROM offices ORDER BY office_name ASC");
if ($offices_res) {
    while ($row = $offices_res->fetch_assoc()) {
        $offices_list[] = $row;
    }
}
$total_registered_offices = count($offices_list);

// 4. Primary & Secondary Executive Analytics Queries (Dynamic N/A Exclusion)
$kpi_query = "
    SELECT
        COUNT(*) AS total_feedbacks,
        SUM(CASE WHEN f.user_id IS NULL THEN 1 ELSE 0 END) AS total_guest_submissions,
        SUM(CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END) AS total_logged_submissions,
        SUM(CASE WHEN f.comments_recommendations IS NOT NULL AND TRIM(f.comments_recommendations) != '' THEN 1 ELSE 0 END) AS total_comments_given,

        AVG(CASE WHEN cc1 IN (1, 2, 3) THEN 100.0 ELSE 0.0 END) AS cc_awareness_rate,
        SUM(CASE WHEN cc1 = 1 THEN 1 ELSE 0 END) AS cc1_saw,
        SUM(CASE WHEN cc1 = 2 THEN 1 ELSE 0 END) AS cc1_knew,
        SUM(CASE WHEN cc1 = 3 THEN 1 ELSE 0 END) AS cc1_learned,
        SUM(CASE WHEN cc1 = 4 THEN 1 ELSE 0 END) AS cc1_none,

        SUM(CASE WHEN cc2 = 1 THEN 1 ELSE 0 END) AS cc2_easy,
        SUM(CASE WHEN cc2 = 2 THEN 1 ELSE 0 END) AS cc2_somewhat,
        SUM(CASE WHEN cc2 = 3 THEN 1 ELSE 0 END) AS cc2_difficult,
        SUM(CASE WHEN cc2 = 4 THEN 1 ELSE 0 END) AS cc2_not_visible,
        SUM(CASE WHEN cc2 = 5 THEN 1 ELSE 0 END) AS cc2_na,

        SUM(CASE WHEN cc3 = 1 THEN 1 ELSE 0 END) AS cc3_helped_much,
        SUM(CASE WHEN cc3 = 2 THEN 1 ELSE 0 END) AS cc3_somewhat,
        SUM(CASE WHEN cc3 = 3 THEN 1 ELSE 0 END) AS cc3_did_not,
        SUM(CASE WHEN cc3 = 4 THEN 1 ELSE 0 END) AS cc3_na,

        AVG(NULLIF(sqd1_responsiveness, 0)) AS avg_sqd1,
        AVG(NULLIF(sqd2_reliability, 0)) AS avg_sqd2,
        AVG(NULLIF(sqd3_access_facilities, 0)) AS avg_sqd3,
        AVG(NULLIF(sqd4_communication, 0)) AS avg_sqd4,
        AVG(NULLIF(sqd5_costs, 0)) AS avg_sqd5,
        AVG(NULLIF(sqd6_integrity, 0)) AS avg_sqd6,
        AVG(NULLIF(sqd7_assurance, 0)) AS avg_sqd7,
        AVG(NULLIF(sqd8_outcome, 0)) AS avg_sqd8,

        AVG(
            (
                COALESCE(NULLIF(f.sqd1_responsiveness, 0), 0) +
                COALESCE(NULLIF(f.sqd2_reliability, 0), 0) +
                COALESCE(NULLIF(f.sqd3_access_facilities, 0), 0) +
                COALESCE(NULLIF(f.sqd4_communication, 0), 0) +
                COALESCE(NULLIF(f.sqd5_costs, 0), 0) +
                COALESCE(NULLIF(f.sqd6_integrity, 0), 0) +
                COALESCE(NULLIF(f.sqd7_assurance, 0), 0) +
                COALESCE(NULLIF(f.sqd8_outcome, 0), 0)
            ) / NULLIF(
                (CASE WHEN f.sqd1_responsiveness > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd2_reliability > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd4_communication > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd5_costs > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd6_integrity > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd7_assurance > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd8_outcome > 0 THEN 1 ELSE 0 END), 0
            )
        ) AS overall_sqd_mean,

        SUM(CASE WHEN sqd1_responsiveness >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance >= 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome >= 4 THEN 1 ELSE 0 END) AS total_positive_ratings,

        SUM(CASE WHEN sqd1_responsiveness IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance IN (1, 2) THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome IN (1, 2) THEN 1 ELSE 0 END) AS total_negative_ratings,

        SUM(CASE WHEN sqd1_responsiveness = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance = 5 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome = 5 THEN 1 ELSE 0 END) AS count_star_5,

        SUM(CASE WHEN sqd1_responsiveness = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance = 4 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome = 4 THEN 1 ELSE 0 END) AS count_star_4,

        SUM(CASE WHEN sqd1_responsiveness = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance = 3 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome = 3 THEN 1 ELSE 0 END) AS count_star_3,

        SUM(CASE WHEN sqd1_responsiveness = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance = 2 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome = 2 THEN 1 ELSE 0 END) AS count_star_2,

        SUM(CASE WHEN sqd1_responsiveness = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance = 1 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome = 1 THEN 1 ELSE 0 END) AS count_star_1,

        SUM(CASE WHEN sqd1_responsiveness > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd2_reliability > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd4_communication > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd5_costs > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd6_integrity > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd7_assurance > 0 THEN 1 ELSE 0 END +
            CASE WHEN sqd8_outcome > 0 THEN 1 ELSE 0 END) AS total_valid_ratings,

        COUNT(DISTINCT f.office_id) AS total_evaluated_offices
    FROM arta_feedback f
    $where_sql
";

$kpi_res = $conn->query($kpi_query);
$kpi_data = $kpi_res ? $kpi_res->fetch_assoc() : [];

$total_responses = (int)($kpi_data['total_feedbacks'] ?? 0);
$cc_awareness = round((float)($kpi_data['cc_awareness_rate'] ?? 0), 1);
$total_pos = (int)($kpi_data['total_positive_ratings'] ?? 0);
$total_neg = (int)($kpi_data['total_negative_ratings'] ?? 0);
$total_valid = (int)($kpi_data['total_valid_ratings'] ?? 0);

$satisfaction_rate = $total_valid > 0 ? round(($total_pos / $total_valid) * 100, 1) : 0.0;
$dissatisfaction_rate = $total_valid > 0 ? round(($total_neg / $total_valid) * 100, 1) : 0.0;

if (!empty($kpi_data['overall_sqd_mean'])) {
    $overall_score = round((float)$kpi_data['overall_sqd_mean'], 2);
} else {
    $sqd_columns = ['avg_sqd1', 'avg_sqd2', 'avg_sqd3', 'avg_sqd4', 'avg_sqd5', 'avg_sqd6', 'avg_sqd7', 'avg_sqd8'];
    $sqd_sum = 0;
    $sqd_count = 0;
    foreach ($sqd_columns as $col) {
        if (!empty($kpi_data[$col])) {
            $sqd_sum += (float)$kpi_data[$col];
            $sqd_count++;
        }
    }
    $overall_score = $sqd_count > 0 ? round($sqd_sum / $sqd_count, 2) : 0.00;
}

$comments_count = (int)($kpi_data['total_comments_given'] ?? 0);
$comment_rate = $total_responses > 0 ? round(($comments_count / $total_responses) * 100, 1) : 0.0;

$evaluated_offices = (int)($kpi_data['total_evaluated_offices'] ?? 0);
$coverage_rate = $total_registered_offices > 0 ? round(($evaluated_offices / $total_registered_offices) * 100, 1) : 0.0;

$guest_count = (int)($kpi_data['total_guest_submissions'] ?? 0);
$logged_count = (int)($kpi_data['total_logged_submissions'] ?? 0);

$star_5 = (int)($kpi_data['count_star_5'] ?? 0);
$star_4 = (int)($kpi_data['count_star_4'] ?? 0);
$star_3 = (int)($kpi_data['count_star_3'] ?? 0);
$star_2 = (int)($kpi_data['count_star_2'] ?? 0);
$star_1 = (int)($kpi_data['count_star_1'] ?? 0);

// 5. Dimension Setup
$sqd_specs = [
    1 => ['name' => 'Responsiveness', 'code' => 'SQD1', 'key' => 'avg_sqd1', 'desc' => 'Transaction speed and reasonable waiting time.'],
    2 => ['name' => 'Reliability', 'code' => 'SQD2', 'key' => 'avg_sqd2', 'desc' => 'Adherence to procedures and steps.'],
    3 => ['name' => 'Access & Facilities', 'code' => 'SQD3', 'key' => 'avg_sqd3', 'desc' => 'Ease and simplicity of steps and transaction.'],
    4 => ['name' => 'Communication', 'code' => 'SQD4', 'key' => 'avg_sqd4', 'desc' => 'Information availability and clarity.'],
    5 => ['name' => 'Costs', 'code' => 'SQD5', 'key' => 'avg_sqd5', 'desc' => 'Fairness of official fees and payment.'],
    6 => ['name' => 'Integrity', 'code' => 'SQD6', 'key' => 'avg_sqd6', 'desc' => 'Fairness and zero corruption ("walang palakasan").'],
    7 => ['name' => 'Assurance', 'code' => 'SQD7', 'key' => 'avg_sqd7', 'desc' => 'Courtesy and helpfulness of frontline staff.'],
    8 => ['name' => 'Outcome', 'code' => 'SQD8', 'key' => 'avg_sqd8', 'desc' => 'Fulfillment and resolution of requested service.']
];

// 6. Unit Performance Query
$office_perf_query = "
    SELECT
        o.id,
        o.office_name,
        o.office_type,
        COUNT(f.feedback_id) AS total_reviews,
        ROUND(AVG((
            COALESCE(NULLIF(f.sqd1_responsiveness, 0), 0) +
            COALESCE(NULLIF(f.sqd2_reliability, 0), 0) +
            COALESCE(NULLIF(f.sqd3_access_facilities, 0), 0) +
            COALESCE(NULLIF(f.sqd4_communication, 0), 0) +
            COALESCE(NULLIF(f.sqd5_costs, 0), 0) +
            COALESCE(NULLIF(f.sqd6_integrity, 0), 0) +
            COALESCE(NULLIF(f.sqd7_assurance, 0), 0) +
            COALESCE(NULLIF(f.sqd8_outcome, 0), 0)
        ) / NULLIF(
            (CASE WHEN f.sqd1_responsiveness > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd2_reliability > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd4_communication > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd5_costs > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd6_integrity > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd7_assurance > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd8_outcome > 0 THEN 1 ELSE 0 END), 0
        )), 2) AS avg_unit_score
    FROM offices o
    LEFT JOIN arta_feedback f ON o.id = f.office_id
    GROUP BY o.id, o.office_name, o.office_type
    ORDER BY total_reviews DESC, avg_unit_score DESC
";
$office_perf_res = $conn->query($office_perf_query);
$office_rankings = [];
if ($office_perf_res) {
    while ($r = $office_perf_res->fetch_assoc()) {
        $office_rankings[] = $r;
    }
}

// 7. Recent Comments Query (Increased LIMIT for scrollbar)
$recent_feed_query = "
    SELECT
        f.feedback_id,
        f.service_accessed,
        f.client_type,
        f.comments_recommendations,
        f.submission_timestamp,
        o.office_name,
        ROUND(((
            COALESCE(NULLIF(f.sqd1_responsiveness, 0), 0) +
            COALESCE(NULLIF(f.sqd2_reliability, 0), 0) +
            COALESCE(NULLIF(f.sqd3_access_facilities, 0), 0) +
            COALESCE(NULLIF(f.sqd4_communication, 0), 0) +
            COALESCE(NULLIF(f.sqd5_costs, 0), 0) +
            COALESCE(NULLIF(f.sqd6_integrity, 0), 0) +
            COALESCE(NULLIF(f.sqd7_assurance, 0), 0) +
            COALESCE(NULLIF(f.sqd8_outcome, 0), 0)
        ) / NULLIF(
            (CASE WHEN f.sqd1_responsiveness > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd2_reliability > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd4_communication > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd5_costs > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd6_integrity > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd7_assurance > 0 THEN 1 ELSE 0 END +
             CASE WHEN f.sqd8_outcome > 0 THEN 1 ELSE 0 END), 0
        )), 1) as avg_rating
    FROM arta_feedback f
    JOIN offices o ON f.office_id = o.id
    $where_sql
    ORDER BY f.submission_timestamp DESC
    LIMIT 50
";
$recent_feed_res = $conn->query($recent_feed_query);
$recent_logs = [];
if ($recent_feed_res) {
    while ($r = $recent_feed_res->fetch_assoc()) {
        $recent_logs[] = $r;
    }
}

// Page metadata for admin_header.php
$page_title    = 'Harmonized CSM Analytics Hub';
$page_crumb    = 'Performance Monitoring / Dashboard';
$page_subtitle = 'ARTA R.A. 11032 performance and quality assurance metrics';
$unread_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --warning: #F59E0B;
            --danger: #EF4444;
            --danger-bg: #FEF2F2;

            --sidebar-width: 272px;
            --header-height: 72px;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--bg);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
        }

        /* ============================================================
           MAIN SHELL
           ============================================================ */
        .admin-main-wrap {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-width: 0;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: clip; 
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                        width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-container {
            width: 100%;
            max-width: 100%;
            padding: calc(var(--header-height) + 20px) 28px 60px;
            margin: 0;
        }

        /* ============================================================
           STICKY HEADER DECK 
           ============================================================ */
        .fixed-header-deck {
            position: -webkit-sticky;
            position: sticky;
            top: var(--header-height);
            z-index: 10; /* Fixed Z-Index to avoid dropdown overlap */
            background: var(--bg);
            padding-top: 10px;
            padding-bottom: 12px;
            box-shadow: 0 8px 15px -10px rgba(0, 0, 0, 0.08);
        }

        .toolbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap; /* Forces horizontal alignment on laptop */
            gap: 12px;
            width: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 0;
        }

        .toolbar-title h2 {
            font-size: 19px;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.4px;
            margin: 0;
        }
        .toolbar-title p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 2px 0 0;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap; /* Forces inline layout */
            width: auto; /* Corrects layout width for inline form */
            max-width: 100%;
        }

        .filter-form select {
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: #F8FAFC;
            color: var(--text-heading);
            font-weight: 600;
            outline: none;
            font-family: inherit;
            transition: border-color 0.18s, background 0.18s;
            -webkit-appearance: none; 
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            max-width: 100%;
        }
        .filter-form select:focus {
            border-color: var(--maroon);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(122, 0, 0, 0.06);
        }

        .btn-print {
            background: var(--maroon);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s, transform 0.12s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            font-family: inherit;
            flex-shrink: 0;
        }
        .btn-print:hover { background: var(--maroon-dark); }
        .btn-print:active { transform: scale(0.98); }

        /* ============================================================
           CONTENT PANELS
           ============================================================ */
        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 24px;
            margin-bottom: 20px;
            width: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .panel-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            letter-spacing: -0.3px;
            flex-wrap: wrap;
            gap: 6px;
        }
        .panel-title .meta {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ============================================================
           BENCHMARK OVERVIEW ROW
           ============================================================ */
        .benchmark-overview-row {
            display: grid;
            grid-template-columns: 310px 1fr;
            gap: 16px;
            margin-bottom: 20px;
            align-items: stretch;
            width: 100%;
        }

        .bench-hero-card {
            background: linear-gradient(135deg, #FFFDF7 0%, #FFFFFF 100%);
            border: 2px solid var(--gold);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(242, 169, 0, 0.12);
        }

        .bench-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            gap: 8px;
        }

        .bench-hero-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
        }

        .bench-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 15px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .badge-green { background: var(--success-bg); color: var(--success); }
        .badge-maroon { background: var(--danger-bg); color: var(--maroon); }

        .bench-val {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -1px;
        }
        .val-green { color: var(--success); }
        .val-maroon { color: var(--maroon); }

        .bench-desc {
            font-size: 12px;
            color: var(--text-body);
            line-height: 1.45;
        }

        .bench-bar-track {
            height: 6px;
            background: #E2E8F0;
            border-radius: 4px;
            margin: 14px 0 10px;
            overflow: hidden;
        }
        .bench-bar-fill {
            height: 100%;
            background: var(--gold);
            border-radius: 4px;
        }

        .bench-footer-note {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            gap: 6px;
        }

        .bench-side-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .side-stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.18s;
            min-width: 0;
        }
        .side-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
            border-color: var(--border-strong);
        }

        .side-stat-header {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }

        .side-stat-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .side-stat-sub {
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============================================================
           ANALYTICS VISUAL GRID
           ============================================================ */
        .analytics-visual-grid {
            display: grid;
            grid-template-columns: 1.7fr 1.3fr;
            gap: 16px;
            margin-bottom: 20px;
            width: 100%;
        }

        .chart-box {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .likert-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 8px;
        }

        .likert-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .likert-label {
            width: 125px;
            flex-shrink: 0;
            color: var(--text-heading);
            font-size: 11.5px;
        }

        .likert-bar-bg {
            flex: 1;
            height: 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            overflow: hidden;
        }

        .likert-bar-fill { height: 100%; border-radius: 4px; }
        .bar-5 { background-color: var(--success); }
        .bar-4 { background-color: #34D399; }
        .bar-3 { background-color: var(--warning); }
        .bar-2 { background-color: #F87171; }
        .bar-1 { background-color: var(--danger); }

        .likert-count {
            width: 90px;
            flex-shrink: 0;
            text-align: right;
            color: var(--text-muted);
            font-size: 11px;
        }

        /* ============================================================
           CC TRIAD
           ============================================================ */
        .cc-triad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            width: 100%;
        }

        .cc-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            min-width: 0;
        }

        .cc-card-title {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .cc-stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 5px;
            color: var(--text-body);
            gap: 6px;
        }
        .cc-stat-row strong { color: var(--text-heading); }

        /* ============================================================
           SQD GRID
           ============================================================ */
        .sqd-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 24px;
            width: 100%;
        }

        .sqd-item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .sqd-row-head { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--text-heading); gap: 8px; }
        .sqd-row-desc { font-size: 11px; color: var(--text-muted); }
        .sqd-bar-bg { height: 7px; background: var(--bg); border-radius: 4px; overflow: hidden; border: 1px solid var(--border); }
        .sqd-bar-fill { height: 100%; background: var(--maroon); border-radius: 4px; transition: width 0.6s ease; }

        /* ============================================================
           TABLES
           ============================================================ */
        .scorecard-scroll-wrap {
            max-height: 380px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .scorecard-scroll-wrap::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scorecard-scroll-wrap::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track {
            background: #F1F5F9;
        }
        .scorecard-scroll-wrap::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
            min-width: 580px;
        }

        .scorecard-scroll-wrap thead th {
            position: sticky;
            top: 0;
            background-color: #F1F5F9;
            z-index: 5;
            box-shadow: 0 1px 0 var(--border);
        }

        th {
            background-color: var(--bg);
            color: var(--text-muted);
            font-weight: 700;
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-body);
            vertical-align: top;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background-color: #FAFAFA; }

        .score-pill {
            display: inline-block;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
        }
        .score-high { background: var(--success-bg); color: var(--success); }
        .score-med { background: var(--gold-light); color: var(--gold); }
        .score-low { background: var(--danger-bg); color: var(--danger); }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1200px) {
            .benchmark-overview-row { grid-template-columns: 1fr; }
            .bench-side-grid { grid-template-columns: repeat(3, 1fr); }
            .analytics-visual-grid { grid-template-columns: 1fr; }
            .cc-triad-grid { grid-template-columns: 1fr; }
            .sqd-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 1024px) {
            .admin-main-wrap {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: calc(var(--header-height) + 10px) 12px 45px;
            }
            .panel-card {
                padding: 16px 14px;
                margin-bottom: 14px;
                border-radius: 10px;
            }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
                padding: 12px 14px;
                border-radius: 10px;
            }
            .toolbar-title h2 { font-size: 17px; }
            .toolbar-title p { display: block; margin-top: 4px; } /* Fixed: Display subtitle on mobile */
            
            .filter-form {
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 8px;
                width: 100%;
            }
            .filter-form select { 
                flex: 1 1 0%; 
                width: 0;     
                min-width: 0; 
                font-size: 12px; 
                padding: 8px 30px 8px 10px; 
            }
            .btn-print { 
                padding: 8px 12px; 
                font-size: 12px; 
                flex-shrink: 0; 
            }
            .btn-print svg { display: none; }

            .benchmark-overview-row { gap: 12px; margin-bottom: 14px; }
            .bench-side-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .side-stat-card { padding: 12px 12px; }
            .analytics-visual-grid { gap: 12px; margin-bottom: 14px; }
            .chart-box { height: 260px; }
            .cc-triad-grid { gap: 10px; }
            .sqd-grid { gap: 12px; }

            .panel-title .meta { display: none; }
        }

        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 8px 35px; }
            .panel-card { padding: 14px 12px; margin-bottom: 12px; }
            .toolbar { padding: 10px 12px; }
            .bench-val { font-size: 34px; }
            .side-stat-val { font-size: 19px; }
            .side-stat-header { font-size: 9.5px; }
            .side-stat-sub { font-size: 10px; }
            .likert-row { flex-wrap: wrap; gap: 4px; }
            .likert-label { width: 100%; margin-bottom: 2px; }
            .likert-bar-bg { width: calc(100% - 80px); }
            .likert-count { width: 75px; }
        }

        @media print {
            .admin-sidebar, .admin-header, .toolbar, .sidebar-overlay { display: none !important; }
            .admin-main-wrap { margin-left: 0 !important; width: 100% !important; overflow: visible !important; }
            .admin-container { padding: 0 !important; }
            .scorecard-scroll-wrap { max-height: none !important; overflow: visible !important; }
            .fixed-header-deck { position: static !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body>

    <?php
    // Sidebar first (defines toggleAdminSidebar)
    $sidebar_path = __DIR__ . '/admin_sidebar.php';
    if (!file_exists($sidebar_path)) {
        die("<div style='background:#fee2e2;color:#991b1b;padding:20px;margin:20px;font-family:sans-serif;'>
            <strong>Configuration Error:</strong> <code>admin_sidebar.php</code> was not found in <code>" . htmlspecialchars(__DIR__) . "</code>.
        </div>");
    }
    require_once $sidebar_path;

    // Header second (wraps toggle to sync hamburger icon)
    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) {
        require_once $header_path;
    }
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <!-- ============================================================
                 STICKY HEADER (Harmonized CSM Analytics Hub)
                 ============================================================ -->
            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Harmonized CSM Analytics Hub</h2>
                        <p>ARTA R.A. 11032 performance &amp; quality assurance metrics</p>
                    </div>

                    <form method="GET" action="admin_dashboard.php" class="filter-form">
                        <select name="office_id" onchange="this.form.submit()">
                            <option value="0">Campus-Wide (All Units)</option>
                            <?php foreach ($offices_list as $off): ?>
                                <option value="<?php echo $off['id']; ?>" <?php echo $selected_office_id === (int)$off['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($off['office_name']); ?> (<?php echo htmlspecialchars($off['office_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-print" onclick="window.print()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </form>
                </section>
            </div>

            <!-- ============================================================
                 HERO BENCHMARK + 6 SATELLITE CARDS
                 ============================================================ -->
            <section class="benchmark-overview-row">

                <div class="bench-hero-card">
                    <div>
                        <div class="bench-hero-header">
                            <span class="bench-hero-label">Primary ARTA Benchmark</span>
                            <span class="bench-badge <?php echo $satisfaction_rate >= 80.0 ? 'badge-green' : 'badge-maroon'; ?>">
                                <?php echo $satisfaction_rate >= 80.0 ? '✔ ARTA Compliant' : '⚠ Below Target'; ?>
                            </span>
                        </div>
                        <div class="bench-val <?php echo $satisfaction_rate >= 80.0 ? 'val-green' : 'val-maroon'; ?>">
                            <?php echo $satisfaction_rate; ?>%
                        </div>
                        <div class="bench-desc">
                            <strong>Net Client Satisfaction</strong><br>
                            Evaluations scoring 4 (Agree) or 5 (Strongly Agree) across all active SQD dimensions.
                        </div>
                    </div>

                    <div>
                        <div class="bench-bar-track">
                            <div class="bench-bar-fill" style="width: <?php echo min(100, $satisfaction_rate); ?>%;"></div>
                        </div>
                        <div class="bench-footer-note">
                            <span>National Min: 80.0%</span>
                            <span>Score: <?php echo $satisfaction_rate; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="bench-side-grid">

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>Overall Mean</span>
                            <span style="color: var(--gold); font-weight: 800;">5.00 Max</span>
                        </div>
                        <div class="side-stat-val" style="color: var(--maroon);">
                            <?php echo number_format($overall_score, 2); ?>
                        </div>
                        <div class="side-stat-sub">Cumulative valid quality mean</div>
                    </div>

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>Evaluations</span>
                            <span style="font-size: 10px; font-weight: 700; color: var(--text-muted);">Audited</span>
                        </div>
                        <div class="side-stat-val">
                            <?php echo number_format($total_responses); ?>
                        </div>
                        <div class="side-stat-sub"><?php echo number_format($logged_count); ?> Users • <?php echo number_format($guest_count); ?> Guests</div>
                    </div>

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>CC Awareness</span>
                            <span style="color: var(--success); font-weight: 700;">Codes 1-3</span>
                        </div>
                        <div class="side-stat-val" style="color: var(--success);">
                            <?php echo $cc_awareness; ?>%
                        </div>
                        <div class="side-stat-sub">Clients aware of Charter</div>
                    </div>

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>Dissatisfaction</span>
                            <span style="color: var(--danger); font-weight: 700;">Score 1-2</span>
                        </div>
                        <div class="side-stat-val" style="color: <?php echo $dissatisfaction_rate > 10 ? 'var(--danger)' : 'var(--text-heading)'; ?>;">
                            <?php echo $dissatisfaction_rate; ?>%
                        </div>
                        <div class="side-stat-sub"><?php echo number_format($total_neg); ?> negative ratings flagged</div>
                    </div>

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>Feedback Ratio</span>
                            <span style="font-size: 10px; font-weight: 700; color: var(--text-muted);">Written</span>
                        </div>
                        <div class="side-stat-val">
                            <?php echo $comment_rate; ?>%
                        </div>
                        <div class="side-stat-sub"><?php echo number_format($comments_count); ?> qualitative suggestions</div>
                    </div>

                    <div class="side-stat-card">
                        <div class="side-stat-header">
                            <span>Coverage</span>
                            <span style="font-size: 10px; font-weight: 700; color: var(--text-muted);">Offices</span>
                        </div>
                        <div class="side-stat-val">
                            <?php echo $coverage_rate; ?>%
                        </div>
                        <div class="side-stat-sub"><?php echo $evaluated_offices; ?> of <?php echo $total_registered_offices; ?> units reviewed</div>
                    </div>

                </div>
            </section>

            <!-- ============================================================
                 VISUAL ANALYTICS
                 ============================================================ -->
            <section class="analytics-visual-grid">

                <div class="panel-card">
                    <div class="panel-title">
                        <span>SQD Dimension Performance (Radar Profile)</span>
                        <span class="meta">Scale: 1.00 – 5.00</span>
                    </div>
                    <div class="chart-box">
                        <canvas id="sqdRadarChart"></canvas>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-title">
                        <span>Likert Rating Distribution</span>
                        <span class="meta">Total: <?php echo number_format($total_valid); ?></span>
                    </div>

                    <div class="likert-list">
                        <?php
                        $stars_data = [
                            ['label' => '5 - Strongly Agree', 'count' => $star_5, 'class' => 'bar-5'],
                            ['label' => '4 - Agree', 'count' => $star_4, 'class' => 'bar-4'],
                            ['label' => '3 - Neutral', 'count' => $star_3, 'class' => 'bar-3'],
                            ['label' => '2 - Disagree', 'count' => $star_2, 'class' => 'bar-2'],
                            ['label' => '1 - Strongly Disagree', 'count' => $star_1, 'class' => 'bar-1']
                        ];
                        foreach ($stars_data as $s):
                            $pct = $total_valid > 0 ? round(($s['count'] / $total_valid) * 100, 1) : 0.0;
                        ?>
                        <div class="likert-row">
                            <div class="likert-label"><?php echo $s['label']; ?></div>
                            <div class="likert-bar-bg">
                                <div class="likert-bar-fill <?php echo $s['class']; ?>" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <div class="likert-count"><?php echo $pct; ?>% (<?php echo number_format($s['count']); ?>)</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </section>

            <!-- ============================================================
                 CITIZEN'S CHARTER (CC1, CC2, CC3)
                 ============================================================ -->
            <section class="panel-card">
                <div class="panel-title">
                    <span>Citizen's Charter (CC) Triad</span>
                </div>

                <div class="cc-triad-grid">
                    <div class="cc-card">
                        <div class="cc-card-title">CC1: Awareness of Charter</div>
                        <div class="cc-stat-row"><span>1. Saw office's CC:</span><strong><?php echo (int)($kpi_data['cc1_saw'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>2. Knew CC, did not see:</span><strong><?php echo (int)($kpi_data['cc1_knew'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>3. Learned upon visit:</span><strong><?php echo (int)($kpi_data['cc1_learned'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>4. Did not know/see:</span><strong><?php echo (int)($kpi_data['cc1_none'] ?? 0); ?></strong></div>
                    </div>

                    <div class="cc-card">
                        <div class="cc-card-title">CC2: Charter Visibility</div>
                        <div class="cc-stat-row"><span>Easy to see:</span><strong><?php echo (int)($kpi_data['cc2_easy'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>Somewhat easy:</span><strong><?php echo (int)($kpi_data['cc2_somewhat'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>Difficult to see:</span><strong><?php echo (int)($kpi_data['cc2_difficult'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>Not visible / NA:</span><strong><?php echo (int)($kpi_data['cc2_not_visible'] ?? 0) + (int)($kpi_data['cc2_na'] ?? 0); ?></strong></div>
                    </div>

                    <div class="cc-card">
                        <div class="cc-card-title">CC3: Charter Helpfulness</div>
                        <div class="cc-stat-row"><span>Helped very much:</span><strong><?php echo (int)($kpi_data['cc3_helped_much'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>Somewhat helped:</span><strong><?php echo (int)($kpi_data['cc3_somewhat'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>Did not help:</span><strong><?php echo (int)($kpi_data['cc3_did_not'] ?? 0); ?></strong></div>
                        <div class="cc-stat-row"><span>N/A:</span><strong><?php echo (int)($kpi_data['cc3_na'] ?? 0); ?></strong></div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 HARMONIZED SQD DIMENSIONS
                 ============================================================ -->
            <section class="panel-card">
                <div class="panel-title">
                    <span>Harmonized Service Quality Dimensions (SQD)</span>
                    <span class="meta">Mean Score / 5.00</span>
                </div>

                <div class="sqd-grid">
                    <?php foreach ($sqd_specs as $spec): ?>
                        <?php
                            $dim_avg = !empty($kpi_data[$spec['key']]) ? round((float)$kpi_data[$spec['key']], 2) : 0.00;
                            $bar_pct = min(100, round(($dim_avg / 5.0) * 100));
                        ?>
                        <div class="sqd-item">
                            <div class="sqd-row-head">
                                <span><strong><?php echo $spec['code']; ?>:</strong> <?php echo $spec['name']; ?></span>
                                <span><?php echo number_format($dim_avg, 2); ?></span>
                            </div>
                            <div class="sqd-row-desc"><?php echo $spec['desc']; ?></div>
                            <div class="sqd-bar-bg">
                                <div class="sqd-bar-fill" style="width: <?php echo $bar_pct; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ============================================================
                 UNIT & COLLEGE SCORECARD
                 ============================================================ -->
            <section class="panel-card">
                <div class="panel-title">
                    <span>Unit &amp; College Performance Scorecard</span>
                    <span class="meta">Scroll to view more</span>
                </div>

                <div class="scorecard-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Office / College Unit</th>
                                <th>Classification</th>
                                <th>Evaluations</th>
                                <th>Mean</th>
                                <th>Status Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($office_rankings)): ?>
                                <?php foreach ($office_rankings as $row): ?>
                                    <?php
                                        $score = (float)($row['avg_unit_score'] ?? 0);
                                        $pill_class = $score >= 4.0 ? 'score-high' : ($score >= 3.0 ? 'score-med' : ($score > 0 ? 'score-low' : ''));
                                        $label = $score >= 4.0 ? 'Outstanding' : ($score >= 3.0 ? 'Satisfactory' : ($score > 0 ? 'Needs Action' : 'No Data'));
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['office_name']); ?></strong></td>
                                        <td><span style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?php echo htmlspecialchars($row['office_type']); ?></span></td>
                                        <td><?php echo number_format($row['total_reviews']); ?> reviews</td>
                                        <td><strong><?php echo $score > 0 ? number_format($score, 2) : '—'; ?></strong></td>
                                        <td>
                                            <?php if ($score > 0): ?>
                                                <span class="score-pill <?php echo $pill_class; ?>"><?php echo $label; ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 11px;">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 25px;">No office records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================================================
                 RECENT CLIENT COMMENTS (Updated with Scroll View)
                 ============================================================ -->
            <section class="panel-card">
                <div class="panel-title">
                    <span>Recent Client Feedback Stream</span>
                    <span class="meta">Scroll to view more</span>
                </div>

                <div class="scorecard-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ref ID</th>
                                <th style="width: 120px;">Date &amp; Time</th>
                                <th style="width: 170px;">Office / Unit</th>
                                <th style="width: 170px;">Service Accessed</th>
                                <th style="width: 90px;">Client</th>
                                <th style="width: 70px;">Rating</th>
                                <th>Feedback / Recommendations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_logs)): ?>
                                <?php foreach ($recent_logs as $entry): ?>
                                    <tr>
                                        <td style="font-size: 11px; font-weight: 700; color: var(--text-muted);">
                                            #<?php echo str_pad($entry['feedback_id'], 5, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                            <?php echo date('M d, Y', strtotime($entry['submission_timestamp'])); ?><br>
                                            <small><?php echo date('h:i A', strtotime($entry['submission_timestamp'])); ?></small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($entry['office_name']); ?></strong></td>
                                        <td style="font-size: 12px; color: var(--text-body);">
                                            <?php echo htmlspecialchars($entry['service_accessed']); ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 11.5px; font-weight: 600;"><?php echo htmlspecialchars($entry['client_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="score-pill <?php echo ((float)$entry['avg_rating'] >= 4.0) ? 'score-high' : 'score-med'; ?>">
                                                ★ <?php echo number_format((float)$entry['avg_rating'], 1); ?>
                                            </span>
                                        </td>
                                        <td style="line-height: 1.45; font-size: 12px;">
                                            <?php if (!empty($entry['comments_recommendations'])): ?>
                                                "<?php echo htmlspecialchars($entry['comments_recommendations']); ?>"
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">No additional comments.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 25px;">No feedback logs available yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         Chart.js Radar Initialization
         ============================================================ -->
    <script>
        const ctx = document.getElementById('sqdRadarChart').getContext('2d');
        const sqdRadarChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['SQD1: Time', 'SQD2: Process', 'SQD3: Access', 'SQD4: Info', 'SQD5: Fees', 'SQD6: Integrity', 'SQD7: Staff', 'SQD8: Outcome'],
                datasets: [{
                    label: 'Campus Mean (out of 5.00)',
                    data: [
                        <?php echo !empty($kpi_data['avg_sqd1']) ? round((float)$kpi_data['avg_sqd1'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd2']) ? round((float)$kpi_data['avg_sqd2'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd3']) ? round((float)$kpi_data['avg_sqd3'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd4']) ? round((float)$kpi_data['avg_sqd4'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd5']) ? round((float)$kpi_data['avg_sqd5'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd6']) ? round((float)$kpi_data['avg_sqd6'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd7']) ? round((float)$kpi_data['avg_sqd7'], 2) : 0; ?>,
                        <?php echo !empty($kpi_data['avg_sqd8']) ? round((float)$kpi_data['avg_sqd8'], 2) : 0; ?>
                    ],
                    fill: true,
                    backgroundColor: 'rgba(122, 0, 0, 0.15)',
                    borderColor: '#7A0000',
                    pointBackgroundColor: '#F2A900',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#7A0000'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: '#E2E8F0' },
                        grid: { color: '#E2E8F0' },
                        pointLabels: {
                            font: { size: 10, family: 'Plus Jakarta Sans', weight: '600' },
                            color: '#0F172A'
                        },
                        suggestedMin: 1,
                        suggestedMax: 5,
                        ticks: { stepSize: 1, backdropColor: 'transparent', font: { size: 9 } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11, family: 'Plus Jakarta Sans', weight: '600' } }
                    }
                }
            }
        });
    </script>
</body>
</html>