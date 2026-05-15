<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized', 'labs' => [], 'software' => []]);
    exit();
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'students');
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB error', 'labs' => [], 'software' => []]);
    exit();
}

$lab = trim($_GET['lab'] ?? '');

// Get labs from lab_software table only
$labs = [];
$lr = $conn->query("SELECT DISTINCT lab_name FROM lab_software ORDER BY lab_name");
if ($lr) {
    while ($row = $lr->fetch_assoc()) {
        $labs[] = $row['lab_name'];
    }
}

// Get software for selected lab
$software = [];
if ($lab !== '') {
    $stmt = $conn->prepare("SELECT software_name FROM lab_software WHERE lab_name = ? ORDER BY software_name");
    $stmt->bind_param('s', $lab);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $software[] = $row['software_name'];
    }
    $stmt->close();
}

echo json_encode(['labs' => $labs, 'software' => $software, 'lab' => $lab]);
$conn->close();