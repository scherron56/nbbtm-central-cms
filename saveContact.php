<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

if (isset($_POST['contact-form'])) {
    // 1. Force the response header to be JSON


    // 2. Map and clean inputs
    $contact_id     = isset($POST['contact_id']) ? intval($_POST['contact_id']) : 0;
    $title_id       = $_POST['title_id'] ?? '';
    $first_name     = $_POST['first_name'] ?? '';
    $last_name      = $_POST['last_name'] ?? '';
    $middle_name    = $_POST['middle_name'] ?? ''; 
    $date_of_birth  = $_POST['date_of_birth'] ?? null;
    $gender         = $_POST['gender'] ?? '';      
    $address_1      = $_POST['address_1'] ?? '';
    $city           = $_POST['city'] ?? '';
    $state          = $_POST['state'] ?? '';
    $zipcode        = $_POST['zipcode'] ?? '';
    $phone_1        = $_POST['phone_1'] ?? '';
    $phone_1_type   = $_POST['phone_1_type'] ?? '';
    $phone_2        = $_POST['phone_2'] ?? '';
    $phone_2_type   = $_POST['phone_2_type'] ?? '';
    $phone_3        = $_POST['phone_3'] ?? '';
    $phone_3_type   = $_POST['phone_3_type'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $join_date      = $_POST['join_date'] ?? null;
    $baptized_date  = $_POST['baptized_date'] ?? null;
    
    $c_email       = filter_var($_POST['c_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $is_member     = isset($_POST['is_member']) ? 1 : 0;
    $is_baptized   = isset($_POST['is_baptized']) ? 1 : 0;
    $is_child      = isset($_POST['is_child']) ? 1 : 0;
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    // 3. Prepare SQL Statement
    $sql = "INSERT INTO contacts (
                contact_id, title_id, first_name, last_name, middle_name, 
                date_of_birth, gender, address_1, city, state, 
                zipcode, phone_1, phone_1_type, phone_2, phone_2_type, 
                phone_3, phone_3_type, c_email, is_member, is_baptized, 
                marital_status, join_date, baptized_date, is_child, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $db->prepare($sql)) {
        
        $types = "isssssssssssssssssiisssii";
        $stmt->bind_param(
            $types, 
            $contact_id, $title_id, $first_name, $last_name, $middle_name,
            $date_of_birth, $gender, $address_1, $city, $state,
            $zipcode, $phone_1, $phone_1_type, $phone_2, $phone_2_type,
            $phone_3, $phone_3_type, $c_email, $is_member, $is_baptized,
            $marital_status, $join_date, $baptized_date, $is_child, $is_active
        );
        
        // 4. Handle Execution Response
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                "status"  => "success",
                "message" => "Contact saved successfully.",
                "id"      => $stmt->insert_id // Returns the auto-incremented ID if applicable
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status"  => "error",
                "message" => "Database execution failed.",
                "error"   => $stmt->error
            ]);
        }
        $stmt->close();
        
    } else {
        http_response_code(500);
        echo json_encode([
            "status"  => "error",
            "message" => "SQL statement preparation failed.",
            "error"   => $db->error
        ]);
    }
    
    // 5. Terminate script to ensure no trailing HTML tags are appended
    exit;
}



?>   