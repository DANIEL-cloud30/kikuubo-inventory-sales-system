<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = "";
$error = "";


// PROCESS STOCK MOVEMENTS

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $productId = filter_var(
        $_POST['product_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $movementType = $_POST['movement_type'] ?? '';

    $quantity = filter_var(
        $_POST['quantity'] ?? null,
        FILTER_VALIDATE_INT
    );

    $notes = trim($_POST['notes'] ?? '');

    $allowedTypes = [
        'Stock In',
        'Adjustment In',
        'Adjustment Out'
    ];

    if (
        !$productId ||
        $quantity === false ||
        $quantity <= 0 ||
        !in_array($movementType, $allowedTypes, true)
    ) {

        $error = "Please provide valid stock movement information.";

    } else {

        try {

            $conn->beginTransaction();

            // GET CURRENT PRODUCT STOCK AND LOCK THE ROW

            $stmt = $conn->prepare("
                SELECT id, quantity
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$productId]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Selected product does not exist.");
            }

            $previousQuantity = (int)$product['quantity'];

            // CALCULATE NEW QUANTITY

            if (
                $movementType === 'Stock In' ||
                $movementType === 'Adjustment In'
            ) {

                $newQuantity = $previousQuantity + $quantity;

            } else {

                $newQuantity = $previousQuantity - $quantity;

            }

            if ($newQuantity < 0) {

                throw new Exception(
                    "Insufficient stock. Available quantity: "
                    . $previousQuantity
                );

            }

            // UPDATE PRODUCT QUANTITY

            $update = $conn->prepare("
                UPDATE products
                SET quantity = ?
                WHERE id = ?
            ");

            $update->execute([
                $newQuantity,
                $productId
            ]);


            // RECORD STOCK MOVEMENT

            $insert = $conn->prepare("
                INSERT INTO stock_movements
                (
                    product_id,
                    user_id,
                    movement_type,
                    quantity,
                    previous_quantity,
                    new_quantity,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $insert->execute([
                $productId,
                $_SESSION['user_id'],
                $movementType,
                $quantity,
                $previousQuantity,
                $newQuantity,
                $notes
            ]);


            $conn->commit();

            header("Location: stock.php?success=1");
            exit();

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e instanceof PDOException
                ? "A database error occurred. Please try again."
                : $e->getMessage();

        }

    }

}


// SUCCESS MESSAGE

if (isset($_GET['success'])) {
    $message = "Stock movement recorded successfully!";
}


// GET PRODUCTS

$products = $conn->query("
    SELECT id, product_code, product_name, quantity
    FROM products
    ORDER BY product_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


// GET STOCK STATISTICS

$totalStock = $conn->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM products
")->fetchColumn();

$lowStock = $conn->query("
    SELECT COUNT(*)
    FROM products
    WHERE quantity <= reorder_level
")->fetchColumn();

$totalProducts = $conn->query("
    SELECT COUNT(*)
    FROM products
")->fetchColumn();


// GET STOCK MOVEMENT HISTORY

$history = $conn->query("
    SELECT
        stock_movements.*,
        products.product_name,
        products.product_code,
        users.full_name

    FROM stock_movements

    INNER JOIN products
        ON stock_movements.product_id = products.id

    LEFT JOIN users
        ON stock_movements.user_id = users.id

    ORDER BY stock_movements.created_at DESC

    LIMIT 50

")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stock Management | Kikuubo General Traders</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>


<body class="dashboard-body">


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

    <main class="dashboard-main">


        <header class="topbar">

            <div>

                <h2>Stock Management</h2>

                <p>Monitor and manage inventory movements.</p>

            </div>


            <div class="user-info">

                <div class="user-avatar">

                    <?php
                    echo strtoupper(
                        substr($_SESSION['full_name'], 0, 1)
                    );
                    ?>

                </div>

                <div>

                    <strong>
                        <?php
                        echo htmlspecialchars($_SESSION['full_name']);
                        ?>
                    </strong>

                    <small>
                        <?php
                        echo htmlspecialchars($_SESSION['role']);
                        ?>
                    </small>

                </div>

            </div>

        </header>



        <!-- MESSAGES -->

        <?php if ($message): ?>

            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>



        <!-- STOCK STATISTICS -->

        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon">P</div>

                <p>Total Products</p>

                <h2><?php echo $totalProducts; ?></h2>

                <small>Registered products</small>

            </div>


            <div class="stat-card">

                <div class="stat-icon">S</div>

                <p>Available Stock</p>

                <h2><?php echo number_format($totalStock); ?></h2>

                <small>Total inventory units</small>

            </div>


            <div class="stat-card warning-card">

                <div class="stat-icon">!</div>

                <p>Low Stock Products</p>

                <h2><?php echo $lowStock; ?></h2>

                <small>Products needing attention</small>

            </div>

        </section>



        <!-- RECORD STOCK MOVEMENT -->

        <section class="content-card stock-form-card">


            <div class="section-header">

                <h3>Record Stock Movement</h3>

                <span>Update inventory quantities</span>

            </div>



            <form method="POST" class="stock-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($csrfToken); ?>"
                >


                <div class="stock-fields">


                    <div class="form-group">

                        <label>Select Product</label>

                        <select name="product_id" required>

                            <option value="">Choose a product</option>

                            <?php foreach ($products as $product): ?>

                                <option value="<?php echo $product['id']; ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $product['product_name']
                                    );
                                    ?>

                                    (Available:
                                    <?php echo $product['quantity']; ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>Movement Type</label>

                        <select name="movement_type" required>

                            <option value="">Select movement type</option>

                            <option value="Stock In">Stock In</option>

                            <option value="Adjustment In">
                                Adjustment In
                            </option>

                            <option value="Adjustment Out">
                                Adjustment Out
                            </option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>Quantity</label>

                        <input
                            type="number"
                            name="quantity"
                            min="1"
                            placeholder="Enter quantity"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Notes / Description</label>

                        <input
                            type="text"
                            name="notes"
                            maxlength="255"
                            placeholder="Reason for stock movement"
                        >

                    </div>


                </div>


                <button type="submit" class="primary-button">

                    Record Stock Movement

                </button>

            </form>

        </section>



        <!-- CURRENT STOCK -->

        <section class="content-card stock-list-card">

            <div class="section-header">

                <h3>Current Stock Levels</h3>

                <span>Available inventory</span>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Product Code</th>

                            <th>Product Name</th>

                            <th>Available Quantity</th>

                            <th>Stock Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($products as $product): ?>

                            <?php

                            $productDetails = $conn->prepare("
                                SELECT reorder_level
                                FROM products
                                WHERE id = ?
                            ");

                            $productDetails->execute([
                                $product['id']
                            ]);

                            $reorderLevel = $productDetails->fetchColumn();

                            ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $product['product_code'] ?? ''
                                    );
                                    ?>
                                </td>


                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $product['product_name']
                                    );
                                    ?>
                                </td>


                                <td>
                                    <?php
                                    echo number_format($product['quantity']);
                                    ?>
                                </td>


                                <td>

                                    <?php if ($product['quantity'] <= $reorderLevel): ?>

                                        <span class="stock-low">
                                            Low Stock
                                        </span>

                                    <?php else: ?>

                                        <span class="stock-good">
                                            In Stock
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <?php if (count($products) === 0): ?>

                            <tr>

                                <td colspan="4" class="empty-message">
                                    No products registered yet.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>



        <!-- STOCK MOVEMENT HISTORY -->

        <section class="content-card stock-history-card">


            <div class="section-header">

                <h3>Stock Movement History</h3>

                <span>Latest 50 movements</span>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Movement</th>

                            <th>Quantity</th>

                            <th>Previous Stock</th>

                            <th>New Stock</th>

                            <th>Recorded By</th>

                            <th>Date</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($history) > 0): ?>


                            <?php foreach ($history as $movement): ?>


                                <tr>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $movement['product_name']
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $movement['movement_type']
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo number_format(
                                            $movement['quantity']
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo number_format(
                                            $movement['previous_quantity']
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo number_format(
                                            $movement['new_quantity']
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $movement['full_name'] ?? 'Unknown'
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo date(
                                            "d M Y, h:i A",
                                            strtotime(
                                                $movement['created_at']
                                            )
                                        );
                                        ?>
                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="7" class="empty-message">

                                    No stock movements recorded yet.

                                </td>

                            </tr>


                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


    </main>

</body>

</html>