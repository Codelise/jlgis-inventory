<?php

    session_start();

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        header('Location: inventory.php');
        exit;
    }

    $host = 'localhost';
    $dbname = 'jlgis';
    $username = 'root';
    $password_db = '';

    $error_message = '';
    $show_popup = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password_db);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $password_form = $_POST['password'];

            $stmt = $pdo->prepare("SELECT admin_password FROM admin WHERE admin_id = 1");
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password_form, $admin['admin_password'])) {
                $_SESSION['loggedin'] = true;
                header('Location: inventory.php');
                exit;
            } else {
                $show_popup = true;
            }

        } catch(PDOException $e) {
            $show_popup = true;
        }
    }

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JLGIS Inventory</title>
    <link rel="stylesheet" href="styles/login.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">

</head>

<body class="bg-gray-100">

    <div id="passwordErrorPopup" class="popup-card">
        <p>Password is incorrect.</p>
    </div>

    <div id="easterEgg" class="easter-egg-card">
        <img src="assets/images/leo.jpg" alt="Easter Egg" draggable="false">
    </div>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md">
            <div class="text-center mb-8">
                <img src="assets/images/logo.png" id="logoImage" alt="School Logo" class="mx-auto mb-4 rounded-full border-4 border-white" draggable="false">
                <h1 class="text-3xl font-bold text-gray-800">JLGIS Inventory</h1>
                <p class="text-gray-500 mt-2">Please login to access the dashboard.</p>
            </div>

            <form action="login.php" method="POST">
                
                <?php if (!empty($error_message)): ?>
                    <div class="error-banner">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="mb-6">
                    <h2 style="text-align: center">Mr. Willy R. Antigo</h2>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="text-gray-700 font-semibold">Password</label>
                    </div>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Enter your password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 ring-primary transition-shadow duration-200" required>
                        <img src="assets/images/preview-close.png" alt="Show Password" class="toggle-password" id="togglePassword" draggable="false">
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary text-white font-bold py-3 px-4 rounded-lg hover:bg-primary-dark transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 ring-primary shadow-md hover:shadow-lg">
                    Login
                </button>
            </form>
        </div>
    </div>

    <script>

        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordErrorPopup = document.getElementById('passwordErrorPopup');
        const logoImage = document.getElementById('logoImage');
        const easterEgg = document.getElementById('easterEgg');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            if (type === 'password') {
                this.src = 'assets/images/preview-close.png';
                this.alt = 'Show Password';
            } else {
                this.src = 'assets/images/preview-open.png';
                this.alt = 'Hide Password';
            }
        });

        <?php if ($show_popup): ?>
            document.addEventListener('DOMContentLoaded', function() {
                passwordErrorPopup.classList.add('show');
                setTimeout(() => {
                    passwordErrorPopup.classList.remove('show');
                }, 3000);
            });
        <?php endif; ?>

        logoImage.addEventListener('dblclick', function() {
            easterEgg.classList.toggle('show-easter-egg');
        });


    </script>

</body>

</html>