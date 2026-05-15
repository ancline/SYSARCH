<?php
error_reporting(0);       
ini_set('display_errors', 0);
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'students');
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS lab_software (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        lab_name     VARCHAR(100) NOT NULL,
        software_name VARCHAR(150) NOT NULL,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_lab_software (lab_name, software_name)
    )
");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── LIST: get all labs + their software ──────────────────────────────────────
if ($action === 'list') {
    $labs = [];
    // Fetch distinct lab names from sitin table so only real labs appear
    $lr = $conn->query("SELECT DISTINCT lab FROM sitin WHERE lab IS NOT NULL AND lab != '' ORDER BY lab");
    if ($lr) {
        while ($row = $lr->fetch_assoc()) {
            $labs[] = $row['lab'];
        }
    }
    // Also include any labs that already have software configured even if no sitin rows
    $xr = $conn->query("SELECT DISTINCT lab_name FROM lab_software ORDER BY lab_name");
    if ($xr) {
        while ($row = $xr->fetch_assoc()) {
            if (!in_array($row['lab_name'], $labs)) {
                $labs[] = $row['lab_name'];
            }
        }
    }
    sort($labs);

    // Fetch all software grouped by lab
    $software = [];
    $sr = $conn->query("SELECT id, lab_name, software_name FROM lab_software ORDER BY lab_name, software_name");
    if ($sr) {
        while ($row = $sr->fetch_assoc()) {
            $software[$row['lab_name']][] = ['id' => $row['id'], 'name' => $row['software_name']];
        }
    }

    echo json_encode(['labs' => $labs, 'software' => $software]);
    $conn->close();
    exit();
}

// ── ADD ──────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $lab  = trim($_POST['lab_name'] ?? '');
    $soft = trim($_POST['software_name'] ?? '');
    if ($lab === '' || $soft === '') {
        echo json_encode(['error' => 'Lab name and software name are required.']);
        $conn->close(); exit();
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO lab_software (lab_name, software_name) VALUES (?, ?)");
    $stmt->bind_param('ss', $lab, $soft);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'id' => $new_id, 'lab_name' => $lab, 'software_name' => $soft]);
    $conn->close();
    exit();
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid ID.']);
        $conn->close(); exit();
    }
    $stmt = $conn->prepare("DELETE FROM lab_software WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    $conn->close();
    exit();
}

// ── ADD CUSTOM LAB ───────────────────────────────────────────────────────────
if ($action === 'add_lab') {
    // Labs are derived from sitin; this just returns success so the UI
    // can optimistically add it. The first software entry creates the lab row.
    $lab = trim($_POST['lab_name'] ?? '');
    if ($lab === '') {
        echo json_encode(['error' => 'Lab name is required.']);
        $conn->close(); exit();
    }
    echo json_encode(['success' => true, 'lab_name' => $lab]);
    $conn->close();
    exit();
}

echo json_encode(['error' => 'Unknown action.']);
$conn->close();