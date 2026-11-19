<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Escape output for security
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Automatically highlight the current page
function navActive($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

// Date filters
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$fromDate = DateTime::createFromFormat('!Y-m-d', $from);
$toDate = DateTime::createFromFormat('!Y-m-d', $to);

if (
    !$fromDate ||
    $fromDate->format('Y-m-d') !== $from ||
    !$toDate ||
    $toDate->format('Y-m-d') !== $to ||
    $from > $to
) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}

// Date range condition
$dateCondition = "
    created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
";

// ==========================================
// 1. SALES PERFORMANCE SUMMARY
// ==========================================

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales
    WHERE $dateCondition
");

$stmt->execute([$from, $to]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Total units sold
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(si.quantity), 0)
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE s.created_at >= ?
    AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)
");

$stmt->execute([$from, $to]);
$totalUnits = $stmt->fetchColumn();


// ==========================================
// 2. BEST-SELLING PRODUCTS
// ==========================================

$stmt = $conn->prepare("
    SELECT
        p.product_name,
        SUM(si.quantity) AS quantity_sold,
        SUM(si.subtotal) AS revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.created_at >= ?
    AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY p.id, p.product_name
    ORDER BY quantity_sold DESC
    LIMIT 10
");

$stmt->execute([$from, $to]);
$bestSelling = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 3. LOW STOCK PRODUCTS
// ==========================================

$stmt = $conn->query("
    SELECT
        product_name,
        product_code,
        quantity,
        reorder_level
    FROM products
    WHERE quantity <= reorder_level
    ORDER BY quantity ASC
");

$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 4. INVENTORY SUMMARY
// ==========================================

$stmt = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(quantity), 0) AS total_units
    FROM products
");

$inventory = $stmt->fetch(PDO::FETCH_ASSOC);


// ==========================================
// 5. COMPLETE SALES HISTORY
// ==========================================

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.total_amount,
        s.created_at,
        COUNT(si.id) AS items_count,
        COALESCE(SUM(si.quantity), 0) AS units_sold
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE s.created_at >= ?
    AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY s.id, s.total_amount, s.created_at
    ORDER BY s.created_at DESC
");

$stmt->execute([$from, $to]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 6. INDIVIDUAL SALE DETAILS
// ==========================================

$selectedSale = null;
$saleItems = [];

if (isset($_GET['view'])) {

    $saleId = filter_var(
        $_GET['view'],
        FILTER_VALIDATE_INT
    );

    if ($saleId && $saleId > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM sales
            WHERE id = ?
        ");

        $stmt->execute([$saleId]);
        $selectedSale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedSale) {

            $stmt = $conn->prepare("
                SELECT
                    si.quantity,
                    si.unit_price,
                    si.subtotal,
                    p.product_name,
                    p.product_code
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ?
            ");

            $stmt->execute([$saleId]);
            $saleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Reports | Kikuubo Inventory</title>

    <!--<link rel="stylesheet" href="assets/css/style.css">-->
    <link rel="stylesheet" href="assets/css/reports.css">

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

                <h1>Business Reports</h1>

                <p>
                    Monitor sales performance and inventory levels.
                </p>

            </div>

            <button onclick="window.print()"
                    class="btn-primary">
                Print Report
            </button>

        </div>


        <!-- DATE FILTER -->

        <section class="content-card">

            <h2>Report Period</h2>

            <form method="GET" class="filter-form">

                <div class="form-group">

                    <label>From Date</label>

                    <input
                        type="date"
                        name="from"
                        value="<?= e($from) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>To Date</label>

                    <input
                        type="date"
                        name="to"
                        value="<?= e($to) ?>"
                        required
                    >

                </div>
            </form>

        </section>


        <!-- SALES PERFORMANCE -->

        <h2 class="section-title">
            Sales Performance
        </h2>

        <div class="stats-grid">

            <div class="stat-card">

                <h3>Total Revenue</h3>

                <h2>
                    UGX <?= number_format($summary['total_revenue']) ?>
                </h2>

            </div>


            <div class="stat-card">

                <h3>Total Transactions</h3>

                <h2>
                    <?= number_format($summary['total_orders']) ?>
                </h2>

            </div>


            <div class="stat-card">

                <h3>Units Sold</h3>

                <h2>
                    <?= number_format($totalUnits) ?>
                </h2>

            </div>

        </div>


        <!-- BEST SELLING PRODUCTS -->

        <section class="content-card">

            <h2>Top Selling Products</h2>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Revenue Generated</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($bestSelling) > 0): ?>

                        <?php foreach ($bestSelling as $product): ?>

                            <tr>

                                <td>
                                    <?= e($product['product_name']) ?>
                                </td>

                                <td>
                                    <?= number_format($product['quantity_sold']) ?>
                                </td>

                                <td>
                                    UGX <?= number_format($product['revenue']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="3">
                                No sales recorded during this period.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- INVENTORY OVERVIEW -->

        <h2 class="section-title">
            Inventory Overview
        </h2>

        <div class="stats-grid">

            <div class="stat-card">

                <h3>Registered Products</h3>

                <h2>
                    <?= number_format($inventory['total_products']) ?>
                </h2>

            </div>


            <div class="stat-card">

                <h3>Total Stock Units</h3>

                <h2>
                    <?= number_format($inventory['total_units']) ?>
                </h2>

            </div>


            <div class="stat-card">

                <h3>Low Stock Products</h3>

                <h2>
                    <?= number_format(count($lowStock)) ?>
                </h2>

            </div>

        </div>


        <!-- LOW STOCK ALERT -->

        <section class="content-card">

            <h2>Low Stock Alerts</h2>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>
                            <th>Product</th>
                            <th>Product Code</th>
                            <th>Current Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($lowStock) > 0): ?>

                        <?php foreach ($lowStock as $product): ?>

                            <tr>

                                <td>
                                    <?= e($product['product_name']) ?>
                                </td>

                                <td>
                                    <?= e($product['product_code']) ?>
                                </td>

                                <td>
                                    <?= e($product['quantity']) ?>
                                </td>

                                <td>
                                    <?= e($product['reorder_level']) ?>
                                </td>

                                <td>

                                    <span class="status-low">
                                        Low Stock
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5">
                                All products have sufficient stock.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- COMPLETE SALES HISTORY -->

        <section class="content-card">

            <h2>Sales History</h2>

            <p>
                View all recorded transactions within the selected period.
            </p>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>
                            <th>Sale ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Units Sold</th>
                            <th>Total Amount</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($sales) > 0): ?>

                        <?php foreach ($sales as $sale): ?>

                            <tr>

                                <td>
                                    #<?= e($sale['id']) ?>
                                </td>

                                <td>
                                    <?= e($sale['created_at']) ?>
                                </td>

                                <td>
                                    <?= e($sale['items_count']) ?>
                                </td>

                                <td>
                                    <?= e($sale['units_sold']) ?>
                                </td>

                                <td>
                                    UGX <?= number_format($sale['total_amount']) ?>
                                </td>

                                <td>

                                    <a
                                        href="reports.php?from=<?= e($from) ?>&to=<?= e($to) ?>&view=<?= e($sale['id']) ?>"
                                        class="btn-small"
                                    >
                                        View Details
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6">
                                No transactions found for this period.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- INDIVIDUAL TRANSACTION DETAILS -->

        <?php if (isset($_GET['view'])): ?>

            <section class="content-card">

                <?php if ($selectedSale): ?>

                    <div class="page-header">

                        <div>

                            <h2>
                                Transaction Details
                                #<?= e($selectedSale['id']) ?>
                            </h2>

                            <p>
                                <?= e($selectedSale['created_at']) ?>
                            </p>

                        </div>

                        <button onclick="window.print()"
                                class="btn-primary">
                            Print Details
                        </button>

                    </div>


                    <div class="table-responsive">

                        <table>

                            <thead>

                                <tr>
                                    <th>Product</th>
                                    <th>Product Code</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach ($saleItems as $item): ?>

                                <tr>

                                    <td>
                                        <?= e($item['product_name'] ?? 'Product unavailable') ?>
                                    </td>

                                    <td>
                                        <?= e($item['product_code'] ?? 'N/A') ?>
                                    </td>

                                    <td>
                                        <?= e($item['quantity']) ?>
                                    </td>

                                    <td>
                                        UGX <?= number_format($item['unit_price']) ?>
                                    </td>

                                    <td>
                                        UGX <?= number_format($item['subtotal']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <h3 class="sale-total">

                        Total:
                        UGX <?= number_format($selectedSale['total_amount']) ?>

                    </h3>

                <?php else: ?>

                    <p>Transaction not found.</p>

                <?php endif; ?>

            </section>

        <?php endif; ?>


    </main>

</div>

</body>
</html>