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
        $roomCheck = $pdo->prepare("SELECT room_id FROM rooms WHERE room_id = ?");
        $roomCheck->execute([$room_id]);
        
        if ($roomCheck->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Room not found'
            ]);
            exit;
        }

        // Fetch items for the room with proper JOIN
        $stmt = $pdo->prepare("
            SELECT 
                ri.inventory_id,
                ri.item_id,
                i.item_name as name,
                i.category,
                i.description,
                i.unit,
                ri.quantity,
                ri.expected_quantity,
                ri.ownership,
                ri.remarks,
                ri.created_at,
                ri.updated_at
            FROM roominventory ri
            INNER JOIN items i ON ri.item_id = i.item_id
            WHERE ri.room_id = ?
            ORDER BY i.item_name ASC
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