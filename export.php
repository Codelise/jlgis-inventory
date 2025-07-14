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

    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY building_number, room_number");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM items ORDER BY item_name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT ri.*, 
               r.room_name, r.room_number, r.building_number, r.room_type, r.teacher_name,
               i.item_name, i.category, i.description, i.unit
        FROM roomInventory ri
        JOIN rooms r ON ri.room_id = r.room_id
        JOIN items i ON ri.item_id = i.item_id
        ORDER BY r.building_number, r.room_number, i.item_name
    ");
    $roomInventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'rooms' => $rooms,
        'items' => $items,
        'roomInventory' => $roomInventory
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.json"');

    echo json_encode($exportData, JSON_PRETTY_PRINT);
    
?>