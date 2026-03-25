<?php

// 1. Fetch the student's CURRENT session count as a snapshot
$stmt = $conn->prepare("SELECT sessions FROM student WHERE IdNumber = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$res = $stmt->get_result();
$student_row = $res->fetch_assoc();
$sessions_snapshot = $student_row['sessions'] ?? null;
$stmt->close();

// 2. Insert the sit-in record WITH the snapshot stored in sessions column
//    so this value is frozen and will never change even if the student
//    earns more sessions later.
$stmt = $conn->prepare("
    INSERT INTO sitin (student_id, student_name, lab, purpose, sessions, time_in)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssssi", $student_id, $student_name, $lab, $purpose, $sessions_snapshot);
$stmt->execute();
$stmt->close();