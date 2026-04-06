<?php
session_start();

// Database connection
$host    = "localhost";
$dbname  = "students";
$db_user = "root";
$db_pass = "";

$conn = new mysqli($host, $db_user, $db_pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_number = trim($_POST["username"]);
    $password  = trim($_POST["password"]);

    if (empty($id_number) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        // ── HARDCODED ADMIN CHECK ──
        if ($id_number === "admin" && $password === "admin123") {
            $_SESSION["admin_id"]      = "admin";
            $_SESSION["admin_name"]    = "Administrator";
            $_SESSION["logged_in"]     = true;
            $_SESSION["role"]          = "admin";
            header("Location: admin/admin_home.php");
            exit();
        }

        // ── STUDENT LOGIN ──
        $stmt = $conn->prepare("SELECT * FROM student WHERE IdNumber = ?");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $student = $result->fetch_assoc();

            if (password_verify($password, $student["Password"])) {
                $_SESSION["student_id"]        = $student["IdNumber"];
                $_SESSION["student_name"]      = $student["FirstName"] . " " . $student["LastName"];
                $_SESSION["logged_in"]         = true;
                $_SESSION["role"]              = "student";
                $_SESSION["email"]             = $student["Email"];
                $_SESSION["course"]            = $student["Course"];
                $_SESSION["year_level"]        = $student["CourseLvl"];
                $_SESSION["sessions_remaining"] = $student["Sessions"] ?? 28;

                header("Location: user/user_home.php");
                exit();
            } else {
                $error = "Invalid ID Number or Password.";
            }
        } else {
            $error = "Invalid ID Number or Password.";
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - CCS Sit-in Monitoring System</title>
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

.btn-register {
    background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
    color: #fff !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    padding: 7px 18px !important;
    margin-left: 6px;
    box-shadow: 0 2px 8px rgba(240,165,0,0.4);
    transition: transform 0.15s, box-shadow 0.15s !important;
}

.btn-register:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important;
}

/* ── LOGIN SECTION ── */
.main {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 60px);
    padding: 40px 20px;
}

.login-card {
    background: var(--panel);
    width: 100%;
    max-width: 440px;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(15,38,83,0.08);
    border: 1px solid var(--border);
    overflow: hidden;
    animation: fadeUp 0.45s ease both;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

.login-card-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 22px 28px;
    text-align: center;
}

.login-card-header h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    color: white;
    letter-spacing: 0.3px;
}

.login-card-header p {
    font-size: 12.5px;
    color: rgba(255,255,255,0.65);
    margin-top: 4px;
}

.login-card-body {
    padding: 28px 28px 24px;
}

.error-msg {
    background: #fff0f0;
    color: #c0392b;
    border: 1px solid #f5c6cb;
    border-left: 4px solid #e74c3c;
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 13px;
}

.input-group {
    margin-bottom: 16px;
}

.input-group label {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.input-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    font-size: 13.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: var(--bg);
    transition: border-color 0.18s, box-shadow 0.18s;
    outline: none;
}

.input-group input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
    background: white;
}

.input-group input::placeholder {
    color: #aab4c8;
}

.login-btn {
    width: 100%;
    padding: 11px;
    background: linear-gradient(135deg, var(--navy), var(--navy-light));
    border: none;
    color: white;
    border-radius: 9px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    font-family: 'DM Sans', sans-serif;
    letter-spacing: 0.3px;
    margin-top: 4px;
    box-shadow: 0 3px 12px rgba(15,38,83,0.25);
    transition: transform 0.15s, box-shadow 0.15s;
}

.login-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 18px rgba(15,38,83,0.35);
}

.login-btn:active {
    transform: translateY(0);
}

.login-card-footer {
    text-align: center;
    padding: 0 28px 22px;
    font-size: 13px;
    color: var(--muted);
}

.login-card-footer a {
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s;
}

.login-card-footer a:hover {
    color: var(--navy);
    text-decoration: underline;
}

.divider {
    height: 1px;
    background: var(--border);
    margin: 0 28px 18px;
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
        <a href="login.php" class="active">Login</a>
        <a href="register.php" class="btn-register">Register</a>
    </div>
</div>

<!-- LOGIN -->
<div class="main">
    <div class="login-card">

        <div class="login-card-header">
            <h2>Welcome Back</h2>
            <p>Sign in to your student account</p>
        </div>

        <div class="login-card-body">

            <?php if (!empty($error)): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">

                <div class="input-group">
                    <label>ID Number</label>
                    <input type="text" name="username" placeholder="Enter your ID Number"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>

                <button class="login-btn" type="submit">Login</button>

            </form>

        </div>

        <div class="divider"></div>

        <div class="login-card-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>

    </div>
</div>

</body>
</html>