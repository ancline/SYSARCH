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

// Auto-detect sessions column
$session_col = null;
$cols = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}

$select_cols = $session_col
    ? "IdNumber, FirstName, MiddleName, LastName, Course, CourseLvl, Email, `$session_col` AS sessions"
    : "IdNumber, FirstName, MiddleName, LastName, Course, CourseLvl, Email";

$students = [];
$searched = false;
$query    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query    = trim($_POST['query']);
    $searched = true;

    if (!empty($query)) {
        $like = '%' . $query . '%';
        $stmt = $conn->prepare(
            "SELECT $select_cols FROM student
             WHERE IdNumber LIKE ? OR FirstName LIKE ? OR LastName LIKE ?
                OR MiddleName LIKE ? OR Course LIKE ?
             ORDER BY LastName ASC"
        );
        $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Search</title>
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
        }

        /* ── PAGE ── */
        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); }

        /* ── SEARCH CARD ── */
        .search-card {
            background: var(--panel);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
            overflow: hidden;
            max-width: 540px;
            margin: 0 auto 32px;
        }

        .search-card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .search-card-header h3 { font-size: 15px; font-weight: 700; color: white; letter-spacing: 0.3px; }

        .search-card-header a {
            background: rgba(255,255,255,0.15);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.15s;
        }

        .search-card-header a:hover { background: rgba(255,255,255,0.28); }
        .search-card-body { padding: 24px 24px 20px; }

        .search-input-row { display: flex; gap: 10px; align-items: center; }

        .search-input-wrap {
            flex: 1;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            overflow: hidden;
            background: var(--bg);
            display: flex;
            align-items: center;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .search-input-wrap:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
            background: white;
        }

        .search-input-wrap input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 14px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            outline: none;
        }

        .search-input-wrap input::placeholder { color: #aab4c8; }
        .search-input-wrap .search-icon { padding: 0 12px; color: var(--muted); font-size: 15px; }

        .btn-search {
            padding: 10px 24px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-search:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,38,83,0.32); }

        /* ── RESULTS ── */
        .results-card {
            background: var(--panel);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
            border: 1px solid var(--border);
        }

        .results-header {
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

        .results-header .hicon {
            width: 28px; height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        .results-count {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.3px;
            text-transform: none;
        }

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
            white-space: nowrap;
        }

        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.13s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--tag-bg); }
        tbody td { padding: 11px 20px; color: var(--text); vertical-align: middle; }

        .id-cell { font-family: monospace; font-size: 12.5px; font-weight: 600; color: var(--navy-light); }

        .session-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--tag-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3px 11px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--navy-light);
        }

        /* ── SIT IN BUTTON (replaces Edit) ── */
        .btn-sitin-row {
            padding: 5px 14px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            margin-right: 5px;
            transition: opacity 0.15s, transform 0.15s;
        }

        .btn-sitin-row:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-delete {
            padding: 5px 14px;
            background: white;
            color: #c0392b;
            border: 1.5px solid #e8b4b4;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-delete:hover { background: #fff0f0; border-color: #e74c3c; }

        /* ── EMPTY / INITIAL STATES ── */
        .state-box { text-align: center; padding: 48px 20px; color: var(--muted); }
        .state-box .state-icon { font-size: 40px; margin-bottom: 12px; }
        .state-box h4 { font-family: 'DM Serif Display', serif; font-size: 18px; color: var(--navy); margin-bottom: 6px; }
        .state-box p { font-size: 13px; }

        /* ── SIT IN MODAL ── */
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 { font-size: 15px; font-weight: 700; color: white; letter-spacing: 0.3px; }

        .modal-close {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            width: 28px; height: 28px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
            font-family: 'DM Sans', sans-serif;
        }

        .modal-close:hover { background: rgba(255,255,255,0.28); }

        .modal-body { padding: 24px 24px 8px; }

        .modal-field {
            display: flex;
            align-items: center;
            margin-bottom: 14px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            overflow: hidden;
            background: var(--bg);
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .modal-field:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
            background: white;
        }

        .modal-field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            white-space: nowrap;
            padding: 10px 14px;
            border-right: 1.5px solid var(--border);
            min-width: 150px;
            background: var(--tag-bg);
        }

        .modal-field input,
        .modal-field select {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 14px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            outline: none;
        }

        .modal-field select { cursor: pointer; appearance: none; }
        .modal-field input[readonly] { color: var(--muted); cursor: not-allowed; }

        .sessions-hint { font-size: 11.5px; margin: -8px 0 10px 0; padding: 0 4px; }
        .sessions-hint.hint-new    { color: var(--accent); }
        .sessions-hint.hint-exists { color: var(--muted); }

        .modal-footer {
            padding: 16px 24px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-close {
            padding: 9px 22px;
            background: white;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: background 0.15s;
        }

        .btn-modal-close:hover { background: var(--tag-bg); }

        .btn-modal-submit {
            padding: 9px 24px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(15,38,83,0.32); }
        .btn-modal-submit:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

        @media (max-width: 768px) {
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
            .search-input-row { flex-direction: column; }
            .btn-search { width: 100%; }
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
        <a href="admin_home.php">Home ▾</a>
        <a href="admin_search.php" class="active">Search</a>
        <a href="admin_Student.php">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="admin_SitInReports.php">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- SIT IN MODAL -->
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
                    <input type="text" name="student_id" id="modal_id" readonly>
                </div>

                <div class="modal-field">
                    <label>Student Name:</label>
                    <input type="text" name="student_name" id="modal_name" readonly>
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
                           placeholder="—" min="1" max="999">
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

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <h2>Search Student</h2>
        <p>Look up students by name, ID number, or course.</p>
    </div>

    <!-- SEARCH CARD -->
    <div class="search-card">
        <div class="search-card-header">
            <h3>🔍 Search Student</h3>
            <a href="admin_home.php" title="Close">✕</a>
        </div>
        <div class="search-card-body">
            <form method="POST" action="admin_search.php">
                <div class="search-input-row">
                    <div class="search-input-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="query"
                               value="<?= htmlspecialchars($query) ?>"
                               placeholder="Search by name, ID, or course…"
                               autofocus>
                    </div>
                    <button type="submit" class="btn-search">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RESULTS -->
    <?php if ($searched): ?>
    <div class="results-card">
        <div class="results-header">
            <div class="hicon">👥</div>
            Search Results
            <span class="results-count"><?= count($students) ?> found</span>
        </div>

        <?php if (empty($students)): ?>
        <div class="state-box">
            <div class="state-icon">🔎</div>
            <h4>No Results Found</h4>
            <p>No students matched "<strong><?= htmlspecialchars($query) ?></strong>". Try a different name or ID.</p>
        </div>

        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <?php if ($session_col): ?><th>Sessions</th><?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s):
                        $fullName = htmlspecialchars(trim($s['FirstName'] . ' ' . $s['MiddleName'] . ' ' . $s['LastName']));
                        $sessions = isset($s['sessions']) ? (int)$s['sessions'] : 0;
                    ?>
                    <tr>
                        <td class="id-cell"><?= htmlspecialchars($s['IdNumber']) ?></td>
                        <td><?= $fullName ?></td>
                        <td><?= htmlspecialchars($s['Course']) ?></td>
                        <td>Year <?= htmlspecialchars($s['CourseLvl']) ?></td>
                        <?php if ($session_col): ?>
                        <td><span class="session-pill">💻 <?= $sessions ?></span></td>
                        <?php endif; ?>
                        <td>
                            <!-- SIT IN button: opens modal pre-filled with this student's data -->
                            <button class="btn-sitin-row"
                                onclick="openSitInModal(
                                    '<?= htmlspecialchars($s['IdNumber'], ENT_QUOTES) ?>',
                                    '<?= addslashes($fullName) ?>',
                                    <?= $sessions ?>
                                )">
                                💻 Sit In
                            </button>
                            <button class="btn-delete" onclick="deleteStudent('<?= $s['IdNumber'] ?>')">🗑️ Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Initial state — no search yet -->
    <div class="results-card">
        <div class="state-box">
            <div class="state-icon">🔍</div>
            <h4>Search for a Student</h4>
            <p>Enter a student name, ID number, or course above and click Search.</p>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    // ── OPEN modal pre-filled with student data from search results ──
    function openSitInModal(id, name, sessions) {
        document.getElementById('modal_id').value   = id;
        document.getElementById('modal_name').value = name;

        const sessionsInput = document.getElementById('modal_sessions');
        const hint          = document.getElementById('sessions_hint');
        const submitBtn     = document.getElementById('btnSitIn');

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
        document.getElementById('sitInModal').classList.add('open');
    }

    function closeSitInModal() {
        document.getElementById('sitInModal').classList.remove('open');
        document.getElementById('modal_id').value         = '';
        document.getElementById('modal_name').value       = '';
        const si = document.getElementById('modal_sessions');
        si.value    = '';
        si.readOnly = false;
        document.getElementById('sessions_hint').textContent = '';
        document.getElementById('sessions_hint').className   = 'sessions-hint';
        document.getElementById('btnSitIn').disabled = false;
    }

    // Close on backdrop click
    document.getElementById('sitInModal').addEventListener('click', function(e) {
        if (e.target === this) closeSitInModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSitInModal();
    });

    function deleteStudent(id) {
        if (confirm('Are you sure you want to delete student ' + id + '?')) {
            window.location.href = 'delete_student.php?id=' + id;
        }
    }
</script>

</body>
</html>