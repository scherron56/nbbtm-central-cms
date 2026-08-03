<!-- ## 2. Read Action Processor (`class_read.php`)
This file returns data grids as structured JSON vectors. It joins your `contacts` table to display human-readable names instead of empty reference index keys. -->
<?php
header('Content-Type: application/json');
require_once("config/db.php");

$session_id = isset($_GET['vbs_sessions_id']) ? intval($_GET['vbs_sessions_id']) : 0;

$query = "SELECT c.vbs_class_id, c.vbs_class_session_id, c.vbs_class_age_start, 
                 c.vbs_class_age_end, c.vbs_class_desc, c.vbs_class_teacher_id,
                 CONCAT(con.first_name, ' ', con.last_name) AS teacher_name 
          FROM vbs_classes c 
          LEFT JOIN contacts con ON c.vbs_class_teacher_id = con.contact_id 
          WHERE c.vbs_class_session_id = ? 
          ORDER BY c.vbs_class_id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

echo json_encode($classes);
$stmt->close();
$conn->close();