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

// Fetch sessions remaining from student table
$sessions_remaining = 0;
$stmt = $conn->prepare("SELECT sessions FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $_SESSION['student_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) {
    $sessions_remaining = (int)$row['sessions'];
    $_SESSION['sessions_remaining'] = $sessions_remaining;
}
$stmt->close();

// Fetch announcements from DB
$announcements = [];
$ann_check = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($ann_check && $ann_check->num_rows > 0) {
    $ar = $conn->query("SELECT admin_name, message, created_at FROM announcements ORDER BY created_at DESC");
    if ($ar) {
        while ($row = $ar->fetch_assoc()) {
            $announcements[] = [
                'admin'   => $row['admin_name'] ?? 'CCS Admin',
                'date'    => date('Y-M-d', strtotime($row['created_at'])),
                'message' => $row['message'],
            ];
        }
    }
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
$nstmt->bind_param('s', $_SESSION['student_id']);
$nstmt->execute();
$nstmt->bind_result($unread_count);
$nstmt->fetch();
$nstmt->close();

// ── Sit-in Summary Stats ──
$summary = [
    'total_hours'      => 0,
    'num_sessions'     => 0,
    'avg_duration_min' => 0,
    'longest_min'      => 0,
];

$completed_sessions = 0;

$sitin_table_check = $conn->query("SHOW TABLES LIKE 'sitin'");
if ($sitin_table_check && $sitin_table_check->num_rows > 0) {
    $sstmt = $conn->prepare("
        SELECT
            COUNT(*)                                                                        AS num_sessions,
            SUM(CASE WHEN time_out IS NOT NULL THEN 1 ELSE 0 END)                          AS completed_sessions,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, COALESCE(time_out, NOW()))),  0)   AS total_minutes,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, time_in, COALESCE(time_out, NOW()))),  0)   AS avg_minutes,
            COALESCE(MAX(TIMESTAMPDIFF(MINUTE, time_in, COALESCE(time_out, NOW()))),  0)   AS longest_minutes
        FROM sitin
        WHERE student_id = ?
          AND time_in IS NOT NULL
    ");
    $sstmt->bind_param('s', $_SESSION['student_id']);
    $sstmt->execute();
    $sr = $sstmt->get_result()->fetch_assoc();
    if ($sr) {
        $summary['num_sessions']     = (int)$sr['num_sessions'];
        $summary['total_hours']      = round($sr['total_minutes'] / 60, 1);
        $summary['avg_duration_min'] = round($sr['avg_minutes']);
        $summary['longest_min']      = round($sr['longest_minutes']);
        $completed_sessions          = (int)$sr['completed_sessions'];
    }
    $sstmt->close();
}

// ── Points: 1 point per 3 completed sit-ins ──
$earned_points      = (int)floor($completed_sessions / 3);
$progress_remainder = $completed_sessions % 3;
$needed_for_next    = 3 - $progress_remainder;

// ── Sessions Table ──
$session_rows = [];
$sitin_table_check2 = $conn->query("SHOW TABLES LIKE 'sitin'");
if ($sitin_table_check2 && $sitin_table_check2->num_rows > 0) {
    $trstmt = $conn->prepare("
        SELECT
            DATE(time_in)                                                               AS date,
            time_in,
            time_out,
            TIMESTAMPDIFF(MINUTE, time_in, COALESCE(time_out, NOW()))                  AS duration_minutes,
            COALESCE(lab, '—')                                                          AS pc_number,
            CASE WHEN time_out IS NULL THEN 'active' ELSE 'completed' END              AS status
        FROM sitin
        WHERE student_id = ?
          AND time_in IS NOT NULL
        ORDER BY time_in DESC
        LIMIT 50
    ");
    $trstmt->bind_param('s', $_SESSION['student_id']);
    $trstmt->execute();
    $tr = $trstmt->get_result();
    while ($r = $tr->fetch_assoc()) {
        $session_rows[] = $r;
    }
    $trstmt->close();
}

// ── Reservation Enabled/Disabled setting ──
$reservation_enabled = true;
$settings_check = $conn->query("SHOW TABLES LIKE 'settings'");
if ($settings_check && $settings_check->num_rows > 0) {
    $set_r = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'reservation_enabled' LIMIT 1");
    if ($set_r && $set_r->num_rows > 0) {
        $set_row = $set_r->fetch_assoc();
        $reservation_enabled = (bool)(int)$set_row['setting_value'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Sit-in Monitoring System</title>
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
            --green:      #0fa86a;
            --red:        #e53535;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background-color: var(--bg); min-height: 100vh; color: var(--text); }

        /* ── NAVBAR ── */
        .navbar {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            padding: 0 28px; display: flex; justify-content: space-between; align-items: center;
            height: 60px; position: sticky; top: 0; z-index: 300;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }
        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .nav-title { font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3; }
        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }
        .nav-links { display: flex; align-items: center; gap: 2px; }
        .nav-links a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; font-weight: 500; padding: 7px 13px; border-radius: 6px; transition: background 0.18s, color 0.18s; letter-spacing: 0.2px; }
        .nav-links a:hover  { background: rgba(255,255,255,0.12); color: white; }
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }
        .btn-logout { background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important; color: #fff !important; font-weight: 700 !important; border-radius: 8px !important; padding: 7px 18px !important; margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4); transition: transform 0.15s, box-shadow 0.15s !important; }
        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        /* ── NOTIFICATION ── */
        .notif-wrapper { position: relative; display: flex; align-items: center; }
        .notif-btn { position: relative; background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.22); cursor: pointer; color: white; font-size: 13px; font-weight: 600; padding: 6px 14px 6px 11px; border-radius: 8px; display: flex; align-items: center; gap: 7px; font-family: 'DM Sans', sans-serif; letter-spacing: 0.2px; transition: background 0.18s, border-color 0.18s; }
        .notif-btn:hover  { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.4); }
        .notif-btn.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.5); }
        .notif-bell { font-size: 15px; line-height: 1; }
        .notif-badge { background: #e53535; color: white; font-size: 9px; font-weight: 800; min-width: 17px; height: 17px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; border: 2px solid var(--navy-mid); line-height: 1; margin-left: 2px; }
        .notif-badge.hidden { display: none; }
        .notif-dropdown { display: none; position: absolute; top: calc(100% + 10px); right: 0; width: 360px; background: var(--panel); border-radius: 10px; box-shadow: 0 8px 40px rgba(15,38,83,0.22), 0 2px 8px rgba(15,38,83,0.10); border: 1px solid var(--border); z-index: 400; overflow: hidden; animation: dropIn 0.18s ease both; }
        .notif-dropdown.open { display: block; }
        @keyframes dropIn { from { opacity: 0; transform: translateY(-8px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .notif-dropdown::before { content: ''; position: absolute; top: -7px; right: 22px; width: 13px; height: 13px; background: var(--panel); border-left: 1px solid var(--border); border-top: 1px solid var(--border); transform: rotate(45deg); z-index: 1; }
        .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 13px 16px 11px; border-bottom: 1px solid var(--border); background: #f7f9fd; }
        .notif-dropdown-header .notif-hd-left { display: flex; align-items: center; gap: 8px; }
        .notif-dropdown-header .notif-hd-icon { font-size: 16px; }
        .notif-dropdown-header h4 { font-size: 12px; font-weight: 800; color: var(--navy); text-transform: uppercase; letter-spacing: 0.8px; }
        .btn-mark-all-read { font-size: 11.5px; font-weight: 600; color: var(--accent); background: none; border: 1px solid #c8d8f5; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background 0.15s, color 0.15s; }
        .btn-mark-all-read:hover { background: var(--tag-bg); }
        .notif-tabs { display: flex; border-bottom: 1px solid var(--border); background: white; }
        .notif-tab-btn { flex: 1; padding: 8px 0; font-size: 11.5px; font-weight: 700; color: var(--muted); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-family: 'DM Sans', sans-serif; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.15s, border-color 0.15s; }
        .notif-tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .notif-scroll { max-height: 320px; overflow-y: auto; }
        .notif-scroll::-webkit-scrollbar { width: 4px; }
        .notif-scroll::-webkit-scrollbar-track { background: transparent; }
        .notif-scroll::-webkit-scrollbar-thumb { background: #c8d4ee; border-radius: 4px; }
        .notif-item { display: flex; align-items: flex-start; gap: 11px; padding: 11px 16px; border-bottom: 1px solid #f2f4fb; cursor: pointer; transition: background 0.13s; position: relative; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f5f7fd; }
        .notif-item.unread { background: #f0f5ff; }
        .notif-item.unread:hover { background: #e6eeff; }
        .notif-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; font-weight: 700; color: white; }
        .notif-avatar.type-accepted     { background: linear-gradient(135deg,#0fa86a,#1cb87e); }
        .notif-avatar.type-rejected     { background: linear-gradient(135deg,#c0392b,#e53535); }
        .notif-avatar.type-announcement { background: linear-gradient(135deg,#e67e00,#f0a500); }
        .notif-avatar.type-default      { background: linear-gradient(135deg,var(--navy-light),var(--accent)); }
        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title { font-size: 13px; font-weight: 700; color: var(--navy); line-height: 1.3; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-item-msg { font-size: 12px; color: var(--muted); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-item-time { font-size: 11px; color: #a0aec0; margin-top: 4px; font-weight: 500; }
        .notif-unread-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
        .notif-empty-state { text-align: center; padding: 38px 20px; color: var(--muted); }
        .notif-empty-state .nei { font-size: 32px; opacity: 0.3; margin-bottom: 8px; }
        .notif-empty-state p { font-size: 12.5px; }
        .notif-loading-state { text-align: center; padding: 30px; font-size: 12.5px; color: var(--muted); }

        /* ── LAYOUT ── */
        .dashboard { display: grid; grid-template-columns: 340px 1fr 380px; gap: 20px; padding: 24px 28px; align-items: start; }
        .bottom-row { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; }

        /* ── CARD BASE ── */
        .card { background: var(--panel); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border); animation: fadeUp 0.5s ease both; }
        .card:nth-child(1) { animation-delay: 0.05s; }
        .card:nth-child(2) { animation-delay: 0.12s; }
        .card:nth-child(3) { animation-delay: 0.18s; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        .card-header { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); color: white; padding: 13px 18px; display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .card-header .hicon { width: 28px; height: 28px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; }

        /* ── STUDENT PANEL ── */
        .student-body { padding: 26px 24px; }
        .student-avatar-area { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
        .avatar-initials { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--navy-light), var(--accent)); display: flex; align-items: center; justify-content: center; font-family: 'DM Serif Display', serif; font-size: 30px; color: white; letter-spacing: 1px; box-shadow: 0 6px 20px rgba(36,82,160,0.35); border: 3px solid white; outline: 2px solid var(--accent); flex-shrink: 0; }
        .student-name-display { text-align: left; }
        .student-name-display .sname   { font-size: 16px; font-weight: 700; color: var(--navy); line-height: 1.3; }
        .student-name-display .sid     { font-size: 12px; color: var(--muted); margin-top: 2px; font-weight: 500; }
        .student-name-display .scourse { font-size: 12px; color: var(--accent); margin-top: 4px; font-weight: 600; }
        .info-rows { display: flex; flex-direction: column; gap: 0; }
        .info-row { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid #f0f3f9; }
        .info-row:last-child { border-bottom: none; }
        .info-row .ri { width: 34px; height: 34px; background: var(--tag-bg); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .info-row .rl { color: var(--muted); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; line-height: 1; }
        .info-row .rv { color: var(--text); font-weight: 500; font-size: 13px; line-height: 1.3; }
        .info-row .rstack { display: flex; flex-direction: column; gap: 3px; }

        /* ── SESSION BADGE ── */
        .session-badge { display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, var(--navy), var(--accent)); color: white; border-radius: 12px; padding: 14px 18px; margin-top: 20px; width: 100%; box-shadow: 0 3px 10px rgba(36,82,160,0.3); }
        .session-badge .session-icon { font-size: 26px; flex-shrink: 0; line-height: 1; }
        .session-badge .session-info  { display: flex; flex-direction: column; gap: 1px; }
        .session-badge .session-num   { font-family: 'DM Serif Display', serif; font-size: 28px; line-height: 1; }
        .session-badge .session-lbl   { font-size: 11px; font-weight: 600; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── POINTS BADGE (inside student panel) ── */
        .points-banner {
            display: flex; align-items: center; gap: 14px;
            background: linear-gradient(135deg, #7a4800, #b86e00);
            border: 1.5px solid var(--gold-light);
            color: white; border-radius: 12px; padding: 14px 18px;
            margin-top: 12px; width: 100%;
            box-shadow: 0 3px 10px rgba(190,120,0,0.3);
        }
        .points-banner .pb-icon { font-size: 26px; flex-shrink: 0; line-height: 1; }
        .points-banner .pb-info { display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .points-banner .pb-num  { font-family: 'DM Serif Display', serif; font-size: 28px; color: var(--gold-light); line-height: 1; }
        .points-banner .pb-lbl  { font-size: 11px; font-weight: 600; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; }
        .points-banner .pb-progress-wrap { margin-top: 5px; }
        .points-banner .pb-bar  { height: 4px; background: rgba(255,255,255,0.2); border-radius: 99px; overflow: hidden; }
        .points-banner .pb-fill { height: 100%; background: var(--gold-light); border-radius: 99px; }
        .points-banner .pb-sub  { font-size: 10px; color: rgba(255,220,120,0.8); margin-top: 3px; font-weight: 600; }

        /* ── ANNOUNCEMENTS ── */
        .ann-body { padding: 18px; display: flex; flex-direction: column; gap: 14px; max-height: 560px; overflow-y: auto; }
        .ann-body::-webkit-scrollbar { width: 5px; }
        .ann-body::-webkit-scrollbar-track { background: transparent; }
        .ann-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
        .ann-item { border-radius: 12px; border: 1px solid var(--border); overflow: hidden; transition: box-shadow 0.2s, transform 0.2s; }
        .ann-item:hover { box-shadow: 0 6px 20px rgba(15,38,83,0.1); transform: translateY(-2px); }
        .ann-item-header { background: var(--tag-bg); padding: 10px 14px; display: flex; align-items: center; gap: 8px; }
        .ann-dot  { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }
        .ann-meta { font-size: 12px; font-weight: 700; color: var(--navy); }
        .ann-date { font-size: 11px; color: var(--muted); margin-left: auto; }
        .ann-body-text { padding: 12px 14px; font-size: 13px; color: #445; line-height: 1.6; }
        .ann-empty     { padding: 12px 14px; font-size: 12px; color: var(--muted); font-style: italic; }

        /* ── RULES ── */
        .rules-body { padding: 24px 22px; max-height: 640px; overflow-y: auto; }
        .rules-body::-webkit-scrollbar { width: 5px; }
        .rules-body::-webkit-scrollbar-track { background: transparent; }
        .rules-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
        .rules-university { text-align: center; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 2px solid var(--tag-bg); }
        .rules-university h3 { font-family: 'DM Serif Display', serif; font-size: 17px; color: var(--navy); margin-bottom: 3px; }
        .rules-university h4 { font-size: 11.5px; font-weight: 700; color: var(--navy-light); letter-spacing: 0.5px; margin-bottom: 10px; }
        .rules-badge { display: inline-block; background: linear-gradient(135deg, var(--navy), var(--accent)); color: white; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 4px 14px; border-radius: 20px; }
        .rules-intro { font-size: 12.5px; color: var(--muted); line-height: 1.65; margin-bottom: 16px; padding: 11px 14px; background: var(--tag-bg); border-radius: 9px; border-left: 3px solid var(--accent); }
        .rules-list { display: flex; flex-direction: column; gap: 9px; }
        .rule-item { display: flex; gap: 12px; align-items: flex-start; padding: 11px 14px; border-radius: 10px; background: #fafbfd; border: 1px solid var(--border); transition: background 0.15s, border-color 0.15s; }
        .rule-item:hover { background: var(--tag-bg); border-color: #b8c5e0; }
        .rule-num { min-width: 24px; height: 24px; background: linear-gradient(135deg, var(--navy-light), var(--accent)); color: white; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
        .rule-text { font-size: 12.5px; line-height: 1.6; color: #3a4a6b; }

        /* ── SUMMARY + RESERVATION ── */
        .summary-body { padding: 22px 20px; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px; }
        .stat-tile { background: var(--tag-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px 14px; display: flex; flex-direction: column; gap: 6px; transition: box-shadow 0.2s, transform 0.2s; }
        .stat-tile:hover { box-shadow: 0 4px 16px rgba(15,38,83,0.1); transform: translateY(-2px); }
        .stat-tile .st-icon  { font-size: 22px; line-height: 1; }
        .stat-tile .st-value { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--navy); line-height: 1; }
        .stat-tile .st-unit  { font-size: 11px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-tile .st-label { font-size: 11px; color: var(--muted); font-weight: 500; }
        .stat-tile.highlight { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); border-color: transparent; }
        .stat-tile.highlight .st-value,
        .stat-tile.highlight .st-label,
        .stat-tile.highlight .st-unit { color: rgba(255,255,255,0.9); }

        /* Points tile */
        .stat-tile.points-tile { background: linear-gradient(135deg, #7a4800, #b86e00); border-color: transparent; }
        .stat-tile.points-tile .st-value { color: var(--gold-light); }
        .stat-tile.points-tile .st-unit  { color: rgba(255,220,120,0.85); }
        .stat-tile.points-tile .st-label { color: rgba(255,220,120,0.75); }
        .stat-tile.points-tile .st-icon  { color: var(--gold-light); }
        .pt-progress-bar { height: 4px; background: rgba(255,255,255,0.2); border-radius: 99px; overflow: hidden; margin-top: 4px; }
        .pt-progress-fill { height: 100%; background: var(--gold-light); border-radius: 99px; }
        .pt-progress-label { font-size: 10px; color: rgba(255,220,120,0.75); margin-top: 3px; font-weight: 600; }

        /* ── RESERVATION ── */
        .reservation-section { border-top: 1px solid var(--border); padding-top: 18px; }
        .reservation-section h5 { font-size: 12px; font-weight: 800; color: var(--navy); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 12px; display: flex; align-items: center; gap: 7px; }
        .reservation-status-card { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 12px; border: 1.5px solid; transition: all 0.3s ease; }
        .reservation-status-card.enabled  { background: #f0fdf8; border-color: #a7f3d0; }
        .reservation-status-card.disabled { background: #fef2f2; border-color: #fecaca; }
        .res-status-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .reservation-status-card.enabled  .res-status-icon { background: #d1fae5; }
        .reservation-status-card.disabled .res-status-icon { background: #fee2e2; }
        .res-status-info { flex: 1; }
        .res-status-title { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
        .reservation-status-card.enabled  .res-status-title { color: #065f46; }
        .reservation-status-card.disabled .res-status-title { color: #991b1b; }
        .res-status-sub { font-size: 11.5px; color: var(--muted); }
        .res-status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .reservation-status-card.enabled  .res-status-pill { background: #d1fae5; color: #065f46; }
        .reservation-status-card.disabled .res-status-pill { background: #fee2e2; color: #991b1b; }
        .btn-go-reserve { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; margin-top: 12px; padding: 11px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'DM Sans', sans-serif; cursor: pointer; text-decoration: none; transition: all 0.2s; border: none; }
        .btn-go-reserve.active   { background: linear-gradient(135deg, var(--navy), var(--accent)); color: white; box-shadow: 0 3px 12px rgba(36,82,160,0.35); }
        .btn-go-reserve.active:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(36,82,160,0.45); }
        .btn-go-reserve.inactive { background: #f1f3f7; color: #a0aec0; cursor: not-allowed; border: 1px solid var(--border); }

        /* ── SESSIONS TABLE ── */
        .sessions-body { padding: 0; }
        .sessions-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border); background: #fafbfd; gap: 10px; flex-wrap: wrap; }
        .sessions-toolbar .total-badge { font-size: 12px; font-weight: 700; color: var(--navy); background: var(--tag-bg); border: 1px solid var(--border); border-radius: 20px; padding: 4px 12px; }
        .sessions-search { display: flex; align-items: center; gap: 7px; background: white; border: 1px solid var(--border); border-radius: 8px; padding: 6px 10px; font-size: 12.5px; color: var(--muted); }
        .sessions-search input { border: none; outline: none; font-family: 'DM Sans', sans-serif; font-size: 12.5px; color: var(--text); background: transparent; width: 160px; }
        .sessions-table-wrap { overflow-x: auto; max-height: 400px; overflow-y: auto; }
        .sessions-table-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
        .sessions-table-wrap::-webkit-scrollbar-track { background: transparent; }
        .sessions-table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
        table.sessions-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.sessions-tbl thead { background: var(--tag-bg); position: sticky; top: 0; z-index: 1; }
        table.sessions-tbl thead th { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--navy); white-space: nowrap; border-bottom: 2px solid var(--border); }
        table.sessions-tbl tbody tr { border-bottom: 1px solid #f2f4fb; transition: background 0.13s; }
        table.sessions-tbl tbody tr:last-child { border-bottom: none; }
        table.sessions-tbl tbody tr:hover { background: #f5f7fd; }
        table.sessions-tbl tbody td { padding: 10px 14px; color: var(--text); white-space: nowrap; }
        .sessions-empty { text-align: center; padding: 40px 20px; color: var(--muted); }
        .sessions-empty .sei { font-size: 36px; opacity: 0.25; margin-bottom: 8px; }
        .sessions-empty p { font-size: 12.5px; }
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-pill.completed { background: #d1fae5; color: #065f46; }
        .status-pill.active    { background: #dbeafe; color: #1e40af; }
        .status-pill.timeout   { background: #fef3c7; color: #92400e; }
        .status-pill.cancelled { background: #fee2e2; color: #991b1b; }
        .pc-badge { display: inline-flex; align-items: center; justify-content: center; background: var(--tag-bg); border: 1px solid var(--border); border-radius: 7px; padding: 2px 9px; font-size: 12px; font-weight: 700; color: var(--navy); min-width: 36px; }
        .duration-val { font-weight: 600; color: var(--navy-light); }

        @media (max-width: 960px) {
            .dashboard { grid-template-columns: 1fr; padding: 16px; }
            .bottom-row { grid-template-columns: 1fr; }
            .notif-dropdown { width: calc(100vw - 20px); right: -10px; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
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
        <a href="/SYSARCH/user/user_home.php" class="active">Home</a>
        <a href="/SYSARCH/user/user_edit_profile.php">Edit Profile</a>
        <a href="/SYSARCH/user/history.php">History</a>
        <a href="/SYSARCH/user/reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- DASHBOARD -->
<div class="dashboard">

    <!-- LEFT: Student Info -->
    <div class="card">
        <div class="card-header">
            <div class="hicon">👤</div>
            Student Information
        </div>
        <div class="student-body">
            <?php
                $name     = $_SESSION['student_name'] ?? 'Student';
                $parts    = explode(' ', trim($name));
                $initials = strtoupper(
                    substr($parts[0], 0, 1) .
                    (count($parts) > 1 ? substr($parts[count($parts)-1], 0, 1) : '')
                );
            ?>
            <div class="student-avatar-area">
                <div class="avatar-initials"><?= htmlspecialchars($initials) ?></div>
                <div class="student-name-display">
                    <div class="sname"><?= htmlspecialchars($name) ?></div>
                    <div class="sid"><?= htmlspecialchars($_SESSION['student_id'] ?? '') ?></div>
                    <div class="scourse">
                        <?= htmlspecialchars($_SESSION['course'] ?? 'N/A') ?> &mdash; Year <?= htmlspecialchars($_SESSION['year_level'] ?? 'N/A') ?>
                    </div>
                </div>
            </div>

            <div class="info-rows">
                <div class="info-row">
                    <div class="ri">✉️</div>
                    <div class="rstack">
                        <span class="rl">Email</span>
                        <span class="rv"><?= htmlspecialchars($_SESSION['email'] ?? 'N/A') ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="ri">🎓</div>
                    <div class="rstack">
                        <span class="rl">Course</span>
                        <span class="rv"><?= htmlspecialchars($_SESSION['course'] ?? 'N/A') ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="ri">📅</div>
                    <div class="rstack">
                        <span class="rl">Year Level</span>
                        <span class="rv">Year <?= htmlspecialchars($_SESSION['year_level'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Sessions remaining -->
            <div class="session-badge">
                <div class="session-icon">💻</div>
                <div class="session-info">
                    <span class="session-num"><?= htmlspecialchars($sessions_remaining) ?></span>
                    <span class="session-lbl">Sessions Remaining</span>
                </div>
            </div>

            <!-- Points banner -->
            <div class="points-banner">
                <div class="pb-icon">⭐</div>
                <div class="pb-info">
                    <span class="pb-num"><?= $earned_points ?> pts</span>
                    <span class="pb-lbl">Earned Points</span>
                    <div class="pb-progress-wrap">
                        <div class="pb-bar">
                            <div class="pb-fill" style="width:<?= ($progress_remainder / 3) * 100 ?>%"></div>
                        </div>
                        <div class="pb-sub">
                            <?= $progress_remainder ?>/3 toward next point
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- MIDDLE: Announcements -->
    <div class="card">
        <div class="card-header">
            <div class="hicon">📣</div>
            Announcements
        </div>
        <div class="ann-body">
            <?php if (empty($announcements)): ?>
                <div class="ann-empty">No announcements yet.</div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="ann-item">
                    <div class="ann-item-header">
                        <div class="ann-dot"></div>
                        <span class="ann-meta"><?= htmlspecialchars($ann['admin']) ?></span>
                        <span class="ann-date">📅 <?= htmlspecialchars($ann['date']) ?></span>
                    </div>
                    <?php if (!empty($ann['message'])): ?>
                    <div class="ann-body-text"><?= htmlspecialchars($ann['message']) ?></div>
                    <?php else: ?>
                    <div class="ann-empty">No message content.</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: Rules and Regulations -->
    <div class="card">
        <div class="card-header">
            <div class="hicon">📋</div>
            Rules &amp; Regulations
        </div>
        <div class="rules-body">
            <div class="rules-university">
                <h3>University of Cebu</h3>
                <h4>COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</h4>
                <span class="rules-badge">Laboratory Rules</span>
            </div>
            <p class="rules-intro">
                To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:
            </p>
            <div class="rules-list">
                <?php
                $rules = [
                    "Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.",
                    "Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.",
                    "Surfing the internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.",
                    "Getting access to other websites not related to the course is strictly prohibited.",
                    "Deleting computer files and changing the computer settings are not allowed.",
                    "Observe proper sitting posture at all times.",
                    "Laboratory users must clean up after themselves.",
                    "Laboratory users are held responsible for any damage to the equipment they use.",
                    "No food, drinks, and cigarettes are allowed inside the lab.",
                    "Students are not allowed to stay in the laboratory without the presence of an instructor.",
                ];
                foreach ($rules as $i => $rule): ?>
                <div class="rule-item">
                    <div class="rule-num"><?= $i + 1 ?></div>
                    <div class="rule-text"><?= htmlspecialchars($rule) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- BOTTOM ROW -->
    <div class="bottom-row">

        <!-- LEFT: Sit-in Summary + Reservation -->
        <div class="card" style="animation-delay:0.25s;">
            <div class="card-header">
                <div class="hicon">📊</div>
                My Sit-in Summary
            </div>
            <div class="summary-body">
                <div class="stat-grid">
                    <div class="stat-tile highlight">
                        <div class="st-icon">⏱️</div>
                        <div class="st-value"><?= $summary['total_hours'] ?></div>
                        <div class="st-unit">hrs total</div>
                        <div class="st-label">Total Sit-in Hours</div>
                    </div>
                    <div class="stat-tile">
                        <div class="st-icon">🖥️</div>
                        <div class="st-value"><?= $summary['num_sessions'] ?></div>
                        <div class="st-unit">sessions</div>
                        <div class="st-label">Number of Sessions</div>
                    </div>
                    <div class="stat-tile">
                        <div class="st-icon">📈</div>
                        <div class="st-value"><?= $summary['avg_duration_min'] ?></div>
                        <div class="st-unit">min avg</div>
                        <div class="st-label">Avg Session Duration</div>
                    </div>
                    <div class="stat-tile">
                        <div class="st-icon">🏆</div>
                        <div class="st-value"><?= $summary['longest_min'] >= 60
                            ? round($summary['longest_min'] / 60, 1) . '<span style="font-size:14px;font-weight:500"> hr</span>'
                            : $summary['longest_min'] . '<span style="font-size:14px;font-weight:500"> m</span>' ?></div>
                        <div class="st-unit">longest session</div>
                        <div class="st-label">Best Single Session</div>
                    </div>

                    <!-- POINTS TILE spans full width -->
                    <div class="stat-tile points-tile" style="grid-column: 1 / -1; flex-direction: row; align-items: center; gap: 16px;">
                        <div class="st-icon" style="font-size:28px;">⭐</div>
                        <div style="flex:1;">
                            <div class="st-value"><?= $earned_points ?></div>
                            <div class="st-unit">points earned</div>
                            <div class="pt-progress-bar">
                                <div class="pt-progress-fill" style="width:<?= ($progress_remainder / 3) * 100 ?>%"></div>
                            </div>
                            <div class="pt-progress-label"><?= $progress_remainder ?>/3 completed sit-ins toward next point</div>
                        </div>
                    </div>
                </div>

                <!-- Reservation Toggle -->
                <div class="reservation-section">
                    <h5>📅 Reservation Status</h5>
                    <?php if ($reservation_enabled): ?>
                    <div class="reservation-status-card enabled">
                        <div class="res-status-icon">✅</div>
                        <div class="res-status-info">
                            <div class="res-status-title">Reservations Open</div>
                            <div class="res-status-sub">You can book a lab slot now</div>
                        </div>
                        <div class="res-status-pill">Open</div>
                    </div>
                    <a href="/SYSARCH/user/reservation.php" class="btn-go-reserve active">📅 Go to Reservation</a>
                    <?php else: ?>
                    <div class="reservation-status-card disabled">
                        <div class="res-status-icon">🚫</div>
                        <div class="res-status-info">
                            <div class="res-status-title">Reservations Disabled</div>
                            <div class="res-status-sub">Admin has turned off reservations</div>
                        </div>
                        <div class="res-status-pill">Closed</div>
                    </div>
                    <button class="btn-go-reserve inactive" disabled>🚫 Reservation Unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Sessions Table -->
        <div class="card" style="animation-delay:0.30s;">
            <div class="card-header">
                <div class="hicon">📋</div>
                My Sessions
            </div>
            <div class="sessions-body">
                <div class="sessions-toolbar">
                    <span class="total-badge">
                        <?= count($session_rows) ?> record<?= count($session_rows) !== 1 ? 's' : '' ?>
                    </span>
                    <div class="sessions-search">
                        🔍
                        <input type="text" id="sessionSearch" placeholder="Search date, PC, status…" oninput="filterSessions()">
                    </div>
                </div>
                <div class="sessions-table-wrap">
                    <?php if (empty($session_rows)): ?>
                    <div class="sessions-empty">
                        <div class="sei">💻</div>
                        <p>No sit-in sessions recorded yet.</p>
                    </div>
                    <?php else: ?>
                    <table class="sessions-tbl" id="sessionsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Timeout</th>
                                <th>Duration</th>
                                <th>PC No.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($session_rows as $i => $sr):
                            $dur = (int)($sr['duration_minutes'] ?? 0);
                            $dur_str = $dur >= 60 ? floor($dur / 60) . 'h ' . ($dur % 60) . 'm' : $dur . ' min';
                            $status_raw = strtolower(trim($sr['status'] ?? 'unknown'));
                            $status_map = [
                                'completed' => ['label' => 'Completed', 'class' => 'completed'],
                                'active'    => ['label' => 'Active',    'class' => 'active'],
                                'timeout'   => ['label' => 'Timeout',   'class' => 'timeout'],
                                'cancelled' => ['label' => 'Cancelled', 'class' => 'cancelled'],
                            ];
                            $sp = $status_map[$status_raw] ?? ['label' => ucfirst($status_raw), 'class' => 'timeout'];
                            $date_fmt   = $sr['date']     ? date('M d, Y', strtotime($sr['date']))    : '—';
                            $timein_fmt = $sr['time_in']  ? date('h:i A',  strtotime($sr['time_in'])) : '—';
                            $tout_fmt   = $sr['time_out'] ? date('h:i A',  strtotime($sr['time_out'])): '—';
                            $pc         = $sr['pc_number'] ?? '—';
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:11px;"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($date_fmt) ?></td>
                            <td><?= htmlspecialchars($timein_fmt) ?></td>
                            <td><?= htmlspecialchars($tout_fmt) ?></td>
                            <td><span class="duration-val"><?= htmlspecialchars($dur_str) ?></span></td>
                            <td><span class="pc-badge"><?= htmlspecialchars($pc) ?></span></td>
                            <td><span class="status-pill <?= $sp['class'] ?>"><?= htmlspecialchars($sp['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /bottom-row -->
</div><!-- /dashboard -->

<script>
// ── NOTIFICATION DROPDOWN ────────────────────────────────────────────────────
let notifData = [], notifTab = 'all', notifLoaded = false, notifOpen = false;

function toggleNotifDropdown() {
    notifOpen = !notifOpen;
    document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
    document.getElementById('notifBtn').classList.toggle('active', notifOpen);
    if (notifOpen && !notifLoaded) fetchNotifications();
}
function closeNotifDropdown() {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
    document.getElementById('notifBtn').classList.remove('active');
}
document.addEventListener('click', function(e) {
    const w = document.getElementById('notifWrapper');
    if (notifOpen && w && !w.contains(e.target)) closeNotifDropdown();
});
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeNotifDropdown(); });

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
        .then(function(data) { notifLoaded = true; notifData = data.notifications || []; renderNotifItems(); refreshBadge(); })
        .catch(function() { document.getElementById('notifScroll').innerHTML = '<div class="notif-empty-state"><div class="nei">⚠️</div><p>Could not load notifications.</p></div>'; });
}
function renderNotifItems() {
    const scroll = document.getElementById('notifScroll');
    let items = notifData;
    if (notifTab === 'reservation') items = notifData.filter(function(n) { return n.type === 'reservation'; });
    if (notifTab === 'announce')    items = notifData.filter(function(n) { return n.type === 'announcement'; });
    if (items.length === 0) { scroll.innerHTML = '<div class="notif-empty-state"><div class="nei">🔕</div><p>No notifications here.</p></div>'; return; }
    const ac = { accepted: '✅', rejected: '❌', announcement: '📢' };
    const av = { accepted: 'type-accepted', rejected: 'type-rejected', announcement: 'type-announcement' };
    scroll.innerHTML = items.map(function(n) {
        const sub = n.subtype || (n.type === 'announcement' ? 'announcement' : 'default');
        return '<div class="notif-item ' + (n.is_read ? '' : 'unread') + '" data-id="' + n.id + '" onclick="markOneRead(this,' + n.id + ')">'
             + '<div class="notif-avatar ' + (av[sub] || 'type-default') + '">' + (ac[sub] || '🔔') + '</div>'
             + '<div class="notif-item-body"><div class="notif-item-title">' + esc(n.title || 'Notification') + '</div>'
             + '<div class="notif-item-msg">' + esc(n.message || '') + '</div>'
             + '<div class="notif-item-time">' + (n.created_at ? timeAgo(n.created_at) : '') + '</div></div>'
             + (n.is_read ? '' : '<div class="notif-unread-dot"></div>') + '</div>';
    }).join('');
}
function markOneRead(el, id) {
    if (!el.classList.contains('unread')) return;
    el.classList.remove('unread');
    var dot = el.querySelector('.notif-unread-dot'); if (dot) dot.remove();
    var item = notifData.find(function(n) { return n.id == id; }); if (item) item.is_read = true;
    refreshBadge();
    fetch('/SYSARCH/user/mark_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) }).catch(function(){});
}
function markAllRead() {
    notifData.forEach(function(n) { n.is_read = true; }); renderNotifItems(); refreshBadge();
    fetch('/SYSARCH/user/mark_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ all: true }) }).catch(function(){});
}
function refreshBadge() {
    var u = notifData.filter(function(n) { return !n.is_read; }).length;
    var b = document.getElementById('notifBadge');
    b.textContent = u > 9 ? '9+' : u; b.classList.toggle('hidden', u === 0);
}
function timeAgo(dt) {
    var d = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (d < 60) return 'Just now'; if (d < 3600) return Math.floor(d/60)+'m ago';
    if (d < 86400) return Math.floor(d/3600)+'h ago'; return Math.floor(d/86400)+'d ago';
}
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── SESSIONS SEARCH ──────────────────────────────────────────────────────────
function filterSessions() {
    const q = document.getElementById('sessionSearch').value.toLowerCase();
    const tbl = document.getElementById('sessionsTable');
    if (!tbl) return;
    tbl.querySelectorAll('tbody tr').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>