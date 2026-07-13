
 <?php
 error_log(print_r($_POST, true));
  ini_set('display_errors', 1);
 // $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';

// 1. Force the response header to be JSON
header('Content-Type: application/json; charset=utf-8');

$errors = [];
$contactSelect = $_POST['contactID'] ?? ''; 
// 2. Map and clean inputs (Fixed $_POST typos and missing array quotes)
$contact_id     = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$title_id       = isset($_POST['title_id']) ? intval($_POST['title_id']) : 0;
$first_name     = $_POST['first_name'] ?? '';
$last_name      = $_POST['last_name'] ?? '';
$middle_name    = $_POST['middle_name'] ?? ''; 
$date_of_birth  = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$gender         = $_POST['gender'] ?? '';      
$address_1      = $_POST['address_1'] ?? '';
$city           = $_POST['city'] ?? '';
$state          = $_POST['state'] ?? '';
$zipcode        = $_POST['zipcode'] ?? '';
$phone_1        = $_POST['phone_1'] ?? '';
$phone_1_type   = isset($_POST['phone_1_type']) ? intval($_POST['phone_1_type']) : 0;
$phone_2        = $_POST['phone_2'] ?? '';
$phone_2_type   = isset($_POST['phone_2_type']) ? intval($_POST['phone_2_type']) : 0;
$emergency_contact = $_POST['emergency_contact'] ?? '';
$phone_3        = $_POST['phone_3'] ?? '';
$phone_3_type   = isset($_POST['phone_3_type']) ? intval($_POST['phone_3_type']) : 0;
$anniv_date     = !empty($_POST['anniv_date']) ? $_POST['anniv_date'] : null;
$marital_status = isset($_POST['marital_status']) ? intval($_POST['marital_status']) : 0;
$join_date      = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
$baptized_date  = !empty($_POST['baptized_date']) ? $_POST['baptized_date'] : null;

$c_email       = $_POST['c_email'] ?? '';
$is_member     = isset($_POST['is_member']) ? 1 : 0;
$is_baptized   = isset($_POST['is_baptized']) ? 1 : 0;
$is_child      = isset($_POST['is_child']) ? 1 : 0;
$is_head       = isset($_POST['is_head']) ? 1 : 0;
$is_active     = isset($_POST['is_active']) ? 1 : 0;

// Helper function to robustly sanitize ambiguous date strings
function sanitizeToSqlDate($dateStr) {
    if (!$dateStr) return null;
    // Replace slashes with dashes to ensure standard interpretation, or use specialized parser
    $timestamp = strtotime(str_replace('/', '-', $dateStr));
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

$date_of_birth = sanitizeToSqlDate($date_of_birth);
$anniv_date    = sanitizeToSqlDate($anniv_date);
$join_date     = sanitizeToSqlDate($join_date);
$baptized_date = sanitizeToSqlDate($baptized_date);

// Validate entries
if (!isset($_POST['first_name']) || empty(trim($_POST['first_name']))) {
    $errors['firstname'] = 'First Name is required.';
}
if (!isset($_POST['last_name']) || empty(trim($_POST['last_name']))) {
    $errors['lastname'] = 'Last Name is required.';
}
if (!empty($c_email) && !filter_var($c_email, FILTER_VALIDATE_EMAIL)){
    $errors['c_email'] = 'Please enter a valid email';
}

// 3. Handle Validation Failures (Crucial: Send errors back to AJAX)
if(!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "status" => "error",
        "message" => "Validation failed.",
        "errors" => $errors
    ]);
    exit;
}

// 4. Prepare SQL Statement
$sql = "INSERT INTO contacts (
            title_id, first_name, last_name, middle_name, 
            date_of_birth, gender, address_1, city, state, 
            zipcode, phone_1, phone_1_type, phone_2, phone_2_type, 
            emergency_contact, phone_3, phone_3_type, c_email, is_member, is_baptized, 
            marital_status, anniv_date, join_date, baptized_date, is_child, is_head, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $db->prepare($sql)) {
    
    // Fixed type mapping string to exactly 27 characters matching the 27 variables
    $types = "issssssssssisisisssiiisssiii";

    $stmt->bind_param(
        $types, 
        $title_id, $first_name, $last_name, $middle_name,
        $date_of_birth, $gender, $address_1, $city, $state,
        $zipcode, $phone_1, $phone_1_type, $phone_2, $phone_2_type,
        $emergency_contact, $phone_3, $phone_3_type, $c_email, $is_member, $is_baptized,
        $marital_status, $anniv_date, $join_date, $baptized_date, $is_child, $is_head, $is_active
    );
    
    // 5. Handle Execution Response
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Contact saved successfully.",
            "id"      => $stmt->insert_id
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

$db->close();
exit;
?>
