<?php

    $host = 'localhost';
    $dbname = 'jlgis';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY room_number");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM item ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'rooms' => $rooms,
        'items' => $items
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.json"');

    echo json_encode($exportData, JSON_PRETTY_PRINT);
    
?>