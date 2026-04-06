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
$conn->close();

if (!$user) {
    die('User not found.');
}
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
            z-index: 100;
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
        <a href="/SYSARCH/user/notification.php">Notification ▾</a>
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
</script>

</body>
</html>