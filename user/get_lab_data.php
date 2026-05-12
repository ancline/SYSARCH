<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit();
}

header('Content-Type: application/json');
$ajax = $_GET['ajax'] ?? '';

// ── GET CONFIGURED LABS ──────────────────────────────────────────
if ($ajax === 'get_configured_labs') {
    $req_date = $conn->real_escape_string($_GET['date']      ?? '');
    $req_slot = $conn->real_escape_string($_GET['time_slot'] ?? '');

    $result = $conn->query("
        SELECT lab_name, total_pcs
        FROM configured_labs
        WHERE is_active = 1 AND pc_status_set = 1
        ORDER BY lab_name
    ");

    $labs_out = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $lab_esc = $conn->real_escape_string($row['lab_name']);

            $avail = (int)$conn->query("
                SELECT COUNT(*) AS c FROM lab_pc_status
                WHERE lab = '$lab_esc' AND status != 'unavailable'
            ")->fetch_assoc()['c'];

            if ($req_date && $req_slot) {
                $booked = (int)$conn->query("
                    SELECT COUNT(DISTINCT pc_number) AS c FROM reservations
                    WHERE lab = '$lab_esc'
                      AND date = '$req_date'
                      AND time_slot = '$req_slot'
                      AND status = 'approved'
                      AND pc_number IS NOT NULL
                ")->fetch_assoc()['c'];
                $avail = max(0, $avail - $booked);
            }

            if ($avail > 0) {
                $labs_out[] = [
                    'name'      => $row['lab_name'],
                    'total_pcs' => $row['total_pcs'],
                    'available' => $avail
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'labs' => $labs_out]);
    $conn->close();
    exit();
}

// ── GET AVAILABLE PCS FOR A LAB ──────────────────────────────────
if ($ajax === 'get_available_pcs' && isset($_GET['lab'])) {
    $lab       = $conn->real_escape_string($_GET['lab']);
    $date      = $conn->real_escape_string($_GET['date']      ?? '');
    $time_slot = $conn->real_escape_string($_GET['time_slot'] ?? '');

    $lab_row = $conn->query("
        SELECT id FROM configured_labs
        WHERE lab_name = '$lab' AND is_active = 1 AND pc_status_set = 1
    ")->fetch_assoc();

    if (!$lab_row) {
        echo json_encode(['success' => false, 'error' => 'Lab not available.']);
        $conn->close();
        exit();
    }

    $result = $conn->query("
        SELECT pc_number FROM lab_pc_status
        WHERE lab = '$lab' AND status != 'unavailable'
        ORDER BY pc_number
    ");

    $available = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $available[] = (int)$row['pc_number'];
        }
    }

    if ($date && $time_slot && !empty($available)) {
        $booked_result = $conn->query("
            SELECT pc_number FROM reservations
            WHERE lab = '$lab'
              AND date = '$date'
              AND time_slot = '$time_slot'
              AND status = 'approved'
              AND pc_number IS NOT NULL
        ");
        $booked = [];
        if ($booked_result) {
            while ($row = $booked_result->fetch_assoc()) {
                $booked[] = (int)$row['pc_number'];
            }
        }
        $available = array_values(array_diff($available, $booked));
    }

    echo json_encode(['success' => true, 'available_pcs' => $available]);
    $conn->close();
    exit();
}

// ── GET PC STATUS (for the picker grid) ─────────────────────────
if ($ajax === 'get_pc_status' && isset($_GET['lab'])) {
    $lab = $conn->real_escape_string($_GET['lab']);

    $lab_row = $conn->query("
        SELECT total_pcs FROM configured_labs WHERE lab_name = '$lab'
    ")->fetch_assoc();

    $total_pcs = $lab_row ? (int)$lab_row['total_pcs'] : 50;

    $result = $conn->query("
        SELECT pc_number, status FROM lab_pc_status
        WHERE lab = '$lab' ORDER BY pc_number
    ");

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

    echo json_encode(['success' => true, 'pcs' => $out, 'total_pcs' => $total_pcs]);
    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'error' => 'Unknown request']);
$conn->close();