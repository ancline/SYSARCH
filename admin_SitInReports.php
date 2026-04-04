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

// --- FILTERS ---
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to   = $_GET['date_to']   ?? '';
$filter_purpose   = $_GET['purpose']   ?? '';
$filter_lab       = $_GET['lab']       ?? '';
$filter_idno      = trim($_GET['idno'] ?? '');

$conditions = [];
$params     = [];
$types      = '';

if ($filter_date_from !== '') {
    $conditions[] = "DATE(s.time_in) >= ?";
    $params[] = $filter_date_from;
    $types   .= 's';
}
if ($filter_date_to !== '') {
    $conditions[] = "DATE(s.time_in) <= ?";
    $params[] = $filter_date_to;
    $types   .= 's';
}
if ($filter_purpose !== '') {
    $conditions[] = "s.purpose = ?";
    $params[] = $filter_purpose;
    $types   .= 's';
}
if ($filter_lab !== '') {
    $conditions[] = "s.lab = ?";
    $params[] = $filter_lab;
    $types   .= 's';
}
if ($filter_idno !== '') {
    $conditions[] = "s.student_id = ?";
    $params[] = $filter_idno;
    $types   .= 's';
}

$where_sql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$sql = "
    SELECT s.id, s.student_id, s.student_name, s.lab, s.purpose,
           s.time_in, s.time_out, s.sessions,
           TIMESTAMPDIFF(MINUTE, s.time_in, IFNULL(s.time_out, NOW())) AS duration_min
    FROM sitin s
    $where_sql
    ORDER BY s.time_in DESC
";

$records = [];
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

$total_records = count($records);
$dur_sum = 0; $dur_cnt = 0;
foreach ($records as $r) {
    if ($r['time_out'] !== null) { $dur_sum += $r['duration_min']; $dur_cnt++; }
}
$avg_duration = $dur_cnt > 0 ? round($dur_sum / $dur_cnt) : 0;

$purposes = [];
$pr = $conn->query("SELECT DISTINCT purpose FROM sitin WHERE purpose IS NOT NULL ORDER BY purpose");
if ($pr) while ($row = $pr->fetch_assoc()) $purposes[] = $row['purpose'];

$labs = [];
$lr = $conn->query("SELECT DISTINCT lab FROM sitin WHERE lab IS NOT NULL ORDER BY lab");
if ($lr) while ($row = $lr->fetch_assoc()) $labs[] = $row['lab'];

$chart_purpose = [];
$cpr = $conn->query("SELECT purpose, COUNT(*) AS cnt FROM sitin GROUP BY purpose ORDER BY cnt DESC");
if ($cpr) while ($row = $cpr->fetch_assoc()) $chart_purpose[] = $row;

$chart_daily = [];
$dr = $conn->query("
    SELECT DATE(time_in) AS day, COUNT(*) AS cnt
    FROM sitin
    WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(time_in)
    ORDER BY day ASC
");
if ($dr) while ($row = $dr->fetch_assoc()) $chart_daily[] = $row;

$chart_lab = [];
$labr = $conn->query("SELECT lab, COUNT(*) AS cnt FROM sitin WHERE lab IS NOT NULL GROUP BY lab ORDER BY cnt DESC");
if ($labr) while ($row = $labr->fetch_assoc()) $chart_lab[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Sit-in Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

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
            --green:      #1a7a4a;
            --red:        #c0392b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

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
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .nav-title { font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3; }
        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }
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
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }
        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important; font-weight: 700 !important;
            border-radius: 8px !important; padding: 7px 18px !important;
            margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }
        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header { margin-bottom: 22px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .page-header-left h2 { font-family: 'DM Serif Display', serif; font-size: 24px; color: var(--navy); margin-bottom: 3px; }
        .page-header-left p { font-size: 13px; color: var(--muted); }

        .export-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-export {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: transform 0.15s, box-shadow 0.15s;
            text-decoration: none;
        }
        .btn-export:hover { transform: translateY(-1px); }
        .btn-pdf   { background: linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; box-shadow:0 2px 8px rgba(192,57,43,0.35); }
        .btn-excel { background: linear-gradient(135deg,#1a7a4a,#27ae60); color:#fff; box-shadow:0 2px 8px rgba(26,122,74,0.35); }
        .btn-print { background: linear-gradient(135deg,#2452a0,#3b6fd4); color:#fff; box-shadow:0 2px 8px rgba(36,82,160,0.35); }
        .btn-pdf:hover   { box-shadow:0 4px 14px rgba(192,57,43,0.45); }
        .btn-excel:hover { box-shadow:0 4px 14px rgba(26,122,74,0.45); }
        .btn-print:hover { box-shadow:0 4px 14px rgba(36,82,160,0.45); }

        .stat-strip { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 22px; }
        .stat-card {
            background: var(--panel); border-radius: 14px; border: 1px solid var(--border);
            padding: 16px 18px; display: flex; align-items: center; gap: 14px;
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }
        .stat-icon { width: 44px; height: 44px; border-radius: 11px; background: linear-gradient(135deg,var(--navy),var(--navy-light)); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-value { font-size: 26px; font-weight: 700; color: var(--navy); line-height: 1; margin-bottom: 3px; }
        .stat-label { font-size: 11px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.4px; }

        .card { background: var(--panel); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%); color: white; padding: 12px 18px; display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .card-header .hicon { width: 26px; height: 26px; background: rgba(255,255,255,0.15); border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .card-body { padding: 20px; }

        .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text);
            background: var(--bg); outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            min-width: 130px;
        }
        .filter-group input:focus,
        .filter-group select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white; }
        .btn-filter {
            padding: 9px 20px; background: linear-gradient(135deg,var(--navy),var(--navy-light));
            color: white; border: none; border-radius: 8px; font-size: 13px;
            font-weight: 700; font-family: 'DM Sans', sans-serif; cursor: pointer;
            box-shadow: 0 2px 8px rgba(15,38,83,0.25); transition: transform 0.15s;
            align-self: flex-end;
        }
        .btn-filter:hover { transform: translateY(-1px); }
        .btn-reset { padding: 9px 16px; background: var(--tag-bg); color: var(--navy); border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: background 0.15s; align-self: flex-end; text-decoration: none; }
        .btn-reset:hover { background: var(--border); }

        .charts-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .chart-wrap { position: relative; height: 230px; }

        .table-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .table-meta-count { font-size: 12.5px; color: var(--muted); font-weight: 500; }
        .table-search { padding: 7px 12px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--bg); outline: none; width: 220px; transition: border-color 0.18s; }
        .table-search:focus { border-color: var(--accent); }

        .data-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .data-table thead th {
            background: var(--tag-bg); padding: 10px 12px;
            text-align: left; font-size: 11px; font-weight: 700;
            color: var(--navy); text-transform: uppercase; letter-spacing: 0.4px;
            border-bottom: 2px solid var(--border); white-space: nowrap;
        }
        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        .data-table tbody tr:hover { background: var(--tag-bg); }
        .data-table tbody td { padding: 9px 12px; color: var(--text); vertical-align: middle; }
        .data-table tbody tr:last-child { border-bottom: none; }

        .badge {
            display: inline-block; padding: 3px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
        }
        .badge-active   { background: #e8faf2; color: #1a7a4a; }
        .badge-complete { background: #e8edf8; color: #2452a0; }

        .duration-pill {
            display: inline-block; padding: 2px 8px; border-radius: 6px;
            font-size: 11px; font-weight: 600;
            background: var(--tag-bg); color: var(--muted);
        }

        .no-data { text-align: center; padding: 40px; color: var(--muted); font-size: 13.5px; }

        .table-scroll { overflow-x: auto; }

        .pagination { display: flex; align-items: center; gap: 6px; justify-content: flex-end; margin-top: 14px; }
        .pg-btn { padding: 6px 12px; border: 1.5px solid var(--border); border-radius: 7px; background: white; font-size: 12.5px; font-family: 'DM Sans', sans-serif; color: var(--navy); cursor: pointer; font-weight: 600; transition: background 0.15s; }
        .pg-btn:hover, .pg-btn.active { background: var(--navy); color: white; border-color: var(--navy); }
        .pg-info { font-size: 12px; color: var(--muted); }

        @media print {
            .navbar, .filter-bar, .export-group, .btn-filter, .btn-reset,
            .pagination, .table-search, .table-meta, .no-print { display: none !important; }
            .page-wrapper { padding: 0; }
            .card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
            .charts-grid { grid-template-columns: 1fr 1fr; }
            body { background: white; }
            .print-header { display: block !important; }
        }
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #0f2653;
        }
        .print-header h1 { font-size: 18px; color: #0f2653; }
        .print-header p { font-size: 12px; color: #666; margin-top: 4px; }

        @media (max-width: 1100px) {
            .charts-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 900px) {
            .stat-strip { grid-template-columns: 1fr 1fr; }
            .charts-grid { grid-template-columns: 1fr; }
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
        }
    </style>
</head>
<body>

<div class="navbar no-print">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="admin_home.php">Home ▾</a>
        <a href="admin_search.php">Search</a>
        <a href="admin_Student.php">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="admin_SitInReports.php" class="active">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="admin_reservation.php">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<div class="page-wrapper">

    <div class="print-header">
        <h1>College of Computer Studies — Sit-in Reports</h1>
        <p>Generated on: <?= date('F d, Y h:i A') ?></p>
    </div>

    <div class="page-header">
        <div class="page-header-left">
            <h2>Sit-in Reports</h2>
            <p>Analytics and records for all sit-in sessions. Filter, export, or print as needed.</p>
        </div>
        <div class="export-group no-print">
            <button class="btn-export btn-pdf" onclick="exportPDF()">📄 Export PDF</button>
            <button class="btn-export btn-excel" onclick="exportExcel()">📊 Export Excel</button>
            <button class="btn-export btn-print" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div>
                <div class="stat-value"><?= $total_records ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🖥️</div>
            <div>
                <div class="stat-value"><?= count(array_filter($records, fn($r) => $r['time_out'] === null)) ?></div>
                <div class="stat-label">Currently Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div>
                <div class="stat-value"><?= count(array_filter($records, fn($r) => $r['time_out'] !== null)) ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div>
                <div class="stat-value"><?= $avg_duration ?>m</div>
                <div class="stat-label">Avg. Duration</div>
            </div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="card">
            <div class="card-header"><div class="hicon">🎯</div> By Purpose</div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="chartPurpose"></canvas></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="hicon">📅</div> Daily Trend (14 days)</div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="chartDaily"></canvas></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="hicon">🏫</div> Lab Utilization</div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="chartLab"></canvas></div>
            </div>
        </div>
    </div>

    <div class="card no-print">
        <div class="card-header"><div class="hicon">🔍</div> Filter Records</div>
        <div class="card-body">
            <form method="GET" action="admin_SitInReports.php">
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>Student ID</label>
                        <input type="text" name="idno" value="<?= htmlspecialchars($filter_idno) ?>" placeholder="e.g. 123456789">
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Purpose</label>
                        <select name="purpose">
                            <option value="">All Purposes</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= $filter_purpose === $p ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Lab</label>
                        <select name="lab">
                            <option value="">All Labs</option>
                            <?php foreach ($labs as $l): ?>
                                <option value="<?= htmlspecialchars($l) ?>" <?= $filter_lab === $l ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">🔎 Apply</button>
                    <a href="admin_SitInReports.php" class="btn-reset">✕ Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div class="hicon">📋</div> Sit-in Records</div>
        <div class="card-body">

            <div class="table-meta no-print">
                <span class="table-meta-count">Showing <strong id="visibleCount"><?= $total_records ?></strong> of <strong><?= $total_records ?></strong> records</span>
                <input type="text" class="table-search" id="tableSearch" placeholder="🔍  Search table…" oninput="filterTable()">
            </div>

            <div class="table-scroll">
                <table class="data-table" id="sitinTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Lab</th>
                            <th>Purpose</th>
                            <th>Sessions</th>
                            <th>Date In</th>
                            <th>Time In</th>
                            <th>Date Out</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="12" class="no-data">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($r['student_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['student_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['lab'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['purpose'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['sessions'] ?? '-') ?></td>
                                <td><?= $r['time_in']  ? date('Y-m-d', strtotime($r['time_in']))  : '-' ?></td>
                                <td><?= $r['time_in']  ? date('H:i',   strtotime($r['time_in']))  : '-' ?></td>
                                <td><?= $r['time_out'] ? date('Y-m-d', strtotime($r['time_out'])) : '-' ?></td>
                                <td><?= $r['time_out'] ? date('H:i',   strtotime($r['time_out'])) : '-' ?></td>
                                <td>
                                    <?php if ($r['time_out']): ?>
                                        <span class="duration-pill">
                                            <?php
                                                $mins = $r['duration_min'];
                                                if ($mins >= 60) echo floor($mins/60).'h '.($mins%60).'m';
                                                else echo $mins.'m';
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="duration-pill">Ongoing</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['time_out'] === null): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-complete">Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination no-print" id="pagination"></div>

        </div>
    </div>

</div>

<script>
const purposeData = <?= json_encode($chart_purpose) ?>;
const dailyData   = <?= json_encode($chart_daily) ?>;
const labData     = <?= json_encode($chart_lab) ?>;

const palette = ['#2452a0','#3b6fd4','#f0a500','#1a7a4a','#e74c3c','#8e44ad','#16a085','#d35400','#2980b9','#c0392b'];

new Chart(document.getElementById('chartPurpose'), {
    type: 'doughnut',
    data: {
        labels: purposeData.length ? purposeData.map(d => d.purpose) : ['No Data'],
        datasets: [{ data: purposeData.length ? purposeData.map(d => +d.cnt) : [1], backgroundColor: palette, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '58%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { family: 'DM Sans', size: 11 }, color: '#1c2b4a', padding: 10, boxWidth: 10, boxHeight: 10 } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
        }
    }
});

(function() {
    const today = new Date();
    const days = [], counts = [];
    for (let i = 13; i >= 0; i--) {
        const d = new Date(today); d.setDate(d.getDate() - i);
        const key = d.toISOString().slice(0,10);
        days.push(key.slice(5));
        const found = dailyData.find(x => x.day === key);
        counts.push(found ? +found.cnt : 0);
    }
    new Chart(document.getElementById('chartDaily'), {
        type: 'line',
        data: {
            labels: days,
            datasets: [{ label: 'Sit-ins', data: counts, borderColor: '#2452a0', backgroundColor: 'rgba(36,82,160,0.1)', borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#2452a0', fill: true, tension: 0.4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 10 }, color: '#6b7fa3' }, grid: { color: '#e8edf8' } },
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 }, color: '#6b7fa3' }, grid: { color: '#e8edf8' } }
            }
        }
    });
})();

new Chart(document.getElementById('chartLab'), {
    type: 'bar',
    data: {
        labels: labData.length ? labData.map(d => d.lab) : ['No Data'],
        datasets: [{ label: 'Sessions', data: labData.length ? labData.map(d => +d.cnt) : [0], backgroundColor: palette.slice(0, labData.length || 1), borderRadius: 6, borderSkipped: false }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { font: { size: 10 }, color: '#6b7fa3' }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 }, color: '#6b7fa3' }, grid: { color: '#e8edf8' } }
        }
    }
});

const ROWS_PER_PAGE = 15;
let currentPage = 1;

function getVisibleRows() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const tbody = document.getElementById('sitinTable').querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    return rows.filter(row => {
        if (row.querySelector('td[colspan]')) return false;
        if (!q) return true;
        return row.textContent.toLowerCase().includes(q);
    });
}

function filterTable() {
    currentPage = 1;
    renderTable();
}

function renderTable() {
    const visible = getVisibleRows();
    const total   = visible.length;
    const start   = (currentPage - 1) * ROWS_PER_PAGE;
    const end     = start + ROWS_PER_PAGE;

    const tbody = document.getElementById('sitinTable').querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr'));

    allRows.forEach(row => row.style.display = 'none');
    visible.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? '' : 'none';
    });

    document.getElementById('visibleCount').textContent = total;

    const totalPages = Math.ceil(total / ROWS_PER_PAGE);
    const pag = document.getElementById('pagination');
    pag.innerHTML = '';
    if (totalPages <= 1) return;

    const mkBtn = (label, page, active) => {
        const b = document.createElement('button');
        b.className = 'pg-btn' + (active ? ' active' : '');
        b.textContent = label;
        b.onclick = () => { currentPage = page; renderTable(); };
        return b;
    };

    if (currentPage > 1) pag.appendChild(mkBtn('‹ Prev', currentPage - 1, false));
    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentPage) > 2 && p !== 1 && p !== totalPages) continue;
        pag.appendChild(mkBtn(p, p, p === currentPage));
    }
    if (currentPage < totalPages) pag.appendChild(mkBtn('Next ›', currentPage + 1, false));

    const info = document.createElement('span');
    info.className = 'pg-info';
    info.textContent = `Page ${currentPage} of ${totalPages}`;
    pag.appendChild(info);
}

renderTable();

function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.setTextColor(15, 38, 83);
    doc.text('College of Computer Studies — Sit-in Reports', 14, 16);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(107, 127, 163);
    doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);

    const headers = [['#','Student ID','Name','Lab','Purpose','Sessions','Date In','Time In','Date Out','Time Out','Duration','Status']];
    const rows = getTableRows();

    doc.autoTable({
        head: headers,
        body: rows,
        startY: 28,
        styles: { fontSize: 8, cellPadding: 2.5, font: 'helvetica', textColor: [28,43,74] },
        headStyles: { fillColor: [15,38,83], textColor: [255,255,255], fontStyle: 'bold', fontSize: 8 },
        alternateRowStyles: { fillColor: [238,241,248] },
        margin: { left: 14, right: 14 },
        tableLineColor: [214,220,232],
        tableLineWidth: 0.1
    });

    doc.save('sitin_report_' + new Date().toISOString().slice(0,10) + '.pdf');
}

function exportExcel() {
    const headers = ['#','Student ID','Name','Lab','Purpose','Sessions','Date In','Time In','Date Out','Time Out','Duration','Status'];
    const rows = getTableRows();

    const wsData = [headers, ...rows];
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [5,12,22,14,14,10,12,10,12,10,12,10].map(w => ({ wch: w }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sit-in Report');
    XLSX.writeFile(wb, 'sitin_report_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

function getTableRows() {
    const tbody = document.getElementById('sitinTable').querySelector('tbody');
    return Array.from(tbody.querySelectorAll('tr'))
        .filter(r => !r.querySelector('td[colspan]'))
        .map((r) => {
            const cells = r.querySelectorAll('td');
            return Array.from(cells).map(c => c.textContent.trim());
        });
}
</script>

</body>
</html>