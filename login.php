<?php
session_start();

require_once __DIR__ . "/includes/db.php";

// Redirect users who are already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === "" || $password === "") {

        $error = "Please enter both username and password.";

    } else {

        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':username' => $username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboard.php");
            exit();

        } else {

            $error = "Invalid username or password.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Kikuubo General Traders</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>

<body class="login-body">

    <div class="login-container">

        <div class="login-left">

            <div class="brand">
                <div class="brand-icon">K</div>
                <h2>Kikuubo <span>Traders</span></h2>
            </div>

            <div class="login-message">

                <h1>Manage Your Business Smarter.</h1>

                <p>
                    Monitor your inventory, manage sales and keep
                    your business operations organised in one place.
                </p>

                <div class="login-features">
                    <p>✓ Inventory Management</p>
                    <p>✓ Sales Tracking</p>
                    <p>✓ Business Reports</p>
                </div>

            </div>

            <div class="login-footer">
                &copy; 2026 Kikuubo General Traders
            </div>

        </div>


        <div class="login-right">

            <div class="login-form">

                <h2>Welcome Back!</h2>

                <p class="login-subtitle">
                    Sign in to access your management dashboard.
                </p>

                <?php if ($error != ""): ?>

                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php endif; ?>


                <form method="POST" action="">

                    <div class="form-group">

                        <label>Username</label>

                        <input
                            type="text"
                            name="username"
                            placeholder="Enter your username"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Password</label>

                        <div class="password-box">

                            <input
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Enter your password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                onclick="togglePassword()"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                    <button type="submit" class="login-button">
                        Sign In
                    </button>

                </form>

                <p class="login-note">
                    Authorised personnel only.
                </p>

            </div>

        </div>

    </div>

    <script src="assets/javascript/script.js"></script>

</body>
</html>