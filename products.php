<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$message = "";
$error = "";

$editProduct = null;

// HANDLE FORM SUBMISSIONS

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $action = $_POST['action'] ?? '';

    try {

        // ADD PRODUCT
        if ($action === "add") {

            $code = trim($_POST['product_code'] ?? '');
            $name = trim($_POST['product_name'] ?? '');
            $category = $_POST['category_id'] ?: null;
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $reorder = filter_var($_POST['reorder_level'] ?? null, FILTER_VALIDATE_INT);
            $cost = $_POST['cost_price'] ?? '';
            $selling = $_POST['selling_price'] ?? '';

            if (
                $code === '' || $name === '' ||
                $quantity === false || $quantity < 0 ||
                $reorder === false || $reorder < 0 ||
                !is_numeric($cost) || !is_numeric($selling) ||
                $cost < 0 || $selling < 0
            ) {
                throw new Exception("Please enter valid product information.");
            }

            if ($category !== null && !ctype_digit((string)$category)) {
                throw new Exception("Invalid product category.");
            }

            $sql = "INSERT INTO products
                    (product_code, product_name, category_id, quantity,
                     reorder_level, cost_price, selling_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $code, $name, $category, $quantity,
                $reorder, $cost, $selling
            ]);

            $message = "Product added successfully!";
        }


        // UPDATE PRODUCT
        elseif ($action === "update") {

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $code = trim($_POST['product_code'] ?? '');
            $name = trim($_POST['product_name'] ?? '');
            $category = $_POST['category_id'] ?: null;
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $reorder = filter_var($_POST['reorder_level'] ?? null, FILTER_VALIDATE_INT);
            $cost = $_POST['cost_price'] ?? '';
            $selling = $_POST['selling_price'] ?? '';

            if (
                !$id || $code === '' || $name === '' ||
                $quantity === false || $quantity < 0 ||
                $reorder === false || $reorder < 0 ||
                !is_numeric($cost) || !is_numeric($selling) ||
                $cost < 0 || $selling < 0
            ) {
                throw new Exception("Please enter valid product information.");
            }

            if ($category !== null && !ctype_digit((string)$category)) {
                throw new Exception("Invalid product category.");
            }

            $sql = "UPDATE products SET
                    product_code = ?,
                    product_name = ?,
                    category_id = ?,
                    quantity = ?,
                    reorder_level = ?,
                    cost_price = ?,
                    selling_price = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $code, $name, $category, $quantity,
                $reorder, $cost, $selling, $id
            ]);

            $message = "Product updated successfully!";
        }


        // DELETE PRODUCT
        elseif ($action === "delete") {

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                throw new Exception("Invalid product selected.");
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);

            $message = "Product deleted successfully!";
        }

        header("Location: products.php?message=" . urlencode($message));
        exit();

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {
            $error = "This product code already exists or the category is invalid.";
        } else {
            $error = "A database error occurred. Please try again.";
        }

    } catch (Exception $e) {

        $error = $e->getMessage();

    }

}


// DISPLAY SUCCESS MESSAGE

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}


// GET PRODUCT FOR EDITING

if (isset($_GET['edit'])) {

    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);

    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);

}


// GET CATEGORIES

$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);


// SEARCH PRODUCTS

$search = trim($_GET['search'] ?? '');

$sql = "SELECT products.*, categories.category_name
        FROM products
        LEFT JOIN categories
        ON products.category_id = categories.id";

if ($search !== '') {

    $sql .= " WHERE products.product_name LIKE :search
              OR products.product_code LIKE :search";

}

$sql .= " ORDER BY products.id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $stmt->execute([':search' => "%$search%"]);
} else {
    $stmt->execute();
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Products | Kikuubo General Traders</title>

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
                <h2>Product Management</h2>
                <p>Manage your business inventory and products.</p>
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


        <!-- ADD / EDIT PRODUCT FORM -->

        <section class="content-card product-form-card">

            <div class="section-header">

                <h3>
                    <?php echo $editProduct ? "Edit Product" : "Add New Product"; ?>
                </h3>

                <span>Enter product information</span>

            </div>


            <form method="POST" class="product-form">

                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars($csrfToken); ?>">

                <input type="hidden" name="action"
                       value="<?php echo $editProduct ? 'update' : 'add'; ?>">

                <?php if ($editProduct): ?>

                    <input type="hidden" name="id"
                           value="<?php echo $editProduct['id']; ?>">

                <?php endif; ?>


                <div class="product-fields">

                    <div class="form-group">

                        <label>Product Code</label>

                        <input type="text" name="product_code"
                               placeholder="e.g. KGT001"
                               value="<?php echo htmlspecialchars($editProduct['product_code'] ?? ''); ?>"
                               required>

                    </div>


                    <div class="form-group">

                        <label>Product Name</label>

                        <input type="text" name="product_name"
                               placeholder="Enter product name"
                               value="<?php echo htmlspecialchars($editProduct['product_name'] ?? ''); ?>"
                               required>

                    </div>


                    <div class="form-group">

                        <label>Category</label>

                        <select name="category_id">

                            <option value="">Uncategorised</option>

                            <?php foreach ($categories as $category): ?>

                                <option value="<?php echo $category['id']; ?>"
                                    <?php echo (($editProduct['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($category['category_name']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Quantity</label>

                        <input type="number" name="quantity"
                               min="0" placeholder="Available quantity"
                               value="<?php echo htmlspecialchars((string)($editProduct['quantity'] ?? '0')); ?>"
                               required>

                    </div>


                    <div class="form-group">

                        <label>Cost Price (UGX)</label>

                        <input type="number" name="cost_price"
                               min="0" step="0.01"
                               placeholder="Buying price"
                               value="<?php echo htmlspecialchars((string)($editProduct['cost_price'] ?? '0')); ?>"
                               required>

                    </div>


                    <div class="form-group">

                        <label>Selling Price (UGX)</label>

                        <input type="number" name="selling_price"
                               min="0" step="0.01"
                               placeholder="Selling price"
                               value="<?php echo htmlspecialchars((string)($editProduct['selling_price'] ?? '0')); ?>"
                               required>

                    </div>


                    <div class="form-group">

                        <label>Reorder Level</label>

                        <input type="number" name="reorder_level"
                               min="0"
                               value="<?php echo htmlspecialchars((string)($editProduct['reorder_level'] ?? '5')); ?>"
                               required>

                    </div>

                </div>


                <div class="form-buttons">

                    <button type="submit" class="primary-button">

                        <?php echo $editProduct ? "Update Product" : "Save Product"; ?>

                    </button>

                    <?php if ($editProduct): ?>

                        <a href="products.php" class="cancel-button">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- PRODUCT LIST -->

        <section class="content-card products-list-card">

            <div class="section-header">

                <h3>Registered Products</h3>

                <span>
                    <?php echo count($products); ?> products displayed
                </span>

            </div>


            <form method="GET" class="search-form">

                <input type="text" name="search"
                       placeholder="Search by product name or code..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <button type="submit">Search</button>

                <a href="products.php">Clear</a>

            </form>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($products) > 0): ?>

                            <?php foreach ($products as $product): ?>

                                <tr>

                                    <td><?php echo htmlspecialchars($product['product_code'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>

                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorised'); ?></td>

                                    <td><?php echo number_format($product['quantity']); ?></td>

                                    <td>UGX <?php echo number_format($product['cost_price']); ?></td>

                                    <td>UGX <?php echo number_format($product['selling_price']); ?></td>

                                    <td>

                                        <?php if ($product['quantity'] <= $product['reorder_level']): ?>

                                            <span class="stock-low">Low Stock</span>

                                        <?php else: ?>

                                            <span class="stock-good">In Stock</span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="action-buttons">

                                            <a href="products.php?edit=<?php echo $product['id']; ?>"
                                               class="edit-button">
                                                Edit
                                            </a>


                                            <form method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this product?');">

                                                <input type="hidden" name="csrf_token"
                                                       value="<?php echo htmlspecialchars($csrfToken); ?>">

                                                <input type="hidden" name="action" value="delete">

                                                <input type="hidden" name="id"
                                                       value="<?php echo $product['id']; ?>">

                                                <button type="submit" class="delete-button">
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8" class="empty-message">
                                    No products found. Add your first product above.
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