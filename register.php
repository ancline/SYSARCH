<?php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'students';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idNumber       = trim($_POST['idNumber']       ?? '');
    $lastName       = trim($_POST['lastName']       ?? '');
    $firstName      = trim($_POST['firstName']      ?? '');
    $middleName     = trim($_POST['middleName']     ?? '');
    $course         = trim($_POST['course']         ?? '');
    $courseLevel    = trim($_POST['courseLevel']    ?? '');
    $password       = $_POST['password']            ?? '';
    $repeatPassword = $_POST['repeatPassword']      ?? '';
    $email          = trim($_POST['email']          ?? '');
    $address        = trim($_POST['address']        ?? '');

    if ($password === '' || $repeatPassword === '') {
        $errors[] = 'Please enter and confirm your password.';
    } elseif ($password !== $repeatPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($idNumber === '' || $lastName === '' || $firstName === '' || $email === '' || $course === '' || $address === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if (empty($errors)) {
        $conn = new mysqli($dbHost, $dbUser, $dbPass);
        if ($conn->connect_error) {
            $errors[] = 'Database connection failed: ' . $conn->connect_error;
        } else {
            if (!$conn->select_db($dbName)) {
                if ($conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === false) {
                    $errors[] = 'Unable to create database: ' . $conn->error;
                } else {
                    $conn->select_db($dbName);
                }
            }

            if (empty($errors)) {
                $createTableSql = "CREATE TABLE IF NOT EXISTS student (
                    IdNumber   VARCHAR(50)  NOT NULL,
                    LastName   VARCHAR(100) NOT NULL,
                    FirstName  VARCHAR(100) NOT NULL,
                    MiddleName VARCHAR(100),
                    CourseLvl  TINYINT      NOT NULL,
                    Email      VARCHAR(255) NOT NULL,
                    Password   VARCHAR(255) NOT NULL,
                    Course     VARCHAR(255) NOT NULL,
                    Address    VARCHAR(255) NOT NULL,
                    PRIMARY KEY (IdNumber),
                    UNIQUE KEY (Email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                if ($conn->query($createTableSql) === false) {
                    $errors[] = 'Unable to create student table: ' . $conn->error;
                }
            }

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO student (IdNumber, LastName, FirstName, MiddleName, CourseLvl, Email, Password, Course, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if ($stmt === false) {
                    $errors[] = 'Query preparation failed: ' . $conn->error;
                } else {
                    $stmt->bind_param('ssssissss', $idNumber, $lastName, $firstName, $middleName, $courseLevel, $email, $passwordHash, $course, $address);

                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now <a href="login.php" style="color:var(--accent);font-weight:700;">log in</a>.';
                        $idNumber = $lastName = $firstName = $middleName = $courseLevel = $email = $course = $address = '';
                    } else {
                        if ($conn->errno === 1062) {
                            $errors[] = 'The provided ID number or email is already registered.';
                        } else {
                            $errors[] = 'Registration failed: ' . $stmt->error;
                        }
                    }
                    $stmt->close();
                }
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System - Register</title>
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

    .nav-left { display: flex; align-items: center; gap: 12px; }

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

    .nav-links { display: flex; align-items: center; gap: 2px; }

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

    .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }

    .nav-links a.active {
        background: rgba(255,255,255,0.18);
        color: white;
        font-weight: 700;
    }

    .btn-login {
        background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
        color: #fff !important;
        font-weight: 700 !important;
        border-radius: 8px !important;
        padding: 7px 18px !important;
        margin-left: 6px;
        box-shadow: 0 2px 8px rgba(240,165,0,0.4);
        transition: transform 0.15s, box-shadow 0.15s !important;
    }

    .btn-login:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important;
    }

    /* ── MAIN ── */
    .main {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 36px 20px 48px;
        min-height: calc(100vh - 60px);
    }

    /* ── CARD ── */
    .register-card {
        background: var(--panel);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(15,38,83,0.08);
        border: 1px solid var(--border);
        width: 100%;
        max-width: 520px;
        overflow: hidden;
        animation: fadeUp 0.45s ease both;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .card-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
        padding: 20px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-header h2 {
        font-family: 'DM Serif Display', serif;
        font-size: 20px;
        color: white;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        background: rgba(255,255,255,0.15);
        color: white;
        text-decoration: none;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 600;
        transition: background 0.15s;
    }

    .btn-back:hover { background: rgba(255,255,255,0.28); }

    .card-body { padding: 28px 28px 24px; }

    /* ── ALERTS ── */
    .alert {
        padding: 11px 16px;
        border-radius: 9px;
        margin-bottom: 20px;
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

    .alert-error ul { padding-left: 16px; margin: 0; }
    .alert-error ul li { margin-top: 3px; }

    /* ── SECTION LABEL ── */
    .section-label {
        font-size: 10.5px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin: 20px 0 12px;
    }

    .section-label:first-child { margin-top: 0; }

    .form-divider {
        height: 1px;
        background: var(--border);
        margin: 20px 0;
    }

    /* ── FORM ── */
    .form-group { margin-bottom: 14px; }

    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .input-wrap {
        display: flex;
        align-items: center;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        overflow: hidden;
        background: var(--bg);
        transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    }

    .input-wrap:focus-within {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
        background: white;
    }

    .input-wrap .iicon {
        padding: 0 12px;
        color: var(--muted);
        font-size: 14px;
        flex-shrink: 0;
    }

    .input-wrap input,
    .input-wrap select {
        flex: 1;
        border: none;
        background: transparent;
        padding: 10px 12px 10px 0;
        font-size: 13.5px;
        font-family: 'DM Sans', sans-serif;
        color: var(--text);
        outline: none;
    }

    .input-wrap select {
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
    }

    .input-wrap input::placeholder { color: #aab4c8; }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    /* ── REGISTER BUTTON ── */
    .btn-register {
        margin-top: 24px;
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

    .btn-register:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 18px rgba(15,38,83,0.32);
    }

    /* ── FOOTER ── */
    .card-footer {
        padding: 0 28px 22px;
        text-align: center;
        font-size: 13px;
        color: var(--muted);
        border-top: 1px solid var(--border);
        padding-top: 18px;
        margin-top: 4px;
    }

    .card-footer a {
        color: var(--accent);
        font-weight: 700;
        text-decoration: none;
    }

    .card-footer a:hover { text-decoration: underline; }

    @media (max-width: 560px) {
        .form-row { grid-template-columns: 1fr; }
        .card-body { padding: 22px 18px 18px; }
        .nav-title { display: none; }
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
        <a href="landingpage.php">Home</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php" class="active btn-login">Register</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="register-card">

        <div class="card-header">
            <h2>Create an Account</h2>
            <a href="landingpage.php" class="btn-back">← Back</a>
        </div>

        <div class="card-body">

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- PERSONAL INFO -->
                <div class="section-label">Personal Information</div>

                <div class="form-group">
                    <label>ID Number</label>
                    <div class="input-wrap">
                        <span class="iicon">🪪</span>
                        <input type="number" name="idNumber" placeholder="e.g. 3677937"
                               value="<?= htmlspecialchars($idNumber ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <div class="input-wrap">
                        <span class="iicon">👤</span>
                        <input type="text" name="lastName" placeholder="Last Name"
                               value="<?= htmlspecialchars($lastName ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <div class="input-wrap">
                            <span class="iicon">👤</span>
                            <input type="text" name="firstName" placeholder="First Name"
                                   value="<?= htmlspecialchars($firstName ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <div class="input-wrap">
                            <span class="iicon">👤</span>
                            <input type="text" name="middleName" placeholder="Middle Name"
                                   value="<?= htmlspecialchars($middleName ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <span class="iicon">📧</span>
                        <input type="email" name="email" placeholder="your@email.com"
                               value="<?= htmlspecialchars($email ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <div class="input-wrap">
                        <span class="iicon">📍</span>
                        <input type="text" name="address" placeholder="Home Address"
                               value="<?= htmlspecialchars($address ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-divider"></div>

                <!-- ACADEMIC INFO -->
                <div class="section-label">Academic Information</div>

                <div class="form-group">
                    <label>Course</label>
                    <div class="input-wrap">
                        <span class="iicon">🎓</span>
                        <select name="course" required>
                            <option value="" <?= empty($course ?? '') ? 'selected' : '' ?>>Select course…</option>
                            <?php
                            $courses = [
                                'Information Technology','Computer Engineering','Civil Engineering',
                                'Mechanical Engineering','Electrical Engineering','Industrial Engineering',
                                'Naval Architecture and Marine Engineering','Elementary Education (BEEd)',
                                'Secondary Education (BSEd)','Criminology','Commerce','Accountancy',
                                'Hotel and Restaurant Management','Customs Administration',
                                'Computer Secretarial','Industrial Psychology','AB Political Science','AB English',
                            ];
                            foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= (isset($course) && $course === $c) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Year Level</label>
                    <div class="input-wrap">
                        <span class="iicon">📚</span>
                        <select name="courseLevel" required>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                            <option value="<?= $y ?>" <?= (isset($courseLevel) && $courseLevel == $y) ? 'selected' : '' ?>>
                                Year <?= $y ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-divider"></div>

                <!-- PASSWORD -->
                <div class="section-label">Security</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <span class="iicon">🔒</span>
                            <input type="password" name="password" id="password"
                                   placeholder="••••••••" minlength="6" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-wrap">
                            <span class="iicon">🔒</span>
                            <input type="password" name="repeatPassword" id="repeatPassword"
                                   placeholder="••••••••" required>
                        </div>
                        <p id="passHint" style="font-size:11.5px;color:#e74c3c;margin-top:5px;display:none;">
                            Passwords do not match.
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-register">✅ Register</button>

            </form>
        </div>

        <div class="card-footer">
            Already have an account? <a href="login.php">Log in here</a>
        </div>

    </div>
</div>

<script>
    const pw  = document.getElementById('password');
    const pwc = document.getElementById('repeatPassword');
    const hint = document.getElementById('passHint');

    function checkPass() {
        hint.style.display = (pwc.value && pw.value !== pwc.value) ? 'block' : 'none';
    }

    pw.addEventListener('input', checkPass);
    pwc.addEventListener('input', checkPass);
</script>

</body>
</html>