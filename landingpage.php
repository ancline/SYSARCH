<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System</title>
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

/* ── MAIN ── */
.main {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 60px);
    padding: 40px 20px;
}

/* ── WELCOME CARD ── */
.welcome-card {
    background: var(--panel);
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(15,38,83,0.10);
    border: 1px solid var(--border);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    animation: fadeUp 0.5s ease both;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card-top {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 36px 40px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
}

.card-top img {
    width: 110px;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
    margin-bottom: 18px;
}

.card-top h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    color: white;
    text-align: center;
    line-height: 1.35;
    letter-spacing: 0.2px;
}

.card-body {
    padding: 28px 36px 32px;
    text-align: center;
}

.card-body p {
    font-size: 14px;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 28px;
}

.features {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.feature-pill {
    background: var(--tag-bg);
    color: var(--navy-light);
    font-size: 11.5px;
    font-weight: 600;
    padding: 5px 13px;
    border-radius: 20px;
    border: 1px solid var(--border);
    letter-spacing: 0.2px;
}

.btn-start {
    display: inline-block;
    padding: 12px 36px;
    background: linear-gradient(135deg, var(--navy), var(--navy-light));
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 3px 12px rgba(15,38,83,0.25);
    transition: transform 0.15s, box-shadow 0.15s;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(15,38,83,0.35);
}

.card-footer {
    padding: 0 36px 24px;
    text-align: center;
    font-size: 13px;
    color: var(--muted);
}

.card-footer a {
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
}

.card-footer a:hover {
    text-decoration: underline;
}

.divider {
    height: 1px;
    background: var(--border);
    margin: 0 36px 20px;
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
        <a href="landingpage.php" class="active">Home</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php" class="btn-register">Register</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="welcome-card">

        <div class="card-top">
            <img src="ucmainccslogo.png" alt="CCS Logo">
            <h2>Welcome to the Sit-in Monitoring System</h2>
        </div>

        <div class="card-body">
            <p>Monitor student laboratory sit-ins, manage sessions, and track activity inside the College of Computer Studies laboratories.</p>

            <div class="features">
                <span class="feature-pill">📋 Session Tracking</span>
                <span class="feature-pill">🖥️ Lab Monitoring</span>
                <span class="feature-pill">📅 Reservations</span>
            </div>

            <a href="login.php" class="btn-start">Get Started</a>
        </div>

        <div class="divider"></div>

        <div class="card-footer">
            Already have an account? <a href="login.php">Log in here</a>
        </div>

    </div>
</div>

</body>
</html>