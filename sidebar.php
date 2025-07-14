<?php



    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }

    $current_page = basename($_SERVER['PHP_SELF']);

?>

<link rel="stylesheet" href="styles/style.css">

<div class="sidebar">

    <div class="sidebar-logo">
        <div class="logo-placeholder">
            <img src="./assets/images/logo.png" alt="Logo" draggable="false">
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                    <img src="assets/images/box.png" draggable="false">
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="list.php" class="nav-link <?php echo ($current_page == 'list.php') ? 'active' : ''; ?>">
                    <img src="assets/images/view-list.png" draggable="false">
                    <span>Inventory</span>
                </a>
            </li>
        </ul>
    </nav>

    <a href="javascript:void(0);" id="logout-btn" title="Logout">
        <img src="assets/images/logout.png" draggable="false">
    </a>
</div>

<!-- Logout Confirmation Modal -->
<div id="logout-confirmation-modal" style="display:none;">
    <div class="logout-confirmation-modal-content">
        <div class="logout-confirmation-modal-header">
            Are you sure you want to logout?
        </div>
        <div class="logout-confirmation-modal-actions">
            <button class="btn btn-secondary" onclick="confirmLogout()">Yes</button>
            <button class="btn btn-danger" onclick="closeLogoutModal()">No</button>
        </div>
    </div>
</div>

<style>
/* Logout Confirmation Modal Internal CSS */
#logout-confirmation-modal {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.4);
    display: none;
    z-index: 2000;
}
.logout-confirmation-modal-content {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    padding: 32px 24px 24px 24px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    min-width: 300px;
    text-align: center;
}
.logout-confirmation-modal-header {
    font-size: 1.2em;
    margin-bottom: 18px;
    font-weight: 500;
}
.logout-confirmation-modal-actions {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 10px;
}
.logout-confirmation-modal-actions .btn {
    padding: 8px 28px;
    border: none;
    border-radius: 6px;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
}
.logout-confirmation-modal-actions .btn-secondary {
    background: #4f8cff;
    color: #fff;
}
.logout-confirmation-modal-actions .btn-secondary:hover {
    background: #2563eb;
}
.logout-confirmation-modal-actions .btn-danger {
    background: #e74c3c;
    color: #fff;
}
.logout-confirmation-modal-actions .btn-danger:hover {
    background: #c0392b;
}
</style>

<script>
document.getElementById('logout-btn').onclick = function() {
    document.getElementById('logout-confirmation-modal').style.display = 'block';
};
function closeLogoutModal() {
    document.getElementById('logout-confirmation-modal').style.display = 'none';
}
function confirmLogout() {
    window.location.href = 'logout.php';
}
</script>