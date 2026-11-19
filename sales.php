<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = "";
$error = "";
$receipt = null;
$receiptItems = [];


// PROCESS A SALE

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (
        !is_array($productIds) ||
        !is_array($quantities) ||
        count($productIds) === 0 ||
        count($productIds) !== count($quantities) ||
        count($productIds) > 50
    ) {

        $error = "Please add valid products to the sale.";

    } else {

        try {

            $conn->beginTransaction();

            $items = [];
            $totalAmount = 0;

            // VALIDATE AND PROCESS EACH PRODUCT

            foreach ($productIds as $index => $productId) {

                $productId = filter_var(
                    $productId,
                    FILTER_VALIDATE_INT
                );

                $quantity = filter_var(
                    $quantities[$index],
                    FILTER_VALIDATE_INT
                );

                if (
                    !$productId ||
                    $quantity === false ||
                    $quantity <= 0
                ) {
                    throw new Exception(
                        "Invalid product or quantity entered."
                    );
                }

                // LOCK PRODUCT RECORD

                $stmt = $conn->prepare("
                    SELECT id, product_name, selling_price, quantity
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");

                $stmt->execute([$productId]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception(
                        "One of the selected products does not exist."
                    );
                }

                // CHECK STOCK

                if ($quantity > $product['quantity']) {

                    throw new Exception(
                        "Insufficient stock for " .
                        $product['product_name'] .
                        ". Available: " . $product['quantity']
                    );

                }

                // GET PRICE FROM DATABASE

                $unitPrice = (float)$product['selling_price'];

                $subtotal = $unitPrice * $quantity;

                $previousQuantity = (int)$product['quantity'];

                $newQuantity = $previousQuantity - $quantity;

                $totalAmount += $subtotal;


                $items[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['product_name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newQuantity
                ];

            }


            // CREATE SALE RECORD

            $stmt = $conn->prepare("
                INSERT INTO sales (total_amount)
                VALUES (?)
            ");

            $stmt->execute([$totalAmount]);

            $saleId = $conn->lastInsertId();


            // SAVE SALE ITEMS AND DEDUCT STOCK

            foreach ($items as $item) {

                // INSERT SALE ITEM

                $stmt = $conn->prepare("
                    INSERT INTO sale_items
                    (sale_id, product_id, quantity, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $saleId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ]);


                // DEDUCT STOCK

                $stmt = $conn->prepare("
                    UPDATE products
                    SET quantity = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $item['new_quantity'],
                    $item['product_id']
                ]);


                // RECORD STOCK MOVEMENT

                $stmt = $conn->prepare("
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
                    VALUES (?, ?, 'Sale', ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $item['product_id'],
                    $_SESSION['user_id'],
                    $item['quantity'],
                    $item['previous_quantity'],
                    $item['new_quantity'],
                    "Sale transaction #" . $saleId
                ]);

            }


            // COMPLETE TRANSACTION

            $conn->commit();

            header("Location: sales.php?receipt=" . $saleId);
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


// DISPLAY RECEIPT

if (isset($_GET['receipt'])) {

    $receiptId = filter_var(
        $_GET['receipt'],
        FILTER_VALIDATE_INT
    );

    if ($receiptId) {

        $stmt = $conn->prepare("
            SELECT *
            FROM sales
            WHERE id = ?
        ");

        $stmt->execute([$receiptId]);

        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($receipt) {

            $stmt = $conn->prepare("
                SELECT
                    sale_items.*,
                    products.product_name,
                    products.product_code

                FROM sale_items

                INNER JOIN products
                    ON sale_items.product_id = products.id

                WHERE sale_items.sale_id = ?

            ");

            $stmt->execute([$receiptId]);

            $receiptItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

    }

}


// GET AVAILABLE PRODUCTS

$products = $conn->query("
    SELECT id, product_name, product_code,
           selling_price, quantity
    FROM products
    WHERE quantity > 0
    ORDER BY product_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


// GET RECENT SALES

$recentSales = $conn->query("
    SELECT *
    FROM sales
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales Management | Kikuubo General Traders</title>

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

                <h2>Sales Management</h2>

                <p>Record customer purchases and manage sales transactions.</p>

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



        <!-- ERROR MESSAGE -->

        <?php if ($error): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>



        <?php if ($receipt): ?>


            <!-- RECEIPT -->

            <section class="content-card receipt-card" id="receipt">

                <div class="receipt-header">

                    <h2>KIKUUBO GENERAL TRADERS</h2>

                    <p>Sales Receipt</p>

                    <h3>Thank you for shopping with us!</h3>

                </div>


                <hr>


                <div class="receipt-details">

                    <p>
                        <strong>Receipt Number:</strong>
                        #<?php echo $receipt['id']; ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php echo date(
                            "d M Y, h:i A",
                            strtotime($receipt['created_at'])
                        ); ?>
                    </p>

                </div>


                <div class="table-container">

                    <table>

                        <thead>

                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($receiptItems as $item): ?>

                                <tr>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            $item['product_name']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php echo $item['quantity']; ?>
                                    </td>

                                    <td>
                                        UGX <?php echo number_format(
                                            $item['unit_price']
                                        ); ?>
                                    </td>

                                    <td>
                                        UGX <?php echo number_format(
                                            $item['subtotal']
                                        ); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <div class="receipt-total">

                    <h3>
                        TOTAL: UGX
                        <?php echo number_format(
                            $receipt['total_amount']
                        ); ?>
                    </h3>

                </div>


                <div class="receipt-actions">

                    <button onclick="window.print()" class="primary-button">
                        Print Receipt
                    </button>

                    <a href="sales.php" class="cancel-button">
                        Record Another Sale
                    </a>

                </div>

            </section>


        <?php else: ?>


            <!-- RECORD SALE -->

            <section class="content-card sales-form-card">

                <div class="section-header">

                    <h3>Record New Sale</h3>

                    <span>Enter products purchased by the customer</span>

                </div>


                <?php if (count($products) > 0): ?>


                    <form method="POST" id="salesForm">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($csrfToken); ?>"
                        >


                        <div id="saleItems">


                            <div class="sale-row">

                                <div class="sale-field">

                                    <label>Product</label>

                                    <select
                                        name="product_id[]"
                                        class="sale-product"
                                        required
                                    >

                                        <option value="">
                                            Select Product
                                        </option>


                                        <?php foreach ($products as $product): ?>

                                            <option
                                                value="<?php echo $product['id']; ?>"
                                                data-price="<?php echo $product['selling_price']; ?>"
                                                data-stock="<?php echo $product['quantity']; ?>"
                                            >

                                                <?php echo htmlspecialchars(
                                                    $product['product_name']
                                                ); ?>

                                                (Stock:
                                                <?php echo $product['quantity']; ?>)

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="sale-field">

                                    <label>Quantity</label>

                                    <input
                                        type="number"
                                        name="quantity[]"
                                        class="sale-quantity"
                                        min="1"
                                        placeholder="Quantity"
                                        required
                                    >

                                </div>


                                <div class="sale-field">

                                    <label>Unit Price</label>

                                    <input
                                        type="text"
                                        class="sale-price"
                                        value="UGX 0"
                                        readonly
                                    >

                                </div>


                                <div class="sale-field">

                                    <label>Subtotal</label>

                                    <input
                                        type="text"
                                        class="sale-subtotal"
                                        value="UGX 0"
                                        readonly
                                    >

                                </div>


                                <button
                                    type="button"
                                    class="remove-sale-row"
                                    onclick="removeSaleRow(this)"
                                >
                                    Remove
                                </button>

                            </div>


                        </div>


                        <button
                            type="button"
                            class="add-sale-button"
                            onclick="addSaleRow()"
                        >
                            + Add Another Product
                        </button>


                        <div class="sale-total-box">

                            <p>Grand Total</p>

                            <h2 id="grandTotal">
                                UGX 0
                            </h2>

                        </div>


                        <button type="submit" class="primary-button">

                            Complete Sale

                        </button>

                    </form>


                <?php else: ?>


                    <p class="empty-message">
                        No products with available stock.
                        Please add products or update inventory first.
                    </p>


                <?php endif; ?>


            </section>


            <!-- RECENT SALES -->

            <section class="content-card recent-sales-card">

                <div class="section-header">

                    <h3>Recent Sales</h3>

                    <span>Latest 10 transactions</span>

                </div>


                <div class="table-container">

                    <table>

                        <thead>

                            <tr>
                                <th>Sale ID</th>
                                <th>Total Amount</th>
                                <th>Date</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php if (count($recentSales) > 0): ?>


                                <?php foreach ($recentSales as $sale): ?>

                                    <tr>

                                        <td>
                                            #<?php echo $sale['id']; ?>
                                        </td>

                                        <td>
                                            UGX <?php echo number_format(
                                                $sale['total_amount']
                                            ); ?>
                                        </td>

                                        <td>
                                            <?php echo date(
                                                "d M Y, h:i A",
                                                strtotime($sale['created_at'])
                                            ); ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td colspan="3" class="empty-message">
                                        No sales have been recorded yet.
                                    </td>

                                </tr>


                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


        <?php endif; ?>


    </main>



    <script src="assets/js/script.js"></script>

</body>

</html>