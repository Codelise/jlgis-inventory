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
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="list.php" class="nav-link <?php echo ($current_page == 'list.php') ? 'active' : ''; ?>">
                    <img src="assets/images/view-list.png" draggable="false">
                    <span>List</span>
                </a>
            </li>
        </ul>

    </nav>

    <a href="logout.php" id="logout-btn" title="Logout">
        <img src="assets/images/logout.png" draggable="false">
    </a>

</div>