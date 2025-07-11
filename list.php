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

    include 'sidebar.php';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $query = "SELECT 
        i.id,
        i.name as product_name,
        i.category,
        i.quantity,
        r.room_number,
        i.created_at
    FROM item i
    LEFT JOIN rooms r ON i.room_id = r.id";

    $params = [];

    if (!empty($search)) {
        $query .= " WHERE i.name LIKE :search OR i.category LIKE :search OR r.room_number LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY i.id ASC";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        switch ($_POST['action']) {
            case 'delete_room':
                $room_id = $_POST['room_id'];
                try {
                    // Unassign all items from this room first
                    $stmt = $pdo->prepare("UPDATE item SET room_id = NULL WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    // Now delete the room
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
                    $stmt->execute([$room_id]);
                    echo json_encode(['success' => true, 'message' => 'Room deleted successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete room: ' . $e->getMessage()]);
                }
                exit;
                
            case 'delete_item':
                $item_id = $_POST['item_id'];
                $stmt = $pdo->prepare("DELETE FROM item WHERE id = ?");
                $stmt->execute([$item_id]);
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                exit;
                    }
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = 10;
    $offset = ($page - 1) * $items_per_page;

    $count_query = "SELECT COUNT(*) FROM item i LEFT JOIN rooms r ON i.room_id = r.id";
    if (!empty($search)) {
        $count_query .= " WHERE i.name LIKE :search OR i.category LIKE :search OR r.room_number LIKE :search";
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

    $stmt = $pdo->query("
        SELECT r.id, r.room_number, r.created_at,
            COUNT(i.id) as item_count
        FROM rooms r 
        LEFT JOIN item i ON r.id = i.room_id 
        GROUP BY r.id, r.room_number, r.created_at
        ORDER BY r.room_number
    ");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, room_number FROM rooms ORDER BY room_number");
    $rooms_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $room_items = [];
    foreach($rooms as $room) {
        $stmt = $pdo->prepare("SELECT id, name, category FROM item WHERE room_id = ? ORDER BY name");
        $stmt->execute([$room['id']]);
        $room_items[$room['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>JLGIS Inventory</title>

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
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="🔍 Search items, categories, or room numbers...">
                    </form>
                </div>
                
                <?php if ($total_items > 0): ?>
                    <div class="table-container">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Expected Quantity</th>
                                    <th>Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                                        <td>
                                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="item-category"><?php echo htmlspecialchars($item['category']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($item['room_number'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
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
                        <div class="room-card" data-room-id="<?php echo $room['id']; ?>" onclick="openRoomModal(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')">
                            <div class="room-header">
                                <div class="room-name"><?php echo htmlspecialchars($room['room_number']); ?></div>
                                <div class="room-count"><?php echo $room['item_count']; ?> items</div>
                            </div>
                            
                            <div class="item-list">
                                <?php if(empty($room_items[$room['id']])): ?>
                                    <div class="no-item">No items assigned to this room</div>
                                <?php else: ?>
                                    <?php foreach($room_items[$room['id']] as $item): ?>
                                        <div class="item-item" data-item-id="<?php echo $item['id']; ?>">
                                            <div>
                                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="item-category"><?php echo htmlspecialchars($item['category']); ?></div>
                                            </div>
                                            <button class="btn btn-small btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)"><img src="assets/images/delete-two.png" draggable="false"></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button class="btn btn-small btn-danger" onclick="deleteRoom(<?php echo $room['id']; ?>)">Delete Room</button>
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
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Expected Quantity</th>
                                <th>Date Added</th>
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
                                <td>${item.id}</td>
                                <td><div class="item-name">${item.name}</div></td>
                                <td><div class="item-category">${item.category}</div></td>
                                <td>${item.quantity}</td>
                                <td>${item.expected_quantity ?? ''}</td>
                                <td>${new Date(item.created_at).toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: '2-digit' 
                                })}</td>
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
        
        window.onclick = function(event) {
            const modal = document.getElementById('roomModal');
            if (event.target === modal) {
                closeRoomModal();
            }
        }
        
        document.querySelectorAll('.room-card .btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
        // Delete Room Function
        function deleteRoom(roomId) {
            showConfirm('Are you sure you want to delete this room? All items will be unassigned.', function() {
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
        function deleteItem(itemId) {
            showConfirm('Are you sure you want to delete this item?', function() {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_item&item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Item deleted successfully', 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                    // Add a 2-second delay before reloading the page
                    setTimeout(() => location.reload(), 2000);
                })
                .catch(error => {
                    showNotification('Item Deleted!');
                    // Add a 2-second delay before reloading the page even on error
                    setTimeout(() => location.reload(), 2000);
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
    </script>

</body>

</html>