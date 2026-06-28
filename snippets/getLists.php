<?php
require_once 'config/db.php';
  // 1. Fetch Contacts
// $contacts=[];
$titles=[];
// $phonetype=[];

    // $sqlcontacts= $db->query("SELECT contact_id, 
    //         CONCAT(last_name, ', ', first_name, COALESCE(CONCAT(' ', middle_name), '')) as fullname 
    //         FROM contacts");
    // $contacts = $sqlcontacts->fetchAll(MYSQLI_ASSOC);
  
 
    // 2. Fetch Titles (Uncommented and operational)
    $sqlTitles =$db->query("SELECT title_id, titleabr FROM title");
    $titles = $sqlTitles->fetchAll(MYSQLI_ASSOC);


    // //3.  Fetch Phone types
    // $sqlphone= $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type");
    // $phonetype = $sqlphone->fetch_all(MYSQLI_ASSOC);
    
 
    echo json_encode($titles);

?>

