<?php
session_start();

// Guard: only logged-in students can access this page
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');  // FIX #1: removed ../ prefix
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            background-color: #9757d6;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-left img { height: 36px; }

        .navbar h1 {
            font-size: 15px;
            font-weight: 600;
            color: white;
            letter-spacing: 0.2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 13.5px;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .nav-links a:hover { background-color: rgba(255,255,255,0.15); }

        .nav-links a.active {
            background-color: rgba(255,255,255,0.20);
            font-weight: 600;
        }

        .btn-logout {
            background-color: #f0a500 !important;
            color: white !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
            padding: 6px 16px !important;
        }

        .btn-logout:hover { background-color: #d4920a !important; }

        /* ── PAGE ── */
        .page-wrapper {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #666;
            margin-bottom: 16px;
        }

        .breadcrumb span {
            color: #1a3a6b;
            font-weight: 600;
        }

        /* ── CARD ── */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            padding: 40px 48px;
        }

        .card h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 28px;
        }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            border: 1.5px solid #dde1e9;
            border-radius: 8px;
            overflow: hidden;
            background: #fafbfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrapper:focus-within {
            border-color: #9757d6;
            box-shadow: 0 0 0 3px rgba(151,87,214,0.1);
            background: #fff;
        }

        .input-wrapper .icon {
            padding: 0 12px;
            color: #aab0be;
            font-size: 16px;
            flex-shrink: 0;
        }

        .input-wrapper input,
        .input-wrapper select {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 12px 10px 0;
            font-size: 14px;
            color: #333;
            outline: none;
        }

        /* FIX #2: select styling */
        .input-wrapper select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
        }

        .select-arrow {
            padding-right: 12px;
            color: #aab0be;
            pointer-events: none;
        }

        .input-wrapper input[readonly] {
            color: #888;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn-save {
            margin-top: 28px;
            width: 100%;
            padding: 12px;
            background-color: #9757d6;
            color: white;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-save:hover {
            background-color: #7c42b3;
            transform: translateY(-1px);
        }

        .btn-save:active { transform: translateY(0); }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        /* FIX #6: password confirm field hint */
        .pass-hint {
            font-size: 12px;
            color: #e53935;
            margin-top: 5px;
            display: none;
        }

        @media (max-width: 768px) {
            .card { padding: 28px 24px; }
            .form-row { grid-template-columns: 1fr; }
            .navbar h1 { display: none; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <h1>College of Computer Studies Sit-in Monitoring System</h1>
    </div>
    <div class="nav-links">
        <a href="notification.php">Notification ▾</a>
        <a href="user_home.php">Home</a>
        <a href="user_profile.php" class="active">Edit Profile</a>
        <a href="history.php">History</a>
        <a href="reservation.php">Reservation</a>
        <a href="logout.php" class="btn-logout">Log out</a>
    </div>
</div>

<div class="page-wrapper">
    <p class="breadcrumb">Dashboard &rsaquo; <span>Edit Profile</span></p>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">✅ Profile updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert-error">❌ <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Edit Profile</h2>

        <form action="user_update_profile.php" method="POST" id="profileForm">

            <!-- FIX #3: CSRF token -->
            <?php
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            ?>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>ID Number</label>
                <div class="input-wrapper">
                    <span class="icon">🪪</span>
                    <input type="text" name="id_number"
                           value="<?= htmlspecialchars($user['IdNumber']) ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <!-- FIX #4: required attribute added -->
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

            <div class="form-row">
                <!-- FIX #2: Year level is now a <select> that sends numeric value -->
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
                        <input type="text" name="course" required maxlength="100"
                               value="<?= htmlspecialchars($user['Course']) ?>"
                               placeholder="e.g. BSIT">
                    </div>
                </div>
            </div>

            <!-- FIX #6: Password + confirm field -->
            <div class="form-row">
                <div class="form-group">
                    <label>New Password
                        <small style="text-transform:none;font-weight:400;color:#aaa;">
                            (leave blank to keep current)
                        </small>
                    </label>
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

            <button type="submit" class="btn-save">Save Changes</button>

        </form>
    </div>
</div>

<script>
// FIX #6: Live password match check
const pw   = document.getElementById('password');
const pwc  = document.getElementById('password_confirm');
const hint = document.getElementById('passHint');

function checkPasswords() {
    if (pwc.value && pw.value !== pwc.value) {
        hint.style.display = 'block';
        pwc.style.borderColor = '#e53935';
    } else {
        hint.style.display = 'none';
        pwc.style.borderColor = '';
    }
}

pw.addEventListener('input', checkPasswords);
pwc.addEventListener('input', checkPasswords);

// Prevent form submit if passwords don't match
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