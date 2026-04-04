<?php
session_start();

if (!isset($_SESSION['student_id'])) {
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

$student_id = $_SESSION['student_id'];

// ── Fetch sit-in history ──
$history = [];
$stmt = $conn->prepare("
    SELECT s.lab, s.purpose, s.student_name, s.sessions,
           s.time_in, s.time_out,
           TIMESTAMPDIFF(MINUTE, s.time_in, COALESCE(s.time_out, NOW())) AS duration_min
    FROM sitin s
    WHERE s.student_id = ?
    ORDER BY s.time_in DESC
    LIMIT 50
");
if ($stmt) {
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

// ── Stats ──
$total_sessions = count($history);
$total_minutes  = array_sum(array_column($history, 'duration_min'));
$total_hours    = round($total_minutes / 60, 1);

// Unique labs visited
$labs_visited = count(array_unique(array_filter(array_column($history, 'lab'))));

// Unread notifications count (for badge)
$unread_count = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $nstmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0");
    $nstmt->bind_param('s', $student_id);
    $nstmt->execute();
    $nstmt->bind_result($unread_count);
    $nstmt->fetch();
    $nstmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History – CCS Sit-in Monitoring System</title>
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
            --green:      #1cb87e;
            --green-bg:   #e6f8f2;
            --red:        #e53535;
            --red-bg:     #fdeaea;
            --orange:     #f07b00;
            --orange-bg:  #fff3e0;
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
            display: flex; justify-content: space-between; align-items: center;
            height: 60px; position: sticky; top: 0; z-index: 200;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }

        .nav-title {
            font-size: 13.5px; font-weight: 600;
            color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3;
        }

        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 13px; font-weight: 500; padding: 7px 13px;
            border-radius: 6px; transition: background 0.18s, color 0.18s; letter-spacing: 0.2px;
        }

        .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important; font-weight: 700 !important;
            border-radius: 8px !important; padding: 7px 18px !important;
            margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        /* ── NOTIFICATION BUTTON ── */
        .notif-wrapper { position: relative; display: flex; align-items: center; }

        .notif-btn {
            position: relative; background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.85); font-size: 13px; font-weight: 500;
            padding: 7px 13px; border-radius: 6px;
            display: flex; align-items: center; gap: 6px;
            font-family: 'DM Sans', sans-serif; letter-spacing: 0.2px;
            transition: background 0.18s, color 0.18s;
        }

        .notif-btn:hover { background: rgba(255,255,255,0.12); color: white; }

        .notif-badge {
            position: absolute; top: 4px; right: 6px;
            background: #e53535; color: white;
            font-size: 9px; font-weight: 700;
            min-width: 16px; height: 16px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid var(--navy-mid); line-height: 1;
        }

        .notif-badge.hidden { display: none; }

        /* ── PAGE WRAP ── */
        .page-wrap { padding: 24px 28px; max-width: 1300px; margin: 0 auto; }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px;
            animation: fadeUp 0.4s ease both;
        }

        .page-header-left { display: flex; align-items: center; gap: 14px; }

        .page-header-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 14px rgba(36,82,160,0.35);
        }

        .page-header-text h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--navy); line-height: 1.2;
        }

        .page-header-text p { font-size: 13px; color: var(--muted); margin-top: 2px; }

        /* ── STAT CARDS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
            margin-bottom: 22px;
            animation: fadeUp 0.4s ease 0.08s both;
        }

        .stat-card {
            background: var(--panel); border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 3px 16px rgba(15,38,83,0.07);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }

        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }

        .stat-icon.blue   { background: linear-gradient(135deg, var(--navy-light), var(--accent)); }
        .stat-icon.green  { background: linear-gradient(135deg, #0fa86a, var(--green)); }
        .stat-icon.gold   { background: linear-gradient(135deg, var(--orange), var(--gold)); }

        .stat-info .stat-val {
            font-family: 'DM Serif Display', serif;
            font-size: 28px; color: var(--navy); line-height: 1;
        }

        .stat-info .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── MAIN CARD ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
            animation: fadeUp 0.5s ease 0.14s both;
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .card-header-left { display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            background: #fafbfd;
        }

        .filter-bar label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }

        .filter-bar select,
        .filter-bar input[type="text"] {
            height: 34px; padding: 0 10px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: white;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        }

        .filter-bar select:focus,
        .filter-bar input[type="text"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.12);
        }

        .filter-bar input[type="text"] { width: 200px; }

        .btn-reset {
            height: 34px; padding: 0 14px;
            border: 1px solid var(--border); border-radius: 8px;
            background: white; color: var(--muted);
            font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }

        .btn-reset:hover { background: var(--tag-bg); color: var(--navy); border-color: #b0bdd8; }

        .filter-count {
            margin-left: auto;
            font-size: 12px; color: var(--muted); font-weight: 500;
        }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%; border-collapse: collapse;
            font-size: 13.5px;
        }

        thead tr {
            background: var(--tag-bg);
            border-bottom: 2px solid var(--border);
        }

        thead th {
            padding: 11px 16px;
            text-align: left;
            font-size: 11px; font-weight: 700;
            color: var(--navy); text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }

        thead th.center { text-align: center; }

        tbody tr {
            border-bottom: 1px solid #f0f3f9;
            transition: background 0.14s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }

        tbody td {
            padding: 12px 16px;
            color: var(--text);
            vertical-align: middle;
        }

        tbody td.center { text-align: center; }

        .td-date {
            font-weight: 700; color: var(--navy); font-size: 13px;
            white-space: nowrap;
        }

        .td-date .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

        .lab-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--tag-bg); color: var(--navy-light);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee;
        }

        .purpose-text { color: #445; font-size: 13px; line-height: 1.4; }

        .time-text { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; }
        .time-text .time-sub { font-size: 11px; color: var(--muted); font-weight: 400; }

        .duration-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }

        .duration-badge.short  { background: var(--green-bg);  color: var(--green);  }
        .duration-badge.medium { background: var(--orange-bg); color: var(--orange); }
        .duration-badge.long   { background: #e8edf8; color: var(--navy-light); }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
        }

        .status-pill.done    { background: var(--green-bg);  color: var(--green); }
        .status-pill.ongoing { background: #fff8e1; color: #c07000; }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 60px 24px;
            color: var(--muted);
        }

        .empty-state .empty-icon { font-size: 48px; margin-bottom: 14px; opacity: 0.4; }
        .empty-state h3 { font-size: 16px; font-weight: 700; color: var(--navy); margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; line-height: 1.6; }

        /* ── PAGINATION ── */
        .table-footer {
            padding: 13px 20px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            background: #fafbfd; flex-wrap: wrap; gap: 10px;
        }

        .pagination { display: flex; gap: 4px; }

        .page-btn {
            height: 30px; min-width: 30px; padding: 0 8px;
            border: 1px solid var(--border); border-radius: 7px;
            background: white; color: var(--text);
            font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }

        .page-btn:hover:not(:disabled) { background: var(--tag-bg); border-color: var(--accent); color: var(--accent); }
        .page-btn.active { background: var(--accent); border-color: var(--accent); color: white; }
        .page-btn:disabled { opacity: 0.38; cursor: default; }

        .table-footer-info { font-size: 12px; color: var(--muted); font-weight: 500; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .page-wrap { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; }
            .filter-bar input[type="text"] { width: 140px; }
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

        <div class="notif-wrapper">
            <button class="notif-btn" onclick="window.location.href='notification.php'">
                <span>🔔</span>
                Notification
                <?php if ($unread_count > 0): ?>
                <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                <?php else: ?>
                <span class="notif-badge hidden"></span>
                <?php endif; ?>
            </button>
        </div>

        <a href="user_home.php">Home</a>
        <a href="user_edit_profile.php">Edit Profile</a>
        <a href="history.php" class="active">History</a>
        <a href="reservation.php">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrap">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">🕒</div>
            <div class="page-header-text">
                <h1>Sit-in History</h1>
                <p>Your complete laboratory session records</p>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue">💻</div>
            <div class="stat-info">
                <div class="stat-val"><?= $total_sessions ?></div>
                <div class="stat-lbl">Total Sessions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">⏱️</div>
            <div class="stat-info">
                <div class="stat-val"><?= $total_hours ?>h</div>
                <div class="stat-lbl">Total Time Logged</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">🏛️</div>
            <div class="stat-info">
                <div class="stat-val"><?= $labs_visited ?></div>
                <div class="stat-lbl">Labs Visited</div>
            </div>
        </div>
    </div>

    <!-- HISTORY TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="hicon">📋</div>
                Session Records
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <label>Filter:</label>
            <input type="text" id="searchInput" placeholder="Search purpose or lab…" oninput="applyFilters()">

            <select id="labFilter" onchange="applyFilters()">
                <option value="">All Labs</option>
                <?php
                $unique_labs = array_unique(array_filter(array_column($history, 'lab')));
                sort($unique_labs);
                foreach ($unique_labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>"><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="done">Completed</option>
                <option value="ongoing">Ongoing</option>
            </select>

            <button class="btn-reset" onclick="resetFilters()">✕ Reset</button>

            <span class="filter-count" id="filterCount">
                Showing <?= $total_sessions ?> record<?= $total_sessions !== 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No sessions yet</h3>
                <p>Your sit-in sessions will appear here once you've logged in to a laboratory.</p>
            </div>
            <?php else: ?>
            <table id="historyTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Laboratory</th>
                        <th>Purpose</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th class="center">Duration</th>
                        <th class="center">Status</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php foreach ($history as $i => $row):
                        $is_ongoing = empty($row['time_out']);
                        $dur = (int)$row['duration_min'];

                        if ($dur < 60)       $dur_label = $dur . 'm';
                        elseif ($dur < 120)  $dur_label = '1h ' . ($dur - 60) . 'm';
                        else                 $dur_label = floor($dur / 60) . 'h ' . ($dur % 60) . 'm';

                        $dur_class = $dur < 60 ? 'short' : ($dur < 180 ? 'medium' : 'long');

                        $date_fmt   = $row['time_in'] ? date('M j, Y', strtotime($row['time_in'])) : '—';
                        $day_fmt    = $row['time_in'] ? date('l', strtotime($row['time_in'])) : '';
                        $login_fmt  = $row['time_in']  ? date('h:i A', strtotime($row['time_in']))  : '—';
                        $logout_fmt = $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—';
                    ?>
                    <tr data-lab="<?= htmlspecialchars($row['lab'] ?? '') ?>"
                        data-status="<?= $is_ongoing ? 'ongoing' : 'done' ?>"
                        data-purpose="<?= htmlspecialchars(strtolower($row['purpose'] ?? '')) ?>"
                        data-lab-lower="<?= htmlspecialchars(strtolower($row['lab'] ?? '')) ?>">
                        <td style="color:var(--muted); font-size:12px; font-weight:600;"><?= $i + 1 ?></td>
                        <td>
                            <div class="td-date">
                                <?= htmlspecialchars($date_fmt) ?>
                                <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="lab-tag">🏛️ <?= htmlspecialchars($row['lab'] ?? 'N/A') ?></span>
                        </td>
                        <td>
                            <div class="purpose-text"><?= htmlspecialchars($row['purpose'] ?? '—') ?></div>
                        </td>
                        <td>
                            <div class="time-text"><?= $login_fmt ?></div>
                        </td>
                        <td>
                            <div class="time-text">
                                <?= $is_ongoing ? '<span style="color:var(--orange); font-weight:700;">Active</span>' : $logout_fmt ?>
                            </div>
                        </td>
                        <td class="center">
                            <?php if ($is_ongoing): ?>
                            <span class="duration-badge medium">⏳ In progress</span>
                            <?php else: ?>
                            <span class="duration-badge <?= $dur_class ?>">⏱ <?= $dur_label ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php if ($is_ongoing): ?>
                            <span class="status-pill ongoing"><span class="status-dot"></span> Ongoing</span>
                            <?php else: ?>
                            <span class="status-pill done"><span class="status-dot"></span> Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- TABLE FOOTER / PAGINATION -->
        <?php if (!empty($history)): ?>
        <div class="table-footer">
            <div class="table-footer-info" id="pageInfo">Page 1 of 1</div>
            <div class="pagination" id="pagination"></div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-wrap -->

<script>
    const ROWS_PER_PAGE = 10;
    let currentPage = 1;
    let visibleRows  = [];

    function getRows() {
        return Array.from(document.querySelectorAll('#historyBody tr'));
    }

    function applyFilters() {
        const search     = document.getElementById('searchInput').value.toLowerCase().trim();
        const labFilter  = document.getElementById('labFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;

        visibleRows = [];

        getRows().forEach(function(row) {
            const purpose = row.dataset.purpose || '';
            const labLow  = row.dataset.labLower || '';
            const lab     = row.dataset.lab || '';
            const status  = row.dataset.status || '';

            const matchSearch = !search || purpose.includes(search) || labLow.includes(search);
            const matchLab    = !labFilter || lab === labFilter;
            const matchStatus = !statusFilter || status === statusFilter;

            if (matchSearch && matchLab && matchStatus) {
                row.style.display = '';
                visibleRows.push(row);
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('filterCount').textContent =
            'Showing ' + visibleRows.length + ' record' + (visibleRows.length !== 1 ? 's' : '');

        currentPage = 1;
        paginate();
    }

    function paginate() {
        visibleRows.forEach(function(row, idx) {
            const page = Math.floor(idx / ROWS_PER_PAGE) + 1;
            row.style.display = page === currentPage ? '' : 'none';
        });

        const totalPages = Math.max(1, Math.ceil(visibleRows.length / ROWS_PER_PAGE));
        document.getElementById('pageInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages;

        const pg = document.getElementById('pagination');
        pg.innerHTML = '';

        // Prev
        const prev = makePgBtn('‹', currentPage > 1, function() { currentPage--; paginate(); });
        pg.appendChild(prev);

        // Page numbers
        for (let p = 1; p <= totalPages; p++) {
            if (totalPages > 7 && p > 2 && p < totalPages - 1 && Math.abs(p - currentPage) > 1) {
                if (p === 3 || p === totalPages - 2) {
                    const dots = document.createElement('span');
                    dots.style.cssText = 'padding:0 4px;color:var(--muted);font-size:13px;display:flex;align-items:center;';
                    dots.textContent = '…';
                    pg.appendChild(dots);
                }
                continue;
            }
            const btn = makePgBtn(p, true, (function(page) { return function() { currentPage = page; paginate(); }; })(p));
            if (p === currentPage) btn.classList.add('active');
            pg.appendChild(btn);
        }

        // Next
        const next = makePgBtn('›', currentPage < totalPages, function() { currentPage++; paginate(); });
        pg.appendChild(next);
    }

    function makePgBtn(label, enabled, onClick) {
        const btn = document.createElement('button');
        btn.className = 'page-btn';
        btn.textContent = label;
        btn.disabled = !enabled;
        if (enabled) btn.addEventListener('click', onClick);
        return btn;
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('labFilter').value   = '';
        document.getElementById('statusFilter').value = '';
        applyFilters();
    }

    // Init
    visibleRows = getRows();
    paginate();
</script>

</body>
</html>