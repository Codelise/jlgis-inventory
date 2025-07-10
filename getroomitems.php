<?php
    // Database configuration
    $host = 'localhost';
    $dbname = 'jlgis';
    $username = 'root';
    $password = '';

    header('Content-Type: application/json');

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }

    // Check if room_id is provided
    if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Room ID is required'
        ]);
        exit;
    }

    $room_id = (int)$_GET['room_id'];

    try {
        // Fetch items for the room
        $stmt = $pdo->prepare("
            SELECT id, name, category, quantity, expected_quantity, created_at 
            FROM item 
            WHERE room_id = ? 
            ORDER BY name ASC
        ");
        $stmt->execute([$room_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'count' => count($items)
        ]);
        
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed: ' . $e->getMessage()
        ]);
    }
?>