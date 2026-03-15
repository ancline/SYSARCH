<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ── ADMIN CHECK ──
    $admin_username = 'admin';
    $admin_password = 'admin123';

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin'] = true;
        $_SESSION['username'] = $username;
        header('Location: admin/home.php');
        exit();
    }

    // ── STUDENT CHECK ──
    $dbHost = '127.0.0.1';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'students';

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT * FROM student WHERE IdNumber = ? OR Email = ?");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($user && password_verify($password, $user['Password'])) {
        $_SESSION['id_number'] = $user['IdNumber'];
        $_SESSION['username']  = $user['FirstName'];
        header('Location: student/home.php');
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - CCS Sit-in Monitoring System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f5f6fa;
}

/* NAVBAR */
.navbar {
    background: #9757d6;
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.nav-left {
    display: flex;
    align-items: center;
    gap: 5px;
}

.nav-left img { height: 40px; }

.navbar h1 {
    font-size: 18px;
    font-weight: normal;
}

.nav-links a {
    color: white;
    text-decoration: none;
    margin-left: 20px;
    font-size: 14px;
}

.nav-links a:hover { text-decoration: underline; }

/* LOGIN SECTION */
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

.error-msg {
    background: #fde8e8;
    color: #c0392b;
    border: 1px solid #f5c6cb;
    padding: 10px;
    border-radius: 5px;
    font-size: 14px;
    margin-bottom: 15px;
}

.input-group {
    margin-bottom: 15px;
    text-align: left;
}

.input-group label { font-size: 14px; }

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
    font-size: 15px;
}

.login-btn:hover { background: #7c42b3; }

.register-link {
    margin-top: 15px;
    font-size: 14px;
}

.register-link a {
    color: #9757d6;
    text-decoration: none;
}

.register-link a:hover { text-decoration: underline; }
</style>
</head>

<body>

<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <h1>College of Computer Studies Sit-in Monitoring System</h1>
    </div>
    <div class="nav-links">
        <a href="landingpage.php">Home</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>
</div>

<div class="main">
    <div class="login-box">

        <h2>Login</h2>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">

            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button class="login-btn" type="submit">Login</button>

        </form>

        <div class="register-link">Don't have an account? <a href="register.php">Register</a></div>

    </div>
</div>

</body>
</html>