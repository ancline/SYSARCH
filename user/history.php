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

$student_id = $_SESSION['student_id'];

// ── Fetch sit-in history ──
$history = [];
$stmt = $conn->prepare("
    SELECT s.id, s.lab, s.purpose, s.student_name, s.sessions,
           s.time_in, s.time_out,
           TIMESTAMPDIFF(MINUTE, s.time_in, COALESCE(s.time_out, NOW())) AS duration_min
    FROM sitin s
    WHERE s.student_id = ?
    ORDER BY s.time_in DESC
    LIMIT 50
");
if ($stmt) {
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

// ── Stats ──
$total_sessions    = count($history);
$completed_sessions = count(array_filter($history, fn($r) => !empty($r['time_out'])));
$total_minutes     = array_sum(array_column($history, 'duration_min'));
$total_hours       = round($total_minutes / 60, 1);
$labs_visited      = count(array_unique(array_filter(array_column($history, 'lab'))));

// ── Points: 1 point per 3 completed sit-ins ──
$earned_points     = (int)floor($completed_sessions / 3);
$progress_remainder = $completed_sessions % 3;   // how many toward next point
$needed_for_next   = 3 - $progress_remainder;    // how many more needed

// ── Feedback IDs ──
$feedback_ids = [];
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sitin_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");
if ($total_sessions > 0) {
    $sitin_ids    = array_column($history, 'id');
    $placeholders = implode(',', array_fill(0, count($sitin_ids), '?'));
    $types        = str_repeat('i', count($sitin_ids));
    $fstmt        = $conn->prepare("SELECT sitin_id FROM feedback WHERE student_id = ? AND sitin_id IN ($placeholders)");
    $bind_params  = array_merge([$student_id], $sitin_ids);
    $fstmt->bind_param('s' . $types, ...$bind_params);
    $fstmt->execute();
    $fres = $fstmt->get_result();
    while ($frow = $fres->fetch_assoc()) {
        $feedback_ids[] = (int)$frow['sitin_id'];
    }
    $fstmt->close();
}

// ── Unread notifications count ──
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History – CCS Sit-in Monitoring System</title>
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
        .nav-links a:hover  { background: rgba(255,255,255,0.12); color: white; }
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }
        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important; font-weight: 700 !important;
            border-radius: 8px !important; padding: 7px 18px !important;
            margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }
        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        /* ── NOTIFICATION BUTTON + DROPDOWN ── */
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
        .notif-btn:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.4); }
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
            display: none; position: absolute; top: calc(100% + 10px); right: 0;
            width: 360px; background: var(--panel); border-radius: 10px;
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
            content: ''; position: absolute; top: -7px; right: 22px;
            width: 13px; height: 13px; background: var(--panel);
            border-left: 1px solid var(--border); border-top: 1px solid var(--border);
            transform: rotate(45deg); z-index: 1;
        }
        .notif-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 13px 16px 11px; border-bottom: 1px solid var(--border); background: #f7f9fd;
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
            flex: 1; padding: 8px 0; font-size: 11.5px; font-weight: 700; color: var(--muted);
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
            display: flex; align-items: flex-start; gap: 11px; padding: 11px 16px;
            border-bottom: 1px solid #f2f4fb; cursor: pointer; transition: background 0.13s; position: relative;
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
        .notif-avatar.type-accepted    { background: linear-gradient(135deg,#0fa86a,#1cb87e); }
        .notif-avatar.type-rejected    { background: linear-gradient(135deg,#c0392b,#e53535); }
        .notif-avatar.type-announcement { background: linear-gradient(135deg,#e67e00,#f0a500); }
        .notif-avatar.type-default     { background: linear-gradient(135deg,var(--navy-light),var(--accent)); }
        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title { font-size: 13px; font-weight: 700; color: var(--navy); line-height: 1.3; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-item-msg { font-size: 12px; color: var(--muted); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-item-time { font-size: 11px; color: #a0aec0; margin-top: 4px; font-weight: 500; }
        .notif-unread-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
        .notif-empty-state { text-align: center; padding: 38px 20px; color: var(--muted); }
        .notif-empty-state .nei { font-size: 32px; opacity: 0.3; margin-bottom: 8px; }
        .notif-empty-state p { font-size: 12.5px; }
        .notif-loading-state { text-align: center; padding: 30px; font-size: 12.5px; color: var(--muted); }

        /* ── PAGE WRAP ── */
        .page-wrap { padding: 24px 28px; max-width: 1300px; margin: 0 auto; }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; animation: fadeUp 0.4s ease both;
        }
        .page-header-left { display: flex; align-items: center; gap: 14px; }
        .page-header-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 22px; box-shadow: 0 4px 14px rgba(36,82,160,0.35);
        }
        .page-header-text h1 { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--navy); line-height: 1.2; }
        .page-header-text p  { font-size: 13px; color: var(--muted); margin-top: 2px; }

        /* ── POINTS BADGE (header right) ── */
        .points-badge {
            display: flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #7a4800, #c27a00);
            border: 2px solid var(--gold-light);
            border-radius: 14px; padding: 10px 18px;
            box-shadow: 0 4px 16px rgba(240,165,0,0.3);
            animation: fadeUp 0.4s ease both;
        }
        .points-badge-icon { font-size: 26px; line-height: 1; }
        .points-badge-info { display: flex; flex-direction: column; }
        .points-badge-val {
            font-family: 'DM Serif Display', serif;
            font-size: 24px; color: var(--gold-light); line-height: 1;
        }
        .points-badge-lbl { font-size: 10px; font-weight: 700; color: rgba(255,220,120,0.85); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .points-badge-progress { font-size: 10px; color: rgba(255,220,120,0.7); margin-top: 1px; }

        /* ── STAT CARDS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
            margin-bottom: 22px; animation: fadeUp 0.4s ease 0.08s both;
        }
        .stat-card {
            background: var(--panel); border-radius: 14px; border: 1px solid var(--border);
            padding: 18px 20px; display: flex; align-items: center; gap: 16px;
            box-shadow: 0 3px 16px rgba(15,38,83,0.07);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }
        .stat-card.points-card { border-color: #f0d080; background: linear-gradient(135deg, #fffbf0, #fff8e6); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue   { background: linear-gradient(135deg, var(--navy-light), var(--accent)); }
        .stat-icon.green  { background: linear-gradient(135deg, #0fa86a, var(--green)); }
        .stat-icon.gold   { background: linear-gradient(135deg, var(--orange), var(--gold)); }
        .stat-icon.purple { background: linear-gradient(135deg, #7a4800, #c27a00); }
        .stat-info .stat-val {
            font-family: 'DM Serif Display', serif; font-size: 28px; color: var(--navy); line-height: 1;
        }
        .stat-card.points-card .stat-val { color: #7a4800; }
        .stat-info .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-info .stat-sub { font-size: 11px; color: var(--muted); margin-top: 4px; }

        /* Progress bar inside points stat card */
        .points-progress-wrap { margin-top: 6px; }
        .points-progress-bar {
            height: 5px; background: #f0d080; border-radius: 99px; overflow: hidden;
        }
        .points-progress-fill {
            height: 100%; background: linear-gradient(90deg, #c27a00, var(--gold));
            border-radius: 99px; transition: width 0.6s ease;
        }
        .points-progress-label { font-size: 10px; color: #a07030; margin-top: 3px; font-weight: 600; }

        /* ── MAIN CARD ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
            animation: fadeUp 0.5s ease 0.14s both;
        }
        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header-left { display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .card-header .hicon { width: 28px; height: 28px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; }

        /* ── FILTER BAR ── */
        .filter-bar {
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fafbfd;
        }
        .filter-bar label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .filter-bar select, .filter-bar input[type="text"] {
            height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); background: white;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        }
        .filter-bar select:focus, .filter-bar input[type="text"]:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
        }
        .filter-bar input[type="text"] { width: 200px; }
        .btn-reset {
            height: 34px; padding: 0 14px; border: 1px solid var(--border); border-radius: 8px;
            background: white; color: var(--muted); font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .btn-reset:hover { background: var(--tag-bg); color: var(--navy); border-color: #b0bdd8; }
        .filter-count { margin-left: auto; font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead tr { background: var(--tag-bg); border-bottom: 2px solid var(--border); }
        thead th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        thead th.center { text-align: center; }
        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.14s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }
        tbody td { padding: 12px 16px; color: var(--text); vertical-align: middle; }
        tbody td.center { text-align: center; }
        .td-date { font-weight: 700; color: var(--navy); font-size: 13px; white-space: nowrap; }
        .td-date .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }
        .lab-tag { display: inline-flex; align-items: center; gap: 5px; background: var(--tag-bg); color: var(--navy-light); padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee; }
        .purpose-text { color: #445; font-size: 13px; line-height: 1.4; }
        .time-text { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; }
        .duration-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .duration-badge.short  { background: var(--green-bg);  color: var(--green);  }
        .duration-badge.medium { background: var(--orange-bg); color: var(--orange); }
        .duration-badge.long   { background: #e8edf8; color: var(--navy-light); }
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
        .status-pill.done    { background: var(--green-bg); color: var(--green); }
        .status-pill.ongoing { background: #fff8e1; color: #c07000; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Point chip shown on every 3rd completed row */
        .point-chip {
            display: inline-flex; align-items: center; gap: 4px;
            background: #fff8e0; border: 1px solid #f0d080;
            color: #7a4800; border-radius: 20px;
            font-size: 10px; font-weight: 800; padding: 2px 8px;
            letter-spacing: 0.3px;
        }

        .btn-feedback {
            padding: 4px 11px; background: var(--tag-bg); color: var(--navy-light);
            border: 1.5px solid var(--border); border-radius: 6px;
            font-size: 12px; font-weight: 600; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s; white-space: nowrap;
        }
        .btn-feedback:hover { background: #d6e0f5; border-color: var(--accent); color: var(--accent); }
        .btn-feedback.submitted { background: var(--green-bg); color: var(--green); border-color: #a8e8ce; cursor: default; }
        .status-cell { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .empty-state { text-align: center; padding: 60px 24px; color: var(--muted); }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 14px; opacity: 0.4; }
        .empty-state h3 { font-size: 16px; font-weight: 700; color: var(--navy); margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; line-height: 1.6; }

        /* ── PAGINATION ── */
        .table-footer {
            padding: 13px 20px; border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            background: #fafbfd; flex-wrap: wrap; gap: 10px;
        }
        .pagination { display: flex; gap: 4px; }
        .page-btn {
            height: 30px; min-width: 30px; padding: 0 8px; border: 1px solid var(--border); border-radius: 7px;
            background: white; color: var(--text); font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; display: flex; align-items: center; justify-content: center; transition: all 0.15s;
        }
        .page-btn:hover:not(:disabled) { background: var(--tag-bg); border-color: var(--accent); color: var(--accent); }
        .page-btn.active { background: var(--accent); border-color: var(--accent); color: white; }
        .page-btn:disabled { opacity: 0.38; cursor: default; }
        .table-footer-info { font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── FEEDBACK MODAL ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(10,20,50,0.45); backdrop-filter: blur(3px); z-index: 999; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: var(--panel); border-radius: 16px; box-shadow: 0 20px 60px rgba(15,38,83,0.25); width: 100%; max-width: 480px; overflow: hidden; animation: modalIn 0.25s ease both; }
        @keyframes modalIn { from { opacity: 0; transform: translateY(-16px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .modal-header { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 15px; font-weight: 700; color: white; }
        .modal-close { background: rgba(255,255,255,0.15); border: none; color: white; width: 28px; height: 28px; border-radius: 6px; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; font-family: 'DM Sans', sans-serif; }
        .modal-close:hover { background: rgba(255,255,255,0.28); }
        .modal-body { padding: 22px 24px 12px; }
        .modal-session-info { background: var(--tag-bg); border: 1px solid var(--border); border-radius: 9px; padding: 10px 14px; font-size: 12.5px; color: var(--muted); margin-bottom: 16px; }
        .modal-session-info strong { color: var(--navy); }
        .textarea-wrap { border: 1.5px solid var(--border); border-radius: 9px; overflow: hidden; background: var(--bg); transition: border-color 0.18s, box-shadow 0.18s; }
        .textarea-wrap:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white; }
        .textarea-wrap textarea { width: 100%; border: none; background: transparent; padding: 12px 14px; font-size: 13.5px; font-family: 'DM Sans', sans-serif; color: var(--text); outline: none; resize: vertical; min-height: 110px; display: block; }
        .char-count { padding: 4px 14px 8px; font-size: 11px; color: var(--muted); text-align: right; }
        .modal-error { color: var(--red); font-size: 12px; margin-top: 8px; display: none; font-weight: 500; }
        .modal-footer { padding: 14px 24px 20px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { padding: 9px 22px; background: white; color: var(--text); border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: background 0.15s; }
        .btn-cancel:hover { background: var(--tag-bg); }
        .btn-submit { padding: 9px 24px; background: linear-gradient(135deg, var(--navy), var(--navy-light)); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: 'DM Sans', sans-serif; cursor: pointer; box-shadow: 0 3px 10px rgba(15,38,83,0.22); transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(15,38,83,0.32); }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

        /* ── TOAST ── */
        .fb-toast { display: none; position: fixed; top: 72px; right: 24px; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; color: white; z-index: 9999; box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
        .fb-toast.success { background: #1a7a4a; }
        .fb-toast.error   { background: #c0392b; }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .page-wrap { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; }
            .filter-bar input[type="text"] { width: 140px; }
            .status-cell { flex-direction: column; gap: 4px; }
            .notif-dropdown { width: calc(100vw - 20px); right: -10px; }
            .points-badge { display: none; }
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
        <a href="/SYSARCH/user/history.php" class="active">History</a>
        <a href="/SYSARCH/user/reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrap">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">🕒</div>
            <div class="page-header-text">
                <h1>Sit-in History</h1>
                <p>Your complete laboratory session records</p>
            </div>
        </div>
        <!-- POINTS BADGE (top-right of header) -->
        <div class="points-badge">
            <div class="points-badge-icon">⭐</div>
            <div class="points-badge-info">
                <div class="points-badge-val"><?= $earned_points ?> pts</div>
                <div class="points-badge-lbl">Earned Points</div>
                <div class="points-badge-progress">
                    <?php if ($progress_remainder === 0 && $completed_sessions > 0): ?>
                        Next point in 3 more sessions
                    <?php else: ?>
                        <?= $needed_for_next ?> more session<?= $needed_for_next !== 1 ? 's' : '' ?> to next point
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue">💻</div>
            <div class="stat-info">
                <div class="stat-val"><?= $total_sessions ?></div>
                <div class="stat-lbl">Total Sessions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">⏱️</div>
            <div class="stat-info">
                <div class="stat-val"><?= $total_hours ?>h</div>
                <div class="stat-lbl">Total Time Logged</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">🏛️</div>
            <div class="stat-info">
                <div class="stat-val"><?= $labs_visited ?></div>
                <div class="stat-lbl">Labs Visited</div>
            </div>
        </div>
        <!-- POINTS STAT CARD -->
        <div class="stat-card points-card">
            <div class="stat-icon purple">⭐</div>
            <div class="stat-info">
                <div class="stat-val"><?= $earned_points ?></div>
                <div class="stat-lbl">Points Earned</div>
                <div class="points-progress-wrap">
                    <div class="points-progress-bar">
                        <div class="points-progress-fill" style="width: <?= ($progress_remainder / 3) * 100 ?>%"></div>
                    </div>
                    <div class="points-progress-label">
                        <?= $progress_remainder ?>/3 toward next point
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HISTORY TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="hicon">📋</div>
                Session Records
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <label>Filter:</label>
            <input type="text" id="searchInput" placeholder="Search purpose or lab…" oninput="applyFilters()">
            <select id="labFilter" onchange="applyFilters()">
                <option value="">All Labs</option>
                <?php
                $unique_labs = array_unique(array_filter(array_column($history, 'lab')));
                sort($unique_labs);
                foreach ($unique_labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>"><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="done">Completed</option>
                <option value="ongoing">Ongoing</option>
            </select>
            <button class="btn-reset" onclick="resetFilters()">✕ Reset</button>
            <span class="filter-count" id="filterCount">
                Showing <?= $total_sessions ?> record<?= $total_sessions !== 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No sessions yet</h3>
                <p>Your sit-in sessions will appear here once you've logged in to a laboratory.</p>
            </div>
            <?php else: ?>
            <table id="historyTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Laboratory</th>
                        <th>Purpose</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th class="center">Duration</th>
                        <th class="center">Status</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php
                    $completed_count = 0;
                    foreach ($history as $i => $row):
                        $is_ongoing   = empty($row['time_out']);
                        $dur          = (int)$row['duration_min'];
                        $sitin_id     = (int)$row['id'];
                        $has_feedback = in_array($sitin_id, $feedback_ids);

                        // Track completed count and flag every 3rd one
                        

                        if ($dur < 60)      $dur_label = $dur . 'm';
                        elseif ($dur < 120) $dur_label = '1h ' . ($dur - 60) . 'm';
                        else                $dur_label = floor($dur / 60) . 'h ' . ($dur % 60) . 'm';

                        $dur_class  = $dur < 60 ? 'short' : ($dur < 180 ? 'medium' : 'long');
                        $date_fmt   = $row['time_in'] ? date('M j, Y', strtotime($row['time_in'])) : '—';
                        $day_fmt    = $row['time_in'] ? date('l',       strtotime($row['time_in'])) : '';
                        $login_fmt  = $row['time_in']  ? date('h:i A',  strtotime($row['time_in']))  : '—';
                        $logout_fmt = $row['time_out'] ? date('h:i A',  strtotime($row['time_out'])) : '—';
                        $js_label   = addslashes($date_fmt . ' – ' . ($row['lab'] ?? ''));
                    ?>
                    <tr data-lab="<?= htmlspecialchars($row['lab'] ?? '') ?>"
                        data-status="<?= $is_ongoing ? 'ongoing' : 'done' ?>"
                        data-purpose="<?= htmlspecialchars(strtolower($row['purpose'] ?? '')) ?>"
                        data-lab-lower="<?= htmlspecialchars(strtolower($row['lab'] ?? '')) ?>">
                        <td style="color:var(--muted);font-size:12px;font-weight:600;"><?= $i + 1 ?></td>
                        <td>
                            <div class="td-date">
                                <?= htmlspecialchars($date_fmt) ?>
                                <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                            </div>
                        </td>
                        <td><span class="lab-tag">🏛️ <?= htmlspecialchars($row['lab'] ?? 'N/A') ?></span></td>
                        <td><div class="purpose-text"><?= htmlspecialchars($row['purpose'] ?? '—') ?></div></td>
                        <td><div class="time-text"><?= $login_fmt ?></div></td>
                        <td>
                            <div class="time-text">
                                <?= $is_ongoing
                                    ? '<span style="color:var(--orange);font-weight:700;">Active</span>'
                                    : $logout_fmt ?>
                            </div>
                        </td>
                        <td class="center">
                            <?php if ($is_ongoing): ?>
                            <span class="duration-badge medium">⏳ In progress</span>
                            <?php else: ?>
                            <span class="duration-badge <?= $dur_class ?>">⏱ <?= $dur_label ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <div class="status-cell">
                                <?php if ($is_ongoing): ?>
                                <span class="status-pill ongoing"><span class="status-dot"></span> Ongoing</span>
                                <?php else: ?>
                                <span class="status-pill done"><span class="status-dot"></span> Done</span>
                                <?php endif; ?>

                                <?php if ($has_feedback): ?>
                                <button class="btn-feedback submitted" disabled>✅ Feedback Sent</button>
                                <?php else: ?>
                                <button class="btn-feedback"
                                    onclick="openFeedbackModal(<?= $sitin_id ?>, '<?= $js_label ?>')">
                                    💬 Feedback
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($history)): ?>
        <div class="table-footer">
            <div class="table-footer-info" id="pageInfo">Page 1 of 1</div>
            <div class="pagination" id="pagination"></div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-wrap -->

<!-- ── FEEDBACK MODAL ── -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal">
        <div class="modal-header">
            <h3>💬 Leave Feedback</h3>
            <button class="modal-close" onclick="closeFeedbackModal()">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div class="modal-session-info">
                Session: <strong id="fb_session_label"></strong>
            </div>
            <div class="textarea-wrap">
                <textarea id="fb_message" placeholder="Write your feedback here…"
                    maxlength="1000" oninput="updateCharCount()"></textarea>
                <div class="char-count"><span id="charCount">0</span> / 1000</div>
            </div>
            <p class="modal-error" id="fb_error">Please write a message before submitting.</p>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeFeedbackModal()">Cancel</button>
            <button class="btn-submit" id="btn_submit_fb" onclick="submitFeedback()">Submit Feedback</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="fb-toast" id="fb_toast"></div>

<script>
// ── TABLE / PAGINATION ──────────────────────────────────────────────────────
const ROWS_PER_PAGE = 10;
let currentPage = 1;
let visibleRows = [];

function getRows() { return Array.from(document.querySelectorAll('#historyBody tr')); }

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const labF   = document.getElementById('labFilter').value;
    const statF  = document.getElementById('statusFilter').value;
    visibleRows  = [];

    getRows().forEach(function(row) {
        const ok = (!search || (row.dataset.purpose||'').includes(search) || (row.dataset.labLower||'').includes(search))
                && (!labF   || row.dataset.lab    === labF)
                && (!statF  || row.dataset.status === statF);
        row.style.display = ok ? '' : 'none';
        if (ok) visibleRows.push(row);
    });

    document.getElementById('filterCount').textContent =
        'Showing ' + visibleRows.length + ' record' + (visibleRows.length !== 1 ? 's' : '');
    currentPage = 1;
    paginate();
}

function paginate() {
    visibleRows.forEach(function(row, idx) {
        row.style.display = (Math.floor(idx / ROWS_PER_PAGE) + 1 === currentPage) ? '' : 'none';
    });
    const total = Math.max(1, Math.ceil(visibleRows.length / ROWS_PER_PAGE));
    document.getElementById('pageInfo').textContent = 'Page ' + currentPage + ' of ' + total;

    const pg = document.getElementById('pagination');
    pg.innerHTML = '';
    pg.appendChild(mkBtn('‹', currentPage > 1, function() { currentPage--; paginate(); }));
    for (let p = 1; p <= total; p++) {
        if (total > 7 && p > 2 && p < total - 1 && Math.abs(p - currentPage) > 1) {
            if (p === 3 || p === total - 2) {
                const d = document.createElement('span');
                d.style.cssText = 'padding:0 4px;color:var(--muted);font-size:13px;display:flex;align-items:center;';
                d.textContent = '…'; pg.appendChild(d);
            }
            continue;
        }
        const b = mkBtn(p, true, (function(pp) { return function() { currentPage = pp; paginate(); }; })(p));
        if (p === currentPage) b.classList.add('active');
        pg.appendChild(b);
    }
    pg.appendChild(mkBtn('›', currentPage < total, function() { currentPage++; paginate(); }));
}

function mkBtn(label, enabled, onClick) {
    const b = document.createElement('button');
    b.className = 'page-btn'; b.textContent = label; b.disabled = !enabled;
    if (enabled) b.addEventListener('click', onClick);
    return b;
}

function resetFilters() {
    document.getElementById('searchInput').value  = '';
    document.getElementById('labFilter').value    = '';
    document.getElementById('statusFilter').value = '';
    applyFilters();
}

visibleRows = getRows();
paginate();

// ── FEEDBACK MODAL ──────────────────────────────────────────────────────────
let fb_sitin_id = null;

function openFeedbackModal(id, label) {
    fb_sitin_id = id;
    document.getElementById('fb_session_label').textContent = label;
    document.getElementById('fb_message').value = '';
    document.getElementById('fb_error').style.display = 'none';
    document.getElementById('charCount').textContent = '0';
    document.getElementById('btn_submit_fb').disabled = false;
    document.getElementById('btn_submit_fb').textContent = 'Submit Feedback';
    document.getElementById('feedbackModal').classList.add('open');
    setTimeout(function() { document.getElementById('fb_message').focus(); }, 120);
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.remove('open');
    fb_sitin_id = null;
}

function updateCharCount() {
    document.getElementById('charCount').textContent = document.getElementById('fb_message').value.length;
}

function submitFeedback() {
    const msg = document.getElementById('fb_message').value.trim();
    if (!msg) { document.getElementById('fb_error').style.display = 'block'; return; }
    document.getElementById('fb_error').style.display = 'none';
    const btn = document.getElementById('btn_submit_fb');
    btn.disabled = true; btn.textContent = 'Submitting…';

    fetch('/SYSARCH/user/submit_feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sitin_id: fb_sitin_id, message: msg })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        closeFeedbackModal();
        if (data.success) {
            document.querySelectorAll('.btn-feedback').forEach(function(b) {
                if (b.getAttribute('onclick') && b.getAttribute('onclick').includes('(' + fb_sitin_id + ',')) {
                    b.textContent = '✅ Feedback Sent';
                    b.classList.add('submitted');
                    b.disabled = true;
                    b.removeAttribute('onclick');
                }
            });
            showToast('✅ Feedback submitted successfully!', 'success');
        } else {
            showToast('❌ ' + (data.error || 'Failed to submit.'), 'error');
        }
    })
    .catch(function() { closeFeedbackModal(); showToast('❌ Network error.', 'error'); });
}

function showToast(msg, type) {
    const t = document.getElementById('fb_toast');
    t.textContent = msg; t.className = 'fb-toast ' + type; t.style.display = 'block';
    setTimeout(function() { t.style.display = 'none'; }, 3500);
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if (e.target === this) closeFeedbackModal();
});

// ── NOTIFICATION DROPDOWN ────────────────────────────────────────────────────
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
    if (notifOpen && !wrapper.contains(e.target)) closeNotifDropdown();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeNotifDropdown(); closeFeedbackModal(); }
});

function switchNotifTab(tab) {
    notifTab = tab;
    ['all','reservation','announce'].forEach(function(t) {
        document.getElementById('ntab-' + t).classList.toggle('active', t === tab);
    });
    renderNotifItems();
}

function fetchNotifications() {
    document.getElementById('notifScroll').innerHTML =
        '<div class="notif-loading-state">Loading…</div>';

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