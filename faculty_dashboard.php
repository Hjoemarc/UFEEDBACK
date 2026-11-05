<?php
// faculty_dashboard.php — Staff & Faculty Workspace with Analytics

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (!in_array($_SESSION['internal_user_type'] ?? '', ['zppsu_employee'])) {
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Faculty Member';

// Fetch Faculty Profile (Check if office_id exists in schema)
$user_stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.office_id, o.office_name 
    FROM users u 
    LEFT JOIN offices o ON u.office_id = o.id 
    WHERE u.user_id = ?
");
$user_stmt->bind_param("s", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$my_office_id   = (int)($user_data['office_id'] ?? 0);
$my_office_name = $user_data['office_name'] ?? 'Unassigned';

// Fetch Office Analytics (Only if assigned to an office)
$office_stats = [
    'total_feedback' => 0, 'csat' => 0.0,
    'avg_sqd1' => 0, 'avg_sqd2' => 0, 'avg_sqd3' => 0, 'avg_sqd4' => 0,
    'avg_sqd5' => 0, 'avg_sqd6' => 0, 'avg_sqd7' => 0, 'avg_sqd8' => 0
];

if ($my_office_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_feedback,
            SUM(CASE WHEN sqd1_responsiveness >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd2_reliability >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd3_access_facilities >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd4_communication >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd5_costs >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd6_integrity >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd7_assurance >= 4 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd8_outcome >= 4 THEN 1 ELSE 0 END) AS satisfied,
            SUM(CASE WHEN sqd1_responsiveness > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd2_reliability > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd3_access_facilities > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd4_communication > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd5_costs > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd6_integrity > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd7_assurance > 0 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN sqd8_outcome > 0 THEN 1 ELSE 0 END) AS rated,
            AVG(NULLIF(sqd1_responsiveness, 0)) AS avg_sqd1,
            AVG(NULLIF(sqd2_reliability, 0)) AS avg_sqd2,
            AVG(NULLIF(sqd3_access_facilities, 0)) AS avg_sqd3,
            AVG(NULLIF(sqd4_communication, 0)) AS avg_sqd4,
            AVG(NULLIF(sqd5_costs, 0)) AS avg_sqd5,
            AVG(NULLIF(sqd6_integrity, 0)) AS avg_sqd6,
            AVG(NULLIF(sqd7_assurance, 0)) AS avg_sqd7,
            AVG(NULLIF(sqd8_outcome, 0)) AS avg_sqd8
        FROM arta_feedback 
        WHERE office_id = ? AND is_archived = 0
    ");
    $stmt->bind_param("i", $my_office_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res && $res['total_feedback'] > 0) {
        $office_stats['total_feedback'] = (int)$res['total_feedback'];
        $rated = (int)$res['rated'];
        $office_stats['csat'] = $rated > 0 ? round(((int)$res['satisfied'] / $rated) * 100, 1) : 0.0;
        $office_stats['avg_sqd1'] = round((float)$res['avg_sqd1'], 2);
        $office_stats['avg_sqd2'] = round((float)$res['avg_sqd2'], 2);
        $office_stats['avg_sqd3'] = round((float)$res['avg_sqd3'], 2);
        $office_stats['avg_sqd4'] = round((float)$res['avg_sqd4'], 2);
        $office_stats['avg_sqd5'] = round((float)$res['avg_sqd5'], 2);
        $office_stats['avg_sqd6'] = round((float)$res['avg_sqd6'], 2);
        $office_stats['avg_sqd7'] = round((float)$res['avg_sqd7'], 2);
        $office_stats['avg_sqd8'] = round((float)$res['avg_sqd8'], 2);
    }
    $stmt->close();
}

// Recent written feedback for the office
$recent_feedback = [];
if ($my_office_id > 0) {
    $fb_res = $conn->query("
        SELECT feedback_id, service_accessed, comments_recommendations, submission_timestamp 
        FROM arta_feedback 
        WHERE office_id = " . (int)$my_office_id . " AND is_archived = 0 AND comments_recommendations != ''
        ORDER BY submission_timestamp DESC LIMIT 5
    ");
    if ($fb_res) while ($r = $fb_res->fetch_assoc()) $recent_feedback[] = $r;
}

// Assigned tickets
$my_tickets = [];
$ticket_res = $conn->query("
    SELECT ap.id, ap.status, ap.created_at, f.service_accessed 
    FROM action_plans ap
    JOIN arta_feedback f ON ap.feedback_id = f.feedback_id
    WHERE ap.assigned_staff_id = '" . $conn->real_escape_string($user_id) . "'
    ORDER BY CASE WHEN ap.status = 'Resolved' THEN 2 ELSE 1 END, ap.created_at DESC
");
if ($ticket_res) while ($r = $ticket_res->fetch_assoc()) $my_tickets[] = $r;

$pending_tickets  = 0;
$resolved_tickets = 0;
foreach ($my_tickets as $t) {
    if ($t['status'] === 'Resolved') $resolved_tickets++;
    else $pending_tickets++;
}

$page_title    = 'Faculty Workspace';
$page_subtitle = 'Manage your assigned resolutions and view office analytics';
$unread_notifications = 0;
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
            padding: calc(var(--header-height) + 20px) 24px 40px;
            flex:1; display:flex; flex-direction:column;
        }

        /* Sticky deck */
        .fixed-header-deck {
            position: -webkit-sticky; position: sticky; top: var(--header-height); z-index: 800;
            background: var(--bg); padding-top: 10px; padding-bottom: 12px;
            box-shadow: 0 8px 15px -10px rgba(0, 0, 0, 0.08);
        }
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
        .badge-office { background: #FEF2F2; color: var(--maroon); border: 1px solid #FECACA; padding: 6px 12px; border-radius: 20px; font-weight: 800; font-size: 12px; white-space: nowrap; }

        /* Warning Banner for missing office */
        .warning-banner {
            background: #FFFBEB; border: 1px solid #FDE68A; color: #B45309;
            padding: 16px; border-radius: 12px; margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .warning-banner svg { width: 24px; height: 24px; flex-shrink: 0; }
        .warning-banner h4 { margin: 0 0 4px; font-size: 14px; font-weight: 800; }
        .warning-banner p { margin: 0; font-size: 13px; line-height: 1.5; }

        /* KPI */
        .kpi-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .kpi-card {
            background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px;
            box-shadow:0 1px 3px rgba(0,0,0,0.02); position:relative; overflow:hidden;
            display:flex; flex-direction:column; gap:8px; min-width: 0;
        }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; }
        .kpi-card.csat::before { background:linear-gradient(180deg,#10B981,#047857); }
        .kpi-card.volume::before { background:linear-gradient(180deg,#2563EB,#1D4ED8); }
        .kpi-card.pending::before { background:linear-gradient(180deg,#F59E0B,#B45309); }
        .kpi-card.resolved::before { background:linear-gradient(180deg,#8B5CF6,#6D28D9); }

        .kpi-head { display:flex; align-items:center; justify-content:space-between; }
        .kpi-label { font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.7px; }
        .kpi-value { font-size:28px; font-weight:800; color:var(--text-heading); line-height:1; letter-spacing:-0.8px; }
        .kpi-value .unit { font-size:15px; font-weight:700; color:var(--text-muted); }
        .kpi-sub { font-size:11.5px; color:var(--text-muted); font-weight:600; }

        /* Split layout */
        .dashboard-split { display:grid; grid-template-columns: 1.5fr 1fr; gap:20px; align-items:start; }
        
        /* FIX: Prevents grid columns from expanding beyond parent width */
        .dashboard-split > div { min-width: 0; } 

        .panel-card { 
            background:var(--surface); border:1px solid var(--border); border-radius:14px; 
            padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02); margin-bottom:20px; 
            min-width: 0; /* FIX: Prevents table from pushing card width */
            overflow: hidden; /* FIX: Contains the scrolling table */
        }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap: wrap; gap: 10px; }
        .panel-head h3 { font-size:15px; font-weight:800; color:var(--text-heading); margin:0; }
        .panel-head p { font-size:11.5px; color:var(--text-muted); margin:3px 0 0; }

        /* Chart */
        .chart-wrap { position:relative; height: 320px; width:100%; }
        .chart-wrap canvas { max-width: 100% !important; }

        /* Table */
        .table-responsive { 
            overflow-x:auto; border:1px solid var(--border); border-radius:8px; 
            -webkit-overflow-scrolling:touch; width: 100%; 
        }
        table { width:100%; border-collapse:collapse; font-size:12.5px; min-width:600px; text-align:left; }
        th { background:#F8FAFC; color:var(--text-muted); font-weight:700; padding:10px 12px; text-transform:uppercase; font-size:10.5px; border-bottom:1px solid var(--border); }
        td { padding:12px; border-bottom:1px solid var(--border); color:var(--text-body); vertical-align:top; }
        tr:last-child td { border-bottom:none; }
        .badge-status { display:inline-flex; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:800; text-transform:uppercase; }
        .status-pending { background:#FFF7ED; color:#C2410C; }
        .status-progress { background:#EFF6FF; color:#1D4ED8; }
        .status-resolved { background:#ECFDF5; color:#047857; }

        /* Feedback feed */
        .feed-item { padding:12px; background:#FAFBFC; border-radius:8px; border-left:3px solid var(--maroon); margin-bottom:10px; }
        .feed-header { display:flex; justify-content:space-between; margin-bottom:4px; gap: 8px; }
        .feed-service { font-size:12px; font-weight:700; color:var(--text-heading); }
        .feed-date { font-size:10.5px; color:var(--text-muted); font-weight:600; white-space: nowrap; }
        .feed-comment { font-size:12px; font-style:italic; color:var(--text-body); line-height:1.4; }

        /* Empty states */
        .empty-state {
            text-align:center; padding: 30px 20px; color: var(--text-muted);
            background: #FAFBFC; border-radius: 8px; border: 1px dashed var(--border);
        }
        .empty-state svg { width: 32px; height: 32px; color: #CBD5E1; margin-bottom: 8px; }
        .empty-state p { font-size: 12.5px; margin: 0; }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
            .kpi-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
            .dashboard-split { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 30px; }
            .kpi-grid { gap:8px; }
            .kpi-card { padding: 14px; }
            .kpi-value { font-size: 20px; }
            .kpi-label { font-size: 9.5px; }
            .kpi-sub { font-size: 10px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .chart-wrap { height: 250px; }
        }
    </style>
</head>
<body>

    <?php 
    require_once 'faculty_sidebar.php'; 
    if (file_exists('admin_header.php')) require_once 'admin_header.php'; 
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Faculty Workspace</h2>
                        <p>Manage your assigned resolutions and view office analytics</p>
                    </div>
                    <div>
                        <span class="badge-office">
                            Office: <?php echo htmlspecialchars($my_office_name); ?>
                        </span>
                    </div>
                </section>
            </div>

            <!-- DEBUGGING / WARNING BANNER -->
            <?php if ($my_office_id === 0): ?>
                <div class="warning-banner">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <div>
                        <h4>No Office Assigned</h4>
                        <p>You are not currently assigned to an office. The analytics and feedback below will remain empty until an Administrator assigns you to an office via the <strong>User Accounts</strong> module.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- KPI GRID -->
            <section class="kpi-grid">
                <div class="kpi-card csat">
                    <div class="kpi-head">
                        <span class="kpi-label">Office CSAT</span>
                        <svg width="20" height="20" fill="none" stroke="var(--success)" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="kpi-value"><?php echo number_format($office_stats['csat'], 1); ?><span class="unit">%</span></div>
                    <div class="kpi-sub">Satisfaction across all services</div>
                </div>

                <div class="kpi-card volume">
                    <div class="kpi-head">
                        <span class="kpi-label">Total Feedback</span>
                        <svg width="20" height="20" fill="none" stroke="var(--info)" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="kpi-value"><?php echo number_format($office_stats['total_feedback']); ?></div>
                    <div class="kpi-sub">Evaluations for your office</div>
                </div>

                <div class="kpi-card pending">
                    <div class="kpi-head">
                        <span class="kpi-label">Pending Tickets</span>
                        <svg width="20" height="20" fill="none" stroke="var(--warning)" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="kpi-value"><?php echo $pending_tickets; ?></div>
                    <div class="kpi-sub">Action plans to respond to</div>
                </div>

                <div class="kpi-card resolved">
                    <div class="kpi-head">
                        <span class="kpi-label">Resolved</span>
                        <svg width="20" height="20" fill="none" stroke="#8B5CF6" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="kpi-value"><?php echo $resolved_tickets; ?></div>
                    <div class="kpi-sub">Successfully closed tickets</div>
                </div>
            </section>

            <!-- SPLIT LAYOUT -->
            <div class="dashboard-split">
                
                <!-- LEFT COLUMN -->
                <div>
                    <div class="panel-card">
                        <div class="panel-head">
                            <div>
                                <h3>Service Quality Dimensions (SQD)</h3>
                                <p>How your office is rated across the 8 ARTA dimensions</p>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="sqdRadarChart"></canvas>
                        </div>
                    </div>

                    <div class="panel-card">
                        <div class="panel-head">
                            <div>
                                <h3>My Assigned Action Plans</h3>
                                <p>Review feedback, document root causes, log corrective actions</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($my_tickets)): ?>
                                        <?php foreach ($my_tickets as $t): ?>
                                            <?php
                                                $sc = 'status-pending';
                                                if ($t['status'] === 'In Progress') $sc = 'status-progress';
                                                if ($t['status'] === 'Resolved')    $sc = 'status-resolved';
                                            ?>
                                            <tr>
                                                <td style="font-family:monospace; font-weight:700;">#<?php echo str_pad((string)$t['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($t['service_accessed']); ?></td>
                                                <td><span class="badge-status <?php echo $sc; ?>"><?php echo htmlspecialchars($t['status']); ?></span></td>
                                                <td style="text-align:right;">
                                                    <a href="faculty_tickets.php?id=<?php echo $t['id']; ?>" style="color:var(--maroon); font-weight:700; text-decoration:none;">Update</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="empty-state">
                                                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <p>No assigned tickets found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="panel-card">
                    <div class="panel-head">
                        <div>
                            <h3>Recent Office Feedback</h3>
                            <p>Latest comments for <?php echo htmlspecialchars($my_office_name); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($recent_feedback)): ?>
                        <?php foreach ($recent_feedback as $fb): ?>
                            <div class="feed-item">
                                <div class="feed-header">
                                    <span class="feed-service"><?php echo htmlspecialchars($fb['service_accessed']); ?></span>
                                    <span class="feed-date"><?php echo date('M j', strtotime($fb['submission_timestamp'])); ?></span>
                                </div>
                                <div class="feed-comment">"<?php echo htmlspecialchars($fb['comments_recommendations']); ?>"</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <p>No written feedback for your office.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

    <!-- Chart.js -->
    <script>
        const ctx = document.getElementById('sqdRadarChart').getContext('2d');
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['Responsiveness', 'Reliability', 'Access', 'Communication', 'Costs', 'Integrity', 'Assurance', 'Outcome'],
                datasets: [{
                    label: '<?php echo htmlspecialchars($my_office_name); ?> Mean',
                    data: [
                        <?php echo $office_stats['avg_sqd1']; ?>,
                        <?php echo $office_stats['avg_sqd2']; ?>,
                        <?php echo $office_stats['avg_sqd3']; ?>,
                        <?php echo $office_stats['avg_sqd4']; ?>,
                        <?php echo $office_stats['avg_sqd5']; ?>,
                        <?php echo $office_stats['avg_sqd6']; ?>,
                        <?php echo $office_stats['avg_sqd7']; ?>,
                        <?php echo $office_stats['avg_sqd8']; ?>
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
                        pointLabels: { font: { size: 10, weight: '600' }, color: '#0F172A' },
                        suggestedMin: 1, suggestedMax: 5,
                        ticks: { stepSize: 1, backdropColor: 'transparent', font: { size: 9 } }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>