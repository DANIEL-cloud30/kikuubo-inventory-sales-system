<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

/* =====================================
   ADMIN ACCESS CONTROL
===================================== */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit;
}

/* =====================================
   HELPER FUNCTION
===================================== */

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =====================================
   CSRF PROTECTION
===================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = $_SESSION['user_message'] ?? '';
$messageType = $_SESSION['user_message_type'] ?? '';

unset($_SESSION['user_message'], $_SESSION['user_message_type']);

/* =====================================
   HANDLE FORM SUBMISSIONS
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $action = $_POST['action'] ?? '';

    try {

        /* =============================
           REGISTER USER
        ============================== */

        if ($action === 'create') {

            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'Staff';

            if ($fullName === '' || $username === '' || $password === '') {
                throw new Exception("Please fill in all required fields.");
            }

            if (!in_array($role, ['Admin', 'Staff'], true)) {
                throw new Exception("Invalid user role.");
            }

            if (strlen($password) < 8) {
                throw new Exception("Password must contain at least 8 characters.");
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (full_name, username, password, role)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $fullName,
                $username,
                $hashedPassword,
                $role
            ]);

            $_SESSION['user_message'] = "User registered successfully!";
            $_SESSION['user_message_type'] = "success";
        }


        /* =============================
           UPDATE USER
        ============================== */

        elseif ($action === 'update') {

            $userId = filter_var(
                $_POST['user_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (!$userId || $fullName === '' || $username === '') {
                throw new Exception("Please provide valid user details.");
            }

            if (!in_array($role, ['Admin', 'Staff'], true)) {
                throw new Exception("Invalid user role.");
            }

            $stmt = $conn->prepare("
                SELECT role FROM users WHERE id = ?
            ");

            $stmt->execute([$userId]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                throw new Exception("User not found.");
            }

            // Prevent removal of the final administrator.

            if ($existingUser['role'] === 'Admin' && $role !== 'Admin') {

                $adminCount = $conn->query("
                    SELECT COUNT(*) FROM users WHERE role = 'Admin'
                ")->fetchColumn();

                if ($adminCount <= 1) {
                    throw new Exception("You cannot remove the last administrator.");
                }
            }

            if ($password !== '') {

                if (strlen($password) < 8) {
                    throw new Exception("Password must contain at least 8 characters.");
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, username = ?, password = ?, role = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $hashedPassword,
                    $role,
                    $userId
                ]);

            } else {

                $stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, username = ?, role = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $role,
                    $userId
                ]);
            }

            $_SESSION['user_message'] = "User updated successfully!";
            $_SESSION['user_message_type'] = "success";
        }


        /* =============================
           DELETE USER
        ============================== */

        elseif ($action === 'delete') {

            $userId = filter_var(
                $_POST['user_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$userId) {
                throw new Exception("Invalid user selected.");
            }

            if ($userId == $_SESSION['user_id']) {
                throw new Exception("You cannot delete your own account.");
            }

            $stmt = $conn->prepare("
                SELECT role FROM users WHERE id = ?
            ");

            $stmt->execute([$userId]);
            $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userToDelete) {
                throw new Exception("User not found.");
            }

            if ($userToDelete['role'] === 'Admin') {

                $adminCount = $conn->query("
                    SELECT COUNT(*) FROM users WHERE role = 'Admin'
                ")->fetchColumn();

                if ($adminCount <= 1) {
                    throw new Exception("You cannot delete the last administrator.");
                }
            }

            $stmt = $conn->prepare("
                DELETE FROM users WHERE id = ?
            ");

            $stmt->execute([$userId]);

            $_SESSION['user_message'] = "User deleted successfully!";
            $_SESSION['user_message_type'] = "success";
        }

        header("Location: user_management.php");
        exit;

    } catch (PDOException $ex) {

        if ($ex->getCode() === '23000') {
            $message = "That username is already registered.";
        } else {
            $message = "A database error occurred. Please try again.";
        }

        $messageType = "error";

    } catch (Exception $ex) {

        $message = $ex->getMessage();
        $messageType = "error";
    }
}

/* =====================================
   EDIT USER DATA
===================================== */

$editUser = null;

if (isset($_GET['edit'])) {

    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId) {

        $stmt = $conn->prepare("
            SELECT id, full_name, username, role
            FROM users
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/* =====================================
   FETCH ALL USERS
===================================== */

$stmt = $conn->query("
    SELECT id, full_name, username, role, created_at
    FROM users
    ORDER BY created_at DESC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management | Kikuubo Inventory</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <!--<link rel="stylesheet" href="assets/css/user_management.css"-->

</head>

<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <div class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-icon">K</div>

            <h2>Kikuubo <span>Traders</span></h2>

        </div>


        <p class="menu-heading">MAIN MENU</p>

     <a href="dashboard.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
    Dashboard
</a>



<a href="products.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
    Products
</a>

<a href="category.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'category.php' ? 'active' : '' ?>">
    Categories
</a>

<a href="stock.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'active' : '' ?>">
    Stock Management
</a>

<a href="sales.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>">
    Sales Management
</a>



<a href="reports.php"
   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
    Reports
</a>

<?php if ($_SESSION['role'] === 'Admin'): ?>

    <p class="menu-heading">ADMINISTRATION</p>

    <a href="user_management.php"
       class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : '' ?>">
        User Management
    </a>

<?php endif; ?>

<a href="logout.php" class="nav-link logout-link">
    Logout
</a>

<!--<p class="menu-heading">ADMINISTRATION</p>

<a href="user_management.php" class="nav-link">User Management</a>

<a href="logout.php" class="nav-link logout-link">
    Logout
</a>-->

    </div>





    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1>User Management</h1>

                <p>Manage system users and their access permissions.</p>

            </div>

        </div>


        <!-- NOTIFICATIONS -->

        <?php if ($message): ?>

            <div class="alert <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>

        <?php endif; ?>


        <!-- USER REGISTRATION FORM -->

        <section class="content-card">

            <h2>
                <?= $editUser ? 'Edit User Account' : 'Register New User' ?>
            </h2>

            <form method="POST" class="user-form">

                <input type="hidden" name="csrf_token"
                       value="<?= e($csrfToken) ?>">

                <input type="hidden" name="action"
                       value="<?= $editUser ? 'update' : 'create' ?>">

                <?php if ($editUser): ?>

                    <input type="hidden" name="user_id"
                           value="<?= e($editUser['id']) ?>">

                <?php endif; ?>


                <div class="form-group">

                    <label>Full Name</label>

                    <input type="text"
                           name="full_name"
                           placeholder="Enter full name"
                           value="<?= e($editUser['full_name'] ?? '') ?>"
                           maxlength="100"
                           required>

                </div>


                <div class="form-group">

                    <label>Username</label>

                    <input type="text"
                           name="username"
                           placeholder="Enter username"
                           value="<?= e($editUser['username'] ?? '') ?>"
                           maxlength="50"
                           required>

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

                <!-- <div class="form-group">

                   <label>
                        Password
                        <?= $editUser ? '(Leave blank to keep current password)' : '' ?>
                    </label>

                    <input type="password"
                           name="password"
                           placeholder="<?= $editUser ? 'Enter new password (optional)' : 'Enter password (minimum 8 characters)' ?>"
                           <?= $editUser ? '' : 'required' ?>>

                </div> -->


                <div class="form-group">

                    <label>User Role</label>

                    <select name="role" required>

                        <option value="Staff"
                            <?= ($editUser['role'] ?? 'Staff') === 'Staff' ? 'selected' : '' ?>>
                            Staff
                        </option>

                        <option value="Admin"
                            <?= ($editUser['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>
                            Admin
                        </option>

                    </select>

                </div>


                <div class="form-actions">

                    <button type="submit" class="btn-primary">

                        <?= $editUser ? 'Update User' : 'Register User' ?>

                    </button>

                    <?php if ($editUser): ?>

                        <a href="user_management.php" class="btn-secondary">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- REGISTERED USERS -->

        <section class="content-card">

            <h2>Registered System Users</h2>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Full Name</th>

                            <th>Username</th>

                            <th>Role</th>

                            <th>Date Registered</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($users) > 0): ?>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <td>#<?= e($user['id']) ?></td>

                                <td><?= e($user['full_name']) ?></td>

                                <td><?= e($user['username']) ?></td>

                                <td>

                                    <span class="role-badge <?= strtolower(e($user['role'])) ?>">
                                        <?= e($user['role']) ?>
                                    </span>

                                </td>

                                <td><?= e($user['created_at']) ?></td>

                                <td class="action-buttons">

                                    <a href="user_management.php?edit=<?= e($user['id']) ?>"
                                       class="btn-small edit-btn">
                                        Edit
                                    </a>

                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this user?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($csrfToken) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="delete">

                                            <input type="hidden"
                                                   name="user_id"
                                                   value="<?= e($user['id']) ?>">

                                            <button type="submit"
                                                    class="btn-small delete-btn">
                                                Delete
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="current-user">Current Account</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">No users registered.</td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>
<script src="assets/javascript/script.js"></script>

</body>
</html>