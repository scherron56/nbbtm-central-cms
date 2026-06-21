<?php
header("Content-Type: application/json");
require_once "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_file_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // READ ALL
        $result = $conn->query("SELECT * FROM tasks ORDER BY id DESC");
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode($tasks);
        break;

    case 'POST':
        // CREATE
        if (!empty($input['title'])) {
            $stmt = $conn->prepare("INSERT INTO tasks (title, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $input['title'], $input['description']);
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "id" => $conn->insert_id]);
            }
            $stmt->close();
        }
        break;

    case 'PUT':
        // UPDATE
        if (!empty($input['id']) && !empty($input['title'])) {
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $input['title'], $input['description'], $input['id']);
            if ($stmt->execute()) {
                echo json_encode(["success" => true]);
            }
            $stmt->close();
        }
        break;

    case 'DELETE':
        // DELETE
        if (!empty($input['id'])) {
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->bind_param("i", $input['id']);
            if ($stmt->execute()) {
                echo json_encode(["success" => true]);
            }
            $stmt->close();
        }
        break;
}
$conn->close();