<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

<?php
session_start();

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'students';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            $error = 'Database connection failed.';
        } else {
            $stmt = $conn->prepare("SELECT Password FROM student WHERE IdNumber = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['Password'])) {
                    $_SESSION['id_number'] = $username;
                    header('Location: home.php');
                    exit();
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'User not found.';
            }
            $stmt->close();
            $conn->close();
        }
    }
}

// If logged in, redirect to dashboard or stay
if (isset($_SESSION['id_number'])) {
    // User is logged in, show dashboard
} else {
    // Show login form
}
?>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .nav-left img {
            height: 36px;
        }

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

        .nav-links a:hover {
            background-color: rgba(255,255,255,0.15);
        }

        .nav-links a.active {
            background-color: rgba(255,255,255,0.12);
            font-weight: 600;
        }

        .btn-logout {
            background-color: #f0a500 !important;
            color: white !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
            padding: 6px 16px !important;
        }

        .btn-logout:hover {
            background-color: #d4920a !important;
        }

        /* ── PAGE LAYOUT ── */
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
            display: flex;
            gap: 60px;
            align-items: flex-start;
        }

        /* ── FORM SIDE ── */
        .form-side {
            flex: 1;
            min-width: 0;
        }

        .form-side h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

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
            border-color: #1a3a6b;
            box-shadow: 0 0 0 3px rgba(26,58,107,0.1);
            background: #fff;
        }

        .input-wrapper .icon {
            padding: 0 12px;
            color: #aab0be;
            font-size: 16px;
            flex-shrink: 0;
        }

        .input-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 12px 10px 0;
            font-size: 14px;
            color: #333;
            outline: none;
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
            background-color: #9757d6;
            transform: translateY(-1px);
        }

        .btn-save:active {
            transform: translateY(0);
        }


        
        @media (max-width: 768px) {
            .card {
                flex-direction: column;
                padding: 28px 24px;
                gap: 30px;
            }

            .illustration-side {
                width: 100%;
                padding-top: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .navbar h1 {
                display: none;
            }
        }

        /* LOGIN STYLES */
        .main {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 85vh;
        }

        .login-box {
            background: white;
            padding: 40px;
            width: 500px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .login-box h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .input-group label {
            font-size: 14px;
        }

        .input-group input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .login-btn {
            width: 100%;
            padding: 10px;
            background: #9757d6;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #7c42b3;
        }

        .register-link {
            margin-top: 15px;
            font-size: 14px;
        }

        .register-link a {
            color: #9757d6;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<?php
if (!isset($_SESSION['id_number'])) {
    // Show login page
    echo '
    <div class="navbar">
        <div class="nav-left">
            <img src="uclogo-removebg-preview.png" alt="UC Logo">
            <h1>College of Computer Studies Sit-in Monitoring System</h1>
        </div>
        <div class="nav-links">
            <a href="../landingpage.php">Home</a>
            <a href="../community.php">Community</a>
            <a href="../about.php">About</a>
            <a href="../login.php">Login</a>
            <a href="../register.php">Register</a>
        </div>
    </div>

    <div class="main">
    <div class="login-box">
    <h2>Login</h2>';
    if ($error) echo "<p style='color:red;'>$error</p>";
    echo '
    <form action="home.php" method="POST">
    <div class="input-group">
        <label>Username</label>
        <input type="text" name="username" required>
    </div>
    <div class="input-group">
        <label>Password</label>
        <input type="password" name="password" required>
    </div>
    <button class="login-btn" type="submit">Login</button>
    </form>
    <div class="register-link">Don\'t have an account? <a href="../register.php">Register</a></div>
    </div>
    </div>
    </body></html>';
    exit();
}
?>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <img src="uclogo-removebg-preview.png" alt="UC Logo">
            <h1>College of Computer Studies Sit-in Monitoring System</h1>
        </div>
        <div class="nav-links">
            <a href="notification.php">Notification ▾</a>
            <a href="home.php" class="active">Home</a>
            <a href="user_profile.php">Edit Profile</a>
            <a href="history.php">History</a>
            <a href="reservation.php">Reservation</a>
            <a href="logout.php" class="btn-logout">Log out</a>
        </div>
    </div>


</body>
</html>