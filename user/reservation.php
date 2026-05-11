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

$conn->query("
    CREATE TABLE IF NOT EXISTS reservations (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50) NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        pc_number    VARCHAR(20) DEFAULT NULL,
        purpose      VARCHAR(255) NOT NULL,
        date         DATE NOT NULL,
        time_slot    VARCHAR(50) NOT NULL,
        status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$col_check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'pc_number'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN pc_number VARCHAR(20) DEFAULT NULL AFTER lab");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'reserve') {
        $lab       = trim($_POST['lab'] ?? '');
        $date      = trim($_POST['date'] ?? '');
        $time_slot = trim($_POST['time_slot'] ?? '');
        $pc_number = trim($_POST['pc_number'] ?? '');

        $purpose_sel    = trim($_POST['purpose'] ?? '');
        $purpose_custom = trim($_POST['purpose_custom'] ?? '');
        $purpose = ($purpose_sel === 'other') ? $purpose_custom : $purpose_sel;

        if ($lab && $purpose && $date && $time_slot && $pc_number) {
            $srow = $conn->query("SELECT sessions FROM student WHERE IdNumber = '" . $conn->real_escape_string($student_id) . "'")->fetch_assoc();
            if (!$srow || (int)$srow['sessions'] <= 0) {
                $error_msg = 'You have no sessions remaining. Cannot make a reservation.';
            } else {
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

                    $pc_check = $conn->prepare("
                        SELECT COUNT(*) FROM lab_pc_status
                        WHERE lab = ? AND pc_number = ? AND status = 'unavailable'
                    ");
                    $pc_check->bind_param('ss', $lab, $pc_number);
                    $pc_check->execute();
                    $pc_check->bind_result($pc_broken);
                    $pc_check->fetch();
                    $pc_check->close();

                    if ((int)$pc_broken > 0) {
                        $error_msg = 'That PC is marked unavailable by admin. Please choose another.';
                    } else {
                        $pc_slot_chk = $conn->prepare("
                            SELECT id FROM reservations
                            WHERE lab = ? AND pc_number = ? AND date = ? AND time_slot = ?
                              AND status IN ('pending','approved')
                        ");
                        $pc_slot_chk->bind_param('ssss', $lab, $pc_number, $date, $time_slot);
                        $pc_slot_chk->execute();
                        $pc_slot_chk->store_result();
                        $pc_slot_taken = $pc_slot_chk->num_rows > 0;
                        $pc_slot_chk->close();

                        if ($pc_slot_taken) {
                            $error_msg = 'That PC is already reserved for that date and time slot. Please choose another PC or time slot.';
                        } else {
                            $chk = $conn->prepare("
                                SELECT id FROM reservations
                                WHERE student_id = ? AND date = ? AND time_slot = ?
                                  AND status IN ('pending','approved')
                            ");
                            $chk->bind_param('sss', $student_id, $date, $time_slot);
                            $chk->execute();
                            $chk->store_result();
                            if ($chk->num_rows > 0) {
                                $error_msg = 'You already have a pending or approved reservation for that date and time slot.';
                            } else {
                                $ins = $conn->prepare("INSERT INTO reservations (student_id, student_name, lab, pc_number, purpose, date, time_slot) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $ins->bind_param('sssssss', $student_id, $student_name, $lab, $pc_number, $purpose, $date, $time_slot);
                                if ($ins->execute()) {
                                    $success_msg = 'Reservation submitted! Waiting for admin approval.';
                                } else {
                                    $error_msg = 'Failed to submit reservation. Please try again.';
                                }
                                $ins->close();
                            }
                            $chk->close();
                        }
                    }
                }
            }
        } else {
            $error_msg = !$pc_number ? 'Please select a PC from the grid.' : 'Please fill in all fields.';
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

$reservations = [];
$rres = $conn->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
$rres->bind_param('s', $student_id);
$rres->execute();
$rr = $rres->get_result();
while ($row = $rr->fetch_assoc()) {
    $reservations[] = $row;
}
$rres->close();

$total_res    = count($reservations);
$pending_res  = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));
$approved_res = count(array_filter($reservations, fn($r) => $r['status'] === 'approved'));

$sessions_remaining = 0;
$sr = $conn->prepare("SELECT sessions FROM student WHERE IdNumber = ?");
$sr->bind_param('s', $student_id);
$sr->execute();
$srrow = $sr->get_result()->fetch_assoc();
if ($srrow) $sessions_remaining = (int)$srrow['sessions'];
$sr->close();

$unread_count = 0;
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) DEFAULT NULL,
        type       VARCHAR(30) DEFAULT 'announcement',
        subtype    VARCHAR(30) DEFAULT NULL,
        title      VARCHAR(255),
        message    TEXT,
        is_read    TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");
$nstmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0");
$nstmt->bind_param('s', $student_id);
$nstmt->execute();
$nstmt->bind_result($unread_count);
$nstmt->fetch();
$nstmt->close();

$min_date = date('Y-m-d', strtotime('+1 day'));
$max_date = date('Y-m-d', strtotime('+30 days'));

$conn->close();

$purpose_options = [
    'C Programming'                => 'C Programming',
    'Java Programming'             => 'Java Programming',
    'Python Programming'           => 'Python Programming',
    'Web Development'              => 'Web Development',
    'Database (SQL / MySQL)'       => 'Database (SQL / MySQL)',
    'Data Structures'              => 'Data Structures',
    'Algorithms & Problem Solving' => 'Algorithms & Problem Solving',
    'Networking Lab'               => 'Networking Lab',
    'Operating Systems'            => 'Operating Systems',
    'Software Engineering'         => 'Software Engineering',
    'Thesis / Capstone Project'    => 'Thesis / Capstone Project',
    'Research & Study'             => 'Research & Study',
    'Online Class / E-learning'    => 'Online Class / E-learning',
    'other'                        => '✏️ Other (specify)…',
];
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
            height: 60px; position: sticky; top: 0; z-index: 300;
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

        .notif-wrapper { position: relative; display: flex; align-items: center; }

        .notif-btn {
            position: relative; background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.22); cursor: pointer;
            color: white; font-size: 13px; font-weight: 600;
            padding: 6px 14px 6px 11px; border-radius: 8px;
            display: flex; align-items: center; gap: 7px;
            font-family: 'DM Sans', sans-serif; letter-spacing: 0.2px;
            transition: background 0.18s, border-color 0.18s;
        }
        .notif-btn:hover  { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.4); }
        .notif-btn.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.5); }

        .notif-bell { font-size: 15px; line-height: 1; }

        .notif-badge {
            background: #e53535; color: white;
            font-size: 9px; font-weight: 800;
            min-width: 17px; height: 17px; border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid var(--navy-mid); line-height: 1;
            margin-left: 2px;
        }
        .notif-badge.hidden { display: none; }

        .notif-dropdown {
            display: none;
            position: absolute; top: calc(100% + 10px); right: 0;
            width: 360px; background: var(--panel);
            border-radius: 10px;
            box-shadow: 0 8px 40px rgba(15,38,83,0.22), 0 2px 8px rgba(15,38,83,0.10);
            border: 1px solid var(--border); z-index: 400; overflow: hidden;
            animation: dropIn 0.18s ease both;
        }
        .notif-dropdown.open { display: block; }

        @keyframes dropIn {
            from { opacity: 0; transform: translateY(-8px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .notif-dropdown::before {
            content: ''; position: absolute;
            top: -7px; right: 22px;
            width: 13px; height: 13px;
            background: var(--panel);
            border-left: 1px solid var(--border); border-top: 1px solid var(--border);
            transform: rotate(45deg); z-index: 1;
        }

        .notif-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 13px 16px 11px; border-bottom: 1px solid var(--border);
            background: #f7f9fd;
        }
        .notif-dropdown-header .notif-hd-left { display: flex; align-items: center; gap: 8px; }
        .notif-dropdown-header .notif-hd-icon { font-size: 16px; }
        .notif-dropdown-header h4 {
            font-size: 12px; font-weight: 800; color: var(--navy);
            text-transform: uppercase; letter-spacing: 0.8px;
        }
        .btn-mark-all-read {
            font-size: 11.5px; font-weight: 600; color: var(--accent);
            background: none; border: 1px solid #c8d8f5; border-radius: 6px;
            padding: 4px 10px; cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, color 0.15s;
        }
        .btn-mark-all-read:hover { background: var(--tag-bg); }

        .notif-tabs { display: flex; border-bottom: 1px solid var(--border); background: white; }
        .notif-tab-btn {
            flex: 1; padding: 8px 0;
            font-size: 11.5px; font-weight: 700; color: var(--muted);
            background: none; border: none; border-bottom: 2px solid transparent;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            text-transform: uppercase; letter-spacing: 0.5px;
            transition: color 0.15s, border-color 0.15s;
        }
        .notif-tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }

        .notif-scroll { max-height: 320px; overflow-y: auto; }
        .notif-scroll::-webkit-scrollbar { width: 4px; }
        .notif-scroll::-webkit-scrollbar-track { background: transparent; }
        .notif-scroll::-webkit-scrollbar-thumb { background: #c8d4ee; border-radius: 4px; }

        .notif-item {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 11px 16px; border-bottom: 1px solid #f2f4fb;
            cursor: pointer; transition: background 0.13s; position: relative;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f5f7fd; }
        .notif-item.unread { background: #f0f5ff; }
        .notif-item.unread:hover { background: #e6eeff; }

        .notif-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0; font-weight: 700; color: white;
        }
        .notif-avatar.type-accepted     { background: linear-gradient(135deg,#0fa86a,#1cb87e); }
        .notif-avatar.type-rejected     { background: linear-gradient(135deg,#c0392b,#e53535); }
        .notif-avatar.type-announcement { background: linear-gradient(135deg,#e67e00,#f0a500); }
        .notif-avatar.type-default      { background: linear-gradient(135deg,var(--navy-light),var(--accent)); }

        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title {
            font-size: 13px; font-weight: 700; color: var(--navy);
            line-height: 1.3; margin-bottom: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .notif-item-msg {
            font-size: 12px; color: var(--muted); line-height: 1.45;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }
        .notif-item-time { font-size: 11px; color: #a0aec0; margin-top: 4px; font-weight: 500; }

        .notif-unread-dot {
            width: 8px; height: 8px; background: var(--accent);
            border-radius: 50%; flex-shrink: 0; margin-top: 4px;
        }

        .notif-empty-state { text-align: center; padding: 38px 20px; color: var(--muted); }
        .notif-empty-state .nei { font-size: 32px; opacity: 0.3; margin-bottom: 8px; }
        .notif-empty-state p { font-size: 12.5px; }

        .notif-loading-state { text-align: center; padding: 30px; font-size: 12.5px; color: var(--muted); }

        .page-wrap { padding: 24px 28px; max-width: 1200px; margin: 0 auto; }

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

        .main-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 20px;
            align-items: start;
        }

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

        .alert {
            margin: 16px 20px 0;
            padding: 12px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
        }

        .alert.success { background: var(--green-bg); color: #0a7a52; border: 1px solid #9ee3ca; }
        .alert.error   { background: var(--red-bg);   color: #a01010; border: 1px solid #f5b8b8; }
        .alert .alert-icon { font-size: 16px; flex-shrink: 0; }

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
        .form-group input[type="text"],
        .form-group input[type="hidden"],
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
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
        }

        .pc-picker-wrap {
            display: none;
            flex-direction: column;
            gap: 8px;
            animation: slideDown 0.22s ease;
        }
        .pc-picker-wrap.visible { display: flex; }

        .btn-pick-pc {
            display: flex; align-items: center; justify-content: center; gap: 9px;
            padding: 11px 16px; border-radius: 11px;
            border: 2px dashed var(--border);
            background: #fafbfd; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px; font-weight: 700; color: var(--muted);
            text-align: center; line-height: 1.3;
            transition: all 0.18s; width: 100%;
        }

        .btn-pick-pc:hover {
            border-color: var(--accent); color: var(--accent);
            background: #f0f4ff; border-style: solid;
        }

        .btn-pick-pc.has-selection {
            border-style: solid; border-color: var(--accent);
            background: #dce8ff; color: var(--accent);
        }

        .pc-required-hint {
            font-size: 11.5px; color: var(--red);
            display: none; margin-top: 2px;
        }

        .pc-required-hint.visible { display: block; }

        .pc-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,38,83,0.5); z-index: 600;
            align-items: center; justify-content: center;
            padding: 20px;
        }

        .pc-modal-overlay.open { display: flex; }

        .pc-modal {
            background: white; border-radius: 20px;
            width: 100%; max-width: 560px;
            box-shadow: 0 20px 60px rgba(15,38,83,0.35);
            animation: modalIn 0.22s ease;
            overflow: hidden;
            display: flex; flex-direction: column;
            max-height: 90vh;
        }

        .pc-modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0;
        }

        .pc-modal-header-left { display: flex; align-items: center; gap: 10px; }
        .pc-modal-header-left .hicon {
            width: 32px; height: 32px; background: rgba(255,255,255,0.18);
            border-radius: 9px; display: flex; align-items: center;
            justify-content: center; font-size: 16px;
        }

        .pc-modal-header-left h2 {
            font-size: 14px; font-weight: 700; letter-spacing: 0.4px; line-height: 1.2; margin: 0;
        }

        .pc-modal-header-left p {
            font-size: 11.5px; color: rgba(255,255,255,0.7); margin-top: 2px;
        }

        .pc-modal-close-btn {
            background: rgba(255,255,255,0.15); border: none; cursor: pointer;
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: white; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s; flex-shrink: 0;
        }

        .pc-modal-close-btn:hover { background: rgba(255,255,255,0.28); }

        .pc-modal-body { padding: 20px 22px; overflow-y: auto; flex: 1; }

        .pc-legend {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
        }

        .pc-legend-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 600; color: var(--muted);
        }

        .pc-legend-dot { width: 10px; height: 10px; border-radius: 3px; }
        .pc-legend-dot.available { background: var(--green); }
        .pc-legend-dot.taken     { background: #ccc; }
        .pc-legend-dot.selected  { background: var(--accent); }

        .pc-slot-note {
            font-size: 11.5px; color: var(--muted);
            background: var(--tag-bg); border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; margin-bottom: 14px;
            display: flex; align-items: center; gap: 7px;
        }
        .pc-slot-note.has-slot { color: #0a7a52; background: var(--green-bg); border-color: #9ee3ca; }

        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
            gap: 8px;
        }

        .pc-btn {
            position: relative;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; padding: 12px 4px 9px; border-radius: 11px;
            border: 2px solid var(--border); background: #fafbfd;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: all 0.15s; min-height: 68px;
        }

        .pc-btn .pc-icon { font-size: 20px; line-height: 1; }
        .pc-btn .pc-num  { font-size: 11px; font-weight: 700; color: var(--muted); }

        .pc-btn.available { border-color: #b6e8d4; background: var(--green-bg); }
        .pc-btn.available .pc-num { color: #0a7a52; }
        .pc-btn.available:hover {
            border-color: var(--green); background: #d0f3e8;
            transform: translateY(-2px); box-shadow: 0 4px 10px rgba(28,184,126,0.2);
        }

        .pc-btn.taken {
            border-color: #e0e0e0; background: #f5f5f5;
            cursor: not-allowed; opacity: 0.55;
        }
        .pc-btn.taken .pc-icon { filter: grayscale(1); }
        .pc-btn.taken .pc-num  { color: #aaa; }

        .pc-btn.selected {
            border-color: var(--accent) !important;
            background: #dce8ff !important;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.2), 0 4px 12px rgba(36,82,160,0.2);
            transform: translateY(-2px);
        }
        .pc-btn.selected .pc-num { color: var(--accent); }
        .pc-btn.selected::after {
            content: '✓'; position: absolute; top: 5px; right: 6px;
            font-size: 9px; font-weight: 900; color: var(--accent); background: white;
            width: 15px; height: 15px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 1px 4px rgba(59,111,212,0.3);
        }

        .pc-loading {
            text-align: center; padding: 36px 24px;
            color: var(--muted); font-size: 13px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .spin {
            width: 16px; height: 16px; border-radius: 50%;
            border: 2px solid var(--border); border-top-color: var(--accent);
            animation: spin 0.7s linear infinite; display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .pc-modal-footer {
            padding: 14px 22px 18px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
            flex-shrink: 0;
        }

        .pc-modal-selection-label {
            flex: 1; font-size: 13px; font-weight: 600; color: var(--muted);
        }
        .pc-modal-selection-label.chosen { color: var(--accent); }

        .btn-confirm-pc {
            padding: 10px 22px; border-radius: 10px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            color: white; border: none;
            font-size: 13.5px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; box-shadow: 0 3px 12px rgba(36,82,160,0.3);
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
        }
        .btn-confirm-pc:hover { transform: translateY(-1px); box-shadow: 0 5px 18px rgba(36,82,160,0.4); }
        .btn-confirm-pc:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

        .purpose-custom-wrap {
            display: none; flex-direction: column; gap: 5px;
            animation: slideDown 0.18s ease;
        }
        .purpose-custom-wrap.visible { display: flex; }

        .purpose-custom-wrap input {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.10);
        }

        .purpose-custom-wrap .custom-hint {
            font-size: 11px; color: var(--muted);
            display: flex; align-items: center; justify-content: space-between;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .char-count { font-size: 11px; color: var(--muted); text-align: right; }

        .lab-load-msg { font-size: 11.5px; margin-top: 4px; display: none; }
        .lab-load-msg.warning { color: var(--orange); }
        .lab-load-msg.error   { color: var(--red); }

        .slot-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
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

        .sessions-info {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px;
            background: var(--tag-bg); border: 1px solid var(--border);
            font-size: 12.5px; color: var(--navy); font-weight: 600;
        }

        .sessions-info.low { background: var(--red-bg); border-color: #f5b8b8; color: var(--red); }
        .sessions-info .si-num { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--navy); }
        .sessions-info.low .si-num { color: var(--red); }

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

        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }

        thead tr { background: var(--tag-bg); border-bottom: 2px solid var(--border); }

        thead th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 700;
            color: var(--navy); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
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
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee; white-space: nowrap;
        }

        .pc-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: #dce8ff; color: var(--accent);
            padding: 3px 9px; border-radius: 20px;
            font-size: 11.5px; font-weight: 700; border: 1px solid #b8ccf5;
            white-space: nowrap; margin-top: 4px;
        }

        .slot-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: #f0f3f9; color: var(--text);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; border: 1px solid var(--border); white-space: nowrap;
        }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap;
        }

        .status-pill.pending   { background: #fff8e1; color: #c07000; }
        .status-pill.approved  { background: var(--green-bg); color: var(--green); }
        .status-pill.rejected  { background: var(--red-bg); color: var(--red); }
        .status-pill.cancelled { background: #f0f3f9; color: var(--muted); }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .btn-cancel {
            padding: 5px 12px; border-radius: 7px;
            background: var(--red-bg); border: 1px solid #f5b8b8;
            color: var(--red); font-size: 11.5px; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-cancel:hover { background: #fcd5d5; border-color: #e08080; }

        .empty-state { text-align: center; padding: 50px 24px; color: var(--muted); }
        .empty-state .empty-icon { font-size: 44px; margin-bottom: 12px; opacity: 0.4; }
        .empty-state h3 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
        .empty-state p  { font-size: 13px; }

        .td-date { font-weight: 700; color: var(--navy); font-size: 13px; white-space: nowrap; }
        .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

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

        .btn-confirm-cancel { background: var(--red); color: white; }
        .btn-modal-close    { background: var(--tag-bg); color: var(--navy); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-wrap { padding: 16px; }
            .notif-dropdown { width: calc(100vw - 20px); right: -10px; }
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

        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotifDropdown()">
                <span class="notif-bell">🔔</span>
                Notification
                <span class="notif-badge <?= $unread_count === 0 ? 'hidden' : '' ?>" id="notifBadge">
                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                </span>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dropdown-header">
                    <div class="notif-hd-left">
                        <span class="notif-hd-icon">🔔</span>
                        <h4>Notifications</h4>
                    </div>
                    <button class="btn-mark-all-read" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-tabs">
                    <button class="notif-tab-btn active" id="ntab-all"          onclick="switchNotifTab('all')">All</button>
                    <button class="notif-tab-btn"         id="ntab-reservation" onclick="switchNotifTab('reservation')">Reservations</button>
                    <button class="notif-tab-btn"         id="ntab-announce"    onclick="switchNotifTab('announce')">Announcements</button>
                </div>
                <div class="notif-scroll" id="notifScroll">
                    <div class="notif-loading-state">Loading…</div>
                </div>
            </div>
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

<!-- PC PICKER MODAL -->
<div class="pc-modal-overlay" id="pcModal">
    <div class="pc-modal">
        <div class="pc-modal-header">
            <div class="pc-modal-header-left">
                <div class="hicon">🖥️</div>
                <div>
                    <h2>Select a PC</h2>
                    <p id="pcModalLabName">Choose an available PC from the grid</p>
                </div>
            </div>
            <button class="pc-modal-close-btn" onclick="closePcModal()">✕</button>
        </div>
        <div class="pc-modal-body">
            <div class="pc-legend">
                <div class="pc-legend-item"><div class="pc-legend-dot available"></div> Available for this slot</div>
                <div class="pc-legend-item"><div class="pc-legend-dot taken"></div> Taken / Unavailable</div>
                <div class="pc-legend-item"><div class="pc-legend-dot selected"></div> Your Pick</div>
            </div>
            <div class="pc-slot-note" id="pcSlotNote">
                📅 Select a date and time slot first for accurate availability.
            </div>
            <div id="pcGrid" class="pc-grid">
                <div class="pc-loading"><span class="spin"></span> Loading PCs…</div>
            </div>
        </div>
        <div class="pc-modal-footer">
            <div class="pc-modal-selection-label" id="pcModalSelLabel">No PC selected yet</div>
            <button class="btn-confirm-pc" id="btnConfirmPc" disabled onclick="confirmPcSelection()">
                ✓ Confirm Selection
            </button>
        </div>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrap">

    <div class="page-header">
        <div class="page-header-icon">📅</div>
        <div class="page-header-text">
            <h1>Lab Reservation</h1>
            <p>Book a laboratory session in advance</p>
        </div>
    </div>

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

            <form method="POST" class="form-body" onsubmit="return validateReservationForm()">
                <input type="hidden" name="action" value="reserve">

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

                <div class="form-group">
                    <label><span class="licon">📅</span> Date</label>
                    <input type="date" name="date" id="dateInput"
                        min="<?= $min_date ?>"
                        max="<?= $max_date ?>"
                        required
                        <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>
                        onchange="onDateOrSlotChange()">
                </div>

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
                            '5:30 PM – 7:00 PM'
                        ];
                        foreach ($slots as $slot):
                            $sid = 'slot_' . preg_replace('/[^a-z0-9]/', '_', strtolower($slot));
                        ?>
                        <input type="radio" name="time_slot" id="<?= $sid ?>" value="<?= htmlspecialchars($slot) ?>"
                            class="slot-option" required <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>
                            onchange="onDateOrSlotChange()">
                        <label for="<?= $sid ?>" class="slot-label">⏱ <?= htmlspecialchars($slot) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label><span class="licon">🏛️</span> Laboratory</label>
                    <select name="lab" id="labSelect" required <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>
                        onchange="handleLabChange(this.value)">
                        <option value="" disabled selected>Pick a date & time slot first…</option>
                    </select>
                    <div class="lab-load-msg" id="labLoadMsg"></div>
                </div>

                <div class="form-group">
                    <div class="pc-picker-wrap" id="pcPickerWrap">
                        <label><span class="licon">🖥️</span> PC Selection</label>
                        <button type="button" class="btn-pick-pc" id="btnPickPc" onclick="openPcModal()">
                            🖥️ Click to choose a PC
                        </button>
                        <input type="hidden" name="pc_number" id="pcNumberInput">
                        <div class="pc-required-hint" id="pcRequiredHint">⚠️ Please select a PC before submitting.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label><span class="licon">📌</span> Purpose</label>
                    <select name="purpose" id="purposeSelect" required
                        <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>
                        onchange="handlePurposeChange(this)">
                        <option value="" disabled selected>— Select a purpose —</option>
                        <?php foreach ($purpose_options as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="purpose-custom-wrap" id="purposeCustomWrap">
                        <input type="text"
                            name="purpose_custom"
                            id="purposeCustom"
                            maxlength="255"
                            placeholder="Describe your purpose…"
                            <?= $sessions_remaining <= 0 ? 'disabled' : '' ?>
                            oninput="updateCustomCount()">
                        <div class="custom-hint">
                            <span style="font-size:11px;color:var(--muted);">Briefly describe your purpose</span>
                            <span class="char-count"><span id="customCount">0</span> / 255</span>
                        </div>
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
                            <th>Laboratory / PC</th>
                            <th>Time Slot</th>
                            <th>Purpose</th>
                            <th class="center">Status</th>
                            <th class="center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res):
                            $d        = $res['date'];
                            $date_fmt = $d ? date('M j, Y', strtotime($d)) : '—';
                            $day_fmt  = $d ? date('D', strtotime($d)) : '';
                            $status   = $res['status'];
                            $pc_num   = $res['pc_number'] ?? null;
                        ?>
                        <tr>
                            <td>
                                <div class="td-date"><?= htmlspecialchars($date_fmt) ?></div>
                                <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                            </td>
                            <td>
                                <span class="lab-tag">🏛️ <?= htmlspecialchars($res['lab']) ?></span>
                                <?php if ($pc_num): ?>
                                <br><span class="pc-tag">🖥️ PC <?= htmlspecialchars($pc_num) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="slot-tag">⏱ <?= htmlspecialchars($res['time_slot']) ?></span></td>
                            <td style="max-width:160px;">
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

    </div>
</div>

<script>
let _pendingPcNumber = '';

function getSelectedDate() {
    return document.getElementById('dateInput')?.value || '';
}

function getSelectedSlot() {
    const checked = document.querySelector('.slot-option:checked');
    return checked ? checked.value : '';
}

function onDateOrSlotChange() {
    const date = getSelectedDate();
    const slot = getSelectedSlot();
    resetPcSelection();
    if (date && slot) {
        loadConfiguredLabs(date, slot);
    } else {
        const sel = document.getElementById('labSelect');
        sel.innerHTML = '<option value="" disabled selected>Pick a date & time slot first…</option>';
        document.getElementById('pcPickerWrap').classList.remove('visible');
    }
}

function resetPcSelection() {
    document.getElementById('pcNumberInput').value = '';
    _pendingPcNumber = '';
    const btn  = document.getElementById('btnPickPc');
    const hint = document.getElementById('pcRequiredHint');
    if (btn)  { btn.className = 'btn-pick-pc'; btn.textContent = '🖥️ Click to choose a PC'; }
    if (hint) hint.classList.remove('visible');
    document.getElementById('pcPickerWrap').classList.remove('visible');
}

function handleLabChange(labName) {
    resetPcSelection();
    if (!labName) return;
    document.getElementById('pcPickerWrap').classList.add('visible');
}

async function loadConfiguredLabs(date, slot) {
    const sel       = document.getElementById('labSelect');
    const msg       = document.getElementById('labLoadMsg');
    const submitBtn = document.getElementById('submitBtn');
    if (!sel) return;

    sel.innerHTML = '<option value="" disabled selected>Loading available labs…</option>';

    try {
        const params = new URLSearchParams({ ajax: 'get_configured_labs' });
        if (date) params.set('date', date);
        if (slot) params.set('time_slot', slot);

        // ── FIXED: use student-side endpoint (no admin session required) ──
        const res  = await fetch('/SYSARCH/user/get_lab_data.php?' + params.toString());
        const data = await res.json();

        sel.innerHTML = '<option value="" disabled selected>Select a laboratory…</option>';

        if (!data.success || !data.labs || data.labs.length === 0) {
            sel.innerHTML = '<option value="" disabled selected>No labs available for this slot</option>';
            sel.disabled  = true;
            msg.textContent = 'No laboratories have available PCs for this date and time slot.';
            msg.className   = 'lab-load-msg warning';
            msg.style.display = 'block';
            if (submitBtn && !submitBtn.disabled) submitBtn.disabled = true;
            return;
        }

        sel.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
        msg.style.display = 'none';

        data.labs.forEach(lab => {
            const opt = document.createElement('option');
            opt.value = lab.name;
            opt.textContent = lab.name + '  (' + lab.available + ' PC' + (lab.available !== 1 ? 's' : '') + ' available)';
            sel.appendChild(opt);
        });

    } catch (e) {
        sel.innerHTML = '<option value="" disabled selected>Failed to load labs — please refresh</option>';
        sel.disabled  = true;
        msg.textContent = 'Could not load lab list. Please refresh the page.';
        msg.className   = 'lab-load-msg error';
        msg.style.display = 'block';
    }
}

async function loadPcGrid(labName) {
    const pcGrid = document.getElementById('pcGrid');
    pcGrid.innerHTML = '<div class="pc-loading"><span class="spin"></span> Loading PCs…</div>';

    const date = getSelectedDate();
    const slot = getSelectedSlot();

    const noteEl = document.getElementById('pcSlotNote');
    if (date && slot) {
        noteEl.textContent = '📅 Showing availability for ' + date + ' · ' + slot;
        noteEl.className = 'pc-slot-note has-slot';
    } else {
        noteEl.textContent = '⚠️ No date/slot selected — showing general availability only.';
        noteEl.className = 'pc-slot-note';
    }

    try {
        const params = new URLSearchParams({ ajax: 'get_available_pcs', lab: labName });
        if (date) params.set('date', date);
        if (slot) params.set('time_slot', slot);

        // ── FIXED: use student-side endpoint ──
        const res  = await fetch('/SYSARCH/user/get_lab_data.php?' + params.toString());
        const data = await res.json();

        if (!data.success) {
            pcGrid.innerHTML = '<div class="pc-loading" style="color:var(--orange);">⚠️ ' + (data.error || 'Lab not available.') + '</div>';
            return;
        }

        const freeForSlot = new Set((data.available_pcs || []).map(Number));

        // ── FIXED: use student-side endpoint ──
        const statusRes  = await fetch('/SYSARCH/user/get_lab_data.php?ajax=get_pc_status&lab=' + encodeURIComponent(labName));
        const statusData = await statusRes.json();

        if (!statusData.success || !statusData.pcs || statusData.pcs.length === 0) {
            pcGrid.innerHTML = '<div class="pc-loading" style="color:var(--orange);">⚠️ No PC data found for this lab.</div>';
            return;
        }

        pcGrid.innerHTML = '';
        statusData.pcs.forEach(pc => {
            const adminBroken = pc.status === 'unavailable';
            const isAvailable = freeForSlot.has(pc.pc);

            const btn = document.createElement('button');
            btn.type       = 'button';
            btn.className  = 'pc-btn ' + (isAvailable ? 'available' : 'taken');
            btn.dataset.pc = pc.pc;
            btn.disabled   = !isAvailable;

            let icon  = isAvailable ? '🖥️' : (adminBroken ? '🚫' : '📅');
            let title = isAvailable ? 'Available' : (adminBroken ? 'Unavailable (admin)' : 'Booked for this slot');
            btn.title   = 'PC ' + pc.pc + ' — ' + title;
            btn.innerHTML = `<span class="pc-icon">${icon}</span><span class="pc-num">PC ${pc.pc}</span>`;

            if (isAvailable) {
                btn.addEventListener('click', () => selectPcInModal(pc.pc, btn));
            }
            pcGrid.appendChild(btn);
        });

        const committed = document.getElementById('pcNumberInput').value;
        if (committed) {
            const existing = pcGrid.querySelector(`[data-pc="${committed}"]`);
            if (existing && existing.classList.contains('available')) {
                existing.classList.add('selected');
                _pendingPcNumber = committed;
                updateModalFooter(committed);
            } else {
                resetPcSelection();
            }
        }

    } catch (e) {
        pcGrid.innerHTML = '<div class="pc-loading" style="color:var(--red);">⚠️ Failed to load PCs. Please refresh.</div>';
    }
}

function selectPcInModal(pcNumber, btnEl) {
    document.querySelectorAll('#pcGrid .pc-btn.selected').forEach(b => b.classList.remove('selected'));
    btnEl.classList.add('selected');
    _pendingPcNumber = pcNumber;
    updateModalFooter(pcNumber);
}

function updateModalFooter(pcNumber) {
    const label   = document.getElementById('pcModalSelLabel');
    const confirm = document.getElementById('btnConfirmPc');
    label.textContent = '🖥️ PC ' + pcNumber + ' selected';
    label.classList.add('chosen');
    confirm.disabled = false;
}

function openPcModal() {
    const labName = document.getElementById('labSelect').value;
    if (!labName) return;
    document.getElementById('pcModalLabName').textContent = labName;
    document.getElementById('pcModal').classList.add('open');
    loadPcGrid(labName);
}

function closePcModal() {
    document.getElementById('pcModal').classList.remove('open');
    _pendingPcNumber = '';
    const label   = document.getElementById('pcModalSelLabel');
    const confirm = document.getElementById('btnConfirmPc');
    label.textContent = 'No PC selected yet';
    label.classList.remove('chosen');
    confirm.disabled = true;
}

function confirmPcSelection() {
    if (!_pendingPcNumber) return;
    document.getElementById('pcNumberInput').value = _pendingPcNumber;
    document.getElementById('pcRequiredHint').classList.remove('visible');
    const btn = document.getElementById('btnPickPc');
    btn.className   = 'btn-pick-pc has-selection';
    btn.textContent = '✓ PC ' + _pendingPcNumber + ' selected — click to change';
    document.getElementById('pcModal').classList.remove('open');
    _pendingPcNumber = '';
}

document.getElementById('pcModal').addEventListener('click', function(e) {
    if (e.target === this) closePcModal();
});

function handlePurposeChange(sel) {
    const wrap  = document.getElementById('purposeCustomWrap');
    const input = document.getElementById('purposeCustom');
    if (sel.value === 'other') {
        wrap.classList.add('visible'); input.required = true; input.focus();
    } else {
        wrap.classList.remove('visible'); input.required = false; input.value = '';
        document.getElementById('customCount').textContent = '0';
    }
}

function updateCustomCount() {
    const input = document.getElementById('purposeCustom');
    const cnt   = document.getElementById('customCount');
    if (input && cnt) {
        cnt.textContent = input.value.length;
        cnt.style.color = input.value.length > 220 ? '#e53535' : '';
    }
}

function validateReservationForm() {
    const pcInput = document.getElementById('pcNumberInput');
    const wrap    = document.getElementById('pcPickerWrap');
    const hint    = document.getElementById('pcRequiredHint');

    if (wrap.classList.contains('visible') && !pcInput.value) {
        hint.classList.add('visible');
        wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    const sel = document.getElementById('purposeSelect');
    if (sel.value === 'other') {
        const custom = document.getElementById('purposeCustom');
        if (!custom.value.trim()) {
            custom.style.borderColor = '#e53535';
            custom.focus();
            return false;
        }
        custom.style.borderColor = '';
        sel.name    = '';
        custom.name = 'purpose';
    }
    return true;
}

function openCancelModal(id) {
    document.getElementById('cancelResId').value = id;
    document.getElementById('cancelModal').classList.add('open');
}

function closeModal() {
    document.getElementById('cancelModal').classList.remove('open');
}

document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeModal(); closePcModal(); closeNotifDropdown(); }
});

document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity    = '0';
        setTimeout(function() { el.style.display = 'none'; }, 400);
    }, 5000);
});

// ── NOTIFICATION DROPDOWN ────────────────────────────────────────
let notifData   = [];
let notifTab    = 'all';
let notifLoaded = false;
let notifOpen   = false;

function toggleNotifDropdown() {
    notifOpen = !notifOpen;
    const dd  = document.getElementById('notifDropdown');
    const btn = document.getElementById('notifBtn');
    dd.classList.toggle('open', notifOpen);
    btn.classList.toggle('active', notifOpen);
    if (notifOpen && !notifLoaded) fetchNotifications();
}

function closeNotifDropdown() {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
    document.getElementById('notifBtn').classList.remove('active');
}

document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifWrapper');
    if (notifOpen && wrapper && !wrapper.contains(e.target)) closeNotifDropdown();
});

function switchNotifTab(tab) {
    notifTab = tab;
    ['all','reservation','announce'].forEach(function(t) {
        document.getElementById('ntab-' + t).classList.toggle('active', t === tab);
    });
    renderNotifItems();
}

function fetchNotifications() {
    document.getElementById('notifScroll').innerHTML = '<div class="notif-loading-state">Loading…</div>';
    fetch('/SYSARCH/user/get_notifications.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            notifLoaded = true;
            notifData   = data.notifications || [];
            renderNotifItems();
            refreshBadge();
        })
        .catch(function() {
            document.getElementById('notifScroll').innerHTML =
                '<div class="notif-empty-state"><div class="nei">⚠️</div><p>Could not load notifications.</p></div>';
        });
}

function renderNotifItems() {
    const scroll = document.getElementById('notifScroll');
    let items = notifData;
    if (notifTab === 'reservation') items = notifData.filter(function(n) { return n.type === 'reservation'; });
    if (notifTab === 'announce')    items = notifData.filter(function(n) { return n.type === 'announcement'; });

    if (items.length === 0) {
        scroll.innerHTML = '<div class="notif-empty-state"><div class="nei">🔕</div><p>No notifications here.</p></div>';
        return;
    }

    const avatarContent = { accepted: '✅', rejected: '❌', announcement: '📢' };
    const avatarClass   = { accepted: 'type-accepted', rejected: 'type-rejected', announcement: 'type-announcement' };

    scroll.innerHTML = items.map(function(n) {
        const sub    = n.subtype || (n.type === 'announcement' ? 'announcement' : 'default');
        const icon   = avatarContent[sub] || '🔔';
        const avCls  = avatarClass[sub]   || 'type-default';
        const unread = n.is_read ? '' : 'unread';
        const dot    = n.is_read ? '' : '<div class="notif-unread-dot"></div>';
        const timeStr = n.created_at ? timeAgo(n.created_at) : '';
        return '<div class="notif-item ' + unread + '" data-id="' + n.id + '" onclick="markOneRead(this,' + n.id + ')">'
             + '<div class="notif-avatar ' + avCls + '">' + icon + '</div>'
             + '<div class="notif-item-body">'
             + '<div class="notif-item-title">' + esc(n.title || 'Notification') + '</div>'
             + '<div class="notif-item-msg">'   + esc(n.message || '') + '</div>'
             + '<div class="notif-item-time">'  + timeStr + '</div>'
             + '</div>' + dot + '</div>';
    }).join('');
}

function markOneRead(el, id) {
    if (!el.classList.contains('unread')) return;
    el.classList.remove('unread');
    var dot = el.querySelector('.notif-unread-dot');
    if (dot) dot.remove();
    var item = notifData.find(function(n) { return n.id == id; });
    if (item) item.is_read = true;
    refreshBadge();
    fetch('/SYSARCH/user/mark_notification_read.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).catch(function(){});
}

function markAllRead() {
    notifData.forEach(function(n) { n.is_read = true; });
    renderNotifItems();
    refreshBadge();
    fetch('/SYSARCH/user/mark_notification_read.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ all: true })
    }).catch(function(){});
}

function refreshBadge() {
    var unread = notifData.filter(function(n) { return !n.is_read; }).length;
    var badge  = document.getElementById('notifBadge');
    badge.textContent = unread > 9 ? '9+' : unread;
    badge.classList.toggle('hidden', unread === 0);
}

function timeAgo(dt) {
    var diff = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>