<?php
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;

    if ($contact_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid Contact ID."]);
        exit;
    }

    $db->begin_transaction();

    try {
        // 1. Delete associated ministry alliances
        $delMin = $db->prepare("DELETE FROM member_alliance WHERE contact_id = ?");
        $delMin->bind_param("i", $contact_id);
        $delMin->execute();
        $delMin->close();

        // 2. Delete family link
        $delFam = $db->prepare("DELETE FROM Families WHERE contact_id = ?");
        $delFam->bind_param("i", $contact_id);
        $delFam->execute();
        $delFam->close();

        // 3. Delete contact record
        $delContact = $db->prepare("DELETE FROM contacts WHERE contact_id = ?");
        $delContact->bind_param("i", $contact_id);
        $delContact->execute();
        $delContact->close();

        $db->commit();

        echo json_encode(["status" => "success", "message" => "Contact deleted successfully."]);
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to delete contact: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}
?>

