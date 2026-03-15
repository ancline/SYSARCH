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
            background-color: #ffffff;
        }

        /* ── TOP NAV ── */
        .top-nav {
            background-color: #9757d6;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .top-nav .brand {
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: .2px;
            white-space: nowrap;
        }
        .top-nav nav { display: flex; align-items: center; gap: 4px; }
        .top-nav nav a {
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13.5px;
            font-weight: 500;
            transition: background .2s;
            white-space: nowrap;
        }
        .top-nav nav a:hover {
            background: rgba(255,255,255,.15);
        }
        .top-nav nav a.active {
            background: rgba(255,255,255,.12);
            font-weight: 600;
        }

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

        .btn:hover {
            background-color: #163a6b;
        }
    </style>
</head>
<body>

    <header class="top-nav">
    <div style="display:flex;align-items:center;gap:10px;">
        <img src="/SYSARCH/uclogo-removebg-preview.png" alt="UC Logo" style="height:36px;">
        <span class="brand">College of Computer Studies Admin</span>
    </div>
    <nav>
        <a href="#" class="active">Home</a>
        <a href="#">Search</a>
        <a href="#">Students</a>
        <a href="#">Sit-in</a>
        <a href="#">View Sit-in Records</a>
        <a href="/SYSARCH/admin/sit-in_report.php">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </nav>
</header>

    

</body>
</html>