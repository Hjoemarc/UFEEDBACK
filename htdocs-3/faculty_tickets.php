<?php
// faculty_tickets.php — Manage Assigned Action Plans

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// 1. Authentication Guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (!in_array($_SESSION['internal_user_type'] ?? '', ['zppsu_employee'])) {
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$user_id = $_SESSION['user_id'];
$toast_message = '';
$toast_type = '';

// ---------------------------------------------------------------------------
// HANDLE TICKET UPDATE (Faculty Action)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ticket') {
    $ticket_id    = (int)$_POST['ticket_id'];
    $action_taken = trim($_POST['action_taken']);
    $status       = $_POST['status'];

    if ($ticket_id <= 0 || empty($action_taken)) {
        $toast_message = "Please describe the action taken.";
        $toast_type = 'error';
    } else {
        // Verify ticket belongs to this faculty member
        $chk = $conn->prepare("SELECT id FROM action_plans WHERE id = ? AND assigned_staff_id = ?");
        $chk->bind_param("is", $ticket_id, $user_id);
        $chk->execute();
        
        if ($chk->get_result()->num_rows > 0) {
            if ($status === 'Resolved') {
                $upd = $conn->prepare("UPDATE action_plans SET action_taken = ?, status = ?, resolved_at = COALESCE(resolved_at, NOW()) WHERE id = ?");
                $upd->bind_param("ssi", $action_taken, $status, $ticket_id);
            } else {
                $upd = $conn->prepare("UPDATE action_plans SET action_taken = ?, status = ?, resolved_at = NULL WHERE id = ?");
                $upd->bind_param("ssi", $action_taken, $status, $ticket_id);
            }
            
            if ($upd->execute()) {
                $toast_message = "Ticket #" . str_pad((string)$ticket_id, 4, '0', STR_PAD_LEFT) . " updated successfully.";
                $toast_type = 'success';
            } else {
                $toast_message = "Failed to update ticket: " . $conn->error;
                $toast_type = 'error';
            }
            $upd->close();
        } else {
            $toast_message = "Unauthorized action.";
            $toast_type = 'error';
        }
        $chk->close();
    }
}

// ---------------------------------------------------------------------------
// FETCH ALL ASSIGNED TICKETS
// ---------------------------------------------------------------------------
$tickets = [];
$ticket_res = $conn->query("
    SELECT 
        ap.id, ap.feedback_id, ap.status, ap.root_cause_analysis, ap.action_taken, 
        ap.created_at, ap.resolved_at,
        f.service_accessed, f.comments_recommendations, f.submission_timestamp
    FROM action_plans ap
    JOIN arta_feedback f ON ap.feedback_id = f.feedback_id
    WHERE ap.assigned_staff_id = '" . $conn->real_escape_string($user_id) . "'
    ORDER BY 
        CASE WHEN ap.status = 'Resolved' THEN 2 ELSE 1 END,
        ap.created_at DESC
");
if ($ticket_res instanceof mysqli_result) {
    while ($r = $ticket_res->fetch_assoc()) $tickets[] = $r;
}

// KPI Stats
$kpi_pending = 0; $kpi_progress = 0; $kpi_resolved = 0;
foreach ($tickets as $t) {
    if ($t['status'] === 'Pending') $kpi_pending++;
    elseif ($t['status'] === 'In Progress') $kpi_progress++;
    elseif ($t['status'] === 'Resolved') $kpi_resolved++;
}

$page_title    = 'My Assigned Tickets';
$page_crumb    = 'Faculty Workspace / Tickets';
$page_subtitle = 'Review assigned feedback and document corrective actions';
$unread_notifications = 0;
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
            padding: calc(var(--header-height) + 20px) 24px 40px;
            flex:1; display:flex; flex-direction:column;
        }

        /* ===== STICKY TOOLBAR ===== */
        .fixed-header-deck {
            position: sticky; top: var(--header-height); z-index: 800;
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
        
        .filter-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; width: 100%; max-width: 400px; }
        .input-live-search, .select-live-filter {
            padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 12.5px; background: #F8FAFC; color: var(--text-heading);
            outline: none; font-family: inherit; transition: border-color 0.18s;
        }
        .input-live-search { flex: 1; min-width: 150px; }
        .input-live-search:focus, .select-live-filter:focus { border-color: var(--maroon); background: #FFFFFF; }

        /* ===== KPI ===== */
        .kpi-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .kpi-card {
            background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px;
            display:flex; align-items:center; gap:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .kpi-icon.orange { background:#FFF7ED; color:#C2410C; }
        .kpi-icon.blue   { background:#EFF6FF; color:#1D4ED8; }
        .kpi-icon.green  { background:#ECFDF5; color:#047857; }
        .kpi-value { font-size:22px; font-weight:800; color:var(--text-heading); line-height:1; }
        .kpi-label { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }

        /* ===== TABLE ===== */
        .panel-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); min-width: 0; }
        .table-responsive { overflow-x:auto; border:1px solid var(--border); border-radius:8px; width: 100%; -webkit-overflow-scrolling:touch; }
        
        table { width:100%; border-collapse:collapse; font-size:12.5px; min-width:800px; text-align:left; }
        th { background:#F8FAFC; color:var(--text-muted); font-weight:700; padding:11px 14px; text-transform:uppercase; font-size:10.5px; letter-spacing:0.4px; border-bottom:1px solid var(--border); white-space: nowrap; }
        td { padding:14px; border-bottom:1px solid var(--border); color:var(--text-body); vertical-align:top; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#FAFBFC; }

        .badge-status { display:inline-flex; align-items:center; padding:4px 10px; border-radius:4px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; white-space: nowrap; }
        .status-pending { background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA; }
        .status-progress { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }
        .status-resolved { background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; }

        .btn-action {
            padding:8px 16px; border-radius:6px; font-size:11.5px; font-weight:700;
            cursor:pointer; border:1px solid transparent; transition: filter 0.18s; 
            font-family:inherit; background:var(--maroon); color:#FFF;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-action:hover { filter: brightness(0.9); }

        /* ===== MODAL ===== */
        .modal-overlay {
            position:fixed; inset:0; background:rgba(15,23,42,0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index:1050; display:none; align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:var(--surface); border-radius:16px; width:100%; max-width:640px;
            max-height: calc(100vh - 40px); display:flex; flex-direction:column; overflow:hidden;
            box-shadow: 0 30px 60px -12px rgba(15,23,42,0.35);
            animation: modalIn 0.3s cubic-bezier(0.34, 1.3, 0.64, 1);
        }
        @keyframes modalIn { from { opacity:0; transform:translateY(20px) scale(0.98); } to { opacity:1; transform:translateY(0) scale(1); } }

        .modal-header {
            padding:18px 24px; border-bottom:1px solid var(--border); 
            display:flex; justify-content:space-between; align-items:center;
            background: linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%);
        }
        .modal-header h3 { margin:0; font-size:16.5px; font-weight:800; color:var(--text-heading); }
        .btn-close { background:transparent; border:none; font-size:22px; cursor:pointer; color:var(--text-muted); }
        .btn-close:hover { color: var(--danger); }

        .modal-body { padding:24px; overflow-y:auto; flex:1; }
        .form-group { margin-bottom:20px; }
        .form-group:last-child { margin-bottom:0; }
        .form-group label { display:block; font-size:12.5px; font-weight:700; color:var(--text-heading); margin-bottom:8px; }
        .form-group label span { color: var(--danger); }
        
        .form-group select, .form-group textarea {
            width:100%; padding:12px 14px; border:1.5px solid var(--border); border-radius:8px;
            font-size:13.5px; background:#F8FAFC; color:var(--text-heading); font-family:inherit; outline:none;
            transition: border-color 0.18s;
        }
        .form-group textarea { min-height:120px; resize:vertical; line-height:1.5; }
        .form-group select:focus, .form-group textarea:focus { border-color:var(--maroon); background:#FFF; box-shadow:0 0 0 3px rgba(122,0,0,0.08); }
        
        .readonly-box {
            background:#F1F5F9; border:1.5px dashed #CBD5E1; border-radius:8px;
            padding:14px; font-size:13px; color:#475569; line-height:1.5;
        }

        .modal-footer {
            padding:16px 24px; border-top:1px solid var(--border); background:#FAFBFC; 
            display:flex; justify-content:flex-end; gap:12px;
        }
        .btn-secondary { background:#FFF; color:var(--text-heading); border:1.5px solid var(--border); padding:10px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition: 0.18s; }
        .btn-secondary:hover { background:#F1F5F9; }

        /* ===== TOAST ===== */
        .toast-container { position:fixed; top: 20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px; max-width:400px; pointer-events:none; }
        .toast { pointer-events:auto; background:#FFF; border-radius:8px; padding:14px 16px; box-shadow:0 10px 25px rgba(0,0,0,0.1); border-left:4px solid var(--success); font-size:13px; font-weight:600; color:var(--text-heading); display:flex; align-items:center; justify-content:space-between; gap:12px; animation: toastSlideIn 0.3s forwards; }
        .toast-error { border-left-color: var(--danger); }
        .toast-close { background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:20px; line-height:1; }
        @keyframes toastSlideIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align:center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state svg { width: 48px; height: 48px; color: #CBD5E1; margin-bottom: 12px; }
        .empty-state h4 { color: var(--text-heading); font-size: 15px; margin: 0 0 4px; }
        .empty-state p { font-size: 13px; margin: 0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 30px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .filter-controls { max-width: 100%; }
            .kpi-grid { grid-template-columns: 1fr; gap: 8px; }
            .kpi-card { padding: 12px; }
            .modal-overlay { padding: 0; align-items: stretch; }
            .modal-box { max-height: 100dvh; height: 100dvh; border-radius: 0; max-width: 100%; }
            .modal-header { padding: 16px; }
            .modal-body { padding: 20px 16px; }
            .modal-footer { padding: 14px 16px; gap: 8px; }
            .modal-footer button { flex: 1; justify-content: center; }
            .toast-container { top: 10px; right:10px; left:10px; max-width:none; }
        }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <?php 
    require_once 'faculty_sidebar.php'; 
    if (file_exists('admin_header.php')) require_once 'admin_header.php'; 
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>My Assigned Tickets</h2>
                        <p>Review feedback and log your corrective actions</p>
                    </div>

                    <div class="filter-controls">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search by ticket or service..." autocomplete="off">
                        <select id="liveStatusSelect" class="select-live-filter">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </section>
            </div>

            <!-- KPI GRID -->
            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon orange">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="kpi-value"><?php echo $kpi_pending; ?></div>
                        <div class="kpi-label">Pending</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon blue">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <div class="kpi-value"><?php echo $kpi_progress; ?></div>
                        <div class="kpi-label">In Progress</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon green">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="kpi-value"><?php echo $kpi_resolved; ?></div>
                        <div class="kpi-label">Resolved</div>
                    </div>
                </div>
            </section>

            <!-- TABLE -->
            <section class="panel-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:100px;">Ticket ID</th>
                                <th style="width:200px;">Service & Feedback</th>
                                <th style="width:120px;">Status</th>
                                <th>Action Taken / Notes</th>
                                <th style="width:100px; text-align:right;">Manage</th>
                            </tr>
                        </thead>
                        <tbody id="ticketTableBody">
                            <?php if (!empty($tickets)): ?>
                                <?php foreach ($tickets as $t): ?>
                                    <?php
                                        $status_class = 'status-pending';
                                        if ($t['status'] === 'In Progress') $status_class = 'status-progress';
                                        if ($t['status'] === 'Resolved') $status_class = 'status-resolved';
                                    ?>
                                    <tr class="ticket-row"
                                        data-search="<?php echo strtolower(htmlspecialchars('#' . $t['id'] . ' ' . $t['service_accessed'])); ?>"
                                        data-status="<?php echo htmlspecialchars($t['status']); ?>">
                                        
                                        <td>
                                            <strong style="color:var(--text-heading); font-family:monospace; font-size: 13px;">#<?php echo str_pad((string)$t['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                            <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">
                                                <?php echo date('M j, Y', strtotime($t['created_at'])); ?>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:12.5px; display:block;"><?php echo htmlspecialchars($t['service_accessed']); ?></strong>
                                            <?php if (!empty($t['comments_recommendations'])): ?>
                                                <div style="font-size:11.5px; color:var(--text-muted); font-style:italic; margin-top:6px; line-height: 1.4;">
                                                    "<?php echo htmlspecialchars($t['comments_recommendations']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge-status <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($t['status']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if (!empty($t['action_taken'])): ?>
                                                <div style="font-size:12px; color:var(--text-body); line-height:1.5;">
                                                    <?php echo nl2br(htmlspecialchars($t['action_taken'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size:11.5px; color:var(--text-muted); font-style:italic;">No action documented yet.</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align:right;">
                                            <button class="btn-action" onclick='openTicketModal(<?php echo htmlspecialchars(json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <h4>All clear!</h4>
                                            <p>You currently have no assigned action plans.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No tickets match your search or filter settings.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL: Update Ticket
         ============================================================ -->
    <div class="modal-overlay" id="ticketModal">
        <div class="modal-box" role="dialog" aria-modal="true">
            
            <div class="modal-header">
                <h3>Update Action Plan</h3>
                <button class="btn-close" onclick="closeModal()" aria-label="Close">&times;</button>
            </div>

            <form method="POST" action="faculty_tickets.php">
                <input type="hidden" name="action" value="update_ticket">
                <input type="hidden" name="ticket_id" id="modalTicketId" value="">

                <div class="modal-body">
                    
                    <!-- Admin Assessment (Read Only) -->
                    <div class="form-group">
                        <label>Root Cause Assessment (from Admin)</label>
                        <div class="readonly-box" id="modalRootCause">
                            No initial assessment provided.
                        </div>
                    </div>

                    <!-- Faculty Action Taken -->
                    <div class="form-group">
                        <label>Action Taken / Resolution Notes <span>*</span></label>
                        <textarea id="modalActionTaken" name="action_taken" required placeholder="Describe the specific steps taken to resolve this issue (e.g., 'Added an additional window for enrollment', 'Retrained frontline staff')..."></textarea>
                    </div>

                    <!-- Status Update -->
                    <div class="form-group">
                        <label>Update Status</label>
                        <select id="modalStatus" name="status" required>
                            <option value="Pending">Pending (Not yet started)</option>
                            <option value="In Progress">In Progress (Currently working on it)</option>
                            <option value="Resolved">Resolved (Issue fixed)</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-action">Save Updates</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ============================================================
           TOAST
           ============================================================ */
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const iconSvg = type === 'success'
                ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`
                : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            toast.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="color:${type === 'success' ? 'var(--success)' : 'var(--danger)'}; display:flex;">${iconSvg}</div>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4500);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($toast_type); ?>);
            });
        <?php endif; ?>

        /* ============================================================
           MODAL LOGIC
           ============================================================ */
        function openTicketModal(ticket) {
            document.getElementById('modalTicketId').value = ticket.id;
            document.getElementById('modalRootCause').textContent = ticket.root_cause_analysis || 'No initial assessment provided.';
            document.getElementById('modalActionTaken').value = ticket.action_taken || '';
            document.getElementById('modalStatus').value = ticket.status;
            document.getElementById('ticketModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('ticketModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        /* ============================================================
           LIVE TABLE FILTER
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const liveStatusSelect = document.getElementById('liveStatusSelect');
        const rows = document.querySelectorAll('.ticket-row');
        const noResults = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term = (liveSearchInput.value || '').toLowerCase().trim();
            const status = (liveStatusSelect.value || '').toLowerCase().trim();
            let matches = 0;

            rows.forEach(row => {
                const rSearch = row.getAttribute('data-search') || '';
                const rStatus = (row.getAttribute('data-status') || '').toLowerCase().trim();

                const okSearch = (term === '' || rSearch.includes(term));
                const okStatus = (status === '' || rStatus === status);

                if (okSearch && okStatus) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
        }

        if (liveSearchInput) liveSearchInput.addEventListener('input', runLiveFilter);
        if (liveStatusSelect) liveStatusSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>