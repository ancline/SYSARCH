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
        <a href="admin_home.php">Home</a>
        <a href="#">Search</a>
        <a href="admin_Student.php">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>



</body>
</html>