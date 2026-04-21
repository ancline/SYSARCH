<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Stats
$total_students = $conn->query("SELECT COUNT(*) AS cnt FROM student")->fetch_assoc()['cnt'] ?? 0;

$sitin_check = $conn->query("SHOW TABLES LIKE 'sitin'");
$sitin_exists = $sitin_check && $sitin_check->num_rows > 0;
$active_sitins = $sitin_exists ? ($conn->query("SELECT COUNT(*) AS cnt FROM sitin WHERE time_out IS NULL")->fetch_assoc()['cnt'] ?? 0) : 0;
$total_sitins  = $sitin_exists ? ($conn->query("SELECT COUNT(*) AS cnt FROM sitin")->fetch_assoc()['cnt'] ?? 0) : 0;

// Purpose breakdown for chart
$purpose_data = [];
if ($sitin_exists) {
    $pr = $conn->query("SELECT purpose, COUNT(*) AS cnt FROM sitin GROUP BY purpose ORDER BY cnt DESC");
    if ($pr) {
        while ($row = $pr->fetch_assoc()) {
            $purpose_data[] = $row;
        }
    }
}

// Create announcements table if needed
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) DEFAULT 'CCS Admin',
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle new announcement POST
$ann_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $msg = trim($_POST['announcement']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO announcements (message) VALUES (?)");
        $stmt->bind_param('s', $msg);
        $stmt->execute();
        $stmt->close();

        // Create notification for all students (student_id IS NULL means global)
        $conn->query("
            CREATE TABLE IF NOT EXISTS notifications (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50),
                type       VARCHAR(30) DEFAULT 'announcement',
                subtype    VARCHAR(30) DEFAULT NULL,
                title      VARCHAR(255),
                message    TEXT,
                is_read    TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt = $conn->prepare("INSERT INTO notifications (student_id, type, title, message) VALUES (NULL, 'announcement', 'New Announcement', ?)");
        $stmt->bind_param('s', $msg);
        $stmt->execute();
        $stmt->close();

        $conn->close();
        header('Location: admin_home.php?posted=1');
        exit();
    } else {
        $ann_error = 'Please enter an announcement message.';
    }
}

// Fetch announcements
$announcements = [];
$ar = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
if ($ar) {
    while ($row = $ar->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .nav-left { display: flex; align-items: center; gap: 12px; }

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

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 7px 13px;
            border-radius: 6px;
            transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }

        .nav-links a.active {
            background: rgba(255,255,255,0.18);
            color: white;
            font-weight: 700;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            padding: 7px 18px !important;
            margin-left: 6px;
            box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important;
        }

        /* ── PAGE ── */
        .page-wrapper {
            padding: 28px 32px 48px;
            animation: fadeUp 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header { margin-bottom: 24px; }

        .page-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 24px;
            color: var(--navy);
            margin-bottom: 3px;
        }

        .page-header p { font-size: 13px; color: var(--muted); }

        /* ── STAT STRIP ── */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            transition: transform 0.18s, box-shadow 0.18s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15,38,83,0.12);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--navy);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11.5px;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ── GRID ── */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        /* ── CARD ── */
        .card {
            background: var(--panel);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
            border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white;
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .card-body { padding: 20px; }

        /* ── CHART ── */
        .chart-wrap {
            position: relative;
            height: 270px;
        }

        /* ── ANNOUNCEMENT FORM ── */
        .ann-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .ann-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: var(--bg);
            resize: vertical;
            min-height: 80px;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            margin-bottom: 10px;
        }

        .ann-textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
            background: white;
        }

        .btn-submit {
            padding: 9px 22px;
            background: linear-gradient(135deg, #1a7a4a, #22a85f);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(26,122,74,0.3);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(26,122,74,0.4);
        }

        .alert-success {
            background: #edfaf3;
            color: #1a6e3f;
            border: 1px solid #b2e8cc;
            border-left: 4px solid #2ecc71;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 14px;
        }

        .alert-error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #e74c3c;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 14px;
        }

        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 18px 0 16px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        /* ── ANNOUNCEMENTS LIST ── */
        .ann-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
        }

        .ann-list::-webkit-scrollbar { width: 4px; }
        .ann-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .ann-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            transition: box-shadow 0.18s;
        }

        .ann-item:hover { box-shadow: 0 4px 14px rgba(15,38,83,0.08); }

        .ann-item-header {
            background: var(--tag-bg);
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ann-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
        }

        .ann-meta { font-size: 12px; font-weight: 700; color: var(--navy); }

        .ann-date { font-size: 11px; color: var(--muted); margin-left: auto; }

        .ann-msg {
            padding: 10px 14px;
            font-size: 12.5px;
            color: #445;
            line-height: 1.6;
        }

        .ann-empty-msg {
            padding: 10px 14px;
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
        }

        .no-announcements {
            text-align: center;
            padding: 28px 16px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
            .stat-strip { grid-template-columns: 1fr; }
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="/SYSARCH/uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="/SYSARCH/admin/admin_home.php" class="active">Home ▾</a>
        <a href="/SYSARCH/admin/admin_search.php">Search</a>
        <a href="/SYSARCH/admin/admin_Student.php">Students</a>
        <a href="/SYSARCH/admin/admin_SitIn.php">Sit-in</a>
        <a href="/SYSARCH/admin/admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="/SYSARCH/admin/admin_SitInReports.php">Sit-in Reports</a>
        <a href="/SYSARCH/admin/admin_feedback.php">Feedback Reports</a>
        <a href="/SYSARCH/admin/admin_reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <h2>Admin Dashboard</h2>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator') ?>. Here's an overview of the system.</p>
    </div>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div>
                <div class="stat-value"><?= $total_students ?></div>
                <div class="stat-label">Students Registered</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🖥️</div>
            <div>
                <div class="stat-value"><?= $active_sitins ?></div>
                <div class="stat-label">Currently Sit-in</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div>
                <div class="stat-value"><?= $total_sitins ?></div>
                <div class="stat-label">Total Sit-ins</div>
            </div>
        </div>
    </div>

    <!-- GRID -->
    <div class="grid">

        <!-- LEFT: CHART -->
        <div class="card">
            <div class="card-header">
                <div class="hicon">📊</div>
                Statistics
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="statsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- RIGHT: ANNOUNCEMENTS -->
        <div class="card">
            <div class="card-header">
                <div class="hicon">📢</div>
                Announcement
            </div>
            <div class="card-body">

                <?php if (isset($_GET['posted'])): ?>
                    <div class="alert-success">✅ Announcement posted successfully!</div>
                <?php endif; ?>
                <?php if (!empty($ann_error)): ?>
                    <div class="alert-error">❌ <?= htmlspecialchars($ann_error) ?></div>
                <?php endif; ?>

                <form method="POST" action="admin_home.php">
                    <label class="ann-label">New Announcement</label>
                    <textarea class="ann-textarea" name="announcement" rows="3"
                              placeholder="Enter announcement message…"></textarea>
                    <button type="submit" class="btn-submit">📤 Post</button>
                </form>

                <div class="section-divider"></div>
                <div class="section-title">Posted Announcements</div>

                <div class="ann-list">
                    <?php if (empty($announcements)): ?>
                        <div class="no-announcements">No announcements yet.</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                        <div class="ann-item">
                            <div class="ann-item-header">
                                <div class="ann-dot"></div>
                                <span class="ann-meta"><?= htmlspecialchars($ann['admin_name'] ?? 'CCS Admin') ?></span>
                                <span class="ann-date">📅 <?= date('Y-M-d', strtotime($ann['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($ann['message'])): ?>
                            <div class="ann-msg"><?= htmlspecialchars($ann['message']) ?></div>
                            <?php else: ?>
                            <div class="ann-empty-msg">No message content.</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    const purposeData = <?= json_encode($purpose_data) ?>;

    const labels = purposeData.length ? purposeData.map(d => d.purpose) : ['No Sit-in Data'];
    const counts = purposeData.length ? purposeData.map(d => parseInt(d.cnt)) : [1];

    const palette = [
        '#2452a0','#3b6fd4','#f0a500','#1a7a4a','#e74c3c',
        '#8e44ad','#16a085','#d35400','#2980b9','#c0392b'
    ];

    const ctx = document.getElementById('statsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: palette.slice(0, labels.length),
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'DM Sans', size: 12 },
                        color: '#1c2b4a',
                        padding: 14,
                        boxWidth: 12,
                        boxHeight: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} sit-ins`
                    }
                }
            },
            cutout: '60%'
        }
    });
</script>

</body>
</html>