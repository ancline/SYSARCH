<?php
session_start();

// Guard: only logged-in students can access this page
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'students';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$id_number = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $id_number);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
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
$nstmt->bind_param('s', $id_number);
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
    <title>CCS | Edit Profile</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 300;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-left img {
            height: 38px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .nav-title {
            font-size: 13.5px;
            font-weight: 600;
            color: rgba(255,255,255,0.92);
            letter-spacing: 0.3px;
            line-height: 1.3;
        }

        .nav-divider {
            width: 1px;
            height: 28px;
            background: rgba(255,255,255,0.2);
            margin: 0 6px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 7px 13px;
            border-radius: 6px;
            transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.12);
            color: white;
        }

        .nav-links a.active {
            background: rgba(255,255,255,0.18);
            color: white;
            font-weight: 700;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            padding: 7px 18px !important;
            margin-left: 6px;
            box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important;
        }

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
            display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2;
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

        /* ── PAGE ── */
        .page-wrapper {
            max-width: 780px;
            margin: 32px auto;
            padding: 0 20px 48px;
            animation: fadeUp 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .breadcrumb {
            font-size: 12.5px;
            color: var(--muted);
            margin-bottom: 16px;
            font-weight: 500;
        }

        .breadcrumb span {
            color: var(--navy-light);
            font-weight: 700;
        }

        /* ── ALERTS ── */
        .alert {
            padding: 11px 16px;
            border-radius: 9px;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: #edfaf3;
            color: #1a6e3f;
            border-color: #2ecc71;
        }

        .alert-error {
            background: #fff0f0;
            color: #c0392b;
            border-color: #e74c3c;
        }

        /* ── CARD ── */
        .card {
            background: var(--panel);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-header .hicon {
            width: 30px;
            height: 30px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .card-body {
            padding: 32px 36px 36px;
        }

        /* ── FORM ── */
        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .form-group label small {
            text-transform: none;
            font-weight: 400;
            color: #bbb;
            font-size: 11px;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            overflow: hidden;
            background: var(--bg);
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }

        .input-wrapper:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
            background: white;
        }

        .input-wrapper .icon {
            padding: 0 12px;
            color: var(--muted);
            font-size: 15px;
            flex-shrink: 0;
        }

        .input-wrapper input,
        .input-wrapper select {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 12px 10px 0;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            outline: none;
        }

        .input-wrapper select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
        }

        .select-arrow {
            padding-right: 12px;
            color: var(--muted);
            pointer-events: none;
            font-size: 12px;
        }

        .input-wrapper input[readonly] {
            color: var(--muted);
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .pass-hint {
            font-size: 11.5px;
            color: #e53935;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        /* ── DIVIDER ── */
        .form-divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 16px;
        }

        /* ── SAVE BUTTON ── */
        .btn-save {
            margin-top: 28px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.3px;
            box-shadow: 0 3px 12px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(15,38,83,0.32);
        }

        .btn-save:active { transform: translateY(0); }

        @media (max-width: 768px) {
            .card-body { padding: 24px 20px; }
            .form-row { grid-template-columns: 1fr; }
            .nav-title { display: none; }
            .notif-dropdown { width: calc(100vw - 20px); right: -10px; }
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

        <!-- NOTIFICATION BUTTON + DROPDOWN -->
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
        <a href="/SYSARCH/user/user_edit_profile.php" class="active">Edit Profile</a>
        <a href="/SYSARCH/user/history.php">History</a>
        <a href="/SYSARCH/user/reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <p class="breadcrumb">Dashboard &rsaquo; <span>Edit Profile</span></p>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Profile updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="hicon">✏️</div>
            Edit Profile
        </div>

        <div class="card-body">
            <form action="user_update_profile.php" method="POST" id="profileForm">

                <?php
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                ?>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- ID -->
                <div class="form-group">
                    <label>ID Number</label>
                    <div class="input-wrapper">
                        <span class="icon">🪪</span>
                        <input type="text" name="id_number"
                               value="<?= htmlspecialchars($user['IdNumber']) ?>" readonly>
                    </div>
                </div>

                <!-- Name -->
                <div class="section-label">Personal Information</div>

                <div class="form-group">
                    <label>Last Name</label>
                    <div class="input-wrapper">
                        <span class="icon">👤</span>
                        <input type="text" name="last_name" required maxlength="100"
                               value="<?= htmlspecialchars($user['LastName']) ?>"
                               placeholder="Last Name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <div class="input-wrapper">
                            <span class="icon">👤</span>
                            <input type="text" name="first_name" required maxlength="100"
                                   value="<?= htmlspecialchars($user['FirstName']) ?>"
                                   placeholder="First Name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <div class="input-wrapper">
                            <span class="icon">👤</span>
                            <input type="text" name="middle_name" maxlength="100"
                                   value="<?= htmlspecialchars($user['MiddleName']) ?>"
                                   placeholder="Middle Name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <span class="icon">📧</span>
                        <input type="email" name="email" required maxlength="150"
                               value="<?= htmlspecialchars($user['Email']) ?>"
                               placeholder="your@email.com">
                    </div>
                </div>

                <div class="form-divider"></div>
                <div class="section-label">Academic Information</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Year Level</label>
                        <div class="input-wrapper">
                            <span class="icon">📚</span>
                            <select name="year_level" required>
                                <option value="1" <?= $user['CourseLvl'] == 1 ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $user['CourseLvl'] == 2 ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $user['CourseLvl'] == 3 ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $user['CourseLvl'] == 4 ? 'selected' : '' ?>>4th Year</option>
                            </select>
                            <span class="select-arrow">▾</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Course</label>
                        <div class="input-wrapper">
                            <span class="icon">🎓</span>
                            <select name="course" required>
                                <option value="" disabled <?= empty($user['Course']) ? 'selected' : '' ?>>Select Course</option>
                                <?php
                                $courses = [
                                    'Information Technology',
                                    'Computer Engineering',
                                    'Civil Engineering',
                                    'Mechanical Engineering',
                                    'Electrical Engineering',
                                    'Industrial Engineering',
                                    'Naval Architecture and Marine Engineering',
                                    'Elementary Education (BEEd)',
                                    'Secondary Education (BSEd)',
                                    'Criminology',
                                    'Commerce',
                                    'Accountancy',
                                    'Hotel and Restaurant Management',
                                    'Customs Administration',
                                    'Computer Secretarial',
                                    'Industrial Psychology',
                                    'AB Political Science',
                                    'AB English',
                                ];
                                foreach ($courses as $course): ?>
                                    <option value="<?= htmlspecialchars($course) ?>"
                                        <?= $user['Course'] === $course ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="select-arrow">▾</span>
                        </div>
                    </div>
                </div>

                <div class="form-divider"></div>
                <div class="section-label">Change Password <small style="text-transform:none;font-weight:400;color:#bbb;font-size:11px;">(leave blank to keep current)</small></div>

                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-wrapper">
                            <span class="icon">🔒</span>
                            <input type="password" name="password" id="password"
                                   placeholder="••••••••" minlength="6">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="input-wrapper">
                            <span class="icon">🔒</span>
                            <input type="password" name="password_confirm" id="password_confirm"
                                   placeholder="••••••••">
                        </div>
                        <p class="pass-hint" id="passHint">Passwords do not match.</p>
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Save Changes</button>

            </form>
        </div>
    </div>
</div>

<script>
const pw   = document.getElementById('password');
const pwc  = document.getElementById('password_confirm');
const hint = document.getElementById('passHint');

function checkPasswords() {
    if (pwc.value && pw.value !== pwc.value) {
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}

pw.addEventListener('input', checkPasswords);
pwc.addEventListener('input', checkPasswords);

document.getElementById('profileForm').addEventListener('submit', function(e) {
    if (pw.value && pw.value !== pwc.value) {
        e.preventDefault();
        hint.style.display = 'block';
        pwc.focus();
    }
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
    if (notifOpen && wrapper && !wrapper.contains(e.target)) closeNotifDropdown();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNotifDropdown();
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