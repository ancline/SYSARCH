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

// Create the sitin table if it doesn't exist yet
$conn->query("
    CREATE TABLE IF NOT EXISTS sitin (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50)  NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        purpose      VARCHAR(255) DEFAULT '',
        time_in      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        time_out     DATETIME     DEFAULT NULL,
        sessions     INT          DEFAULT NULL
    )
");

// ── ONLY fetch ACTIVE sit-ins (time_out IS NULL) ──
$result = $conn->query("SELECT * FROM sitin WHERE time_out IS NULL ORDER BY time_in DESC");
$sitins = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sitins[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Sit-in</title>
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
            color: #fff !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            padding: 7px 18px !important;
            margin-left: 6px;
            box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        /* ── PAGE ── */
        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header-left h2 { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--navy); margin-bottom: 2px; }
        .page-header-left p { font-size: 12.5px; color: var(--muted); }

        /* ── STAT STRIP ── */
        .stat-strip { display: flex; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }

        .stat-pill {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(15,38,83,0.06);
            flex: 1;
            min-width: 150px;
        }

        .stat-pill .sp-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        .stat-pill .sp-value { font-size: 22px; font-weight: 700; color: var(--navy); line-height: 1; }
        .stat-pill .sp-label { font-size: 11.5px; color: var(--muted); font-weight: 500; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

        /* ── CARD ── */
        .card { background: var(--panel); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border); }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white;
            padding: 13px 20px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px; height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        /* live badge in header */
        .live-badge {
            margin-left: auto;
            background: #22c55e;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.6; }
        }

        /* ── TABLE CONTROLS ── */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
            flex-wrap: wrap;
        }

        .entries-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

        .entries-label select {
            padding: 5px 10px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: var(--bg);
            outline: none;
            cursor: pointer;
        }

        .search-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

        .search-wrap input {
            padding: 6px 12px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: var(--bg);
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            width: 200px;
        }

        .search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }

        thead th {
            background: var(--tag-bg);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
        }

        thead th:hover { color: var(--navy); }
        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.13s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--tag-bg); }
        tbody td { padding: 11px 20px; color: var(--text); vertical-align: middle; }

        .id-cell { font-family: monospace; font-size: 12.5px; font-weight: 600; color: var(--navy-light); }

        .status-active {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e6f9f0;
            color: #1a7a4a;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .btn-timeout {
            padding: 5px 14px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(240,165,0,0.3);
            transition: opacity 0.15s, transform 0.15s;
        }

        .btn-timeout:hover { opacity: 0.88; transform: translateY(-1px); }

        /* ── PAGINATION ── */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            font-size: 12.5px;
            color: var(--muted);
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-btns { display: flex; gap: 4px; }

        .page-btn {
            padding: 5px 13px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            background: white;
            font-size: 12.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .page-btn:hover { background: var(--tag-bg); border-color: var(--accent); }
        .page-btn.active { background: linear-gradient(135deg, var(--navy), var(--navy-light)); color: white; border-color: transparent; box-shadow: 0 2px 8px rgba(15,38,83,0.22); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: var(--muted);
        }

        .empty-state .es-icon { font-size: 48px; margin-bottom: 14px; }

        .empty-state h4 {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .empty-state p { font-size: 13px; }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            top: 72px; right: 24px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            animation: toastIn 0.3s ease both;
        }

        .toast-error   { background: #c0392b; }
        .toast-success { background: #1a7a4a; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── SIT IN BUTTON ── */
        .btn-sitin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-sitin:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,38,83,0.32); }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10,20,50,0.45);
            backdrop-filter: blur(3px);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: var(--panel);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15,38,83,0.25);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            animation: modalIn 0.25s ease both;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-16px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .modal-header h3 { font-size: 15px; font-weight: 700; color: white; }

        .modal-close {
            background: rgba(255,255,255,0.15);
            border: none; color: white;
            width: 28px; height: 28px;
            border-radius: 6px; font-size: 16px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
            font-family: 'DM Sans', sans-serif;
        }

        .modal-close:hover { background: rgba(255,255,255,0.28); }
        .modal-body { padding: 24px 24px 8px; }

        .modal-field {
            display: flex; align-items: center;
            margin-bottom: 14px;
            border: 1.5px solid var(--border);
            border-radius: 9px; overflow: hidden;
            background: var(--bg);
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .modal-field:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white; }

        .modal-field label {
            font-size: 12px; font-weight: 700; color: var(--muted);
            white-space: nowrap; padding: 10px 14px;
            border-right: 1.5px solid var(--border);
            min-width: 150px; background: var(--tag-bg);
        }

        .modal-field input,
        .modal-field select {
            flex: 1; border: none; background: transparent;
            padding: 10px 14px; font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text); outline: none;
        }

        .modal-field select { cursor: pointer; appearance: none; }
        .modal-field input[readonly] { color: var(--muted); cursor: not-allowed; }

        .sessions-hint { font-size: 11.5px; margin: -8px 0 10px 0; padding: 0 4px; }
        .sessions-hint.hint-new    { color: var(--accent); }
        .sessions-hint.hint-exists { color: var(--muted); }

        .modal-footer {
            padding: 16px 24px 20px;
            display: flex; justify-content: flex-end; gap: 10px;
        }

        .btn-modal-close {
            padding: 9px 22px; background: white; color: var(--text);
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 13px; font-weight: 600;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            transition: background 0.15s;
        }

        .btn-modal-close:hover { background: var(--tag-bg); }

        .btn-modal-submit {
            padding: 9px 24px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            box-shadow: 0 3px 10px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(15,38,83,0.32); }
        .btn-modal-submit:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

        @media (max-width: 768px) {
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- TOAST NOTIFICATIONS -->
<?php if (isset($_GET['error'])): ?>
<div class="toast toast-error" id="toast">
    <?php
    $errors = [
        'missing_fields'    => '⚠️ Please fill in all required fields.',
        'student_not_found' => '❌ Student ID not found.',
        'no_sessions'       => '🚫 Student has no remaining sessions.',
        'already_sitin'     => '⚠️ Student already has an active sit-in session.',
        'insert_failed'     => '❌ Failed to record sit-in. Please try again.',
    ];
    echo htmlspecialchars($errors[$_GET['error']] ?? 'An error occurred.');
    ?>
</div>
<?php elseif (isset($_GET['success'])): ?>
<div class="toast toast-success" id="toast">✅ Sit-in recorded successfully!</div>
<?php endif; ?>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="admin_home.php">Home ▾</a>
        <a href="admin_search.php">Search</a>
        <a href="admin_Student.php">Students</a>
        <a href="admin_SitIn.php" class="active">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="admin_SitInReports.php">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="admin_reservation.php">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <div class="page-header-left">
            <h2>Sit-in Management</h2>
            <p>Currently active student laboratory sit-in sessions.</p>
        </div>
        <button class="btn-sitin" onclick="openSitInModal()">&#128187; New Sit In</button>
    </div>

    <!-- SIT IN MODAL (manual entry from this page) -->
    <div class="modal-overlay" id="sitInModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Sit In Form</h3>
                <button class="modal-close" onclick="closeSitInModal()">&#x2715;</button>
            </div>
            <form action="process_sitin.php" method="POST">
                <div class="modal-body">

                    <div class="modal-field">
                        <label>ID Number:</label>
                        <input type="text" name="student_id" id="modal_id"
                               placeholder="e.g. 3677937"
                               oninput="lookupStudent(this.value)" required>
                    </div>

                    <div class="modal-field">
                        <label>Student Name:</label>
                        <input type="text" name="student_name" id="modal_name" placeholder="Auto-filled" readonly>
                    </div>

                    <div class="modal-field">
                        <label>Purpose:</label>
                        <select name="purpose" required>
                            <option value="" disabled selected>Select purpose&hellip;</option>
                            <option value="C Programming">C Programming</option>
                            <option value="Java">Java</option>
                            <option value="C#">C#</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Database">Database</option>
                            <option value="Python">Python</option>
                            <option value="Research">Research</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="modal-field">
                        <label>Lab:</label>
                        <select name="lab" required>
                            <option value="" disabled selected>Select lab&hellip;</option>
                            <option value="524">524</option>
                            <option value="526">526</option>
                            <option value="528">528</option>
                            <option value="530">530</option>
                            <option value="542">542</option>
                            <option value="Mac Lab">Mac Lab</option>
                        </select>
                    </div>

                    <div class="modal-field">
                        <label>Remaining Session:</label>
                        <input type="number" name="remaining_session" id="modal_sessions"
                               placeholder="Enter sessions" min="1" max="999">
                    </div>
                    <p class="sessions-hint" id="sessions_hint"></p>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" onclick="closeSitInModal()">Close</button>
                    <button type="submit" class="btn-modal-submit" id="btnSitIn">Sit In</button>
                </div>
            </form>
        </div>
    </div>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="stat-pill">
            <div class="sp-icon">🟢</div>
            <div>
                <div class="sp-value"><?= count($sitins) ?></div>
                <div class="sp-label">Currently Active</div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="hicon">🖥️</div>
            Active Sit-in Sessions
            <?php if (!empty($sitins)): ?>
            <span class="live-badge">● LIVE</span>
            <?php endif; ?>
        </div>

        <?php if (empty($sitins)): ?>
        <!-- ── EMPTY STATE: no one is currently sitting in ── -->
        <div class="empty-state">
            <div class="es-icon">🖥️</div>
            <h4>No Active Sit-in Sessions</h4>
            <p>All students have been logged out. New sit-ins will appear here when recorded.</p>
        </div>

        <?php else: ?>

        <div class="table-controls">
            <div class="entries-label">
                Show
                <select id="entriesSelect" onchange="applyFilter()">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                entries
            </div>
            <div class="search-wrap">
                Search:
                <input type="text" id="searchInput" placeholder="Name, ID, lab…" oninput="applyFilter()">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">ID Number ⬦</th>
                        <th onclick="sortTable(1)">Student Name ⬦</th>
                        <th onclick="sortTable(2)">Lab ⬦</th>
                        <th onclick="sortTable(3)">Purpose ⬦</th>
                        <th onclick="sortTable(4)">Time In ⬦</th>
                        <th onclick="sortTable(5)">Status ⬦</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($sitins as $s): ?>
                    <tr>
                        <td class="id-cell"><?= htmlspecialchars($s['student_id'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['student_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['lab'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['purpose'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['time_in'] ?? '—') ?></td>
                        <td><span class="status-active">🟢 Active</span></td>
                        <td>
                            <button class="btn-timeout"
                                onclick="timeOut('<?= $s['id'] ?>')">
                                🔚 Time Out
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span id="paginationInfo">Showing <?= count($sitins) ?> of <?= count($sitins) ?> entries</span>
            <div class="page-btns">
                <button class="page-btn" onclick="changePage(-1)">← Previous</button>
                <button class="page-btn active" id="pageIndicator">1</button>
                <button class="page-btn" onclick="changePage(1)">Next →</button>
            </div>
        </div>

        <?php endif; ?>
    </div>

</div>

<script>
    const allRows = Array.from(document.querySelectorAll('#tableBody tr'));
    let currentPage = 1;

    function applyFilter() {
        const q       = document.getElementById('searchInput')?.value.toLowerCase() ?? '';
        const perPage = parseInt(document.getElementById('entriesSelect')?.value ?? 10);

        const filtered = allRows.filter(row => {
            const match = row.innerText.toLowerCase().includes(q);
            row.style.display = 'none';
            return match;
        });

        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;

        filtered.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        const showing = Math.min(perPage, Math.max(0, filtered.length - start));
        const info = document.getElementById('paginationInfo');
        if (info) info.textContent = `Showing ${showing} of ${filtered.length} entries`;
    }

    function changePage(dir) {
        currentPage = Math.max(1, currentPage + dir);
        const ind = document.getElementById('pageIndicator');
        if (ind) ind.textContent = currentPage;
        applyFilter();
    }

    function sortTable(col) {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const aText = a.cells[col]?.innerText.trim() ?? '';
            const bText = b.cells[col]?.innerText.trim() ?? '';
            return aText.localeCompare(bText);
        });
        rows.forEach(r => tbody.appendChild(r));
        applyFilter();
    }

    // ── TIME OUT: sets time_out for this sit-in record ──
    function timeOut(id) {
        if (confirm('Mark this student as timed out? They will be removed from the active list.')) {
            window.location.href = 'timeout_sitin.php?id=' + id + '&redirect=admin_SitIn.php';
        }
    }

    // ── TOAST AUTO-DISMISS ──
    const toast = document.getElementById('toast');
    if (toast) setTimeout(() => toast.style.display = 'none', 4000);

    // ── MODAL (for manual "New Sit In" button) ──
    function openSitInModal() {
        document.getElementById('sitInModal').classList.add('open');
        document.getElementById('modal_id').focus();
    }

    function closeSitInModal() {
        document.getElementById('sitInModal').classList.remove('open');
        document.getElementById('modal_id').value   = '';
        document.getElementById('modal_name').value = '';
        const si = document.getElementById('modal_sessions');
        si.value    = '';
        si.readOnly = false;
        document.getElementById('sessions_hint').textContent = '';
        document.getElementById('sessions_hint').className   = 'sessions-hint';
        document.getElementById('btnSitIn').disabled = false;
    }

    document.getElementById('sitInModal').addEventListener('click', function(e) {
        if (e.target === this) closeSitInModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSitInModal();
    });

    // ── AJAX student lookup (for manual modal) ──
    let lookupTimeout;
    function lookupStudent(id) {
        clearTimeout(lookupTimeout);
        const sessionsInput = document.getElementById('modal_sessions');
        const hint          = document.getElementById('sessions_hint');
        const submitBtn     = document.getElementById('btnSitIn');

        if (id.length < 3) {
            document.getElementById('modal_name').value = '';
            sessionsInput.value    = '';
            sessionsInput.readOnly = false;
            hint.textContent = '';
            hint.className   = 'sessions-hint';
            submitBtn.disabled = false;
            return;
        }

        lookupTimeout = setTimeout(() => {
            fetch('get_student.php?id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        document.getElementById('modal_name').value = data.name;
                        const sessions = parseInt(data.sessions) || 0;

                        if (sessions <= 0) {
                            sessionsInput.value       = '';
                            sessionsInput.readOnly    = false;
                            sessionsInput.placeholder = 'Enter no. of sessions';
                            hint.textContent = '⚠️ No sessions set yet — enter the number of sessions to assign.';
                            hint.className   = 'sessions-hint hint-new';
                        } else {
                            sessionsInput.value    = sessions;
                            sessionsInput.readOnly = true;
                            hint.textContent = '✅ 1 session will be deducted on Sit In.';
                            hint.className   = 'sessions-hint hint-exists';
                        }
                        submitBtn.disabled = false;
                    } else {
                        document.getElementById('modal_name').value = '';
                        sessionsInput.value    = '';
                        sessionsInput.readOnly = false;
                        hint.textContent = '';
                        hint.className   = 'sessions-hint';
                        submitBtn.disabled = false;
                    }
                })
                .catch(() => {});
        }, 400);
    }
</script>

</body>
</html>