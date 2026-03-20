<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Community - CCS Sit-in Monitoring System</title>
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

        /* ── CONTENT ── */
        .content {
            max-width: 860px;
            margin: 36px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            animation: fadeUp 0.5s ease 0.1s both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── POST CARD ── */
        .post-card {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .post-card:hover {
            box-shadow: 0 8px 28px rgba(15,38,83,0.12);
            transform: translateY(-2px);
        }

        .post-header {
            padding: 16px 20px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--tag-bg);
        }

        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Serif Display', serif;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }

        .post-meta .pname {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--navy);
        }

        .post-meta .pdate {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 1px;
        }

        .post-tag {
            margin-left: auto;
            background: var(--tag-bg);
            color: var(--navy-light);
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid var(--border);
            letter-spacing: 0.3px;
        }

        .post-body {
            padding: 16px 20px;
        }

        .post-body h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .post-body p {
            font-size: 13px;
            color: #445;
            line-height: 1.65;
        }

        .post-footer {
            padding: 10px 20px 14px;
            display: flex;
            gap: 16px;
        }

        .post-action {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s;
        }

        .post-action:hover { color: var(--accent); }

        /* ── EMPTY STATE ── */
        .empty-state {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 52px 32px;
            text-align: center;
        }

        .empty-state .estate-icon { font-size: 42px; margin-bottom: 14px; }

        .empty-state h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.6;
            max-width: 360px;
            margin: 0 auto 22px;
        }

        .btn-login {
            display: inline-block;
            padding: 10px 28px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 3px 10px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(15,38,83,0.32);
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
        <a href="community.php" class="active">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php" class="btn-register">Register</a>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <h2>🏫 CCS Community</h2>
    <p>Stay updated with announcements, tips, and discussions from the College of Computer Studies.</p>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- Sample post cards -->
    <div class="post-card">
        <div class="post-header">
            <div class="post-avatar">CA</div>
            <div class="post-meta">
                <div class="pname">CCS Admin</div>
                <div class="pdate">February 11, 2026</div>
            </div>
            <span class="post-tag">📢 Announcement</span>
        </div>
        <div class="post-body">
            <h3>New Lab Schedule for AY 2025–2026</h3>
            <p>Please be informed that the laboratory schedules for the second semester have been updated. Students are advised to check the bulletin board or the monitoring system for their assigned laboratory hours.</p>
        </div>
        <div class="post-footer">
            <span class="post-action">👍 Like</span>
            <span class="post-action">💬 Comment</span>
            <span class="post-action">🔗 Share</span>
        </div>
    </div>

    <div class="post-card">
        <div class="post-header">
            <div class="post-avatar">CA</div>
            <div class="post-meta">
                <div class="pname">CCS Admin</div>
                <div class="pdate">May 8, 2024</div>
            </div>
            <span class="post-tag">🔥 News</span>
        </div>
        <div class="post-body">
            <h3>CCS Website Launch!</h3>
            <p>We are excited to announce the launch of our new website! Explore our latest products and services now. Thank you for being part of the CCS community.</p>
        </div>
        <div class="post-footer">
            <span class="post-action">👍 Like</span>
            <span class="post-action">💬 Comment</span>
            <span class="post-action">🔗 Share</span>
        </div>
    </div>

    <!-- Empty / login prompt -->
    <div class="empty-state">
        <div class="estate-icon">🔒</div>
        <h3>Join the Conversation</h3>
        <p>Log in to your student account to post, comment, and engage with the CCS community.</p>
        <a href="login.php" class="btn-login">Log In to Participate</a>
    </div>

</div>

</body>
</html>