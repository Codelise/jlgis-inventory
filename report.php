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

    // Get all rooms with their inventory details
    $stmt = $pdo->query("
        SELECT r.room_id, r.room_name, r.room_number, r.building_number, r.room_type, r.teacher_name,
               ri.quantity, ri.expected_quantity, ri.ownership, ri.remarks,
               i.item_name, i.category, i.description, i.unit
        FROM rooms r 
        LEFT JOIN roomInventory ri ON r.room_id = ri.room_id 
        LEFT JOIN items i ON ri.item_id = i.item_id
        ORDER BY r.building_number, r.room_number, i.category, i.item_name
    ");
    $room_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by room
    $rooms_data = [];
    foreach ($room_inventory as $row) {
        $room_key = $row['room_id'];
        if (!isset($rooms_data[$room_key])) {
            $rooms_data[$room_key] = [
                'room_info' => [
                    'room_name' => $row['room_name'],
                    'room_number' => $row['room_number'],
                    'building_number' => $row['building_number'],
                    'room_type' => $row['room_type'],
                    'teacher_name' => $row['teacher_name']
                ],
                'items' => []
            ];
        }
        
        if ($row['item_name']) {
            $rooms_data[$room_key]['items'][] = [
                'item_name' => $row['item_name'],
                'category' => $row['category'],
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'ownership' => $row['ownership'],
                'remarks' => $row['remarks']
            ];
        }
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Inventory Report</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="styles/style.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
        }
        
        .print-btn:hover {
            background: #0056b3;
        }
        
        .print-btn img {
            width: 16px;
            height: 16px;
        }
        
        .inventory-form {
            background: white;
            margin: 20px auto;
            padding: 20px;
            border: 2px solid #000;
            max-width: 210mm;
            min-height: 297mm;
            page-break-after: always;
        }
        
        .inventory-form:last-child {
            page-break-after: avoid;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        
        .form-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .form-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-group {
            display: flex;
            flex-direction: column;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            margin-right: 10px;
            min-width: 100px;
        }
        
        .info-value {
            border-bottom: 1px solid #000;
            padding: 2px 8px;
            min-width: 200px;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .inventory-table th,
        .inventory-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        .inventory-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .col-property {
            width: 15%;
        }
        
        .col-description {
            width: 30%;
        }
        
        .col-qty {
            width: 8%;
            text-align: center;
        }
        
        .col-uom {
            width: 8%;
            text-align: center;
        }
        
        .col-ownership {
            width: 15%;
            text-align: center;
        }
        
        .col-remarks {
            width: 24%;
        }
        
        .category-header {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        
        .empty-row {
            height: 30px;
        }
        
        .inventory-table tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }
        
        @media print {
            body {
                margin: 0;
                background: white;
            }
            
            .print-btn {
                display: none;
            }
            
            .inventory-form {
                margin: 0;
                border: 2px solid #000;
                page-break-after: always;
            }
            
            .inventory-form:last-child {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">
        <img src="assets/images/printer-two.png" alt="Print">
        Print Report
    </button>

    <?php foreach ($rooms_data as $room_data): ?>
        <div class="inventory-form">
            <div class="form-header">
                <div class="form-title">
                    INDIVIDUAL INVENTORY OF EXISTING SCHOOL CLASSROOM AND OFFICE PROPERTY
                </div>
            </div>
            
            <div class="form-info">
                <div class="info-group">
                    <div class="info-row">
                        <span class="info-label">Name of Teacher:</span>
                        <span class="info-value"><?php echo htmlspecialchars($room_data['room_info']['teacher_name'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Room No.:</span>
                        <span class="info-value"><?php echo htmlspecialchars($room_data['room_info']['room_number']); ?></span>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-row">
                        <span class="info-label">Grade Level & Section/Office:</span>
                        <span class="info-value"><?php echo htmlspecialchars($room_data['room_info']['room_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Building No.:</span>
                        <span class="info-value"><?php echo htmlspecialchars($room_data['room_info']['building_number']); ?></span>
                    </div>
                </div>
            </div>
            
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th class="col-property">Property Classification</th>
                        <th class="col-description">Description<br><small>(Name of Books)<br>Furniture, Fixture, etc. or Wood, Steel, Color and Brand</small></th>
                        <th class="col-qty">QTY</th>
                        <th class="col-uom">UOM<br><small>(Pcs, Set, Copies)</small></th>
                        <th class="col-ownership">Pls. Indicate<br><small>S - School Property<br>HR - Home Room<br>PTA - PTA Donated<br>P - Personal</small></th>
                        <th class="col-remarks">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $current_category = '';
                    $row_count = 0;
                    $max_rows = 24; // Adjust based on page size
                    
                    // Group items by category
                    $items_by_category = [];
                    foreach ($room_data['items'] as $item) {
                        $category = $item['category'] ?? 'Miscellaneous';
                        if (!isset($items_by_category[$category])) {
                            $items_by_category[$category] = [];
                        }
                        $items_by_category[$category][] = $item;
                    }
                    
                    foreach ($items_by_category as $category => $items):
                        if ($row_count < $max_rows) {
                            echo '<tr class="category-header">';
                            echo '<td colspan="6">' . htmlspecialchars($category) . '</td>';
                            echo '</tr>';
                            $row_count++;
                        }
                        
                        foreach ($items as $item):
                            if ($row_count < $max_rows) {
                                $ownership_code = '';
                                switch ($item['ownership']) {
                                    case 'School Property':
                                        $ownership_code = 'S';
                                        break;
                                    case 'Homeroom':
                                        $ownership_code = 'HR';
                                        break;
                                    case 'PTA Donated':
                                        $ownership_code = 'PTA';
                                        break;
                                    case 'Personal':
                                        $ownership_code = 'P';
                                        break;
                                }
                                
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($item['description'] ?? '') . '</td>';
                                echo '<td class="col-qty">' . htmlspecialchars($item['quantity']) . '</td>';
                                echo '<td class="col-uom">' . htmlspecialchars($item['unit']) . '</td>';
                                echo '<td class="col-ownership">' . htmlspecialchars($ownership_code) . '</td>';
                                echo '<td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>';
                                echo '</tr>';
                                $row_count++;
                            }
                        endforeach;
                    endforeach;
                    
                    // Fill remaining rows with empty cells
                    while ($row_count < $max_rows) {
                        echo '<tr class="empty-row">';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($rooms_data)): ?>
        <div class="inventory-form">
            <div class="form-header">
                <div class="form-title">
                    INDIVIDUAL INVENTORY OF EXISTING SCHOOL CLASSROOM AND OFFICE PROPERTY
                </div>
            </div>
            <p style="text-align: center; margin-top: 50px; font-size: 18px; color: #666;">
                No rooms found in the system.
            </p>
        </div>
    <?php endif; ?>

</body>
</html>