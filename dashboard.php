<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// Total products
$productQuery = $conn->query("SELECT COUNT(*) FROM products");
$totalProducts = $productQuery->fetchColumn();

// Total available stock
$stockQuery = $conn->query("SELECT COALESCE(SUM(quantity), 0) FROM products");
$totalStock = $stockQuery->fetchColumn();

// Today's sales
$salesQuery = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
    WHERE DATE(created_at) = CURDATE()
");

$todaySales = $salesQuery->fetchColumn();

// Low stock products
$lowStockQuery = $conn->query("
    SELECT COUNT(*)
    FROM products
    WHERE quantity <= reorder_level
");

$lowStock = $lowStockQuery->fetchColumn();

// Recent sales
$recentSales = $conn->query("
    SELECT id, total_amount, created_at
    FROM sales
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Kikuubo General Traders</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="dashboard-body">

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


    <main class="dashboard-main">

        <header class="topbar">

            <div>
                <h2>Dashboard</h2>
                <p>Overview of your business activities.</p>
            </div>

            <div class="user-info">
                <div class="user-avatar"> 
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </strong>

                    <small>
                        <?php echo htmlspecialchars($_SESSION['role']); ?>
                    </small>
                </div>
            </div>

        </header>


        <section class="welcome-section">

            <h2>
                Welcome back,
                <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
            </h2>

            <p>Here's what's happening with your business today.</p>

        </section>


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

                <small>Units in inventory</small>

            </div>


            <div class="stat-card">

                <div class="stat-icon">U</div>

                <p>Today's Sales</p>

                <h2>UGX <?php echo number_format($todaySales, 0); ?></h2>

                <small>Sales recorded today</small>

            </div>


            <div class="stat-card warning-card">

                <div class="stat-icon">!</div>

                <p>Low Stock Alerts</p>

                <h2><?php echo $lowStock; ?></h2>

                <small>Products requiring attention</small>

            </div>

        </section>


        <section class="content-card">

            <div class="section-header">

                <h3>Recent Sales</h3>

                <span>Latest transactions</span>

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

                                    <td>#<?php echo $sale['id']; ?></td>

                                    <td>
                                        UGX <?php echo number_format($sale['total_amount'], 0); ?>
                                    </td>

                                    <td>
                                        <?php echo date("d M Y, h:i A", strtotime($sale['created_at'])); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="3" class="empty-message">
                                    No sales transactions recorded yet.
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