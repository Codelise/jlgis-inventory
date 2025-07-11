<?php
session_start();


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

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

// Fetch rooms for dropdown
$rooms_dropdown = [];
$stmt = $pdo->query("SELECT id, room_number FROM rooms ORDER BY room_number ASC");
$rooms_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch($_POST['action']) {
        case 'add_room':
            $room_number = $_POST['room_number'];
            $stmt = $pdo->prepare("INSERT INTO rooms (room_number) VALUES (?)");
            $stmt->execute([$room_number]);
            echo json_encode(['success' => true, 'message' => 'Room added successfully']);
            exit;
            
        case 'add_item':
            try {
                $name = $_POST['name'];
                $category = $_POST['category'];
                $room_id = !empty($_POST['room_id']) ? $_POST['room_id'] : null;
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                $expected_quantity = isset($_POST['expected_quantity']) ? (int)$_POST['expected_quantity'] : null;

                $stmt = $pdo->prepare("INSERT INTO item (name, category, room_id, quantity, expected_quantity) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category, $room_id, $quantity, $expected_quantity]);
                echo json_encode(['success' => true, 'message' => 'Item added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'clear_all':
            $pdo->exec("DELETE FROM item");
            $pdo->exec("DELETE FROM rooms");
            echo json_encode(['success' => true, 'message' => 'All data cleared successfully']);
            exit;
    }
}

$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
$stats['total_rooms'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_items FROM item");
$stats['total_items'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as assigned_items FROM item WHERE room_id IS NOT NULL");
$stats['assigned_items'] = $stmt->fetchColumn();

$stats['unassigned_items'] = $stats['total_items'] - $stats['assigned_items'];

include 'sidebar.php';
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="styles/style.css">
    <title>JLGIS Inventory</title>

</head>

<body>

    <div class="main-content-with-sidebar">
        <div class="container">
            <div class="header">
                <h1>JLGIS Inventory</h1>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_rooms']; ?></div>
                    <div class="stat-label">Total Rooms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['assigned_items']; ?></div>
                    <div class="stat-label">Assigned Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['unassigned_items']; ?></div>
                    <div class="stat-label">Unassigned Items</div>
                </div>
            </div>

            <div class="main-content">
                <!-- Add Room Form -->
                <div class="card">
                    <div class="card-header">
                        <img src="assets/images/push-door.png" draggable="false">
                        <h2>Add New Room</h2>
                    </div>
                    <form id="roomForm">
                        <div class="form-group">
                            <label for="roomName">Room Number</label>
                            <input type="text" id="roomName" placeholder="Enter room number" required>
                        </div>
                        <button type="submit" class="btn" id="addRoomBtn">Add Room</button>
                    </form>
                </div>

                <!-- Add Item Form -->
                <div class="card">
                    <div class="card-header">
                        <img src=assets/images/add-item.png draggable="false">
                        <h2>Add New Item</h2>
                    </div>
                    <form id="itemForm">
                        <div class="form-group">
                            <label for="itemName">Item Name</label>
                            <input type="text" id="itemName" placeholder="Enter item name" required>
                        </div>
                        <div class="form-group">
                            <label for="itemCategory">Category</label>
                            <select id="itemCategory" required>
                                <option value="">Select category</option>
                                <option value="Learning Materials">Learning Materials</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Tools">Tools</option>
                                <option value="Appliances">Appliances</option>
                                <option value="Medical">Medical</option>
                                <option value="School Supplies">School Supplies</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assignRoom">Assign to Room</label>
                            <select id="assignRoom">
                                <option value="">Select room (optional)</option>
                                <?php foreach($rooms_dropdown as $room): ?>
                                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="itemQuantity">Quantity</label>
                            <input type="number" id="itemQuantity" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="expectedQuantity">Expected Quantity</label>
                            <input type="number" id="expectedQuantity" min="1" placeholder="Enter expected quantity">
                        </div>
                        <button type="submit" class="btn" id="addItemBtn">Add Item</button>
                    </form>
                </div>

                <!-- Bulk Operations -->
                <div class="card">
                    <div class="card-header">
                        <img src="assets/images/bulk.png" draggable="false">
                        <h2>Bulk Operations</h2>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-success btn-form-group" onclick="exportData()"><img src="assets/images/export.png" draggable="false"> Export Data</button>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-danger btn-form-group" onclick="clearAllData()"><img src="assets/images/clear.png" draggable="false"> Clear All Data</button>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-form-group" onclick="generateReport()"><img src="assets/images/table-report.png" draggable="false"> Generate Report</button>
                    </div>
                </div>
            </div>
        </div>
    </div>                                    

    <div id="notificationPopup" style="display:none;position:fixed;top:40px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:16px 32px;border-radius:8px;z-index:2000;font-size:1.1em;box-shadow:0 4px 16px rgba(0,0,0,0.15);"></div>

    <script>
        // Add Room Form Handler
        document.getElementById('roomForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const roomName = document.getElementById('roomName').value;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_room&room_number=${encodeURIComponent(roomName)}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('roomName').value = '';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error adding room', 'error');
            });
        });

        // Add Item Form Handler
        document.getElementById('itemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const itemName = document.getElementById('itemName').value;
            const itemCategory = document.getElementById('itemCategory').value;
            const assignRoom = document.getElementById('assignRoom').value;
            const itemQuantity = document.getElementById('itemQuantity').value;
            const expectedQuantity = document.getElementById('expectedQuantity').value;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_item&name=${encodeURIComponent(itemName)}&category=${encodeURIComponent(itemCategory)}&room_id=${assignRoom}&quantity=${itemQuantity}&expected_quantity=${expectedQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if(data.success) {
                    document.getElementById('itemName').value = '';
                    document.getElementById('itemCategory').value = '';
                    document.getElementById('assignRoom').value = '';
                    document.getElementById('itemQuantity').value = 1;
                    document.getElementById('expectedQuantity').value = '';
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showNotification('Error adding item', 'error');
            });
        });

        // Clear All Data Function
        function clearAllData() {
            if(confirm('Are you sure you want to clear all data? This cannot be undone.')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_all'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error clearing data', 'error');
                });
            }
        }

        // Export Data Function
        function exportData() {
            window.open('export.php', '_blank');
        }

        // Generate Report Function
        function generateReport() {
            window.open('report.php', '_blank');
        }

        // Show Notification Function
        function showNotification(message, type) {
            const popup = document.getElementById('notificationPopup');
            popup.textContent = message;
            popup.style.background = type === 'success' ? '#4caf50' : '#e53935';
            popup.style.display = 'block';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 2000);
        }
    </script>

</body>

</html>