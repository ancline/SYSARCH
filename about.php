<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About - CCS Sit-in Monitoring System</title>
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

        /* ── PAGE HERO ── */
        .hero {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 48px 40px 40px;
            text-align: center;
            animation: fadeUp 0.4s ease both;
        }

        .hero h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 28px;
            color: white;
            margin-bottom: 8px;
        }

        .hero p {
            font-size: 14px;
            color: rgba(255,255,255,0.65);
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.6;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── CONTENT ── */
        .content {
            max-width: 820px;
            margin: 36px auto;
            padding: 0 24px 48px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            animation: fadeUp 0.5s ease 0.1s both;
        }

        /* ── SECTION CARD ── */
        .section-card {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            overflow: hidden;
        }

        .section-card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 13px 20px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .section-card-header .hicon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .section-card-body {
            padding: 22px 24px;
        }

        .section-card-body p {
            font-size: 13.5px;
            color: #445;
            line-height: 1.75;
            margin-bottom: 12px;
        }

        .section-card-body p:last-child { margin-bottom: 0; }

        /* ── FEATURE GRID ── */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            padding: 22px 24px;
        }

        .feature-item {
            background: var(--tag-bg);
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 16px;
            transition: background 0.15s, border-color 0.15s;
        }

        .feature-item:hover {
            background: #dde3f5;
            border-color: #b8c5e0;
        }

        .feature-item .ficon { font-size: 24px; margin-bottom: 8px; }

        .feature-item h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 4px;
        }

        .feature-item p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* ── TEAM GRID ── */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            padding: 22px 24px;
        }

        .team-card {
            text-align: center;
            padding: 20px 12px;
            border-radius: 10px;
            background: var(--tag-bg);
            border: 1px solid var(--border);
        }

        .team-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: white;
            margin: 0 auto 10px;
            box-shadow: 0 4px 12px rgba(36,82,160,0.25);
        }

        .team-card .tname {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 3px;
        }

        .team-card .trole {
            font-size: 11.5px;
            color: var(--muted);
        }

        /* ── CTA ── */
        .cta-card {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            border-radius: 14px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 4px 18px rgba(15,38,83,0.18);
        }

        .cta-card h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: white;
            margin-bottom: 8px;
        }

        .cta-card p {
            font-size: 13.5px;
            color: rgba(255,255,255,0.65);
            margin-bottom: 22px;
        }

        .btn-start {
            display: inline-block;
            padding: 11px 32px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: white;
            text-decoration: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 3px 12px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 18px rgba(240,165,0,0.5);
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
        <a href="about.php" class="active">About</a>
        <a href="login.php">Login</a>
        <a href="register.php" class="btn-register">Register</a>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <h2>About the System</h2>
    <p>Learn about the CCS Sit-in Monitoring System — what it does, who built it, and why it matters.</p>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- About -->
    <div class="section-card">
        <div class="section-card-header">
            <div class="hicon">ℹ️</div>
            About the System
        </div>
        <div class="section-card-body">
            <p>The <strong>CCS Sit-in Monitoring System</strong> is a web-based platform developed for the College of Information and Computer Studies at the University of Cebu. It streamlines the management of student laboratory sit-in sessions, replacing manual logbooks with a fast and reliable digital solution.</p>
            <p>The system allows students to register, reserve laboratory seats, track their remaining sessions, and view their sit-in history — all from a single dashboard. Administrators can monitor real-time lab usage, post announcements, and generate reports with ease.</p>
        </div>
    </div>

    <!-- Features -->
    <div class="section-card">
        <div class="section-card-header">
            <div class="hicon">⚙️</div>
            Key Features
        </div>
        <div class="feature-grid">
            <div class="feature-item">
                <div class="ficon">🖥️</div>
                <h4>Lab Monitoring</h4>
                <p>Real-time tracking of student sit-ins across all CCS laboratories.</p>
            </div>
            <div class="feature-item">
                <div class="ficon">📅</div>
                <h4>Reservations</h4>
                <p>Students can reserve lab seats in advance through the system.</p>
            </div>
            <div class="feature-item">
                <div class="ficon">📋</div>
                <h4>Session History</h4>
                <p>Full sit-in history and session usage logs per student.</p>
            </div>
            <div class="feature-item">
                <div class="ficon">📢</div>
                <h4>Announcements</h4>
                <p>Admins can broadcast important updates directly to students.</p>
            </div>
            <div class="feature-item">
                <div class="ficon">🔐</div>
                <h4>Secure Login</h4>
                <p>Role-based access for students and administrators.</p>
            </div>
            <div class="feature-item">
                <div class="ficon">📊</div>
                <h4>Reports</h4>
                <p>Admin-facing reports for lab utilization and session data.</p>
            </div>
        </div>
    </div>

    <!-- Developer -->
    <div class="section-card">
        <div class="section-card-header">
            <div class="hicon">👨‍💻</div>
            Developers
        </div>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-avatar">JS</div>
                <div class="tname">Jeff Salimbangon</div>
                <div class="trole">Lead Developer</div>
            </div>
            <div class="team-card">
                <div class="team-avatar">UC</div>
                <div class="tname">University of Cebu</div>
                <div class="trole">BSIT — CCS</div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-card">
        <h3>Ready to Get Started?</h3>
        <p>Log in to your account to access the monitoring system and manage your laboratory sessions.</p>
        <a href="login.php" class="btn-start">Log In Now</a>
    </div>

</div>

</body>
</html>