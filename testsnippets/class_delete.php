<?php
header('Content-Type: application/json');
require_once("config/db.php");

$class_id = intval($_POST['vbs_class_id']);

if ($class_id <= 0) {
    echo json_encode(["success" => false, "message" => "Key boundary parameter out of bounds."]);
    exit;
}

$query = "DELETE FROM vbs_classes WHERE vbs_class_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
