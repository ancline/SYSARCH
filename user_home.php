<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Sit-in Monitoring System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

        /* ── MAIN ── */
        .main {
            padding: 60px 20px;
            text-align: center;
        }

        .main h2 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #333;
        }

        .main p {
            font-size: 18px;
            margin-bottom: 30px;
            color: #555;
        }

        .btn {
            padding: 10px 20px;
            background-color: #1e4f91;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn:hover { background-color: #163a6b; }

        @media (max-width: 768px) {
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
        <a href="user_home.php" class="active">Home</a>
        <a href="user_edit_profile.php">Edit Profile</a>
        <a href="history.php">History</a>
        <a href="reservation.php">Reservation</a>
        <a href="logout.php" class="btn-logout">Log out</a>
    </div>
</div>

<div class="main">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['student_name'] ?? 'Student') ?>!</h2>
    <p>College of Computer Studies Sit-in Monitoring System</p>
</div>

</body>
</html>