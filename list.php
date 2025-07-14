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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        switch ($_POST['action']) {
            case 'delete_room':
                $room_id = $_POST['room_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM roomInventory WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    echo json_encode(['success' => true, 'message' => 'Room deleted successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete room: ' . $e->getMessage()]);
                }
                exit;
                
            case 'edit_room':
                $room_id = $_POST['room_id'];
                $room_number = $_POST['room_number'];
                $room_name = $_POST['room_name'];
                $building_number = $_POST['building_number'];
                $room_type = $_POST['room_type'];
                $teacher_name = $_POST['teacher_name'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, room_name = ?, building_number = ?, room_type = ?, teacher_name = ? WHERE room_id = ?");
                    $stmt->execute([$room_number, $room_name, $building_number, $room_type, $teacher_name, $room_id]);
                    echo json_encode(['success' => true, 'message' => 'Room updated successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update room: ' . $e->getMessage()]);
                }
                exit;
                
            case 'delete_item':
                $inventory_id = $_POST['inventory_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM roomInventory WHERE inventory_id = ?");
                    $stmt->execute([$inventory_id]);
                    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete item: ' . $e->getMessage()]);
                }
                exit;
                
            case 'edit_item':
                $inventory_id = $_POST['inventory_id'];
                $quantity = $_POST['quantity'];
                $expected_quantity = $_POST['expected_quantity'];
                $ownership = $_POST['ownership'];
                $remarks = $_POST['remarks'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE roomInventory SET quantity = ?, expected_quantity = ?, ownership = ?, remarks = ? WHERE inventory_id = ?");
                    $stmt->execute([$quantity, $expected_quantity, $ownership, $remarks, $inventory_id]);
                    echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update item: ' . $e->getMessage()]);
                }
                exit;
        }
    }

    include 'sidebar.php';

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $query = "SELECT 
        ri.inventory_id,
        i.item_name as product_name,
        i.category,
        i.unit,
        ri.quantity,
        ri.expected_quantity,
        r.room_number,
        ri.ownership,
        ri.remarks,
        ri.created_at
    FROM roomInventory ri
    LEFT JOIN items i ON ri.item_id = i.item_id
    LEFT JOIN rooms r ON ri.room_id = r.room_id";

    $params = [];

    if (!empty($search)) {
        $query .= " WHERE i.item_name LIKE :search OR i.category LIKE :search OR r.room_number LIKE :search OR ri.ownership LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY ri.inventory_id ASC";

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = 5;
    $offset = ($page - 1) * $items_per_page;

    $count_query = "SELECT COUNT(*) FROM roomInventory ri 
                    LEFT JOIN items i ON ri.item_id = i.item_id 
                    LEFT JOIN rooms r ON ri.room_id = r.room_id";
    if (!empty($search)) {
        $count_query .= " WHERE i.item_name LIKE :search OR i.category LIKE :search OR r.room_number LIKE :search OR ri.ownership LIKE :search";
    }

    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    $query .= " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $start_item = $offset + 1;
    $end_item = min($offset + $items_per_page, $total_items);

    // Rooms query
    $stmt = $pdo->query("
        SELECT r.room_id, r.room_number, r.room_name, r.building_number, r.room_type, r.teacher_name, r.created_at,
            COUNT(ri.inventory_id) as item_count
        FROM rooms r 
        LEFT JOIN roomInventory ri ON r.room_id = ri.room_id 
        GROUP BY r.room_id, r.room_number, r.room_name, r.building_number, r.room_type, r.teacher_name, r.created_at
        ORDER BY r.room_number
    ");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT room_id, room_number, room_name FROM rooms ORDER BY room_number");
    $rooms_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Room items query
    $room_items = [];
    foreach($rooms as $room) {
        $stmt = $pdo->prepare("
            SELECT ri.inventory_id, i.item_name, i.category, ri.quantity, ri.expected_quantity, ri.ownership 
            FROM roomInventory ri 
            JOIN items i ON ri.item_id = i.item_id 
            WHERE ri.room_id = ? 
            ORDER BY i.item_name
        ");
        $stmt->execute([$room['room_id']]);
        $room_items[$room['room_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
                <h1>Current Inventory</h1>
                <p>Manage and view your inventory items</p>
            </div>
            
            <div class="card">
                <div class="search-bar">
                    <form method="GET" style="display: inline;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="🔍 Search items, categories, room numbers, or ownership...">
                    </form>
                </div>
                
                <?php if ($total_items > 0): ?>
                    <div class="table-container">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Expected Quantity</th>
                                    <th>Room</th>
                                    <th>Ownership</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="item-category"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['expected_quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['room_number'] ?? 'Not Assigned'); ?></td>
                                        <td>
                                            <span class="ownership-badge ownership-<?php echo strtolower(str_replace(' ', '-', $item['ownership'])); ?>">
                                                <?php echo htmlspecialchars($item['ownership']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-small btn-primary" onclick="editItem(<?php echo $item['inventory_id']; ?>, <?php echo $item['quantity']; ?>, <?php echo $item['expected_quantity']; ?>, '<?php echo htmlspecialchars($item['ownership'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['remarks'] ?? '', ENT_QUOTES); ?>')">Edit</button>
                                            <button class="btn btn-small btn-danger" onclick="deleteItem(<?php echo $item['inventory_id']; ?>)">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="info-text">
                        <div class="pagination-info">
                            Showing <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo $total_items; ?> entries
                            <?php if (!empty($search)): ?>
                                (filtered from total entries)
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn">Previous</a>
                            <?php else: ?>
                                <span class="btn disabled">Previous</span>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="btn current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn">Next</a>
                            <?php else: ?>
                                <span class="btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="stat-number">0</div>
                        <div class="stat-label">
                            <?php if (!empty($search)): ?>
                                No items found matching your search criteria.
                            <?php else: ?>
                                No items found in inventory.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Room Card Display -->
            <div class="rooms-grid" id="roomsContainer">
                <?php if(empty($rooms)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7v10c0 5.55 3.84 10 9 11 1.12-.11 2.17-.39 3.12-.82C15.27 25.94 16.5 24.5 17.5 22.5c.5-1 .83-2.17.83-3.5 0-5.5-4.5-10-10-10z"/>
                        </svg>
                        <h3>No rooms added yet</h3>
                        <p>Add your first room to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($rooms as $room): ?>
                        <div class="room-card" data-room-id="<?php echo $room['room_id']; ?>" onclick="openRoomModal(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')">
                            <div class="room-header">
                                <div class="room-name">
                                    <?php echo htmlspecialchars($room['room_number']); ?>
                                    <?php if (!empty($room['room_name'])): ?>
                                        <small>(<?php echo htmlspecialchars($room['room_name']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="room-count"><?php echo $room['item_count']; ?> items</div>
                            </div>
                            
                            <div class="room-details">
                                <div class="room-info">
                                    <span class="room-type"><?php echo htmlspecialchars($room['room_type']); ?></span>
                                    <span class="building">Building <?php echo htmlspecialchars($room['building_number']); ?></span>
                                </div>
                                <?php if (!empty($room['teacher_name'])): ?>
                                    <div class="teacher-name">Teacher: <?php echo htmlspecialchars($room['teacher_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 30px; display: flex; gap: 10px;">
                                <button class="btn btn-small btn-primary" onclick="editRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>', '<?php echo htmlspecialchars($room['room_name']); ?>', '<?php echo htmlspecialchars($room['building_number']); ?>', '<?php echo htmlspecialchars($room['room_type']); ?>', '<?php echo htmlspecialchars($room['teacher_name']); ?>')">Edit Room</button>
                                <button class="btn btn-small btn-danger" onclick="deleteRoom(<?php echo $room['room_id']; ?>)">Delete Room</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Room Modal -->
    <div id="roomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalRoomTitle">Room Details</h2>
                <span class="close" onclick="closeRoomModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalLoader" class="loader">Loading...</div>
                <div id="modalTableContainer" class="table-container" style="display: none;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Expected Quantity</th>
                                <th>Ownership</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="modalTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="modalEmptyState" class="empty-state" style="display: none;">
                    <div class="stat-number">0</div>
                    <div class="stat-label">No items found in this room.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div id="editRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Room</h2>
                <span class="close" onclick="closeEditRoomModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editRoomForm">
                    <input type="hidden" id="editRoomId" name="room_id">
                    <div class="form-group">
                        <label for="editRoomNumber">Room Number:</label>
                        <input type="text" id="editRoomNumber" name="room_number" required>
                    </div>
                    <div class="form-group">
                        <label for="editRoomName">Room Name:</label>
                        <input type="text" id="editRoomName" name="room_name">
                    </div>
                    <div class="form-group">
                        <label for="editBuildingNumber">Building Number:</label>
                        <input type="text" id="editBuildingNumber" name="building_number">
                    </div>
                    <div class="form-group">
                        <label for="editRoomType">Room Type:</label>
                        <select id="editRoomType" name="room_type">
                            <option value="Classroom">Classroom</option>
                            <option value="Office">Office</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editTeacherName">Teacher Name:</label>
                        <input type="text" id="editTeacherName" name="teacher_name">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-small btn-primary">Update Room</button>
                        <button type="button" class="btn btn-small btn-danger" onclick="closeEditRoomModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Item</h2>
                <span class="close" onclick="closeEditItemModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editItemForm">
                    <input type="hidden" id="editItemId" name="inventory_id">
                    <div class="form-group">
                        <label for="editItemQuantity">Quantity:</label>
                        <input type="number" id="editItemQuantity" name="quantity" required>
                    </div>
                    <div class="form-group">
                        <label for="editItemExpectedQuantity">Expected Quantity:</label>
                        <input type="number" id="editItemExpectedQuantity" name="expected_quantity" required>
                    </div>
                    <div class="form-group">
                        <label for="editItemOwnership">Ownership:</label>
                        <select id="editItemOwnership" name="ownership" required>
                            <option value="School Property">School Property</option>
                            <option value="Homeroom">Homeroom</option>
                            <option value="PTA Donated">PTA Donated</option>
                            <option value="Personal">Personal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editItemRemarks">Remarks:</label>
                        <textarea id="editItemRemarks" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-small btn-primary">Update Item</button>
                        <button type="button" class="btn btn-small btn-danger" onclick="closeEditItemModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="confirmModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:3000;background:rgba(0,0,0,0.3);align-items:center;justify-content:center;">
        <div style="background:#fff;padding:32px 24px;border-radius:12px;max-width:90vw;width:340px;box-shadow:0 8px 32px rgba(0,0,0,0.18);text-align:center;">
            <div id="confirmMessage" style="margin-bottom:24px;font-size:1.1em;"></div>
            <div style="display:flex;justify-content:center;gap:12px;">
                <button id="confirmYes" class="btn btn-danger">YES</button>
                <button id="confirmNo" class="btn btn-primary">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Notification Popup -->
    <div id="notificationPopup" style="display:none;position:fixed;top:40px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:16px 32px;border-radius:8px;z-index:4000;font-size:1.1em;box-shadow:0 4px 16px rgba(0,0,0,0.15);"></div>

    <script>
        // Room modal functionality
        function openRoomModal(roomId, roomNumber) {
            const modal = document.getElementById('roomModal');
            const modalTitle = document.getElementById('modalRoomTitle');
            const modalLoader = document.getElementById('modalLoader');
            const modalTableContainer = document.getElementById('modalTableContainer');
            const modalEmptyState = document.getElementById('modalEmptyState');
            const modalTableBody = document.getElementById('modalTableBody');
            
            modalTitle.textContent = `Room: ${roomNumber}`;
            
            modal.style.display = 'block';
            
            modalLoader.style.display = 'block';
            modalTableContainer.style.display = 'none';
            modalEmptyState.style.display = 'none';
            
            // Fetch room items
            fetch(`getroomitems.php?room_id=${roomId}`)
                .then(response => response.json())
                .then(data => {
                    modalLoader.style.display = 'none';
                    
                    if (data.success && data.items.length > 0) {
                        modalTableBody.innerHTML = '';
                        data.items.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><div class="item-name">${item.name}</div></td>
                                <td><div class="item-category">${item.category || 'N/A'}</div></td>
                                <td>${item.quantity} ${item.unit}</td>
                                <td>${item.expected_quantity} ${item.unit}</td>
                                <td><span class="ownership-badge ownership-${item.ownership.toLowerCase().replace(' ', '-')}">${item.ownership}</span></td>
                                <td>
                                    <button class="btn btn-small btn-primary" onclick="editItem(${item.inventory_id}, ${item.quantity}, ${item.expected_quantity}, '${item.ownership}', '${item.remarks || ''}')">Edit</button>
                                    <button class="btn btn-small btn-danger" onclick="deleteItem(${item.inventory_id})">Delete</button>
                                </td>
                            `;
                            modalTableBody.appendChild(row);
                        });
                        modalTableContainer.style.display = 'block';
                    } else {
                        modalEmptyState.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching room items:', error);
                    modalLoader.style.display = 'none';
                    modalEmptyState.style.display = 'block';
                });
        }
        
        function closeRoomModal() {
            document.getElementById('roomModal').style.display = 'none';
        }
        
        // Edit Room Functions
        function editRoom(roomId, roomNumber, roomName, buildingNumber, roomType, teacherName) {
            document.getElementById('editRoomId').value = roomId;
            document.getElementById('editRoomNumber').value = roomNumber;
            document.getElementById('editRoomName').value = roomName;
            document.getElementById('editBuildingNumber').value = buildingNumber;
            document.getElementById('editRoomType').value = roomType;
            document.getElementById('editTeacherName').value = teacherName;
            document.getElementById('editRoomModal').style.display = 'block';
        }
        
        function closeEditRoomModal() {
            document.getElementById('editRoomModal').style.display = 'none';
        }
        
        document.getElementById('editRoomForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_room');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Room updated successfully', 'success');
                    closeEditRoomModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error updating room', 'error');
            });
        });
        
        // Edit Item Functions
        function editItem(inventoryId, quantity, expectedQuantity, ownership, remarks) {
            document.getElementById('editItemId').value = inventoryId;
            document.getElementById('editItemQuantity').value = quantity;
            document.getElementById('editItemExpectedQuantity').value = expectedQuantity;
            document.getElementById('editItemOwnership').value = ownership;
            document.getElementById('editItemRemarks').value = remarks;
            document.getElementById('editItemModal').style.display = 'block';
        }
        
        function closeEditItemModal() {
            document.getElementById('editItemModal').style.display = 'none';
        }
        
        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_item');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Item updated successfully', 'success');
                    closeEditItemModal();
                    const currentRoomId = document.getElementById('roomModal').getAttribute('data-room-id');
                    if (currentRoomId) {
                        openRoomModal(currentRoomId, document.getElementById('modalRoomTitle').textContent.replace('Room: ', ''));
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error updating item', 'error');
            });
        });
        
        window.onclick = function(event) {
            const roomModal = document.getElementById('roomModal');
            const editRoomModal = document.getElementById('editRoomModal');
            const editItemModal = document.getElementById('editItemModal');
            
            if (event.target === roomModal) {
                closeRoomModal();
            } else if (event.target === editRoomModal) {
                closeEditRoomModal();
            } else if (event.target === editItemModal) {
                closeEditItemModal();
            }
        }
        
        document.querySelectorAll('.room-card .btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
        // Delete Room Function
        function deleteRoom(roomId) {
            showConfirm('Are you sure you want to delete this room? All inventory items will be deleted.', function() {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_room&room_id=${roomId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Room deleted successfully', 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                    // Add a 2-second delay before reloading the page
                    setTimeout(() => location.reload(), 2000);
                })
                .catch(error => {
                    showNotification('Room Deleted!');
                    // Add a 2-second delay before reloading the page even on error
                    setTimeout(() => location.reload(), 2000);
                });
            });
        }

        // Delete Item Function
        function deleteItem(inventoryId) {
            showConfirm('Are you sure you want to delete this item?', function() {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_item&inventory_id=${inventoryId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Item deleted successfully', 'success');
                        const currentRoomId = document.getElementById('roomModal').getAttribute('data-room-id');
                        if (currentRoomId) {
                            setTimeout(() => {
                                openRoomModal(currentRoomId, document.getElementById('modalRoomTitle').textContent.replace('Room: ', ''));
                            }, 1000);
                        }
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Item Deleted!');
                    const currentRoomId = document.getElementById('roomModal').getAttribute('data-room-id');
                    if (currentRoomId) {
                        setTimeout(() => {
                            openRoomModal(currentRoomId, document.getElementById('modalRoomTitle').textContent.replace('Room: ', ''));
                        }, 1000);
                    }
                });
            });
        }

        function showConfirm(message, onYes) {
            const modal = document.getElementById('confirmModal');
            const msg = document.getElementById('confirmMessage');
            const yesBtn = document.getElementById('confirmYes');
            const noBtn = document.getElementById('confirmNo');
            msg.textContent = message;
            modal.style.display = 'flex';

            function cleanup() {
                modal.style.display = 'none';
                yesBtn.removeEventListener('click', yesHandler);
                noBtn.removeEventListener('click', noHandler);
            }
            function yesHandler() {
                cleanup();
                onYes(); // Only call the delete function, don't show notification here
            }
            function noHandler() {
                cleanup();
            }
            yesBtn.addEventListener('click', yesHandler);
            noBtn.addEventListener('click', noHandler);
        }

        function showNotification(message, type) {
            const popup = document.getElementById('notificationPopup');
            popup.textContent = message;
            popup.style.display = 'block';
            popup.style.background = type === 'success' ? '#4caf50' : '#f44336';

            setTimeout(() => {
                popup.style.display = 'none';
            }, 3000);
        }

        document.getElementById('roomModal').addEventListener('DOMNodeInserted', function() {
            const roomTitle = document.getElementById('modalRoomTitle').textContent;
            if (roomTitle.includes('Room: ')) {
                const roomNumber = roomTitle.replace('Room: ', '');
                const roomCards = document.querySelectorAll('.room-card');
                roomCards.forEach(card => {
                    const cardRoomNumber = card.querySelector('.room-name').textContent.trim().split('(')[0].trim();
                    if (cardRoomNumber === roomNumber) {
                        document.getElementById('roomModal').setAttribute('data-room-id', card.getAttribute('data-room-id'));
                    }
                });
            }
        });
    </script>
</body>
</html>