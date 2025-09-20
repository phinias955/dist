<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_GET['ward_id']) || !is_numeric($_GET['ward_id'])) {
    echo json_encode([]);
    exit();
}

$ward_id = (int)$_GET['ward_id'];

try {
    $stmt = $pdo->prepare("SELECT id, village_name FROM villages WHERE ward_id = ? ORDER BY village_name");
    $stmt->execute([$ward_id]);
    $villages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($villages);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
