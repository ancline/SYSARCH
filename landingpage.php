<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display&display=swap" rel="stylesheet">

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

.nav-divider {
    width: 1px;
    height: 28px;
    background: rgba(255,255,255,0.2);
    margin: 0 6px;
}

.nav-title {
    font-size: 13.5px;
    font-weight: 600;
    color: rgba(255,255,255,0.92);
    letter-spacing: 0.3px;
    line-height: 1.3;
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
    color: #7a4800 !important;
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

/* ── MAIN LAYOUT ── */
.main {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: calc(100vh - 60px);
    padding: 48px 24px;
    gap: 24px;
    flex-wrap: wrap;
}

/* ── WELCOME CARD ── */
.welcome-card {
    background: var(--panel);
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(15,38,83,0.10);
    border: 1px solid var(--border);
    width: 100%;
    max-width: 460px;
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

.card-footer a:hover { text-decoration: underline; }

.divider {
    height: 1px;
    background: var(--border);
    margin: 0 36px 20px;
}

/* ── LEADERBOARD CARD ── */
.leaderboard-card {
    background: var(--panel);
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(15,38,83,0.10);
    border: 1px solid var(--border);
    width: 100%;
    max-width: 460px;
    overflow: hidden;
    animation: fadeUp 0.5s 0.1s ease both;
}

.lb-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 24px 28px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.lb-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    background: rgba(240,165,0,0.2);
    border: 1px solid rgba(240,165,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.lb-title-wrap h3 {
    font-family: 'DM Serif Display', serif;
    font-size: 18px;
    color: white;
    line-height: 1.2;
}

.lb-title-wrap p {
    font-size: 12px;
    color: rgba(255,255,255,0.6);
    margin-top: 3px;
}

.lb-body { padding: 20px 22px 4px; }

/* Tabs */
.lb-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
}

.lb-tab {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: transparent;
    cursor: pointer;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
    transition: all 0.15s;
}

.lb-tab.active {
    background: var(--navy);
    color: white;
    border-color: var(--navy);
}

.lb-tab:hover:not(.active) {
    background: var(--tag-bg);
    color: var(--navy);
}

/* Podium */
.podium {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 0 8px;
}

.podium-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    flex: 1;
    min-width: 0;
}

.podium-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    position: relative;
    flex-shrink: 0;
}

.podium-crown {
    position: absolute;
    top: -14px;
    font-size: 14px;
    line-height: 1;
}

.podium-name {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text);
    text-align: center;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.podium-sessions {
    font-size: 10.5px;
    color: var(--muted);
    text-align: center;
}

.podium-bar {
    width: 100%;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
    color: white;
    transition: height 0.4s ease;
}

.p1-bar { height: 74px; background: linear-gradient(180deg, var(--gold) 0%, var(--gold-light) 100%); color: #7a4800; }
.p2-bar { height: 54px; background: linear-gradient(180deg, #c8d0e0 0%, #dde3ee 100%); color: var(--muted); }
.p3-bar { height: 40px; background: linear-gradient(180deg, #d4a574 0%, #e8c9a0 100%); color: #7a5a30; }

/* Rank list */
.rank-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
    padding-bottom: 20px;
}

.rank-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 13px;
    border-radius: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    transition: background 0.15s, border-color 0.15s;
}

.rank-item:hover {
    background: #e4e9f5;
    border-color: #b8c4dc;
}

.rank-num {
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
    min-width: 18px;
    text-align: center;
}

.rank-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}

.rank-info { flex: 1; min-width: 0; }

.rank-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rank-id {
    font-size: 11px;
    color: var(--muted);
    margin-top: 1px;
}

.rank-sessions {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    flex-shrink: 0;
}

.rank-sessions span {
    font-size: 10px;
    font-weight: 400;
    color: var(--muted);
    display: block;
    text-align: right;
    margin-top: 1px;
}

.lb-footer {
    border-top: 1px solid var(--border);
    padding: 14px 22px;
    text-align: center;
}

.lb-footer a {
    font-size: 13px;
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
}

.lb-footer a:hover { text-decoration: underline; }

/* Avatar color classes */
.av-gold   { background: #fef3d0; color: #7a4800; }
.av-blue   { background: #e0eaff; color: #1a3a80; }
.av-bronze { background: #f5e8d8; color: #7a5a30; }
.av-teal   { background: #d8f5f0; color: #0f5a50; }
.av-red    { background: #ffe8e8; color: #801a1a; }
.av-green  { background: #e2f5e0; color: #1a6010; }
.av-purple { background: #ede8ff; color: #3a1a80; }
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

    <!-- WELCOME CARD -->
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

    <!-- LEADERBOARD CARD -->
    <div class="leaderboard-card">
        <div class="lb-header">
            <div class="lb-icon">🏆</div>
            <div class="lb-title-wrap">
                <h3>Top Sit-in Students</h3>
                <p>Most lab sessions this semester</p>
            </div>
        </div>

        <div class="lb-body">

            <!-- Tabs -->
            <div class="lb-tabs">
                <button class="lb-tab active" onclick="setTab(this, 'week')">This Week</button>
                <button class="lb-tab" onclick="setTab(this, 'month')">This Month</button>
                <button class="lb-tab" onclick="setTab(this, 'all')">All Time</button>
            </div>

            <!-- Podium top 3 -->
            <div class="podium">
                <!-- 2nd place -->
                <div class="podium-item">
                    <div class="podium-avatar av-blue" id="p2-avatar">MR</div>
                    <div class="podium-name" id="p2-name">Maria R.</div>
                    <div class="podium-sessions" id="p2-sess">18 sessions</div>
                    <div class="podium-bar p2-bar">2</div>
                </div>
                <!-- 1st place -->
                <div class="podium-item">
                    <div class="podium-avatar av-gold" id="p1-avatar" style="width:50px;height:50px;font-size:15px;position:relative;">
                        <span class="podium-crown">👑</span>
                        JD
                    </div>
                    <div class="podium-name" id="p1-name">Juan D.</div>
                    <div class="podium-sessions" id="p1-sess">24 sessions</div>
                    <div class="podium-bar p1-bar">1</div>
                </div>
                <!-- 3rd place -->
                <div class="podium-item">
                    <div class="podium-avatar av-bronze" id="p3-avatar">AL</div>
                    <div class="podium-name" id="p3-name">Ana L.</div>
                    <div class="podium-sessions" id="p3-sess">15 sessions</div>
                    <div class="podium-bar p3-bar">3</div>
                </div>
            </div>

            <!-- Ranks 4–7 -->
            <div class="rank-list" id="rankList">
                <div class="rank-item">
                    <div class="rank-num">4</div>
                    <div class="rank-avatar av-teal">KC</div>
                    <div class="rank-info">
                        <div class="rank-name">Karl C.</div>
                        <div class="rank-id">2021-00412</div>
                    </div>
                    <div class="rank-sessions">12 <span>sessions</span></div>
                </div>
                <div class="rank-item">
                    <div class="rank-num">5</div>
                    <div class="rank-avatar av-red">SR</div>
                    <div class="rank-info">
                        <div class="rank-name">Sofia R.</div>
                        <div class="rank-id">2022-00871</div>
                    </div>
                    <div class="rank-sessions">11 <span>sessions</span></div>
                </div>
                <div class="rank-item">
                    <div class="rank-num">6</div>
                    <div class="rank-avatar av-green">BM</div>
                    <div class="rank-info">
                        <div class="rank-name">Ben M.</div>
                        <div class="rank-id">2021-01103</div>
                    </div>
                    <div class="rank-sessions">9 <span>sessions</span></div>
                </div>
                <div class="rank-item">
                    <div class="rank-num">7</div>
                    <div class="rank-avatar av-purple">LP</div>
                    <div class="rank-info">
                        <div class="rank-name">Lena P.</div>
                        <div class="rank-id">2023-00235</div>
                    </div>
                    <div class="rank-sessions">7 <span>sessions</span></div>
                </div>
            </div>
        </div><!-- /.lb-body -->

        <div class="lb-footer">
            <a href="leaderboard.php">View full leaderboard →</a>
        </div>
    </div><!-- /.leaderboard-card -->

</div><!-- /.main -->

<script>
const leaderboardData = {
    week: {
        p1: { initials: 'JD', name: 'Juan D.',  sessions: 24, av: 'av-gold'   },
        p2: { initials: 'MR', name: 'Maria R.', sessions: 18, av: 'av-blue'   },
        p3: { initials: 'AL', name: 'Ana L.',   sessions: 15, av: 'av-bronze' },
        rest: [
            { rank:4, initials:'KC', name:'Karl C.',  id:'2021-00412', sessions:12, av:'av-teal'   },
            { rank:5, initials:'SR', name:'Sofia R.', id:'2022-00871', sessions:11, av:'av-red'    },
            { rank:6, initials:'BM', name:'Ben M.',   id:'2021-01103', sessions: 9, av:'av-green'  },
            { rank:7, initials:'LP', name:'Lena P.',  id:'2023-00235', sessions: 7, av:'av-purple' },
        ]
    },
    month: {
        p1: { initials: 'JD', name: 'Juan D.',  sessions: 98, av: 'av-gold'   },
        p2: { initials: 'SR', name: 'Sofia R.', sessions: 87, av: 'av-red'    },
        p3: { initials: 'KC', name: 'Karl C.',  sessions: 76, av: 'av-teal'   },
        rest: [
            { rank:4, initials:'MR', name:'Maria R.', id:'2022-00510', sessions:65, av:'av-blue'   },
            { rank:5, initials:'AL', name:'Ana L.',   id:'2021-00780', sessions:54, av:'av-bronze' },
            { rank:6, initials:'LP', name:'Lena P.',  id:'2023-00235', sessions:43, av:'av-purple' },
            { rank:7, initials:'BM', name:'Ben M.',   id:'2021-01103', sessions:38, av:'av-green'  },
        ]
    },
    all: {
        p1: { initials: 'MR', name: 'Maria R.', sessions: 312, av: 'av-blue'   },
        p2: { initials: 'JD', name: 'Juan D.',  sessions: 287, av: 'av-gold'   },
        p3: { initials: 'KC', name: 'Karl C.',  sessions: 254, av: 'av-teal'   },
        rest: [
            { rank:4, initials:'AL', name:'Ana L.',   id:'2021-00780', sessions:210, av:'av-bronze' },
            { rank:5, initials:'BM', name:'Ben M.',   id:'2021-01103', sessions:198, av:'av-green'  },
            { rank:6, initials:'SR', name:'Sofia R.', id:'2022-00871', sessions:175, av:'av-red'    },
            { rank:7, initials:'LP', name:'Lena P.',  id:'2023-00235', sessions:142, av:'av-purple' },
        ]
    }
};

function setTab(btn, key) {
    document.querySelectorAll('.lb-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    const d = leaderboardData[key];

    // Update podium avatars & text (keep initials in HTML for p1 since it has the crown span)
    document.getElementById('p1-sess').textContent = d.p1.sessions + ' sessions';
    document.getElementById('p2-sess').textContent = d.p2.sessions + ' sessions';
    document.getElementById('p3-sess').textContent = d.p3.sessions + ' sessions';
    document.getElementById('p1-name').textContent = d.p1.name;
    document.getElementById('p2-name').textContent = d.p2.name;
    document.getElementById('p3-name').textContent = d.p3.name;

    // Update avatar classes & initials
    const p1av = document.getElementById('p1-avatar');
    p1av.className = 'podium-avatar ' + d.p1.av;
    p1av.style.cssText = 'width:50px;height:50px;font-size:15px;position:relative;';
    p1av.innerHTML = '<span class="podium-crown">👑</span>' + d.p1.initials;

    const p2av = document.getElementById('p2-avatar');
    p2av.className = 'podium-avatar ' + d.p2.av;
    p2av.textContent = d.p2.initials;

    const p3av = document.getElementById('p3-avatar');
    p3av.className = 'podium-avatar ' + d.p3.av;
    p3av.textContent = d.p3.initials;

    // Update rank list
    const list = document.getElementById('rankList');
    list.innerHTML = d.rest.map(r => `
        <div class="rank-item">
            <div class="rank-num">${r.rank}</div>
            <div class="rank-avatar ${r.av}">${r.initials}</div>
            <div class="rank-info">
                <div class="rank-name">${r.name}</div>
                <div class="rank-id">${r.id}</div>
            </div>
            <div class="rank-sessions">${r.sessions} <span>sessions</span></div>
        </div>
    `).join('');
}
</script>

</body>
</html>