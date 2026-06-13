<?php
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

if ($_POST['action'] == 'insert') {
    $stmt = $db->prepare("INSERT INTO contacts (title_id, first_name, middle_name, last_name, address_1, city, state, zipcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $_POST['title_id'], $_POST['fist_name'],  $_POST['middle_name'],  $_POST['last_name'],  $_POST['address_1'],  $_POST['city'],  $_POST['state'],  $_POST['zipcode']);
    $stmt->execute();
    echo "Contact/Member added successfully";
} elseif ($_POST['action'] == 'update') {
    $stmt = $db->prepare("UPDATE contacts SET title_id = ?, first_name = ?, middle_name = ?, last_name = ?, address_1 = ?, city = ?, stat = ?, zipcode = ? WHERE contact_id = ?");
   $stmt->bind_param("isssssss", $_POST['title_id'], $_POST['fist_name'],  $_POST['middle_name'],  $_POST['last_name'],  $_POST['address_1'],  $_POST['city'],  $_POST['state'],  $_POST['zipcode']);
    $stmt->execute();
    echo "Contact/Member updated successfully";
}
$stmt->close();
?>