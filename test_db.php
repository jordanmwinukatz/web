<?php
require 'config/database.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    echo "Success via Apache!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
