<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Auto-create reservations table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS reservations (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50) NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        purpose      VARCHAR(255) NOT NULL,
        date         DATE NOT NULL,
        time_slot    VARCHAR(50) NOT NULL,
        status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$success_msg = '';
$error_msg   = '';

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $res_id = (int)($_POST['res_id'] ?? 0);

    if ($_POST['action'] === 'approve' && $res_id > 0) {
        $conn->query("UPDATE reservations SET status='approved' WHERE id=$res_id AND status='pending'");
        $success_msg = 'Reservation approved.';
    }

    if ($_POST['action'] === 'reject' && $res_id > 0) {
        $conn->query("UPDATE reservations SET status='rejected' WHERE id=$res_id AND status='pending'");
        $success_msg = 'Reservation rejected.';
    }

    if ($_POST['action'] === 'delete' && $res_id > 0) {
        $conn->query("DELETE FROM reservations WHERE id=$res_id");
        $success_msg = 'Reservation deleted.';
    }
}

// ── Stats ──
$total_res    = $conn->query("SELECT COUNT(*) AS c FROM reservations")->fetch_assoc()['c'] ?? 0;
$pending_res  = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$approved_res = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$rejected_res = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='rejected'")->fetch_assoc()['c'] ?? 0;

// ── Fetch reservations with optional filters ──
$filter_status = $_GET['status'] ?? '';
$filter_lab    = $_GET['lab']    ?? '';
$search        = $_GET['search'] ?? '';

$where = [];
if ($filter_status) $where[] = "r.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($filter_lab)    $where[] = "r.lab = '"    . $conn->real_escape_string($filter_lab)    . "'";
if ($search)        $where[] = "(r.student_id LIKE '%" . $conn->real_escape_string($search) . "%' OR r.student_name LIKE '%" . $conn->real_escape_string($search) . "%' OR r.purpose LIKE '%" . $conn->real_escape_string($search) . "%')";

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reservations = [];
$rr = $conn->query("SELECT r.* FROM reservations r $where_sql ORDER BY r.created_at DESC LIMIT 100");
if ($rr) {
    while ($row = $rr->fetch_assoc()) $reservations[] = $row;
}

// Unique labs for filter dropdown
$labs = [];
$lr = $conn->query("SELECT DISTINCT lab FROM reservations ORDER BY lab");
if ($lr) while ($row = $lr->fetch_assoc()) $labs[] = $row['lab'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin – Reservations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:       #0f2653;
            --navy-mid:   #1a3a72;
            --navy-light: #2452a0;
            --gold:       #f0a500;
            --gold-light: #ffc84a;
            --bg:         #eef1f8;
            --panel:      #ffffff;
            --border:     #d6dce8;
            --text:       #1c2b4a;
            --muted:      #6b7fa3;
            --accent:     #3b6fd4;
            --tag-bg:     #e8edf8;
            --green:      #1a7a4a;
            --green-light:#edfaf3;
            --red:        #c0392b;
            --red-light:  #fff0f0;
            --orange:     #c07000;
            --orange-light:#fff8e1;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── NAVBAR ── */
        .navbar {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            padding: 0 28px;
            display: flex; justify-content: space-between; align-items: center;
            height: 60px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }

        .nav-title {
            font-size: 13.5px; font-weight: 600;
            color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3;
        }

        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 13px; font-weight: 500; padding: 7px 13px;
            border-radius: 6px; transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px; white-space: nowrap;
        }

        .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important; font-weight: 700 !important;
            border-radius: 8px !important; padding: 7px 18px !important;
            margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        /* ── PAGE ── */
        .page-wrapper {
            padding: 28px 32px 48px;
            animation: fadeUp 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header { margin-bottom: 24px; }

        .page-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 24px; color: var(--navy); margin-bottom: 3px;
        }

        .page-header p { font-size: 13px; color: var(--muted); }

        /* ── STAT STRIP ── */
        .stat-strip {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 16px; margin-bottom: 24px;
        }

        .stat-card {
            background: var(--panel); border-radius: 14px;
            border: 1px solid var(--border); padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            transition: transform 0.18s, box-shadow 0.18s;
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }

        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }

        .stat-icon.blue   { background: linear-gradient(135deg, var(--navy), var(--navy-light)); }
        .stat-icon.orange { background: linear-gradient(135deg, #c07000, #f0a500); }
        .stat-icon.green  { background: linear-gradient(135deg, #0f6e3a, #1a7a4a); }
        .stat-icon.red    { background: linear-gradient(135deg, #9b1c1c, #c0392b); }

        .stat-value { font-size: 28px; font-weight: 700; color: var(--navy); line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 11.5px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── ALERT ── */
        .alert {
            padding: 10px 16px; border-radius: 9px;
            font-size: 13px; font-weight: 600;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 9px;
        }

        .alert.success { background: var(--green-light); color: var(--green); border: 1px solid #b2e8cc; border-left: 4px solid #2ecc71; }
        .alert.error   { background: var(--red-light);   color: var(--red);   border: 1px solid #f5c6cb; border-left: 4px solid #e74c3c; }

        /* ── CARD ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 18px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .card-header-left { display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            padding: 14px 18px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            background: #fafbfd;
        }

        .filter-bar label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }

        .filter-bar input[type="text"],
        .filter-bar select {
            height: 34px; padding: 0 11px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: white; outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .filter-bar input[type="text"] { width: 210px; }

        .filter-bar input[type="text"]:focus,
        .filter-bar select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
        }

        .btn-filter {
            height: 34px; padding: 0 16px;
            background: var(--accent); color: white;
            border: none; border-radius: 8px;
            font-size: 12.5px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: opacity 0.15s, transform 0.15s;
        }

        .btn-filter:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-reset-filter {
            height: 34px; padding: 0 14px;
            border: 1px solid var(--border); border-radius: 8px;
            background: white; color: var(--muted);
            font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, color 0.15s;
        }

        .btn-reset-filter:hover { background: var(--tag-bg); color: var(--navy); }

        .filter-count { margin-left: auto; font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }

        thead tr { background: var(--tag-bg); border-bottom: 2px solid var(--border); }

        thead th {
            padding: 11px 14px; text-align: left;
            font-size: 11px; font-weight: 700; color: var(--navy);
            text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
        }

        thead th.center { text-align: center; }

        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.14s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }

        tbody td { padding: 11px 14px; vertical-align: middle; }
        tbody td.center { text-align: center; }

        .student-cell .s-name { font-weight: 700; color: var(--navy); font-size: 13px; }
        .student-cell .s-id   { font-size: 11px; color: var(--muted); margin-top: 1px; }

        .lab-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--tag-bg); color: var(--navy-light);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee;
            white-space: nowrap;
        }

        .slot-tag {
            display: inline-flex; align-items: center;
            background: #f0f3f9; color: var(--text);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; border: 1px solid var(--border);
            white-space: nowrap;
        }

        .td-date { font-weight: 700; color: var(--navy); font-size: 13px; white-space: nowrap; }
        .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .status-pill.pending   { background: var(--orange-light); color: var(--orange); }
        .status-pill.approved  { background: var(--green-light);  color: var(--green); }
        .status-pill.rejected  { background: var(--red-light);    color: var(--red); }
        .status-pill.cancelled { background: #f0f3f9; color: var(--muted); }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── ACTION BUTTONS ── */
        .action-btns { display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap; }

        .btn-approve, .btn-reject, .btn-delete {
            padding: 5px 12px; border-radius: 7px;
            font-size: 11.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer; border: none;
            transition: opacity 0.15s, transform 0.15s;
        }

        .btn-approve { background: var(--green-light); color: var(--green); border: 1px solid #b2e8cc; }
        .btn-reject  { background: var(--orange-light); color: var(--orange); border: 1px solid #fcd87a; }
        .btn-delete  { background: var(--red-light); color: var(--red); border: 1px solid #f5c6cb; }

        .btn-approve:hover, .btn-reject:hover, .btn-delete:hover {
            opacity: 0.8; transform: translateY(-1px);
        }

        .empty-state {
            text-align: center; padding: 56px 24px; color: var(--muted);
        }

        .empty-state .empty-icon { font-size: 44px; margin-bottom: 12px; opacity: 0.4; }
        .empty-state h3 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
        .empty-state p  { font-size: 13px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,38,83,0.45); z-index: 500;
            align-items: center; justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: white; border-radius: 18px; padding: 30px 28px;
            width: 100%; max-width: 380px;
            box-shadow: 0 16px 48px rgba(15,38,83,0.3);
            animation: modalIn 0.2s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.94) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-icon { font-size: 36px; text-align: center; margin-bottom: 12px; }
        .modal h3   { font-size: 17px; font-weight: 700; color: var(--navy); text-align: center; margin-bottom: 8px; }
        .modal p    { font-size: 13px; color: var(--muted); text-align: center; line-height: 1.6; margin-bottom: 22px; }

        .modal-btns { display: flex; gap: 10px; }

        .modal-btns button {
            flex: 1; padding: 11px; border-radius: 10px;
            font-size: 13.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer; border: none;
            transition: opacity 0.15s, transform 0.15s;
        }

        .modal-btns button:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-modal-confirm-approve { background: #1a7a4a; color: white; }
        .btn-modal-confirm-reject  { background: #c07000; color: white; }
        .btn-modal-confirm-delete  { background: var(--red); color: white; }
        .btn-modal-close           { background: var(--tag-bg); color: var(--navy); }

        @media (max-width: 1000px) {
            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .page-wrapper { padding: 20px 16px 40px; }
            .nav-title { display: none; }
        }

        @media (max-width: 560px) {
            .stat-strip { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="/SYSARCH/uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="/SYSARCH/admin/admin_home.php">Home ▾</a>
        <a href="/SYSARCH/admin/admin_search.php">Search</a>
        <a href="/SYSARCH/admin/admin_Student.php">Students</a>
        <a href="/SYSARCH/admin/admin_SitIn.php">Sit-in</a>
        <a href="/SYSARCH/admin/admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="/SYSARCH/admin/admin_SitInReports.php">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="/SYSARCH/admin/admin_reservation.php" class="active">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon">❓</div>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalDesc">Are you sure?</p>
        <div class="modal-btns">
            <button class="btn-modal-close" onclick="closeModal()">Cancel</button>
            <form method="POST" style="flex:1;" id="modalForm">
                <input type="hidden" name="action"  id="modalAction">
                <input type="hidden" name="res_id"  id="modalResId">
                <button type="submit" id="modalConfirmBtn" style="width:100%;">Confirm</button>
            </form>
        </div>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <h2>Reservation Management</h2>
        <p>Review, approve, or reject student lab reservation requests.</p>
    </div>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-icon blue">📋</div>
            <div>
                <div class="stat-value"><?= $total_res ?></div>
                <div class="stat-label">Total Reservations</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">⏳</div>
            <div>
                <div class="stat-value"><?= $pending_res ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <div class="stat-value"><?= $approved_res ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">❌</div>
            <div>
                <div class="stat-value"><?= $rejected_res ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert success">✅ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert error">❌ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- MAIN CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="hicon">📅</div>
                Reservation Requests
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <label>Filter:</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student, purpose…">

            <select name="status">
                <option value="">All Status</option>
                <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="approved"  <?= $filter_status === 'approved'  ? 'selected' : '' ?>>Approved</option>
                <option value="rejected"  <?= $filter_status === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>

            <select name="lab">
                <option value="">All Labs</option>
                <?php foreach ($labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>" <?= $filter_lab === $lab ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lab) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-filter">🔍 Filter</button>
            <a href="admin_reservation.php" style="text-decoration:none;">
                <button type="button" class="btn-reset-filter">✕ Reset</button>
            </a>

            <span class="filter-count"><?= count($reservations) ?> record<?= count($reservations) !== 1 ? 's' : '' ?></span>
        </form>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No reservations found</h3>
                <p>No reservation requests match the current filters.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Laboratory</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Purpose</th>
                        <th class="center">Status</th>
                        <th class="center">Submitted</th>
                        <th class="center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $i => $res):
                        $date_fmt = $res['date'] ? date('M j, Y', strtotime($res['date'])) : '—';
                        $day_fmt  = $res['date'] ? date('D', strtotime($res['date'])) : '';
                        $sub_fmt  = $res['created_at'] ? date('M j, g:i A', strtotime($res['created_at'])) : '—';
                        $status   = $res['status'];
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;font-weight:600;"><?= $i + 1 ?></td>
                        <td>
                            <div class="student-cell">
                                <div class="s-name"><?= htmlspecialchars($res['student_name']) ?></div>
                                <div class="s-id"><?= htmlspecialchars($res['student_id']) ?></div>
                            </div>
                        </td>
                        <td><span class="lab-tag">🏛️ <?= htmlspecialchars($res['lab']) ?></span></td>
                        <td>
                            <div class="td-date"><?= htmlspecialchars($date_fmt) ?></div>
                            <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                        </td>
                        <td><span class="slot-tag">⏱ <?= htmlspecialchars($res['time_slot']) ?></span></td>
                        <td style="max-width:180px;">
                            <div style="font-size:13px;color:#445;line-height:1.4;white-space:normal;">
                                <?= htmlspecialchars($res['purpose']) ?>
                            </div>
                        </td>
                        <td class="center">
                            <?php if ($status === 'pending'): ?>
                            <span class="status-pill pending"><span class="status-dot"></span> Pending</span>
                            <?php elseif ($status === 'approved'): ?>
                            <span class="status-pill approved"><span class="status-dot"></span> Approved</span>
                            <?php elseif ($status === 'rejected'): ?>
                            <span class="status-pill rejected"><span class="status-dot"></span> Rejected</span>
                            <?php else: ?>
                            <span class="status-pill cancelled"><span class="status-dot"></span> Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="center" style="font-size:12px;color:var(--muted);white-space:nowrap;">
                            <?= htmlspecialchars($sub_fmt) ?>
                        </td>
                        <td class="center">
                            <div class="action-btns">
                                <?php if ($status === 'pending'): ?>
                                <button class="btn-approve"
                                    onclick="openModal('approve', <?= (int)$res['id'] ?>, '<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    ✓ Approve
                                </button>
                                <button class="btn-reject"
                                    onclick="openModal('reject', <?= (int)$res['id'] ?>, '<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    ✕ Reject
                                </button>
                                <?php endif; ?>
                                <button class="btn-delete"
                                    onclick="openModal('delete', <?= (int)$res['id'] ?>, '<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    🗑
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /page-wrapper -->

<script>
    const modalConfigs = {
        approve: {
            icon: '✅',
            title: 'Approve Reservation?',
            desc: name => `Approve the reservation for <strong>${name}</strong>? The student will be notified.`,
            btnClass: 'btn-modal-confirm-approve',
            btnText: '✓ Approve'
        },
        reject: {
            icon: '⚠️',
            title: 'Reject Reservation?',
            desc: name => `Reject the reservation for <strong>${name}</strong>? This cannot be undone.`,
            btnClass: 'btn-modal-confirm-reject',
            btnText: '✕ Reject'
        },
        delete: {
            icon: '🗑️',
            title: 'Delete Reservation?',
            desc: name => `Permanently delete this reservation record for <strong>${name}</strong>?`,
            btnClass: 'btn-modal-confirm-delete',
            btnText: '🗑 Delete'
        }
    };

    function openModal(action, id, name) {
        const cfg = modalConfigs[action];
        document.getElementById('modalIcon').textContent    = cfg.icon;
        document.getElementById('modalTitle').textContent   = cfg.title;
        document.getElementById('modalDesc').innerHTML      = cfg.desc(name);
        document.getElementById('modalAction').value        = action;
        document.getElementById('modalResId').value         = id;

        const btn = document.getElementById('modalConfirmBtn');
        btn.className = cfg.btnClass;
        btn.textContent = cfg.btnText;

        document.getElementById('confirmModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('confirmModal').classList.remove('open');
    }

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function() { el.style.display = 'none'; }, 400);
        }, 4000);
    });
</script>

</body>
</html>