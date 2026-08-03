<?php
header('Content-Type: application/json');
require_once("config/db.php");

$class_id    = intval($_POST['vbs_class_id']);
$session_id  = intval($_POST['vbs_class_session_id']);
$description = trim($_POST['vbs_class_desc']);
$age_start   = intval($_POST['vbs_class_age_start']);
$age_end     = intval($_POST['vbs_class_age_end']);
$teacher_id  = !empty($_POST['vbs_class_teacher_id']) ? intval($_POST['vbs_class_teacher_id']) : null;

if ($class_id <= 0 || empty($description)) {
    echo json_encode(["success" => false, "message" => "Invalid class item context mapping parameters."]);
    exit;
}

$query = "UPDATE vbs_classes 
          SET vbs_class_session_id = ?, vbs_class_age_start = ?, vbs_class_age_end = ?, vbs_class_desc = ?, vbs_class_teacher_id = ? 
          WHERE vbs_class_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiisii", $session_id, $age_start, $age_end, $description, $teacher_id, $class_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
