<?php
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

/**
 * Fetch all phone types.
 */
function getphonetype($db)
{
    $sql = 'SELECT * FROM phone_type';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }

    return [];
}

/**
 * Fetch all titles.
 */
function gettitle($db)
{
    $sql = 'SELECT * FROM title';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
    return [];
}

/**
 * Fetch all contacts.
 */
function getcontacts($db)
{
    $sql = 'SELECT contact_id, first_name, last_name FROM contacts';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
    return [];
}

/* Populate data once after defining functions */
$phonetype = getphonetype($db);
$title = gettitle($db);
$contacts = getcontacts($db);
?>
