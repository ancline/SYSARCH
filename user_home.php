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

// Fetch notifications FROM announcements table
$notifications = [];
$notif_check = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($notif_check && $notif_check->num_rows > 0) {
    $nr = $conn->query("
        SELECT 
            admin_name  AS title,
            message,
            created_at,
            0           AS is_read
        FROM announcements
        ORDER BY created_at DESC
        LIMIT 20
    ");
    if ($nr) {
        while ($nrow = $nr->fetch_assoc()) {
            $notifications[] = $nrow;
        }
    }
}

// All announcements treated as unread (no per-student read tracking)
$unread_count = count($notifications);

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

        /* ── NOTIFICATION DROPDOWN ── */
        .notif-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .notif-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            font-weight: 500;
            padding: 7px 13px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0.2px;
            transition: background 0.18s, color 0.18s;
        }

        .notif-btn:hover { background: rgba(255,255,255,0.12); color: white; }
        .notif-btn.open  { background: rgba(255,255,255,0.18); color: white; }

        .notif-bell { font-size: 16px; line-height: 1; }

        .notif-badge {
            position: absolute;
            top: 4px; right: 6px;
            background: #e53535;
            color: white;
            font-size: 9px;
            font-weight: 700;
            min-width: 16px; height: 16px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--navy-mid);
            line-height: 1;
            animation: pulse-badge 2s ease infinite;
        }

        .notif-badge.hidden { display: none; }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.15); }
        }

        .notif-caret {
            font-size: 9px;
            opacity: 0.7;
            transition: transform 0.2s;
        }

        .notif-btn.open .notif-caret { transform: rotate(180deg); }

        /* ── DROPDOWN PANEL ── */
        .notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 360px;
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 12px 48px rgba(15,38,83,0.22), 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            opacity: 0;
            transform: translateY(-8px) scale(0.97);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            transform-origin: top right;
        }

        .notif-dropdown.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        .notif-drop-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 13px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .notif-drop-title {
            color: white;
            font-size: 13px; font-weight: 700;
            display: flex; align-items: center; gap: 8px;
            letter-spacing: 0.4px; text-transform: uppercase;
        }

        .notif-drop-title .hicon {
            width: 26px; height: 26px;
            background: rgba(255,255,255,0.15);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }

        .notif-mark-read {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            font-size: 11px; font-weight: 600;
            padding: 4px 10px; border-radius: 6px;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s;
        }

        .notif-mark-read:hover { background: rgba(255,255,255,0.28); }

        .notif-list {
            max-height: 380px;
            overflow-y: auto;
            padding: 10px;
            display: flex; flex-direction: column; gap: 6px;
        }

        .notif-list::-webkit-scrollbar { width: 4px; }
        .notif-list::-webkit-scrollbar-track { background: transparent; }
        .notif-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 8px; }

        .notif-item {
            border-radius: 10px;
            border: 1px solid var(--border);
            overflow: hidden;
            background: #fafbfd;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
            cursor: default;
        }

        .notif-item:hover { background: var(--tag-bg); border-color: #b8c5e0; box-shadow: 0 2px 8px rgba(15,38,83,0.07); }

        .notif-item.unread {
            background: #f0f4ff;
            border-color: #c0d0f0;
        }

        .notif-item.unread:hover { background: #e4ecff; }

        .notif-item-inner {
            padding: 10px 12px;
            display: flex; gap: 10px; align-items: flex-start;
        }

        .notif-icon-wrap {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--tag-bg);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }

        .notif-item.unread .notif-icon-wrap {
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
        }

        .notif-content { flex: 1; min-width: 0; }

        .notif-item-title {
            font-size: 12.5px; font-weight: 700; color: var(--navy);
            margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .notif-item-msg {
            font-size: 12px; color: #556;
            line-height: 1.5; display: -webkit-box;
            -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .notif-item-time {
            font-size: 10.5px; color: var(--muted);
            margin-top: 4px; font-weight: 500;
        }

        .notif-unread-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0; margin-top: 4px;
        }

        .notif-empty {
            text-align: center;
            padding: 32px 16px;
            color: var(--muted);
            font-size: 12.5px;
            font-style: italic;
        }

        .notif-empty .notif-empty-icon { font-size: 32px; margin-bottom: 8px; opacity: 0.5; }

        .notif-drop-footer {
            border-top: 1px solid var(--border);
            padding: 10px 14px;
            text-align: center;
        }

        .notif-view-all {
            font-size: 12px; font-weight: 600; color: var(--accent);
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
            transition: color 0.15s;
        }

        .notif-view-all:hover { color: var(--navy); }

        /* ── LAYOUT ── */
        .dashboard {
            display: grid;
            grid-template-columns: 340px 1fr 380px;
            gap: 20px; padding: 24px 28px;
            min-height: calc(100vh - 60px); align-items: start;
        }

        /* ── CARD BASE ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
            animation: fadeUp 0.5s ease both;
        }

        .card:nth-child(1) { animation-delay: 0.05s; }
        .card:nth-child(2) { animation-delay: 0.12s; }
        .card:nth-child(3) { animation-delay: 0.18s; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 18px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        /* ── STUDENT PANEL ── */
        .student-body { padding: 26px 24px; }

        .student-avatar-area {
            display: flex; align-items: center; gap: 18px;
            margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border);
        }

        .avatar-initials {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Serif Display', serif; font-size: 30px;
            color: white; letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(36,82,160,0.35);
            border: 3px solid white; outline: 2px solid var(--accent); flex-shrink: 0;
        }

        .student-name-display { text-align: left; }
        .student-name-display .sname  { font-size: 16px; font-weight: 700; color: var(--navy); line-height: 1.3; }
        .student-name-display .sid    { font-size: 12px; color: var(--muted); margin-top: 2px; font-weight: 500; }
        .student-name-display .scourse{ font-size: 12px; color: var(--accent); margin-top: 4px; font-weight: 600; }

        .info-rows { display: flex; flex-direction: column; gap: 0; }

        .info-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 0; border-bottom: 1px solid #f0f3f9;
        }

        .info-row:last-child { border-bottom: none; }

        .info-row .ri {
            width: 34px; height: 34px; background: var(--tag-bg); border-radius: 9px;
            display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;
        }

        .info-row .rl { color: var(--muted); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; line-height: 1; }
        .info-row .rv { color: var(--text); font-weight: 500; font-size: 13px; line-height: 1.3; }
        .info-row .rstack { display: flex; flex-direction: column; gap: 3px; }

        /* ── SESSION BADGE ── */
        .session-badge {
            display: flex; align-items: center; gap: 14px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            color: white; border-radius: 12px; padding: 14px 18px;
            margin-top: 20px; width: 100%;
            box-shadow: 0 3px 10px rgba(36,82,160,0.3);
        }

        .session-badge .session-icon { font-size: 26px; flex-shrink: 0; line-height: 1; }
        .session-badge .session-info  { display: flex; flex-direction: column; gap: 1px; }
        .session-badge .session-num   { font-family: 'DM Serif Display', serif; font-size: 28px; line-height: 1; }
        .session-badge .session-lbl   { font-size: 11px; font-weight: 600; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── ANNOUNCEMENTS ── */
        .ann-body {
            padding: 18px; display: flex; flex-direction: column;
            gap: 14px; max-height: 560px; overflow-y: auto;
        }

        .ann-body::-webkit-scrollbar { width: 5px; }
        .ann-body::-webkit-scrollbar-track { background: transparent; }
        .ann-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .ann-item {
            border-radius: 12px; border: 1px solid var(--border);
            overflow: hidden; transition: box-shadow 0.2s, transform 0.2s;
        }

        .ann-item:hover { box-shadow: 0 6px 20px rgba(15,38,83,0.1); transform: translateY(-2px); }

        .ann-item-header {
            background: var(--tag-bg); padding: 10px 14px;
            display: flex; align-items: center; gap: 8px;
        }

        .ann-dot   { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }
        .ann-meta  { font-size: 12px; font-weight: 700; color: var(--navy); }
        .ann-date  { font-size: 11px; color: var(--muted); margin-left: auto; }
        .ann-body-text { padding: 12px 14px; font-size: 13px; color: #445; line-height: 1.6; }
        .ann-empty     { padding: 12px 14px; font-size: 12px; color: var(--muted); font-style: italic; }

        /* ── RULES ── */
        .rules-body { padding: 24px 22px; max-height: 640px; overflow-y: auto; }

        .rules-body::-webkit-scrollbar { width: 5px; }
        .rules-body::-webkit-scrollbar-track { background: transparent; }
        .rules-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .rules-university {
            text-align: center; margin-bottom: 18px;
            padding-bottom: 16px; border-bottom: 2px solid var(--tag-bg);
        }

        .rules-university h3 { font-family: 'DM Serif Display', serif; font-size: 17px; color: var(--navy); margin-bottom: 3px; }
        .rules-university h4 { font-size: 11.5px; font-weight: 700; color: var(--navy-light); letter-spacing: 0.5px; margin-bottom: 10px; }

        .rules-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            color: white; font-size: 10px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase; padding: 4px 14px; border-radius: 20px;
        }

        .rules-intro {
            font-size: 12.5px; color: var(--muted); line-height: 1.65;
            margin-bottom: 16px; padding: 11px 14px;
            background: var(--tag-bg); border-radius: 9px; border-left: 3px solid var(--accent);
        }

        .rules-list { display: flex; flex-direction: column; gap: 9px; }

        .rule-item {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 11px 14px; border-radius: 10px;
            background: #fafbfd; border: 1px solid var(--border);
            transition: background 0.15s, border-color 0.15s;
        }

        .rule-item:hover { background: var(--tag-bg); border-color: #b8c5e0; }

        .rule-num {
            min-width: 24px; height: 24px;
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            color: white; border-radius: 50%; font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
        }

        .rule-text { font-size: 12.5px; line-height: 1.6; color: #3a4a6b; }

        @media (max-width: 960px) {
            .dashboard { grid-template-columns: 1fr; padding: 16px; }
            .notif-dropdown { width: 310px; right: -10px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">

        <!-- NOTIFICATION DROPDOWN -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" aria-haspopup="true" aria-expanded="false">
                <span class="notif-bell">🔔</span>
                Notification
                <span class="notif-caret">▾</span>
                <?php if ($unread_count > 0): ?>
                <span class="notif-badge" id="notifBadge">
                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                </span>
                <?php else: ?>
                <span class="notif-badge hidden" id="notifBadge"></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown" role="menu">
                <div class="notif-drop-header">
                    <div class="notif-drop-title">
                        <div class="hicon">🔔</div>
                        Notifications
                    </div>
                    <?php if (!empty($notifications)): ?>
                    <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="notif-list" id="notifList">
                    <?php if (empty($notifications)): ?>
                    <div class="notif-empty">
                        <div class="notif-empty-icon">🔕</div>
                        No notifications yet.
                    </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                        <?php
                            $is_unread = empty($n['is_read']);
                            $time_ago  = '';
                            $ts = strtotime($n['created_at']);
                            $diff = time() - $ts;
                            if ($diff < 60)           $time_ago = 'just now';
                            elseif ($diff < 3600)     $time_ago = floor($diff/60) . 'm ago';
                            elseif ($diff < 86400)    $time_ago = floor($diff/3600) . 'h ago';
                            else                      $time_ago = date('M j', $ts);
                        ?>
                        <div class="notif-item <?= $is_unread ? 'unread' : '' ?>">
                            <div class="notif-item-inner">
                                <div class="notif-icon-wrap">
                                    <?= $is_unread ? '📣' : '📭' ?>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-item-title">
                                        <?= htmlspecialchars($n['title'] ?? 'Announcement') ?>
                                    </div>
                                    <div class="notif-item-msg">
                                        <?= htmlspecialchars($n['message'] ?? '') ?>
                                    </div>
                                    <div class="notif-item-time"><?= $time_ago ?></div>
                                </div>
                                <?php if ($is_unread): ?>
                                <div class="notif-unread-dot"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notif-drop-footer">
                    <a href="notification.php" class="notif-view-all">View all notifications →</a>
                </div>
            </div>
        </div>
        <!-- END NOTIFICATION DROPDOWN -->

        <a href="user_home.php" class="active">Home</a>
        <a href="user_edit_profile.php">Edit Profile</a>
        <a href="history.php">History</a>
        <a href="reservation.php">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
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

            <div class="session-badge">
                <div class="session-icon">💻</div>
                <div class="session-info">
                    <span class="session-num"><?= htmlspecialchars($sessions_remaining) ?></span>
                    <span class="session-lbl">Sessions Remaining</span>
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

</div>

<script>
    // ── Toggle open/close ──
    function toggleNotif(e) {
        e.stopPropagation();
        const btn      = document.getElementById('notifBtn');
        const dropdown = document.getElementById('notifDropdown');
        const isOpen   = dropdown.classList.contains('visible');

        if (isOpen) {
            closeNotif();
        } else {
            btn.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            dropdown.classList.add('visible');
        }
    }

    function closeNotif() {
        const btn      = document.getElementById('notifBtn');
        const dropdown = document.getElementById('notifDropdown');
        btn.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        dropdown.classList.remove('visible');
    }

    // Click outside closes dropdown
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotif();
        }
    });

    // Escape key closes dropdown
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNotif();
    });

    // ── Mark all read (visual only — announcements have no per-student read state) ──
    function markAllRead() {
        document.querySelectorAll('.notif-item.unread').forEach(function(el) {
            el.classList.remove('unread');
            const icon = el.querySelector('.notif-icon-wrap');
            if (icon) icon.textContent = '📭';
            const dot = el.querySelector('.notif-unread-dot');
            if (dot) dot.remove();
        });

        const badge = document.getElementById('notifBadge');
        if (badge) badge.classList.add('hidden');
    }
</script>

</body>
</html>