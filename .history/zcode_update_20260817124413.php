<?php
// Report all PHP errors and enable MySQLi exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database Configuration
require_once "config/db.php";


// $host = '127.0.0.1';
// $dbname = 'your_database';
// $user = 'your_username';
// $pswd = 'your_password';
// $port = 3306;
// File URLs & Paths
$sourceUrl = 'https://download.geonames.org/export/zip/US.zip';
$tempZip   = __DIR__ . '/US.zip';
$extractTo = __DIR__ . '/geonames_data';
$dataFile  = $extractTo . '/US.txt';

try {
    // 1. Establish MySQLi Connection
    // $mysqli = new mysqli($host, $user, $pswd, $dbname, $port);
    // $mysqli->set_charset('utf8mb4');

    echo "[1/4] Downloading latest dataset from GeoNames...\n";
    $downloadStream = fopen($sourceUrl, 'r');
    if (!$downloadStream) {
        throw new Exception("Unable to open stream for {$sourceUrl}");
    }
    file_put_contents($tempZip, $downloadStream);
    fclose($downloadStream);

    echo "[2/4] Extracting archive...\n";
    $zip = new ZipArchive();
    if ($zip->open($tempZip) === true) {
        $zip->extractTo($extractTo);
        $zip->close();
    } else {
        throw new Exception("Failed to unzip {$tempZip}");
    }

    echo "[3/4] Initializing MySQL table...\n";
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS us_zip_codes (
            country_code CHAR(2),
            zip_code VARCHAR(5) NOT NULL,
            city VARCHAR(100) NOT NULL,
            state_name VARCHAR(100),
            state_code CHAR(2) NOT NULL,
            county_name VARCHAR(100),
            county_code VARCHAR(20),
            latitude DECIMAL(9, 6),
            longitude DECIMAL(9, 6),
            accuracy INT,
            PRIMARY KEY (zip_code, city, state_code),
            INDEX idx_zip (zip_code),
            INDEX idx_city_state (city, state_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Optional: Truncate existing data to start fresh on re-run
    $mysqli->query("TRUNCATE TABLE us_zip_codes;");

    echo "[4/4] Parsing and streaming records to MySQL...\n";
    $handle = fopen($dataFile, 'r');
    if (!$handle) {
        throw new Exception("Could not open {$dataFile}");
    }

    $insertSql = "
        INSERT INTO us_zip_codes (
            country_code, zip_code, city, state_name, state_code,
            county_name, county_code, latitude, longitude, accuracy
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            state_name = VALUES(state_name),
            county_name = VALUES(county_name),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude)
    ";

    $stmt = $mysqli->prepare($insertSql);

    // Disable autocommit for batch transactions
    $mysqli->autocommit(false);
    $mysqli->begin_transaction();

    $rowCount = 0;
    $batchSize = 2000;

    // Define placeholder variables for bind_param
    $cCode = $zip = $city = $sName = $sCode = $cName = $cCodeAdmin = '';
    $lat = $lng = 0.0;
    $acc = 0;

    // s = string, d = double (float), i = integer
    $stmt->bind_param(
        'sssssssddi',
        $cCode,
        $zip,
        $city,
        $sName,
        $sCode,
        $cName,
        $cCodeAdmin,
        $lat,
        $lng,
        $acc
    );

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if (count($row) < 11) continue;

        $cCode      = $row[0];
        $zip        = $row[1];
        $city       = $row[2];
        $sName      = $row[3];
        $sCode      = $row[4];
        $cName      = $row[5] ?? null;
        $cCodeAdmin = $row[6] ?? null;
        $lat        = !empty($row[9])  ? (float)$row[9]  : null;
        $lng        = !empty($row[10]) ? (float)$row[10] : null;
        $acc        = !empty($row[11]) ? (int)$row[11]   : null;

        $stmt->execute();
        $rowCount++;

        // Commit every batchSize rows
        if ($rowCount % $batchSize === 0) {
            $mysqli->commit();
            echo "Inserted {$rowCount} records...\n";
        }
    }

    // Final commit for remaining rows
    $mysqli->commit();
    $mysqli->autocommit(true);

    $stmt->close();
    fclose($handle);

    // Clean up files
    unlink($tempZip);
    unlink($dataFile);
    if (file_exists($extractTo . '/readme.txt')) {
        unlink($extractTo . '/readme.txt');
    }
    rmdir($extractTo);

    echo "Done! Successfully imported {$rowCount} ZIP codes into MySQL.\n";

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
?>