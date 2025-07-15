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
$stmt = $pdo->query("SELECT room_id, room_name, room_number, building_number FROM rooms ORDER BY building_number ASC, room_number ASC");
$rooms_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for dropdown
$items_dropdown = [];
$stmt = $pdo->query("SELECT item_id, item_name, category FROM items ORDER BY item_name ASC");
$items_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch($_POST['action']) {
        case 'add_room':
            try {
                $room_name = $_POST['room_name'];
                $room_number = $_POST['room_number'];
                $building_number = $_POST['building_number'];
                $room_type = $_POST['room_type'];
                $teacher_name = !empty($_POST['teacher_name']) ? $_POST['teacher_name'] : null;
                $grade_level = isset($_POST['grade_level']) ? $_POST['grade_level'] : null;
                
                $stmt = $pdo->prepare("INSERT INTO rooms (room_name, room_number, building_number, grade_level, room_type, teacher_name) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$room_name, $room_number, $building_number, $grade_level, $room_type, $teacher_name]);
                echo json_encode(['success' => true, 'message' => 'Room added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'add_item':
            try {
                $item_name = $_POST['item_name'];
                $category = $_POST['category'];
                $description = !empty($_POST['description']) ? $_POST['description'] : null;
                $unit = $_POST['unit'];

                $stmt = $pdo->prepare("INSERT INTO items (item_name, category, description, unit) VALUES (?, ?, ?, ?)");
                $stmt->execute([$item_name, $category, $description, $unit]);
                echo json_encode(['success' => true, 'message' => 'Item added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'add_inventory':
            try {
                $room_id = $_POST['room_id'];
                $item_id = $_POST['item_id'];
                $quantity = (int)$_POST['quantity'];
                $expected_quantity = (int)$_POST['expected_quantity'];
                $ownership = $_POST['ownership'];
                $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : null;
                $year = isset($_POST['year']) ? $_POST['year'] : null;

                $stmt = $pdo->prepare("INSERT INTO roominventory (room_id, item_id, quantity, expected_quantity, ownership, remarks, year) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$room_id, $item_id, $quantity, $expected_quantity, $ownership, $remarks, $year]);
                echo json_encode(['success' => true, 'message' => 'Inventory item added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'clear_all':
            try {
                $pdo->exec("DELETE FROM roominventory");
                $pdo->exec("DELETE FROM items");
                $pdo->exec("DELETE FROM rooms");
                echo json_encode(['success' => true, 'message' => 'All data cleared successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Calculate statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
$stats['total_rooms'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_items FROM items");
$stats['total_items'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_inventory FROM roominventory");
$stats['total_inventory'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(quantity) as total_quantity FROM roominventory");
$stats['total_quantity'] = $stmt->fetchColumn() ?: 0;

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JLGIS Inventory</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="main-content-with-sidebar">
        <div class="container">
            <div class="header">
                <h1>JLGIS Inventory System</h1>
                <p>View statistics and operations</p>
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
                    <div class="stat-number"><?php echo $stats['total_inventory']; ?></div>
                    <div class="stat-label">Inventory Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_quantity']; ?></div>
                    <div class="stat-label">Total Quantity</div>
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
                            <label for="roomName">Room Name</label>
                            <input type="text" id="roomName" placeholder="Enter (Section / Office)" required>
                        </div>
                        <div class="form-group">
                            <label for="roomNumber">Room Number</label>
                            <input type="text" id="roomNumber" placeholder="Enter room number" required>
                        </div>
                        <div class="form-group">
                            <label for="buildingNumber">Building Number</label>
                            <input type="text" id="buildingNumber" placeholder="Enter building number" required>
                        </div>
                        <div class="form-group">
                            <label for="gradeLevel">Grade Level</label>
                            <select id="gradeLevel" required>
                                <option value="" disabled selected>Select grade level</option>
                                <option value="Kindergarten">Kindergarten</option>
                                <option value="Grade 1">Grade 1</option>
                                <option value="Grade 2">Grade 2</option>
                                <option value="Grade 3">Grade 3</option>
                                <option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option>
                                <option value="Grade 6">Grade 6</option>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 10">Grade 10</option>
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                                <option value="Office">Office</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="roomType">Room Type</label>
                            <select id="roomType" required>
                                <option value="" disabled selected>Select room type</option>
                                <option value="Classroom">Classroom</option>
                                <option value="Office">Office</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="teacherName">Teacher Name (Optional)</label>
                            <input type="text" id="teacherName" placeholder="Enter teacher name">
                        </div>
                        <button type="submit" class="btn" id="addRoomBtn">Add Room</button>
                    </form>
                </div>

                <!-- Add Item Form -->
                <div class="card">
                    <div class="card-header">
                        <img src="assets/images/add-item.png" draggable="false">
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
                                <option value="" disabled selected>Select category</option>
                                <option value="Learning Materials">Learning Materials</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Tools">Tools</option>
                                <option value="Appliances">Appliances</option>
                                <option value="Medical">Medical</option>
                                <option value="School Supplies">School Supplies</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="itemDescription">Description (Optional)</label>
                            <textarea id="itemDescription" placeholder="Enter item description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="itemUnit">Unit</label>
                            <select id="itemUnit" required>
                                <option value="" disabled selected>Select unit</option>
                                <option value="pcs">Pieces</option>
                                <option value="set">Set</option>
                                <option value="copies">Copies</option>
                            </select>
                        </div>
                        <button type="submit" class="btn" id="addItemBtn">Add Item</button>
                    </form>
                </div>

                <!-- Add Inventory Form -->
                <div class="card">
                    <div class="card-header">
                        <img src="assets/images/add-item.png" draggable="false">
                        <h2>Add Inventory Item</h2>
                    </div>
                    <form id="inventoryForm">
                        <div class="form-group">
                            <label for="selectRoom">Select Room</label>
                            <select id="selectRoom" required>
                                <option value="" disabled selected>Select room</option>
                                <?php foreach($rooms_dropdown as $room): ?>
                                    <option value="<?php echo $room['room_id']; ?>">
                                        <?php echo htmlspecialchars($room['room_name'] . ' (' . $room['room_number'] . ', Building ' . $room['building_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="selectItem">Select Item</label>
                            <select id="selectItem" required>
                                <option value="" disabled selected>Select item</option>
                                <?php foreach($items_dropdown as $item): ?>
                                    <option value="<?php echo $item['item_id']; ?>">
                                        <?php echo htmlspecialchars($item['item_name'] . ' (' . $item['category'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inventoryQuantity">Quantity</label>
                            <input type="number" id="inventoryQuantity" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="inventoryExpectedQuantity">Expected Quantity</label>
                            <input type="number" id="inventoryExpectedQuantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="inventoryOwnership">Ownership</label>
                            <select id="inventoryOwnership" required>
                                <option value="" disabled selected>Select ownership</option>
                                <option value="School Property">School Property</option>
                                <option value="Homeroom">Homeroom</option>
                                <option value="PTA Donated">PTA Donated</option>
                                <option value="Personal">Personal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inventoryYear">School Year</label>
                            <select id="inventoryYear" required>
                                <option value="" disabled selected>Select year</option>
                                <option value="2022-2023">2022-2023</option>
                                <option value="2023-2024">2023-2024</option>
                                <option value="2024-2025">2024-2025</option>
                                <option value="2025-2026">2025-2026</option>
                                <option value="2026-2027">2026-2027</option>
                                <option value="2027-2028">2027-2028</option>
                                <option value="2028-2029">2028-2029</option>
                                <option value="2029-2030">2029-2030</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inventoryRemarks">Remarks (Optional)</label>
                            <textarea id="inventoryRemarks" placeholder="Enter remarks"></textarea>
                        </div>
                        <button type="submit" class="btn" id="addInventoryBtn">Add to Inventory</button>
                    </form>
                </div>

                <!-- Bulk Operations -->
                <div class="card">
                    <div class="card-header">
                        <img src="assets/images/bulk.png" draggable="false">
                        <h2>Bulk Operations</h2>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-form-group" onclick="generateReport()">
                            <img src="assets/images/table-report.png" draggable="false"> Generate Report
                        </button>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-success btn-form-group" onclick="exportData()">
                            <img src="assets/images/export.png" draggable="false"> Export Data
                        </button>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-danger btn-form-group" onclick="clearAllData()">
                            <img src="assets/images/clear.png" draggable="false"> Clear All Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="notificationPopup" style="display:none;position:fixed;top:40px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:16px 32px;border-radius:8px;z-index:2000;font-size:1.1em;box-shadow:0 4px 16px rgba(0,0,0,0.15);"></div>

    <!-- Confirmation Modals -->
    <div id="generateReportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Generate Report</h2>
            </div>
            <div class="modal-body">
                <p>Would you like to generate report?</p>
                <div class="modal-buttons" style="display: flex;">
                    <button class="btn btn-primary" onclick="confirmGenerateReport()">Yes</button>
                    <button class="btn btn-secondary" onclick="closeModal('generateReportModal')">No</button>
                </div>
            </div>
        </div>
    </div>

    <div id="exportDataModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Export Data</h2>
            </div>
            <div class="modal-body">
                <p>Would you like to export data?</p>
                <div class="modal-buttons">
                    <button class="btn btn-success" onclick="confirmExportData()">Yes</button>
                    <button class="btn btn-secondary" onclick="closeModal('exportDataModal')">No</button>
                </div>
            </div>
        </div>
    </div>

    <div id="clearAllDataModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Clear All Data</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear data?</p>
                <div class="modal-buttons">
                    <button class="btn btn-primary" onclick="confirmClearAllData()">Yes</button>
                    <button class="btn btn-secondary" onclick="closeModal('clearAllDataModal')">No</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add Room Form Handler
        document.getElementById('roomForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const roomName = document.getElementById('roomName').value;
            const roomNumber = document.getElementById('roomNumber').value;
            const buildingNumber = document.getElementById('buildingNumber').value;
            const roomType = document.getElementById('roomType').value;
            const teacherName = document.getElementById('teacherName').value;
            const gradeLevel = document.getElementById('gradeLevel').value;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_room&room_name=${encodeURIComponent(roomName)}&room_number=${encodeURIComponent(roomNumber)}&building_number=${encodeURIComponent(buildingNumber)}&grade_level=${encodeURIComponent(gradeLevel)}&room_type=${encodeURIComponent(roomType)}&teacher_name=${encodeURIComponent(teacherName)}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('roomForm').reset();
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
            const itemDescription = document.getElementById('itemDescription').value;
            const itemUnit = document.getElementById('itemUnit').value;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_item&item_name=${encodeURIComponent(itemName)}&category=${encodeURIComponent(itemCategory)}&description=${encodeURIComponent(itemDescription)}&unit=${encodeURIComponent(itemUnit)}`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if(data.success) {
                    document.getElementById('itemForm').reset();
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showNotification('Error adding item', 'error');
            });
        });

        // Add Inventory Form Handler
        document.getElementById('inventoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const selectRoom = document.getElementById('selectRoom').value;
            const selectItem = document.getElementById('selectItem').value;
            const inventoryQuantity = document.getElementById('inventoryQuantity').value;
            const inventoryExpectedQuantity = document.getElementById('inventoryExpectedQuantity').value;
            const inventoryOwnership = document.getElementById('inventoryOwnership').value;
            const inventoryRemarks = document.getElementById('inventoryRemarks').value;
            const inventoryYear = document.getElementById('inventoryYear').value;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_inventory&room_id=${selectRoom}&item_id=${selectItem}&quantity=${inventoryQuantity}&expected_quantity=${inventoryExpectedQuantity}&ownership=${encodeURIComponent(inventoryOwnership)}&remarks=${encodeURIComponent(inventoryRemarks)}&year=${encodeURIComponent(inventoryYear)}`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if(data.success) {
                    document.getElementById('inventoryForm').reset();
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showNotification('Error adding inventory item', 'error');
            });
        });

        // Modal Functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Clear All Data Functions
        function clearAllData() {
            showModal('clearAllDataModal');
        }

        function confirmClearAllData() {
            closeModal('clearAllDataModal');
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

        // Export Data Functions
        function exportData() {
            showModal('exportDataModal');
        }

        function confirmExportData() {
            closeModal('exportDataModal');
            window.open('export.php', '_blank');
        }

        // Generate Report Functions
        function generateReport() {
            showModal('generateReportModal');
        }

        function confirmGenerateReport() {
            closeModal('generateReportModal');
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