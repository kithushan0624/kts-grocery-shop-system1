<?php
require_once 'config/db.php';
$db = getDB();
echo "TABLE: employees\n";
try {
    $cols = $db->query("DESCRIBE employees")->fetchAll();
    foreach ($cols as $col) {
        echo "  {$col['Field']} - {$col['Type']}\n";
    }
} catch(Exception $e) {
    echo "  Table not found or error\n";
}
