<?php

    session_start();

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }

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

    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $total_rooms = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as total_items FROM item");
    $total_items = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as assigned_items FROM item WHERE room_id IS NOT NULL");
    $assigned_items = $stmt->fetchColumn();

    $unassigned_items = $total_items - $assigned_items;

    // Get category breakdown
    $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM item GROUP BY category ORDER BY count DESC");
    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get room details
    $stmt = $pdo->query("
        SELECT r.room_number, COUNT(i.id) as item_count,
            GROUP_CONCAT(CONCAT(i.name, ' (', i.category, ')') ORDER BY i.name SEPARATOR ', ') as items
        FROM rooms r 
        LEFT JOIN item i ON r.id = i.room_id 
        GROUP BY r.id, r.room_number
        ORDER BY r.room_number
    ");
    $room_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unassigned items
    $stmt = $pdo->query("SELECT name, category FROM item WHERE room_id IS NULL ORDER BY name");
    $unassigned_items_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="styles/report.css">

</head>

<body>

    <div class="container">
        <button class="print-btn" onclick="window.print()"><img src="assets/images/printer-two.png">Print Report</button>
        
        <h1>Inventory System Report</h1>
        <div class="report-meta">
            Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_rooms; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $assigned_items; ?></div>
                <div class="stat-label">Assigned Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $unassigned_items; ?></div>
                <div class="stat-label">Unassigned Items</div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="section">
            <div class="section-header">
                <img src="assets/images/category.png" draggable="false">
                <h2>Items by Category</h2>
            </div>
            <?php foreach($category_breakdown as $category): ?>
                <div class="category-item">
                    <span><?php echo htmlspecialchars($category['category']); ?></span>
                    <span><strong><?php echo $category['count']; ?> items</strong></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Room Details -->
        <div class="section">
            <div class="section-header">
                <img src="assets/images/room-detail.png" draggable="false">
                <h2>Room Details</h2>
            </div>
            <?php foreach($room_details as $room): ?>
                <div class="room-item">
                    <div class="room-name">
                        <?php echo htmlspecialchars($room['room_number']); ?>
                        (<?php echo $room['item_count']; ?> items)
                    </div>
                    <div class="room-items">
                        <?php if($room['items']): ?>
                            <?php echo htmlspecialchars($room['items']); ?>
                        <?php else: ?>
                            <em>No items assigned</em>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Unassigned Items -->
        <?php if(!empty($unassigned_items_list)): ?>
            <div class="section">
                <div class="section-header">
                    <img src="assets/images/cube.png" draggable="false">
                    <h2>Unassigned Items</h2>
                </div>
                <?php foreach($unassigned_items_list as $item): ?>
                    <div class="category-item">
                        <span><?php echo htmlspecialchars($item['name']); ?></span>
                        <span><?php echo htmlspecialchars($item['category']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>