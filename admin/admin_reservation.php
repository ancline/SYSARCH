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

// ── Auto-create reservations table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS reservations (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50) NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        purpose      VARCHAR(255) NOT NULL,
        date         DATE NOT NULL,
        time_slot    VARCHAR(50) NOT NULL,
        status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ── Auto-create lab_pc_status table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS lab_pc_status (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        lab        VARCHAR(100) NOT NULL,
        pc_number  INT NOT NULL,
        status     ENUM('available','unavailable','in_use') DEFAULT 'available',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY lab_pc (lab, pc_number)
    )
");

// ── Auto-create configured_labs table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS configured_labs (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        lab_name         VARCHAR(100) NOT NULL UNIQUE,
        total_pcs        INT NOT NULL DEFAULT 50,
        is_active        TINYINT(1) NOT NULL DEFAULT 1,
        pc_status_set    TINYINT(1) NOT NULL DEFAULT 0,
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Add pc_status_set column if missing (for existing installs)
$conn->query("ALTER TABLE configured_labs ADD COLUMN IF NOT EXISTS pc_status_set TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");

// ════════════════════════════════════════════════════════════════
// AJAX HANDLERS
// ════════════════════════════════════════════════════════════════

// ── AJAX: Get PC statuses for a lab ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_pc_status' && isset($_GET['lab'])) {
    header('Content-Type: application/json');
    $lab = $conn->real_escape_string($_GET['lab']);

    $lab_row = $conn->query("SELECT total_pcs, pc_status_set FROM configured_labs WHERE lab_name='$lab'")->fetch_assoc();
    $total_pcs     = $lab_row ? (int)$lab_row['total_pcs']    : 50;
    $pc_status_set = $lab_row ? (int)$lab_row['pc_status_set'] : 0;

    $result = $conn->query("SELECT pc_number, status FROM lab_pc_status WHERE lab='$lab' ORDER BY pc_number");
    $pcs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pcs[$row['pc_number']] = $row['status'];
        }
    }
    $out = [];
    for ($i = 1; $i <= $total_pcs; $i++) {
        $out[] = ['pc' => $i, 'status' => $pcs[$i] ?? 'available'];
    }
    echo json_encode(['success' => true, 'pcs' => $out, 'total_pcs' => $total_pcs, 'pc_status_set' => $pc_status_set]);
    $conn->close();
    exit();
}

// ── AJAX: Save PC statuses ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'save_pc_status') {
    header('Content-Type: application/json');
    $lab      = $conn->real_escape_string($_POST['lab']      ?? '');
    $statuses = $_POST['statuses'] ?? [];
    $total_pcs = (int)($_POST['total_pcs'] ?? 50);
    if ($total_pcs < 1)   $total_pcs = 1;
    if ($total_pcs > 200) $total_pcs = 200;

    if ($lab && is_array($statuses)) {
        // Mark pc_status_set = 1 since admin has now configured PCs
        $conn->query("UPDATE configured_labs SET total_pcs=$total_pcs, pc_status_set=1, updated_at=NOW() WHERE lab_name='$lab'");

        // Delete old PC rows beyond new total
        $conn->query("DELETE FROM lab_pc_status WHERE lab='$lab' AND pc_number > $total_pcs");

        foreach ($statuses as $pc_num => $status) {
            $pc_num = (int)$pc_num;
            $status = in_array($status, ['available','unavailable','in_use']) ? $status : 'available';
            if ($pc_num >= 1 && $pc_num <= $total_pcs) {
                $conn->query("INSERT INTO lab_pc_status (lab, pc_number, status) VALUES ('$lab', $pc_num, '$status')
                              ON DUPLICATE KEY UPDATE status='$status', updated_at=NOW()");
            }
        }

        // Count available PCs after save
        $avail_count = (int)$conn->query("SELECT COUNT(*) AS c FROM lab_pc_status WHERE lab='$lab' AND status='available'")->fetch_assoc()['c'];
        echo json_encode(['success' => true, 'available_pcs' => $avail_count]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
    $conn->close();
    exit();
}

// ── AJAX: Get configured labs (for student reservation form) ──
// IMPORTANT: Only returns labs that have pc_status_set=1 AND have at least 1 available PC
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_configured_labs') {
    header('Content-Type: application/json');
    $result = $conn->query("SELECT lab_name, total_pcs FROM configured_labs WHERE is_active=1 AND pc_status_set=1 ORDER BY lab_name");
    $labs_out = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $lab_esc = $conn->real_escape_string($row['lab_name']);
            $avail = (int)$conn->query("SELECT COUNT(*) AS c FROM lab_pc_status WHERE lab='$lab_esc' AND status='available'")->fetch_assoc()['c'];
            // Only expose labs with at least 1 available PC
            if ($avail > 0) {
                $labs_out[] = [
                    'name'      => $row['lab_name'],
                    'total_pcs' => $row['total_pcs'],
                    'available' => $avail,
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'labs' => $labs_out]);
    $conn->close();
    exit();
}

// ── AJAX: Get available PCs for a specific lab (for student PC picker) ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_available_pcs' && isset($_GET['lab'])) {
    header('Content-Type: application/json');
    $lab = $conn->real_escape_string($_GET['lab']);

    // Verify lab is active and pc_status_set
    $lab_row = $conn->query("SELECT id FROM configured_labs WHERE lab_name='$lab' AND is_active=1 AND pc_status_set=1")->fetch_assoc();
    if (!$lab_row) {
        echo json_encode(['success' => false, 'error' => 'Lab not available for reservation.']);
        $conn->close();
        exit();
    }

    $result = $conn->query("SELECT pc_number, status FROM lab_pc_status WHERE lab='$lab' AND status='available' ORDER BY pc_number");
    $available = [];
    if ($result) while ($row = $result->fetch_assoc()) $available[] = (int)$row['pc_number'];

    echo json_encode(['success' => true, 'available_pcs' => $available]);
    $conn->close();
    exit();
}

// ════════════════════════════════════════════════════════════════
// POST ACTIONS
// ════════════════════════════════════════════════════════════════

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $res_id = (int)($_POST['res_id'] ?? 0);
    if ($_POST['action'] === 'approve' && $res_id > 0) {
        $conn->query("UPDATE reservations SET status='approved' WHERE id=$res_id AND status='pending'");
        $success_msg = 'Reservation approved.';
    }
    if ($_POST['action'] === 'reject' && $res_id > 0) {
        $conn->query("UPDATE reservations SET status='rejected' WHERE id=$res_id AND status='pending'");
        $success_msg = 'Reservation rejected.';
    }
    if ($_POST['action'] === 'delete' && $res_id > 0) {
        $conn->query("DELETE FROM reservations WHERE id=$res_id");
        $success_msg = 'Reservation deleted.';
    }

    if ($_POST['action'] === 'add_lab') {
        $lab_name  = trim($conn->real_escape_string($_POST['lab_name'] ?? ''));
        $total_pcs = (int)($_POST['total_pcs'] ?? 50);
        if ($total_pcs < 1)   $total_pcs = 1;
        if ($total_pcs > 200) $total_pcs = 200;
        if ($lab_name) {
            // pc_status_set defaults to 0 — admin must configure PCs before students can reserve
            $r = $conn->query("INSERT INTO configured_labs (lab_name, total_pcs, pc_status_set) VALUES ('$lab_name', $total_pcs, 0)");
            if ($r) {
                $success_msg = "Lab \"$lab_name\" added. ⚠️ Remember to set PC statuses before students can reserve!";
            } else {
                $error_msg = "Lab \"$lab_name\" already exists.";
            }
        } else {
            $error_msg = 'Lab name cannot be empty.';
        }
    }

    if ($_POST['action'] === 'edit_lab') {
        $lab_id    = (int)($_POST['lab_id'] ?? 0);
        $lab_name  = trim($conn->real_escape_string($_POST['lab_name'] ?? ''));
        $total_pcs = (int)($_POST['total_pcs'] ?? 50);
        $is_active = (int)($_POST['is_active'] ?? 1);
        if ($total_pcs < 1)   $total_pcs = 1;
        if ($total_pcs > 200) $total_pcs = 200;
        if ($lab_id && $lab_name) {
            $conn->query("UPDATE configured_labs SET lab_name='$lab_name', total_pcs=$total_pcs, is_active=$is_active, updated_at=NOW() WHERE id=$lab_id");
            $conn->query("DELETE FROM lab_pc_status WHERE lab='$lab_name' AND pc_number > $total_pcs");
            $success_msg = "Lab updated.";
        }
    }

    if ($_POST['action'] === 'delete_lab') {
        $lab_id = (int)($_POST['lab_id'] ?? 0);
        if ($lab_id) {
            $row = $conn->query("SELECT lab_name FROM configured_labs WHERE id=$lab_id")->fetch_assoc();
            if ($row) {
                $ln = $conn->real_escape_string($row['lab_name']);
                $conn->query("DELETE FROM lab_pc_status WHERE lab='$ln'");
                $conn->query("DELETE FROM configured_labs WHERE id=$lab_id");
                $success_msg = "Lab \"{$row['lab_name']}\" removed.";
            }
        }
    }

    if ($_POST['action'] === 'toggle_lab') {
        $lab_id = (int)($_POST['lab_id'] ?? 0);
        if ($lab_id) {
            $conn->query("UPDATE configured_labs SET is_active = 1 - is_active, updated_at=NOW() WHERE id=$lab_id");
            $success_msg = 'Lab availability toggled.';
        }
    }
}

// ════════════════════════════════════════════════════════════════
// DATA FETCH
// ════════════════════════════════════════════════════════════════

$total_res    = $conn->query("SELECT COUNT(*) AS c FROM reservations")->fetch_assoc()['c'] ?? 0;
$pending_res  = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$approved_res = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$rejected_res = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='rejected'")->fetch_assoc()['c'] ?? 0;

// Configured labs — now also fetches pc_status_set
$configured_labs = [];
$clr = $conn->query("SELECT * FROM configured_labs ORDER BY lab_name");
if ($clr) while ($row = $clr->fetch_assoc()) {
    $ln = $conn->real_escape_string($row['lab_name']);
    $row['available_pcs'] = (int)($conn->query("SELECT COUNT(*) AS c FROM lab_pc_status WHERE lab='$ln' AND status='available'")->fetch_assoc()['c'] ?? 0);
    $row['in_use_pcs']    = (int)($conn->query("SELECT COUNT(*) AS c FROM lab_pc_status WHERE lab='$ln' AND status='in_use'")->fetch_assoc()['c'] ?? 0);
    $configured_labs[]    = $row;
}

// Count labs that need PC setup
$labs_needing_setup = array_filter($configured_labs, fn($l) => !$l['pc_status_set'] && $l['is_active']);

// Reservations with filters
$filter_status = $_GET['status'] ?? '';
$filter_lab    = $_GET['lab']    ?? '';
$search        = $_GET['search'] ?? '';

$where = [];
if ($filter_status) $where[] = "r.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($filter_lab)    $where[] = "r.lab = '"    . $conn->real_escape_string($filter_lab)    . "'";
if ($search)        $where[] = "(r.student_id LIKE '%" . $conn->real_escape_string($search) . "%' OR r.student_name LIKE '%" . $conn->real_escape_string($search) . "%' OR r.purpose LIKE '%" . $conn->real_escape_string($search) . "%')";

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reservations = [];
$rr = $conn->query("SELECT r.* FROM reservations r $where_sql ORDER BY r.created_at DESC LIMIT 100");
if ($rr) while ($row = $rr->fetch_assoc()) $reservations[] = $row;

$res_labs = [];
$lr = $conn->query("SELECT DISTINCT lab FROM reservations ORDER BY lab");
if ($lr) while ($row = $lr->fetch_assoc()) $res_labs[] = $row['lab'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin – Reservations</title>
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
            --green:      #1a7a4a;
            --green-light:#edfaf3;
            --red:        #c0392b;
            --red-light:  #fff0f0;
            --orange:     #c07000;
            --orange-light:#fff8e1;
            --amber:      #92400e;
            --amber-light:#fffbeb;
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
            height: 60px; position: sticky; top: 0; z-index: 100;
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
            border-radius: 6px; transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px; white-space: nowrap;
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

        /* ── PAGE ── */
        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .page-header { margin-bottom: 24px; }
        .page-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 24px; color: var(--navy); margin-bottom: 3px;
        }
        .page-header p { font-size: 13px; color: var(--muted); }

        /* ── SETUP REQUIRED BANNER ── */
        .setup-banner {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1.5px solid #fcd34d;
            border-left: 5px solid #f59e0b;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .setup-banner-icon { font-size: 28px; flex-shrink: 0; }
        .setup-banner-text h4 { font-size: 14px; font-weight: 700; color: #92400e; margin-bottom: 3px; }
        .setup-banner-text p  { font-size: 12.5px; color: #a16207; line-height: 1.5; }
        .setup-banner-labs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .setup-banner-lab-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fef3c7; border: 1px solid #fcd34d;
            color: #92400e; border-radius: 20px;
            font-size: 12px; font-weight: 700; padding: 3px 10px;
            cursor: pointer; transition: background 0.15s;
        }
        .setup-banner-lab-chip:hover { background: #fde68a; }

        /* ── STAT STRIP ── */
        .stat-strip {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--panel); border-radius: 14px;
            border: 1px solid var(--border); padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 4px 18px rgba(15,38,83,0.07);
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,38,83,0.12); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue   { background: linear-gradient(135deg, var(--navy), var(--navy-light)); }
        .stat-icon.orange { background: linear-gradient(135deg, #c07000, #f0a500); }
        .stat-icon.green  { background: linear-gradient(135deg, #0f6e3a, #1a7a4a); }
        .stat-icon.red    { background: linear-gradient(135deg, #9b1c1c, #c0392b); }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--navy); line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 11.5px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── ALERT ── */
        .alert {
            padding: 10px 16px; border-radius: 9px;
            font-size: 13px; font-weight: 600; margin-bottom: 16px;
            display: flex; align-items: center; gap: 9px;
        }
        .alert.success { background: var(--green-light); color: var(--green); border: 1px solid #b2e8cc; border-left: 4px solid #2ecc71; }
        .alert.error   { background: var(--red-light);   color: var(--red);   border: 1px solid #f5c6cb; border-left: 4px solid #e74c3c; }
        .alert.warning { background: var(--amber-light); color: var(--amber); border: 1px solid #fcd34d; border-left: 4px solid #f59e0b; }

        /* ── CARD ── */
        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 18px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header-left { display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        /* ── LAB CONFIG SECTION ── */
        .lab-config-body { padding: 20px 22px; }

        /* Add-lab form */
        .add-lab-form {
            display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 18px; margin-bottom: 20px;
        }
        .add-lab-form .field { display: flex; flex-direction: column; gap: 5px; }
        .add-lab-form label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .add-lab-form input[type="text"],
        .add-lab-form input[type="number"] {
            height: 36px; padding: 0 12px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .add-lab-form input[type="text"] { width: 220px; }
        .add-lab-form input[type="number"] { width: 100px; }
        .add-lab-form input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); }
        .btn-add-lab {
            height: 36px; padding: 0 20px; border: none; border-radius: 8px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            box-shadow: 0 2px 8px rgba(15,38,83,0.2);
            transition: opacity 0.15s, transform 0.15s;
        }
        .btn-add-lab:hover { opacity: 0.88; transform: translateY(-1px); }
        .add-lab-note {
            width: 100%; font-size: 11.5px; color: var(--amber);
            background: var(--amber-light); border: 1px solid #fde68a;
            border-radius: 7px; padding: 7px 12px;
            display: flex; align-items: center; gap: 6px;
        }

        /* Lab grid */
        .lab-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }
        .lab-config-card {
            border: 1px solid var(--border); border-radius: 12px;
            background: white; overflow: hidden;
            box-shadow: 0 2px 10px rgba(15,38,83,0.06);
            transition: transform 0.16s, box-shadow 0.16s;
            position: relative;
        }
        .lab-config-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(15,38,83,0.12); }
        .lab-config-card.inactive { opacity: 0.55; }

        /* PC setup required indicator */
        .pc-setup-required-badge {
            position: absolute; top: 10px; right: 10px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white; font-size: 10px; font-weight: 800;
            padding: 3px 9px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(245,158,11,0.4);
            animation: pulse-badge 2s ease-in-out infinite;
        }
        @keyframes pulse-badge {
            0%, 100% { box-shadow: 0 2px 8px rgba(245,158,11,0.4); }
            50%       { box-shadow: 0 2px 16px rgba(245,158,11,0.7); }
        }
        .pc-setup-done-badge {
            position: absolute; top: 10px; right: 10px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white; font-size: 10px; font-weight: 800;
            padding: 3px 9px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* "Needs PC setup" overlay on card */
        .lab-config-card.needs-setup {
            border-color: #fcd34d;
            box-shadow: 0 2px 10px rgba(245,158,11,0.15);
        }
        .lab-setup-warning {
            margin: 0 14px 10px;
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 8px; padding: 8px 12px;
            display: flex; align-items: center; gap: 8px;
            font-size: 11.5px; color: var(--amber); font-weight: 600;
        }
        .lab-setup-warning .btn-setup-now {
            margin-left: auto; height: 24px; padding: 0 10px;
            background: #f59e0b; color: white; border: none;
            border-radius: 6px; font-size: 11px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            white-space: nowrap; transition: opacity 0.15s;
        }
        .lab-setup-warning .btn-setup-now:hover { opacity: 0.85; }

        .lab-card-top {
            padding: 14px 16px 10px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .lab-card-icon {
            width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 18px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
        }
        .lab-card-info { flex: 1; min-width: 0; }
        .lab-card-name {
            font-size: 14px; font-weight: 700; color: var(--navy);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            padding-right: 80px; /* space for badge */
        }
        .lab-card-meta { font-size: 11.5px; color: var(--muted); margin-top: 3px; }

        /* PC bar */
        .pc-availability-bar {
            margin: 0 16px 10px; height: 7px; background: #eef1f8;
            border-radius: 10px; overflow: hidden;
        }
        .pc-bar-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #22c55e, #16a34a); transition: width 0.4s; }
        .pc-bar-fill.not-set { background: linear-gradient(90deg, #d1d5db, #9ca3af); }

        .lab-pc-counts {
            display: flex; gap: 12px; padding: 0 16px 10px;
        }
        .pc-count-chip {
            font-size: 11px; font-weight: 700; padding: 3px 9px;
            border-radius: 20px;
        }
        .pc-count-chip.avail { background: #edfaf3; color: #1a7a4a; }
        .pc-count-chip.inuse { background: #fff8e1; color: #c07000; }
        .pc-count-chip.total { background: var(--tag-bg); color: var(--navy-light); }
        .pc-count-chip.not-set { background: #f3f4f6; color: #6b7280; }

        .lab-card-footer {
            border-top: 1px solid var(--border);
            padding: 9px 12px;
            display: flex; gap: 6px; align-items: center;
            background: #fafbfd;
        }
        .btn-lab-action {
            height: 28px; padding: 0 11px; border-radius: 7px;
            font-size: 11.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            border: 1px solid; transition: opacity 0.15s, transform 0.15s;
        }
        .btn-lab-action:hover { opacity: 0.8; transform: translateY(-1px); }
        .btn-lab-pcs        { background: #e8edf8; color: var(--navy-light); border-color: #c8d4ee; }
        .btn-lab-pcs.urgent { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border-color: #fcd34d; animation: pulse-btn 2s ease-in-out infinite; }
        @keyframes pulse-btn {
            0%,100% { box-shadow: none; }
            50%      { box-shadow: 0 0 0 3px rgba(245,158,11,0.25); }
        }
        .btn-lab-toggle-on  { background: #fff8e1; color: #c07000; border-color: #fcd87a; }
        .btn-lab-toggle-off { background: #edfaf3; color: var(--green); border-color: #b2e8cc; }
        .btn-lab-edit  { background: #f0f3f9; color: var(--muted); border-color: var(--border); }
        .btn-lab-del   { background: var(--red-light); color: var(--red); border-color: #f5c6cb; margin-left: auto; }

        .inactive-badge {
            font-size: 10px; font-weight: 700; padding: 2px 8px;
            background: #f5f5f5; color: #999; border-radius: 20px;
            border: 1px solid #ddd; text-transform: uppercase; letter-spacing: 0.4px;
            margin-left: auto;
        }
        .active-badge {
            font-size: 10px; font-weight: 700; padding: 2px 8px;
            background: #edfaf3; color: var(--green); border-radius: 20px;
            border: 1px solid #b2e8cc; text-transform: uppercase; letter-spacing: 0.4px;
            margin-left: auto;
        }

        .empty-labs { text-align: center; padding: 40px 24px; color: var(--muted); }
        .empty-labs .el-icon { font-size: 40px; margin-bottom: 10px; opacity: 0.4; }
        .empty-labs h3 { font-size: 14px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
        .empty-labs p  { font-size: 13px; }

        /* ── RESERVATION READINESS INDICATOR ── */
        .readiness-strip {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 18px; background: #f0fdf4;
            border-bottom: 1px solid #bbf7d0;
            font-size: 12px; color: var(--green); font-weight: 600;
        }
        .readiness-strip.has-issues {
            background: #fffbeb; border-color: #fde68a; color: var(--amber);
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            padding: 14px 18px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            background: #fafbfd;
        }
        .filter-bar label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .filter-bar input[type="text"],
        .filter-bar select {
            height: 34px; padding: 0 11px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: white; outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .filter-bar input[type="text"] { width: 210px; }
        .filter-bar input[type="text"]:focus,
        .filter-bar select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); }
        .btn-filter {
            height: 34px; padding: 0 16px;
            background: var(--accent); color: white;
            border: none; border-radius: 8px;
            font-size: 12.5px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: opacity 0.15s, transform 0.15s;
        }
        .btn-filter:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-reset-filter {
            height: 34px; padding: 0 14px;
            border: 1px solid var(--border); border-radius: 8px;
            background: white; color: var(--muted);
            font-size: 12px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background 0.15s, color 0.15s;
        }
        .btn-reset-filter:hover { background: var(--tag-bg); color: var(--navy); }
        .filter-count { margin-left: auto; font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead tr { background: var(--tag-bg); border-bottom: 2px solid var(--border); }
        thead th {
            padding: 11px 14px; text-align: left;
            font-size: 11px; font-weight: 700; color: var(--navy);
            text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
        }
        thead th.center { text-align: center; }
        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.14s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }
        tbody td { padding: 11px 14px; vertical-align: middle; }
        tbody td.center { text-align: center; }
        .student-cell .s-name { font-weight: 700; color: var(--navy); font-size: 13px; }
        .student-cell .s-id   { font-size: 11px; color: var(--muted); margin-top: 1px; }

        .lab-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--tag-bg); color: var(--navy-light);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee;
            white-space: nowrap; cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
        }
        .lab-tag:hover { background: #d0daf4; box-shadow: 0 2px 8px rgba(36,82,160,0.15); }

        .slot-tag {
            display: inline-flex; align-items: center;
            background: #f0f3f9; color: var(--text);
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; border: 1px solid var(--border);
            white-space: nowrap;
        }
        .td-date { font-weight: 700; color: var(--navy); font-size: 13px; white-space: nowrap; }
        .td-date-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .status-pill.pending   { background: var(--orange-light); color: var(--orange); }
        .status-pill.approved  { background: var(--green-light);  color: var(--green); }
        .status-pill.rejected  { background: var(--red-light);    color: var(--red); }
        .status-pill.cancelled { background: #f0f3f9; color: var(--muted); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── ACTION BUTTONS ── */
        .action-btns { display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap; }
        .btn-approve, .btn-reject, .btn-delete, .btn-pc {
            padding: 5px 12px; border-radius: 7px;
            font-size: 11.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer; border: none;
            transition: opacity 0.15s, transform 0.15s;
        }
        .btn-approve { background: var(--green-light); color: var(--green); border: 1px solid #b2e8cc; }
        .btn-reject  { background: var(--orange-light); color: var(--orange); border: 1px solid #fcd87a; }
        .btn-delete  { background: var(--red-light); color: var(--red); border: 1px solid #f5c6cb; }
        .btn-pc      { background: #e8edf8; color: var(--navy-light); border: 1px solid #c8d4ee; }
        .btn-approve:hover, .btn-reject:hover, .btn-delete:hover, .btn-pc:hover {
            opacity: 0.8; transform: translateY(-1px);
        }

        .empty-state { text-align: center; padding: 56px 24px; color: var(--muted); }
        .empty-state .empty-icon { font-size: 44px; margin-bottom: 12px; opacity: 0.4; }
        .empty-state h3 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
        .empty-state p  { font-size: 13px; }

        /* ── CONFIRM MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,38,83,0.45); z-index: 500;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; border-radius: 18px; padding: 30px 28px;
            width: 100%; max-width: 380px;
            box-shadow: 0 16px 48px rgba(15,38,83,0.3);
            animation: modalIn 0.2s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.94) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-icon { font-size: 36px; text-align: center; margin-bottom: 12px; }
        .modal h3   { font-size: 17px; font-weight: 700; color: var(--navy); text-align: center; margin-bottom: 8px; }
        .modal p    { font-size: 13px; color: var(--muted); text-align: center; line-height: 1.6; margin-bottom: 22px; }
        .modal-btns { display: flex; gap: 10px; }
        .modal-btns button {
            flex: 1; padding: 11px; border-radius: 10px;
            font-size: 13.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer; border: none;
            transition: opacity 0.15s, transform 0.15s;
        }
        .modal-btns button:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-modal-confirm-approve { background: #1a7a4a; color: white; }
        .btn-modal-confirm-reject  { background: #c07000; color: white; }
        .btn-modal-confirm-delete  { background: var(--red); color: white; }
        .btn-modal-close           { background: var(--tag-bg); color: var(--navy); }

        /* ── EDIT LAB MODAL ── */
        .edit-lab-modal {
            background: white; border-radius: 18px; padding: 28px 26px;
            width: 100%; max-width: 400px;
            box-shadow: 0 16px 48px rgba(15,38,83,0.3);
            animation: modalIn 0.2s ease;
        }
        .edit-lab-modal h3 { font-size: 16px; font-weight: 700; color: var(--navy); margin-bottom: 18px; }
        .edit-lab-modal .field { margin-bottom: 14px; }
        .edit-lab-modal label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px; }
        .edit-lab-modal input[type="text"],
        .edit-lab-modal input[type="number"],
        .edit-lab-modal select {
            width: 100%; height: 38px; padding: 0 12px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .edit-lab-modal input:focus,
        .edit-lab-modal select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); }
        .edit-lab-modal-btns { display: flex; gap: 10px; margin-top: 20px; }
        .edit-lab-modal-btns button {
            flex: 1; padding: 10px; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer; border: none;
            transition: opacity 0.15s;
        }
        .btn-edit-save  { background: linear-gradient(135deg, var(--navy), var(--navy-light)); color: white; }
        .btn-edit-close { background: var(--tag-bg); color: var(--navy); }

        /* ── PC AVAILABILITY MODAL ── */
        .pc-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,38,83,0.55); z-index: 600;
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .pc-modal-overlay.open { display: flex; }
        .pc-modal {
            background: white; border-radius: 20px;
            width: 100%; max-width: 700px;
            box-shadow: 0 24px 64px rgba(15,38,83,0.35);
            animation: modalIn 0.22s ease;
            overflow: hidden;
            max-height: 90vh;
            display: flex; flex-direction: column;
        }
        .pc-modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 18px 24px;
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0;
        }
        .pc-modal-header-left { display: flex; align-items: center; gap: 12px; }
        .pc-modal-header-icon {
            width: 38px; height: 38px; background: rgba(255,255,255,0.15);
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 18px;
        }
        .pc-modal-header h3 { font-size: 15px; font-weight: 700; color: white; margin: 0 0 2px; }
        .pc-modal-header p  { font-size: 12px; color: rgba(255,255,255,0.65); margin: 0; }
        .pc-modal-close {
            width: 32px; height: 32px; background: rgba(255,255,255,0.15);
            border: none; border-radius: 8px; color: white;
            font-size: 16px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .pc-modal-close:hover { background: rgba(255,255,255,0.25); }

        /* First-time setup notice inside PC modal */
        .pc-first-setup-notice {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-bottom: 1px solid #fde68a;
            padding: 12px 24px;
            display: flex; align-items: center; gap: 10px;
            font-size: 12.5px; color: var(--amber); font-weight: 600;
            flex-shrink: 0;
        }
        .pc-first-setup-notice.hidden { display: none; }

        .pc-modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }

        /* PC count control */
        .pc-count-control {
            display: flex; align-items: center; gap: 12px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;
        }
        .pc-count-control label { font-size: 12px; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: 0.4px; }
        .pc-count-control input[type="number"] {
            width: 80px; height: 32px; padding: 0 10px;
            border: 1px solid var(--border); border-radius: 7px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; text-align: center;
            outline: none; transition: border-color 0.18s;
        }
        .pc-count-control input:focus { border-color: var(--accent); }
        .btn-apply-count {
            height: 32px; padding: 0 16px; border: none; border-radius: 7px;
            background: var(--accent); color: white;
            font-size: 12px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: opacity 0.15s;
        }
        .btn-apply-count:hover { opacity: 0.85; }
        .pc-count-note { font-size: 11.5px; color: var(--muted); margin-left: auto; }

        /* Legend */
        .pc-legend {
            display: flex; gap: 16px; flex-wrap: wrap;
            margin-bottom: 18px; padding: 12px 16px;
            background: var(--bg); border-radius: 10px; border: 1px solid var(--border);
        }
        .pc-legend-item {
            display: flex; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 600; color: var(--text);
            cursor: pointer; padding: 4px 8px; border-radius: 6px;
            transition: background 0.14s;
        }
        .pc-legend-item:hover { background: rgba(0,0,0,0.05); }
        .legend-dot { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }
        .legend-dot.available   { background: #22c55e; }
        .legend-dot.unavailable { background: #ef4444; }
        .legend-dot.in_use      { background: #f59e0b; }
        .pc-legend-note { margin-left: auto; font-size: 11px; color: var(--muted); display: flex; align-items: center; font-weight: 400; }

        /* Stats row */
        .pc-stats { display: flex; gap: 10px; margin-bottom: 18px; }
        .pc-stat-chip { flex: 1; text-align: center; padding: 10px 8px; border-radius: 10px; border: 1px solid var(--border); }
        .pc-stat-chip .cs-value { font-size: 22px; font-weight: 700; line-height: 1; margin-bottom: 3px; }
        .pc-stat-chip .cs-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600; }
        .pc-stat-chip.available   { background: #f0fdf4; border-color: #bbf7d0; }
        .pc-stat-chip.available   .cs-value { color: #16a34a; }
        .pc-stat-chip.available   .cs-label { color: #4ade80; }
        .pc-stat-chip.unavailable { background: #fff1f2; border-color: #fecdd3; }
        .pc-stat-chip.unavailable .cs-value { color: #dc2626; }
        .pc-stat-chip.unavailable .cs-label { color: #f87171; }
        .pc-stat-chip.in_use      { background: #fffbeb; border-color: #fde68a; }
        .pc-stat-chip.in_use      .cs-value { color: #d97706; }
        .pc-stat-chip.in_use      .cs-label { color: #fbbf24; }

        /* PC Grid */
        .pc-grid-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .pc-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 8px; margin-bottom: 20px; }
        .pc-cell {
            aspect-ratio: 1; border-radius: 9px; border: 2px solid transparent;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            cursor: pointer; transition: transform 0.14s, box-shadow 0.14s;
            position: relative; min-height: 48px; user-select: none;
        }
        .pc-cell:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .pc-cell:active { transform: scale(0.96); }
        .pc-cell.available   { background: linear-gradient(135deg, #22c55e, #16a34a); border-color: #15803d; }
        .pc-cell.unavailable { background: linear-gradient(135deg, #ef4444, #dc2626); border-color: #b91c1c; }
        .pc-cell.in_use      { background: linear-gradient(135deg, #f59e0b, #d97706); border-color: #b45309; }
        .pc-number { font-size: 11px; font-weight: 800; color: rgba(255,255,255,0.95); line-height: 1; }
        .pc-icon-small { font-size: 14px; margin-bottom: 2px; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2)); }

        /* Bulk actions */
        .pc-bulk {
            display: flex; gap: 8px; flex-wrap: wrap;
            padding-top: 16px; border-top: 1px solid var(--border); margin-bottom: 4px;
        }
        .pc-bulk-label {
            font-size: 11.5px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.4px;
            display: flex; align-items: center; margin-right: 4px;
        }
        .btn-bulk {
            height: 30px; padding: 0 12px; border-radius: 7px;
            font-size: 11.5px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            border: 1px solid; transition: opacity 0.15s, transform 0.15s;
        }
        .btn-bulk:hover { opacity: 0.8; transform: translateY(-1px); }
        .btn-bulk.all-available   { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .btn-bulk.all-unavailable { background: #fff1f2; color: #dc2626; border-color: #fecdd3; }
        .btn-bulk.all-in-use      { background: #fffbeb; color: #d97706; border-color: #fde68a; }

        /* Footer */
        .pc-modal-footer {
            padding: 14px 24px; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
            background: #fafbfd; flex-shrink: 0;
        }
        .btn-pc-cancel {
            height: 38px; padding: 0 20px; border-radius: 10px;
            border: 1px solid var(--border); background: white;
            color: var(--muted); font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer; transition: background 0.15s;
        }
        .btn-pc-cancel:hover { background: var(--tag-bg); color: var(--navy); }
        .btn-pc-save {
            height: 38px; padding: 0 24px; border-radius: 10px;
            border: none; background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            box-shadow: 0 2px 10px rgba(15,38,83,0.25);
            transition: opacity 0.15s, transform 0.15s;
            display: flex; align-items: center; gap: 7px;
        }
        .btn-pc-save:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-pc-save:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .pc-save-toast {
            display: none; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 600; color: var(--green);
            background: var(--green-light); padding: 6px 14px;
            border-radius: 8px; border: 1px solid #b2e8cc; margin-right: auto;
        }
        .pc-save-toast.show { display: flex; }

        /* Loading */
        .pc-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; gap: 14px; }
        .spinner { width: 36px; height: 36px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .pc-loading p { font-size: 13px; color: var(--muted); font-weight: 500; }

        @media (max-width: 1000px) {
            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .page-wrapper { padding: 20px 16px 40px; }
            .nav-title { display: none; }
        }
        @media (max-width: 560px) {
            .stat-strip { grid-template-columns: 1fr; }
            .pc-grid { grid-template-columns: repeat(5, 1fr); }
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
        <a href="/SYSARCH/admin/admin_home.php">Home ▾</a>
        <a href="/SYSARCH/admin/admin_search.php">Search</a>
        <a href="/SYSARCH/admin/admin_Student.php">Students</a>
        <a href="/SYSARCH/admin/admin_SitIn.php">Sit-in</a>
        <a href="/SYSARCH/admin/admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="/SYSARCH/admin/admin_SitInReports.php">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="/SYSARCH/admin/admin_reservation.php" class="active">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon">❓</div>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalDesc">Are you sure?</p>
        <div class="modal-btns">
            <button class="btn-modal-close" onclick="closeModal()">Cancel</button>
            <form method="POST" style="flex:1;" id="modalForm">
                <input type="hidden" name="action" id="modalAction">
                <input type="hidden" name="res_id"  id="modalResId">
                <button type="submit" id="modalConfirmBtn" style="width:100%;">Confirm</button>
            </form>
        </div>
    </div>
</div>

<!-- EDIT LAB MODAL -->
<div class="modal-overlay" id="editLabModal">
    <div class="edit-lab-modal">
        <h3>✏️ Edit Laboratory</h3>
        <form method="POST" id="editLabForm">
            <input type="hidden" name="action" value="edit_lab">
            <input type="hidden" name="lab_id" id="editLabId">
            <div class="field">
                <label>Laboratory Name</label>
                <input type="text" name="lab_name" id="editLabName" required placeholder="e.g. Lab 524">
            </div>
            <div class="field">
                <label>Number of PCs Available for Reservation</label>
                <input type="number" name="total_pcs" id="editLabPcs" min="1" max="200" required>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="is_active" id="editLabActive">
                    <option value="1">Active (students can reserve)</option>
                    <option value="0">Inactive (hidden from students)</option>
                </select>
            </div>
            <div class="edit-lab-modal-btns">
                <button type="button" class="btn-edit-close" onclick="closeEditLabModal()">Cancel</button>
                <button type="submit" class="btn-edit-save">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- PC AVAILABILITY MODAL -->
<div class="pc-modal-overlay" id="pcModal">
    <div class="pc-modal">
        <div class="pc-modal-header">
            <div class="pc-modal-header-left">
                <div class="pc-modal-header-icon">🖥️</div>
                <div>
                    <h3 id="pcModalTitle">Laboratory PC Status</h3>
                    <p id="pcModalSubtitle">Click a PC to cycle its status</p>
                </div>
            </div>
            <button class="pc-modal-close" onclick="closePcModal()">✕</button>
        </div>

        <!-- First-time setup notice -->
        <div class="pc-first-setup-notice hidden" id="pcFirstSetupNotice">
            ⚠️ <strong>Setup required:</strong> This lab has no PC conditions set yet. Students <strong>cannot reserve</strong> until you save PC statuses below.
        </div>

        <div class="pc-modal-body" id="pcModalBody">
            <div class="pc-loading"><div class="spinner"></div><p>Loading…</p></div>
        </div>

        <div class="pc-modal-footer">
            <div class="pc-save-toast" id="pcSaveToast">✅ Saved! Students can now see these PC conditions and reserve available spots.</div>
            <button class="btn-pc-cancel" onclick="closePcModal()">Cancel</button>
            <button class="btn-pc-save" id="btnPcSave" onclick="savePcStatuses()">💾 Save &amp; Publish</button>
        </div>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <h2>Reservation Management</h2>
        <p>Set PC conditions for each lab first — students can only reserve once PC statuses are published.</p>
    </div>

    <?php if (!empty($labs_needing_setup)): ?>
    <!-- SETUP REQUIRED BANNER -->
    <div class="setup-banner">
        <div class="setup-banner-icon">⚠️</div>
        <div class="setup-banner-text">
            <h4>PC conditions not set for <?= count($labs_needing_setup) ?> lab<?= count($labs_needing_setup) > 1 ? 's' : '' ?> — students cannot reserve these labs yet</h4>
            <p>You must set and publish PC statuses before students can see or reserve spots in these labs. Click a lab below to configure:</p>
            <div class="setup-banner-labs">
                <?php foreach ($labs_needing_setup as $nsl): ?>
                <span class="setup-banner-lab-chip"
                    onclick="openPcModal('<?= htmlspecialchars(addslashes($nsl['lab_name'])) ?>', <?= (int)$nsl['total_pcs'] ?>)">
                    🏛️ <?= htmlspecialchars($nsl['lab_name']) ?> → Set PC Status
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-icon blue">📋</div>
            <div><div class="stat-value"><?= $total_res ?></div><div class="stat-label">Total Reservations</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">⏳</div>
            <div><div class="stat-value"><?= $pending_res ?></div><div class="stat-label">Pending</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div><div class="stat-value"><?= $approved_res ?></div><div class="stat-label">Approved</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">❌</div>
            <div><div class="stat-value"><?= $rejected_res ?></div><div class="stat-label">Rejected</div></div>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert success">✅ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert error">❌ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION 1: LAB CONFIGURATION
    ════════════════════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="hicon">🏛️</div>
                Lab Configuration
            </div>
            <div style="font-size:11.5px;color:rgba(255,255,255,0.65);font-weight:500;">
                Labs open for reservation only after PC conditions are published
            </div>
        </div>

        <div class="lab-config-body">

            <!-- Add Lab Form -->
            <form method="POST" class="add-lab-form">
                <input type="hidden" name="action" value="add_lab">
                <div class="field">
                    <label>Laboratory Name</label>
                    <input type="text" name="lab_name" placeholder="e.g. Lab 524, PC Lab 3…" required>
                </div>
                <div class="field">
                    <label>Number of PCs for Reservation</label>
                    <input type="number" name="total_pcs" value="50" min="1" max="200" required>
                </div>
                <button type="submit" class="btn-add-lab">➕ Add Laboratory</button>
                <div class="add-lab-note">
                    ℹ️ After adding a lab, you must click <strong>🖥️ Set PC Status</strong> and publish conditions before students can reserve.
                </div>
            </form>

            <!-- Lab Cards -->
            <?php if (empty($configured_labs)): ?>
            <div class="empty-labs">
                <div class="el-icon">🏛️</div>
                <h3>No labs configured yet</h3>
                <p>Add a lab above so students can start making reservations.</p>
            </div>
            <?php else: ?>
            <div class="lab-grid">
                <?php foreach ($configured_labs as $cl):
                    $avail_pct   = $cl['total_pcs'] > 0 ? round(($cl['available_pcs'] / $cl['total_pcs']) * 100) : 0;
                    $inactive    = !$cl['is_active'];
                    $needs_setup = !$cl['pc_status_set'];
                ?>
                <div class="lab-config-card <?= $inactive ? 'inactive' : '' ?> <?= $needs_setup ? 'needs-setup' : '' ?>">

                    <?php if ($needs_setup): ?>
                    <div class="pc-setup-required-badge">⚙️ Setup Required</div>
                    <?php else: ?>
                    <div class="pc-setup-done-badge">✓ Ready</div>
                    <?php endif; ?>

                    <div class="lab-card-top">
                        <div class="lab-card-icon">🏛️</div>
                        <div class="lab-card-info">
                            <div class="lab-card-name"><?= htmlspecialchars($cl['lab_name']) ?></div>
                            <div class="lab-card-meta"><?= (int)$cl['total_pcs'] ?> PCs configured for reservation</div>
                        </div>
                    </div>

                    <?php if ($needs_setup): ?>
                    <!-- Warning: PC status not yet set -->
                    <div class="lab-setup-warning">
                        🔒 Students cannot reserve — PC conditions not set
                        <button type="button" class="btn-setup-now"
                            onclick="openPcModal('<?= htmlspecialchars(addslashes($cl['lab_name'])) ?>', <?= (int)$cl['total_pcs'] ?>)">
                            Set Now →
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="pc-availability-bar">
                        <div class="pc-bar-fill" style="width:<?= $avail_pct ?>%"></div>
                    </div>
                    <div class="lab-pc-counts">
                        <span class="pc-count-chip avail">✓ <?= $cl['available_pcs'] ?> available</span>
                        <span class="pc-count-chip inuse">⏸ <?= $cl['in_use_pcs'] ?> in use</span>
                        <span class="pc-count-chip total">📊 <?= $cl['total_pcs'] ?> total</span>
                    </div>
                    <?php endif; ?>

                    <div class="lab-card-footer">
                        <button class="btn-lab-action btn-lab-pcs <?= $needs_setup ? 'urgent' : '' ?>"
                            onclick="openPcModal('<?= htmlspecialchars(addslashes($cl['lab_name'])) ?>', <?= (int)$cl['total_pcs'] ?>)">
                            🖥️ <?= $needs_setup ? 'Set PC Status ⚠️' : 'Manage PCs' ?>
                        </button>
                        <button class="btn-lab-action btn-lab-edit"
                            onclick="openEditLabModal(<?= $cl['id'] ?>, '<?= htmlspecialchars(addslashes($cl['lab_name'])) ?>', <?= $cl['total_pcs'] ?>, <?= $cl['is_active'] ?>)">
                            ✏️ Edit
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_lab">
                            <input type="hidden" name="lab_id" value="<?= $cl['id'] ?>">
                            <button type="submit"
                                class="btn-lab-action <?= $inactive ? 'btn-lab-toggle-off' : 'btn-lab-toggle-on' ?>"
                                title="<?= $inactive ? 'Enable lab' : 'Disable lab' ?>">
                                <?= $inactive ? '▶ Enable' : '⏸ Disable' ?>
                            </button>
                        </form>
                        <?php if ($inactive): ?>
                        <span class="inactive-badge">Inactive</span>
                        <?php else: ?>
                        <span class="active-badge">Active</span>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;margin-left:auto;"
                            onsubmit="return confirm('Delete lab \'<?= htmlspecialchars(addslashes($cl['lab_name'])) ?>\'? This also clears its PC status data.')">
                            <input type="hidden" name="action" value="delete_lab">
                            <input type="hidden" name="lab_id" value="<?= $cl['id'] ?>">
                            <button type="submit" class="btn-lab-action btn-lab-del">🗑</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div><!-- /lab-config card -->

    <!-- ═══════════════════════════════════════════════════════════
         SECTION 2: RESERVATION REQUESTS
    ════════════════════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="hicon">📅</div>
                Reservation Requests
            </div>
            <div style="font-size:11.5px;color:rgba(255,255,255,0.65);font-weight:500;">
                🖥️ Click a lab name to manage its PC statuses
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <label>Filter:</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student, purpose…">
            <select name="status">
                <option value="">All Status</option>
                <option value="pending"   <?= $filter_status==='pending'   ? 'selected':'' ?>>Pending</option>
                <option value="approved"  <?= $filter_status==='approved'  ? 'selected':'' ?>>Approved</option>
                <option value="rejected"  <?= $filter_status==='rejected'  ? 'selected':'' ?>>Rejected</option>
                <option value="cancelled" <?= $filter_status==='cancelled' ? 'selected':'' ?>>Cancelled</option>
            </select>
            <select name="lab">
                <option value="">All Labs</option>
                <?php foreach ($res_labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>" <?= $filter_lab===$lab ? 'selected':'' ?>>
                    <?= htmlspecialchars($lab) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">🔍 Filter</button>
            <a href="admin_reservation.php" style="text-decoration:none;">
                <button type="button" class="btn-reset-filter">✕ Reset</button>
            </a>
            <span class="filter-count"><?= count($reservations) ?> record<?= count($reservations)!==1?'s':'' ?></span>
        </form>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No reservations found</h3>
                <p>No reservation requests match the current filters.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Laboratory</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Purpose</th>
                        <th class="center">Status</th>
                        <th class="center">Submitted</th>
                        <th class="center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $i => $res):
                        $date_fmt = $res['date']       ? date('M j, Y',    strtotime($res['date']))       : '—';
                        $day_fmt  = $res['date']       ? date('D',          strtotime($res['date']))        : '';
                        $sub_fmt  = $res['created_at'] ? date('M j, g:i A', strtotime($res['created_at'])) : '—';
                        $status   = $res['status'];
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;font-weight:600;"><?= $i+1 ?></td>
                        <td>
                            <div class="student-cell">
                                <div class="s-name"><?= htmlspecialchars($res['student_name']) ?></div>
                                <div class="s-id"><?= htmlspecialchars($res['student_id']) ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="lab-tag"
                                onclick="openPcModal('<?= htmlspecialchars(addslashes($res['lab'])) ?>')"
                                title="Click to manage PC availability">
                                🏛️ <?= htmlspecialchars($res['lab']) ?> <span>🖥️</span>
                            </span>
                        </td>
                        <td>
                            <div class="td-date"><?= htmlspecialchars($date_fmt) ?></div>
                            <div class="td-date-sub"><?= htmlspecialchars($day_fmt) ?></div>
                        </td>
                        <td><span class="slot-tag">⏱ <?= htmlspecialchars($res['time_slot']) ?></span></td>
                        <td style="max-width:180px;">
                            <div style="font-size:13px;line-height:1.4;white-space:normal;">
                                <?= htmlspecialchars($res['purpose']) ?>
                            </div>
                        </td>
                        <td class="center">
                            <?php if ($status==='pending'): ?>
                            <span class="status-pill pending"><span class="status-dot"></span> Pending</span>
                            <?php elseif ($status==='approved'): ?>
                            <span class="status-pill approved"><span class="status-dot"></span> Approved</span>
                            <?php elseif ($status==='rejected'): ?>
                            <span class="status-pill rejected"><span class="status-dot"></span> Rejected</span>
                            <?php else: ?>
                            <span class="status-pill cancelled"><span class="status-dot"></span> Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="center" style="font-size:12px;color:var(--muted);white-space:nowrap;">
                            <?= htmlspecialchars($sub_fmt) ?>
                        </td>
                        <td class="center">
                            <div class="action-btns">
                                <?php if ($status==='pending'): ?>
                                <button class="btn-approve"
                                    onclick="openModal('approve',<?= (int)$res['id'] ?>,'<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    ✓ Approve
                                </button>
                                <button class="btn-reject"
                                    onclick="openModal('reject',<?= (int)$res['id'] ?>,'<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    ✕ Reject
                                </button>
                                <?php endif; ?>
                                <button class="btn-pc"
                                    onclick="openPcModal('<?= htmlspecialchars(addslashes($res['lab'])) ?>')"
                                    title="Manage PCs">🖥️</button>
                                <button class="btn-delete"
                                    onclick="openModal('delete',<?= (int)$res['id'] ?>,'<?= htmlspecialchars(addslashes($res['student_name'])) ?>')">
                                    🗑
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div><!-- /reservations card -->

</div><!-- /page-wrapper -->

<script>
// ─── CONFIRM MODAL ───────────────────────────────────────────────
const modalConfigs = {
    approve: { icon:'✅', title:'Approve Reservation?',
        desc: n => `Approve the reservation for <strong>${n}</strong>?`,
        btnClass:'btn-modal-confirm-approve', btnText:'✓ Approve' },
    reject:  { icon:'⚠️', title:'Reject Reservation?',
        desc: n => `Reject the reservation for <strong>${n}</strong>?`,
        btnClass:'btn-modal-confirm-reject', btnText:'✕ Reject' },
    delete:  { icon:'🗑️', title:'Delete Reservation?',
        desc: n => `Permanently delete this record for <strong>${n}</strong>?`,
        btnClass:'btn-modal-confirm-delete', btnText:'🗑 Delete' }
};

function openModal(action, id, name) {
    const cfg = modalConfigs[action];
    document.getElementById('modalIcon').textContent  = cfg.icon;
    document.getElementById('modalTitle').textContent = cfg.title;
    document.getElementById('modalDesc').innerHTML    = cfg.desc(name);
    document.getElementById('modalAction').value      = action;
    document.getElementById('modalResId').value       = id;
    const btn = document.getElementById('modalConfirmBtn');
    btn.className   = cfg.btnClass;
    btn.textContent = cfg.btnText;
    document.getElementById('confirmModal').classList.add('open');
}
function closeModal() { document.getElementById('confirmModal').classList.remove('open'); }
document.getElementById('confirmModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

// ─── EDIT LAB MODAL ──────────────────────────────────────────────
function openEditLabModal(id, name, pcs, active) {
    document.getElementById('editLabId').value     = id;
    document.getElementById('editLabName').value   = name;
    document.getElementById('editLabPcs').value    = pcs;
    document.getElementById('editLabActive').value = active;
    document.getElementById('editLabModal').classList.add('open');
}
function closeEditLabModal() { document.getElementById('editLabModal').classList.remove('open'); }
document.getElementById('editLabModal').addEventListener('click', function(e){ if(e.target===this) closeEditLabModal(); });

// ─── PC AVAILABILITY MODAL ───────────────────────────────────────
let currentLab      = '';
let currentTotal    = 50;
let pcStatuses      = {};
let isFirstSetup    = false;
const statusCycle   = ['available','unavailable','in_use'];
const statusLabels  = { available:'Available', unavailable:'Unavailable', in_use:'In Use' };

function openPcModal(lab, totalPcs) {
    currentLab   = lab;
    currentTotal = totalPcs || 50;
    document.getElementById('pcModalTitle').textContent    = lab + ' — PC Conditions';
    document.getElementById('pcModalSubtitle').textContent = 'Click a PC to cycle: Available → Unavailable → In Use';
    document.getElementById('pcModalBody').innerHTML = `<div class="pc-loading"><div class="spinner"></div><p>Loading PC conditions…</p></div>`;
    document.getElementById('pcSaveToast').classList.remove('show');
    document.getElementById('pcFirstSetupNotice').classList.add('hidden');
    document.getElementById('pcModal').classList.add('open');

    fetch('admin_reservation.php?ajax=get_pc_status&lab=' + encodeURIComponent(lab))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentTotal = data.total_pcs || currentTotal;
                isFirstSetup = !data.pc_status_set;
                pcStatuses   = {};
                data.pcs.forEach(p => { pcStatuses[p.pc] = p.status; });

                // Show first-time setup notice if PC conditions haven't been saved yet
                const notice = document.getElementById('pcFirstSetupNotice');
                if (isFirstSetup) {
                    notice.classList.remove('hidden');
                } else {
                    notice.classList.add('hidden');
                }
                renderPcGrid();
            }
        })
        .catch(() => {
            document.getElementById('pcModalBody').innerHTML =
                '<div class="empty-state"><div class="empty-icon">⚠️</div><h3>Failed to load</h3><p>Could not fetch PC conditions.</p></div>';
        });
}

function closePcModal() {
    document.getElementById('pcModal').classList.remove('open');
    currentLab = ''; pcStatuses = {};
}

function applyNewCount() {
    const inp = document.getElementById('pcCountInput');
    const n   = parseInt(inp.value);
    if (!n || n < 1 || n > 200) { inp.style.borderColor='red'; return; }
    inp.style.borderColor='';
    currentTotal = n;
    for (let i = 1; i <= currentTotal; i++) {
        if (!pcStatuses[i]) pcStatuses[i] = 'available';
    }
    Object.keys(pcStatuses).forEach(k => { if (parseInt(k) > currentTotal) delete pcStatuses[k]; });
    renderPcGrid();
}

function renderPcGrid() {
    const counts = { available:0, unavailable:0, in_use:0 };
    for (let i=1; i<=currentTotal; i++) counts[pcStatuses[i] || 'available']++;

    let html = `
    <div class="pc-count-control">
        <label>Total PCs:</label>
        <input type="number" id="pcCountInput" value="${currentTotal}" min="1" max="200">
        <button class="btn-apply-count" onclick="applyNewCount()">Apply</button>
        <span class="pc-count-note">Students see PCs 1–${currentTotal}</span>
    </div>
    <div class="pc-legend">
        <div class="pc-legend-item"><div class="legend-dot available"></div> Available — students can book this PC</div>
        <div class="pc-legend-item"><div class="legend-dot unavailable"></div> Unavailable / Broken</div>
        <div class="pc-legend-item"><div class="legend-dot in_use"></div> In Use</div>
        <div class="pc-legend-note">Click any PC to cycle its condition</div>
    </div>
    <div class="pc-stats">
        <div class="pc-stat-chip available"><div class="cs-value">${counts.available}</div><div class="cs-label">Available</div></div>
        <div class="pc-stat-chip unavailable"><div class="cs-value">${counts.unavailable}</div><div class="cs-label">Unavailable</div></div>
        <div class="pc-stat-chip in_use"><div class="cs-value">${counts.in_use}</div><div class="cs-label">In Use</div></div>
    </div>
    <div class="pc-grid-label">PCs 1 – ${currentTotal} &nbsp;·&nbsp; ${counts.available} available of ${currentTotal}</div>
    <div class="pc-grid" id="pcGridCells">`;

    for (let i=1; i<=currentTotal; i++) {
        const st = pcStatuses[i] || 'available';
        html += `<div class="pc-cell ${st}" id="pc-cell-${i}" onclick="cyclePc(${i})" title="PC ${i} — ${statusLabels[st]}">
                    <span class="pc-icon-small">🖥️</span>
                    <span class="pc-number">${i}</span>
                 </div>`;
    }

    html += `</div>
    <div class="pc-bulk">
        <span class="pc-bulk-label">Bulk:</span>
        <button class="btn-bulk all-available"   onclick="setAllPcs('available')">✓ All Available</button>
        <button class="btn-bulk all-unavailable" onclick="setAllPcs('unavailable')">✕ All Unavailable</button>
        <button class="btn-bulk all-in-use"      onclick="setAllPcs('in_use')">⏸ All In Use</button>
    </div>`;

    document.getElementById('pcModalBody').innerHTML = html;
}

function cyclePc(pcNum) {
    const cur  = pcStatuses[pcNum] || 'available';
    const next = statusCycle[(statusCycle.indexOf(cur)+1) % statusCycle.length];
    pcStatuses[pcNum] = next;
    const cell = document.getElementById('pc-cell-'+pcNum);
    if (cell) { cell.className = 'pc-cell '+next; cell.title = 'PC '+pcNum+' — '+statusLabels[next]; }
    updateStats();
}

function setAllPcs(status) {
    for (let i=1; i<=currentTotal; i++) {
        pcStatuses[i] = status;
        const cell = document.getElementById('pc-cell-'+i);
        if (cell) { cell.className='pc-cell '+status; cell.title='PC '+i+' — '+statusLabels[status]; }
    }
    updateStats();
}

function updateStats() {
    const counts = { available:0, unavailable:0, in_use:0 };
    for (let i=1; i<=currentTotal; i++) counts[pcStatuses[i] || 'available']++;
    const chips = document.querySelectorAll('.pc-stat-chip .cs-value');
    if (chips.length===3) { chips[0].textContent=counts.available; chips[1].textContent=counts.unavailable; chips[2].textContent=counts.in_use; }
    const lbl = document.querySelector('.pc-grid-label');
    if (lbl) lbl.textContent=`PCs 1 – ${currentTotal} · ${counts.available} available of ${currentTotal}`;
}

function savePcStatuses() {
    // Warn if no available PCs
    const availCount = Object.values(pcStatuses).filter(s => s === 'available').length;
    if (availCount === 0) {
        if (!confirm('No PCs are set to Available — students will not be able to reserve any spot in this lab. Save anyway?')) {
            return;
        }
    }

    const btn = document.getElementById('btnPcSave');
    btn.disabled=true; btn.innerHTML='⏳ Saving…';

    const formData = new FormData();
    formData.append('ajax','save_pc_status');
    formData.append('lab', currentLab);
    formData.append('total_pcs', currentTotal);
    for (let i=1; i<=currentTotal; i++) {
        formData.append('statuses['+i+']', pcStatuses[i] || 'available');
    }

    fetch('admin_reservation.php', { method:'POST', body:formData })
        .then(r=>r.json())
        .then(data => {
            btn.disabled=false; btn.innerHTML='💾 Save &amp; Publish';
            if (data.success) {
                // Hide first-setup notice since it's now set
                document.getElementById('pcFirstSetupNotice').classList.add('hidden');
                isFirstSetup = false;

                const toast = document.getElementById('pcSaveToast');
                const avail = data.available_pcs || 0;
                toast.innerHTML = avail > 0
                    ? `✅ Saved! ${avail} PC${avail>1?'s':''} available — students can now reserve this lab.`
                    : `⚠️ Saved, but no PCs are available — students cannot reserve until you set some PCs to Available.`;
                toast.classList.add('show');
                setTimeout(()=>{ toast.classList.remove('show'); location.reload(); }, 3500);
            }
        })
        .catch(()=>{ btn.disabled=false; btn.innerHTML='💾 Save &amp; Publish'; });
}

document.getElementById('pcModal').addEventListener('click', function(e){ if(e.target===this) closePcModal(); });

document.addEventListener('keydown', function(e) {
    if (e.key==='Escape') { closeModal(); closePcModal(); closeEditLabModal(); }
});

document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition='opacity 0.4s'; el.style.opacity='0';
        setTimeout(()=>el.style.display='none', 400);
    }, 4000);
});
</script>

</body>
</html>