<?php
session_start();

if (!isset($_SESSION['student_id'])) {
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

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? '';
$success_msg  = '';
$error_msg    = '';

// ── Auto-create reservations table if missing ──
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

// ── Handle form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'reserve') {
        $lab       = trim($_POST['lab'] ?? '');
        $purpose   = trim($_POST['purpose'] ?? '');
        $date      = trim($_POST['date'] ?? '');
        $time_slot = trim($_POST['time_slot'] ?? '');

        if ($lab && $purpose && $date && $time_slot) {
            // Check sessions remaining
            $srow = $conn->query("SELECT sessions FROM student WHERE IdNumber = '" . $conn->real_escape_string($student_id) . "'")->fetch_assoc();
            if (!$srow || (int)$srow['sessions'] <= 0) {
                $error_msg = 'You have no sessions remaining. Cannot make a reservation.';
            } else {
                // ── SERVER-SIDE: Verify the lab is admin-configured, active, and has available PCs ──
                $lab_check = $conn->prepare("
                    SELECT id FROM configured_labs
                    WHERE lab_name = ? AND is_active = 1 AND pc_status_set = 1
                ");
                $lab_check->bind_param('s', $lab);
                $lab_check->execute();
                $lab_check->store_result();

                if ($lab_check->num_rows === 0) {
                    $error_msg = 'That laboratory is not currently open for reservations.';
                    $lab_check->close();
                } else {
                    $lab_check->close();

                    // Check at least one PC is available
                    $avail_check = $conn->prepare("
                        SELECT COUNT(*) FROM lab_pc_status WHERE lab = ? AND status = 'available'
                    ");
                    $avail_check->bind_param('s', $lab);
                    $avail_check->execute();
                    $avail_check->bind_result($avail_pc_count);
                    $avail_check->fetch();
                    $avail_check->close();

                    if ((int)$avail_pc_count < 1) {
                        $error_msg = 'No PCs are currently available in that laboratory.';
                    } else {
                        // Prevent duplicate pending reservation for same slot
                        $chk = $conn->prepare("SELECT id FROM reservations WHERE student_id = ? AND date = ? AND time_slot = ? AND status = 'pending'");
                        $chk->bind_param('sss', $student_id, $date, $time_slot);
                        $chk->execute();
                        $chk->store_result();
                        if ($chk->num_rows > 0) {
                            $error_msg = 'You already have a pending reservation for that date and time slot.';
                        } else {
                            $ins = $conn->prepare("INSERT INTO reservations (student_id, student_name, lab, purpose, date, time_slot) VALUES (?, ?, ?, ?, ?, ?)");
                            $ins->bind_param('ssssss', $student_id, $student_name, $lab, $purpose, $date, $time_slot);
                            if ($ins->execute()) {
                                $success_msg = 'Reservation submitted successfully! Please wait for admin approval.';
                            } else {
                                $error_msg = 'Failed to submit reservation. Please try again.';
                            }
                            $ins->close();
                        }
                        $chk->close();
                    }
                }
            }
        } else {
            $error_msg = 'Please fill in all fields.';
        }
    }

    if ($_POST['action'] === 'cancel') {
        $res_id = (int)($_POST['res_id'] ?? 0);
        if ($res_id > 0) {
            $upd = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND student_id = ? AND status = 'pending'");
            $upd->bind_param('is', $res_id, $student_id);
            $upd->execute();
            if ($upd->affected_rows > 0) {
                $success_msg = 'Reservation cancelled.';
            } else {
                $error_msg = 'Could not cancel — reservation may already be processed.';
            }
            $upd->close();
        }
    }
}

// ── Fetch my reservations ──
$reservations = [];
$rres = $conn->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
$rres->bind_param('s', $student_id);
$rres->execute();
$rr = $rres->get_result();
while ($row = $rr->fetch_assoc()) {
    $reservations[] = $row;
}
$rres->close();

// ── Stats ──
$total_res   = count($reservations);
$pending_res = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));
$approved_res= count(array_filter($reservations, fn($r) => $r['status'] === 'approved'));

// ── Sessions remaining ──
$sessions_remaining = 0;
$sr = $conn->prepare("SELECT sessions FROM student WHERE IdNumber = ?");
$sr->bind_param('s', $student_id);
$sr->execute();
$srrow = $sr->get_result()->fetch_assoc();
if ($srrow) $sessions_remaining = (int)$srrow['sessions'];
$sr->close();

// ── Unread notifications ──
$unread_count = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $nstmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0");
    $nstmt->bind_param('s', $student_id);
    $nstmt->execute();
    $nstmt->bind_result($unread_count);
    $nstmt->fetch();
    $nstmt->close();
}

// Min date = tomorrow
$min_date = date('Y-m-d', strtotime('+1 day'));
// Max date = 30 days from now
$max_date = date('Y-m-d', strtotime('+30 days'));

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservation – CCS Sit-in Monitoring System</title>
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
            --green:      #1cb87e;
            --green-bg:   #e6f8f2;
            --red:        #e53535;
            --red-bg:     #fdeaea;
            --orange:     #f07b00;
            --orange-bg:  #fff3e0;
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
            height: 60px; position: sticky; top: 0; z-index: 200;
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
            border-radius: 6px; transition: background 0.18s, color 0.18s; letter-spacing: 0.2px;
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

        /* ── NOTIFICATION BUTTON ── */
        .notif-wrapper { position: relative; display: flex; align-items: center; }

        .notif-btn {
            position: relative; background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.85); font-size: 13px; font-weight: 500;
            padding: 7px 13px; border-radius: 6px;
            display: flex; align-items: center; gap: 6px;
            font-family: 'DM Sans', sans-serif; letter-spacing: 0.2px;
            transition: background 0.18s, color 0.18s;
        }

        .notif-btn:hover { background: rgba(255,255,255,0.12); color: white; }

        .notif-badge {
            position: absolute; top: 4px; right: 6px;
            background: #e53535; color: white;
            font-size: 9px; font-weight: 700;
            min-width: 16px; height: 16px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid var(--navy-mid); line-height: 1;
        }

        .notif-badge.hidden { display: none; }

        /* ── PAGE ── */
        .page-wrap { padding: 24px 28px; max-width: 1200px; margin: 0 auto; }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 22px;
            animation: fadeUp 0.4s ease both;
        }

        .page-header-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 14px rgba(36,82,160,0.35);
        }

        .page-header-text h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--navy); line-height: 1.2;
        }

        .page-header-text p { font-size: 13px; color: var(--muted); margin-top: 2px; }

        /* ── STATS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
            margin-bottom: 22px;
            animation: fadeUp 0.4s ease 0.06s both;
        }

        .stat-card {
            background: var(--panel); border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 3px 16px rgba(15,38,83,0.07);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }

        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }

        .stat-icon.blue   { background: linear-gradient(135deg, var(--navy-light), var(--accent)); }
        .stat-icon.orange { background: linear-gradient(135deg, var(--orange), var(--gold)); }
        .stat-icon.green  { background: linear-gradient(135deg, #0fa86a, var(--green)); }

        .stat-info .stat-val {
            font-family: 'DM Serif Display', serif;
            font-size: 28px; color: var(--navy); line-height: 1;
        }

        .stat-info .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── MAIN GRID ── */
        .main-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* ── CARD ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
        }

        .card:nth-child(1) { animation: fadeUp 0.4s ease 0.10s both; }
        .card:nth-child(2) { animation: fadeUp 0.4s ease 0.16s both; }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 20px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        /* ── ALERT MESSAGES ── */
        .alert {
            margin: 16px 20px 0;
            padding: 12px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
        }

        .alert.success { background: var(--green-bg); color: #0a7a52; border: 1px solid #9ee3ca; }
        .alert.error   { background: var(--red-bg);   color: #a01010; border: 1px solid #f5b8b8; }
        .alert .alert-icon { font-size: 16px; flex-shrink: 0; }

        /* ── FORM ── */
        .form-body { padding: 20px; display: flex; flex-direction: column; gap: 16px; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-group label {
            font-size: 12px; font-weight: 700; color: var(--navy);
            text-transform: uppercase; letter-spacing: 0.4px;
            display: flex; align-items: center; gap: 6px;
        }

        .form-group label .licon {
            width: 20px; height: 20px; background: var(--tag-bg); border-radius: 5px;
            display: inline-flex; align-items: center; justify-content: center; font-size: 11px;
        }

        .form-group select,
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: #fafbfd;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }

        .form-group select:focus,
        .form-group input[type="date"]:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.5;
        }

        .char-count {
            font-size: 11px; color: var(--muted);
            text-align: right; margin-top: -4px;
        }

        /* Lab loading state */
        .lab-load-msg {
            font-size: 11.5px; margin-top: 4px; display: none;
        }
        .lab-load-msg.warning { color: var(--orange); }
        .lab-load-msg.error   { color: var(--red); }

        /* Time slots grid */
        .slot-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .slot-option { display: none; }

        .slot-label {
            display: flex; align-items: center; justify-content: center;
            padding: 9px 10px; border-radius: 9px; cursor: pointer;
            border: 1.5px solid var(--border);
            background: #fafbfd;
            font-size: 12.5px; font-weight: 600; color: var(--muted);
            text-align: center; line-height: 1.3;
            transition: all 0.15s;
        }

        .slot-label:hover { border-color: var(--accent); color: var(--accent); background: #f0f4ff; }

        .slot-option:checked + .slot-label {
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            border-color: var(--accent);
            color: white;
            box-shadow: 0 2px 8px rgba(36,82,160,0.25);
        }

        /* Session warning */
        .sessions-info {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px;
            background: var(--tag-bg); border: 1px solid var(--border);
            font-size: 12.5px; color: var(--navy); font-weight: 600;
        }

        .sessions-info.low { background: var(--red-bg); border-color: #f5b8b8; color: var(--red); }
        .sessions-info .si-num { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--navy); }
        .sessions-info.low .si-num { color: var(--red); }

        /* Submit button */
        .btn-submit {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            color: white; border: none; border-radius: 11px;
            font-size: 14px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(36,82,160,0.35);
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
        }

        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(36,82,160,0.45); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* ── MY RESERVATIONS TABLE ── */
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }

        thead tr { background: var(--tag-bg); border-bottom: 2px solid var(--border); }

        thead th {
            padding: 11px 16px;
            text-align: left;
            font-size: 11px; font-weight: 700;
            color: var(--navy); text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }

        thead th.center { text-align: center; }

        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.14s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }

        tbody td { padding: 12px 16px; vertical-align: middle; }
        tbody td.center { text-align: center; }

        .lab-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--tag-bg); color: var(--navy-light);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee;
            white-space: nowrap;
        }

        .slot-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: #f0f3f9; color: var(--text);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; border: 1px solid var(--border);
            white-space: nowrap;
        }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .status-pill.pending  { background: #fff8e1; color: #c07000; }
        .status-pill.approved { background: var(--green-bg); color: var(--green); }
        .status-pill.rejected { background: var(--red-bg); color: var(--red); }
        .status-pill.cancelled{ background: #f0f3f9; color: var(--muted); }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .btn-cancel {
            padding: 5px 12px; border-radius: 7px;
            background: var(--red-bg); border: 1px solid #f5b8b8;
            color: var(--red); font-size: 11.5px; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-cancel:hover { background: #fcd5d5; border-color: #e08080; }

        .empty-state {
            text-align: center; padding: 50px 24px; color: var(--muted);
        }

        .empty-state .empty-icon { font-size: 44px; margin-bottom: 12px; opacity: 0.4; }
        .empty-state h3 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
        .empty-state p  { font-size: 13px; }

        .td-date { font-weight: 700; color: var(--navy); font-size: 13px; white-space: nowrap; }
        .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

        /* ── MODAL CONFIRM ── */
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
        .modal h3 { font-size: 17px; font-weight: 700; color: var(--navy); text-align: center; margin-bottom: 8px; }
        .modal p  { font-size: 13px; color: var(--muted); text-align: center; line-height: 1.6; margin-bottom: 22px; }

        .modal-btns { display: flex; gap: 10px; }

        .modal-btns button {
            flex: 1; padding: 11px; border-radius: 10px;
            font-size: 13.5px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; border: none; transition: opacity 0.15s, transform 0.15s;
        }

        .modal-btns button:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-confirm-cancel {
            background: var(--red); color: white;
        }

        .btn-modal-close {
            background: var(--tag-bg); color: var(--navy);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-wrap { padding: 16px; }
        }

        @media (max-width: 560px) {
            .stats-row { grid-template-columns: 1fr; }
            .slot-grid { grid-template-columns: 1fr; }
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

        <div class="notif-wrapper">
            <button class="notif-btn" onclick="window.location.href='notification.php'">
                <span>🔔</span>
                Notification
                <?php if ($unread_count > 0): ?>
                <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                <?php else: ?>
                <span class="notif-badge hidden"></span>
                <?php endif; ?>
            </button>
        </div>

        <a href="/SYSARCH/user/user_home.php">Home</a>
        <a href="/SYSARCH/user/user_edit_profile.php">Edit Profile</a>
        <a href="/SYSARCH/user/history.php">History</a>
        <a href="/SYSARCH/user/reservation.php" class="active">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- CANCEL CONFIRM MODAL -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal">
        <div class="modal-icon">🗑️</div>
        <h3>Cancel Reservation?</h3>
        <p>This will cancel your pending reservation. This action cannot be undone.</p>
        <div class="modal-btns">
            <button class="btn-modal-close" onclick="closeModal()">Keep it</button>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="res_id" id="cancelResId">
                <button type="submit" class="btn-confirm-cancel" style="width:100%;">Yes, cancel</button>
            </form>
        </div>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrap">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-header-icon">📅</div>
        <div class="page-header-text">
            <h1>Lab Reservation</h1>
            <p>Book a laboratory session in advance</p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue">📋</div>
            <div class="stat-info">
                <div class="stat-val"><?= $total_res ?></div>
                <div class="stat-lbl">Total Reservations</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">⏳</div>
            <div class="stat-info">
                <div class="stat-val"><?= $pending_res ?></div>
                <div class="stat-lbl">Pending Approval</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-info">
                <div class="stat-val"><?= $approved_res ?></div>
                <div class="stat-lbl">Approved</div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="main-grid">

        <!-- LEFT: RESERVATION FORM -->
        <div class="card">
            <div class="card-header">
                <div class="hicon">📝</div>
                New Reservation
            </div>

            <?php if ($success_msg): ?>
            <div class="alert success">
                <span class="alert-icon">✅</span>
                <?= htmlspecialchars($success_msg) ?>
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div class="alert error">
                <span class="alert-icon">⚠️</span>
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="form-body">
                <input type="hidden" name="action" value="reserve">

                <!-- Sessions remaining -->
                <div class="sessions-info <?= $sessions_remaining <= 2 ? 'low' : '' ?>">
                    <span style="font-size:22px;"><?= $sessions_remaining <= 2 ? '⚠️' : '💻' ?></span>
                    <div>
                        <div style="display:flex;align-items:baseline;gap:6px;">
                            <span class="si-num"><?= $sessions_remaining ?></span>
                            <span style="font-size:13px;">sessions remaining</span>
                        </div>
                        <div style="font-size:11px;font-weight:400;color:var(--muted);margin-top:1px;">
                            <?= $sessions_remaining <= 0 ? 'No sessions left — cannot make reservations.' : ($sessions_remaining <= 2 ? 'Running low on sessions.' : 'Available for reservation.') ?>
                        </div>
                    </div>
                </div>

                <!-- Laboratory — dynamically loaded from admin config -->
                <div class="form-group">
                    <label><span class="licon">🏛️</span> Laboratory</label>
                    <select name="lab" id="labSelect" required <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>>
                        <option value="" disabled selected>Loading available labs…</option>
                    </select>
                    <div class="lab-load-msg" id="labLoadMsg"></div>
                </div>

                <!-- Purpose -->
                <div class="form-group">
                    <label><span class="licon">📌</span> Purpose</label>
                    <textarea name="purpose" maxlength="255" placeholder="e.g. Database coursework, Web development project…" required id="purposeTA" <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>></textarea>
                    <div class="char-count"><span id="purposeCount">0</span> / 255</div>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label><span class="licon">📅</span> Date</label>
                    <input type="date" name="date"
                        min="<?= $min_date ?>"
                        max="<?= $max_date ?>"
                        required
                        <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>>
                </div>

                <!-- Time Slot -->
                <div class="form-group">
                    <label><span class="licon">⏰</span> Time Slot</label>
                    <div class="slot-grid">
                        <?php
                        $slots = [
                            '7:30 AM – 9:00 AM',
                            '9:00 AM – 10:30 AM',
                            '10:30 AM – 12:00 PM',
                            '1:00 PM – 2:30 PM',
                            '2:30 PM – 4:00 PM',
                            '4:00 PM – 5:30 PM',
                        ];
                        foreach ($slots as $slot):
                            $sid = 'slot_' . preg_replace('/[^a-z0-9]/', '_', strtolower($slot));
                        ?>
                        <input type="radio" name="time_slot" id="<?= $sid ?>" value="<?= htmlspecialchars($slot) ?>"
                            class="slot-option" required <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>>
                        <label for="<?= $sid ?>" class="slot-label">⏱ <?= htmlspecialchars($slot) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>>
                    📅 Submit Reservation
                </button>
            </form>
        </div>

        <!-- RIGHT: MY RESERVATIONS -->
        <div class="card">
            <div class="card-header">
                <div class="hicon">📋</div>
                My Reservations
            </div>

            <div class="table-wrap">
                <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>No reservations yet</h3>
                    <p>Submit a reservation using the form and it will appear here.</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Laboratory</th>
                            <th>Time Slot</th>
                            <th>Purpose</th>
                            <th class="center">Status</th>
                            <th class="center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res):
                            $d = $res['date'];
                            $date_fmt = $d ? date('M j, Y', strtotime($d)) : '—';
                            $day_fmt  = $d ? date('D', strtotime($d)) : '';
                            $status   = $res['status'];
                        ?>
                        <tr>
                            <td>
                                <div class="td-date"><?= htmlspecialchars($date_fmt) ?></div>
                                <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                            </td>
                            <td><span class="lab-tag">🏛️ <?= htmlspecialchars($res['lab']) ?></span></td>
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
                            <td class="center">
                                <?php if ($status === 'pending'): ?>
                                <button class="btn-cancel" onclick="openCancelModal(<?= (int)$res['id'] ?>)">
                                    ✕ Cancel
                                </button>
                                <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /main-grid -->
</div><!-- /page-wrap -->

<script>
    // ── Dynamically load labs from admin configuration ──
    // Only labs where the admin has set pc_status_set=1 AND has at least 1 available PC
    // will be returned by the endpoint. Students cannot see or select any other lab.
    async function loadConfiguredLabs() {
        const sel = document.getElementById('labSelect');
        const msg = document.getElementById('labLoadMsg');
        const submitBtn = document.getElementById('submitBtn');
        if (!sel) return;

        try {
            const res = await fetch('/SYSARCH/admin/admin_reservation.php?ajax=get_configured_labs');
            const data = await res.json();

            sel.innerHTML = '<option value="" disabled selected>Select a laboratory…</option>';

            if (!data.success || !data.labs || data.labs.length === 0) {
                sel.innerHTML = '<option value="" disabled selected>No labs available for reservation</option>';
                sel.disabled = true;
                msg.textContent = 'No laboratories are currently open for reservation. Please check back later or contact the admin.';
                msg.className = 'lab-load-msg warning';
                msg.style.display = 'block';
                // Disable submit if no sessions were already blocking it
                if (submitBtn && !submitBtn.disabled) submitBtn.disabled = true;
                return;
            }

            data.labs.forEach(lab => {
                const opt = document.createElement('option');
                opt.value = lab.name;
                opt.textContent = lab.name + '  (' + lab.available + ' PC' + (lab.available !== 1 ? 's' : '') + ' available)';
                sel.appendChild(opt);
            });

            msg.style.display = 'none';

        } catch (e) {
            sel.innerHTML = '<option value="" disabled selected>Failed to load labs — please refresh</option>';
            sel.disabled = true;
            msg.textContent = 'Could not load lab list. Please refresh the page.';
            msg.className = 'lab-load-msg error';
            msg.style.display = 'block';
        }
    }

    // Only fetch labs if the student has sessions remaining
    <?php if ($sessions_remaining > 0): ?>
    loadConfiguredLabs();
    <?php endif; ?>

    // Character counter for purpose textarea
    const ta = document.getElementById('purposeTA');
    const cnt = document.getElementById('purposeCount');
    if (ta && cnt) {
        ta.addEventListener('input', function() {
            cnt.textContent = ta.value.length;
            cnt.style.color = ta.value.length > 220 ? '#e53535' : '';
        });
    }

    // Cancel modal
    function openCancelModal(id) {
        document.getElementById('cancelResId').value = id;
        document.getElementById('cancelModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('cancelModal').classList.remove('open');
    }

    // Close modal on overlay click
    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function() { el.style.display = 'none'; }, 400);
        }, 5000);
    });

    // Block past dates on the date input (extra client-side guard)
    const dateInput = document.querySelector('input[type="date"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const chosen = new Date(this.value);
            const today  = new Date();
            today.setHours(0, 0, 0, 0);
            if (chosen <= today) {
                this.setCustomValidity('Please choose a future date.');
            } else {
                this.setCustomValidity('');
            }
        });
    }
</script>

</body>
</html>