<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = "";
$error = "";
$editCategory = null;


// HANDLE FORM SUBMISSIONS

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $action = $_POST['action'] ?? '';

    try {

        // ADD CATEGORY

        if ($action === "add") {

            $categoryName = trim($_POST['category_name'] ?? '');

            if ($categoryName === '') {
                throw new Exception("Please enter a category name.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO categories (category_name) VALUES (?)"
            );

            $stmt->execute([$categoryName]);

            $message = "Category added successfully!";

        }


        // UPDATE CATEGORY

        elseif ($action === "update") {

            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $categoryName = trim($_POST['category_name'] ?? '');

            if (!$id || $categoryName === '') {
                throw new Exception("Please provide valid category information.");
            }

            $stmt = $conn->prepare(
                "UPDATE categories SET category_name = ? WHERE id = ?"
            );

            $stmt->execute([$categoryName, $id]);

            $message = "Category updated successfully!";

        }


        // DELETE CATEGORY

        elseif ($action === "delete") {

            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                throw new Exception("Invalid category selected.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM categories WHERE id = ?"
            );

            $stmt->execute([$id]);

            $message = "Category deleted successfully!";

        }

        else {
            throw new Exception("Invalid action.");
        }

        header("Location: category.php?message=" . urlencode($message));
        exit();

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {
            $error = "This category already exists.";
        } else {
            $error = "A database error occurred. Please try again.";
        }

    } catch (Exception $e) {

        $error = $e->getMessage();

    }

}


// SUCCESS MESSAGE

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}


// GET CATEGORY FOR EDITING

if (isset($_GET['edit'])) {

    $stmt = $conn->prepare(
        "SELECT * FROM categories WHERE id = ?"
    );

    $stmt->execute([$_GET['edit']]);

    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);

}


// SEARCH CATEGORIES

$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM categories";

if ($search !== '') {
    $sql .= " WHERE category_name LIKE :search";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $stmt->execute([
        ':search' => "%$search%"
    ]);
} else {
    $stmt->execute();
}

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


// COUNT PRODUCTS IN EACH CATEGORY

$productCount = [];

$countQuery = $conn->query("
    SELECT category_id, COUNT(*) AS total
    FROM products
    GROUP BY category_id
");

foreach ($countQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $productCount[$row['category_id']] = $row['total'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Management | Kikuubo General Traders</title>

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

                <h2>Category Management</h2>

                <p>Organise and manage your inventory categories.</p>

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



        <!-- SUCCESS AND ERROR MESSAGES -->

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



        <!-- CATEGORY FORM -->

        <section class="content-card category-form-card">

            <div class="section-header">

                <h3>

                    <?php
                    echo $editCategory
                        ? "Edit Category"
                        : "Add New Category";
                    ?>

                </h3>

                <span>Category information</span>

            </div>


            <form method="POST" class="category-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($csrfToken); ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="<?php echo $editCategory ? 'update' : 'add'; ?>"
                >


                <?php if ($editCategory): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo $editCategory['id']; ?>"
                    >

                <?php endif; ?>


                <div class="form-group">

                    <label>Category Name</label>

                    <input
                        type="text"
                        name="category_name"
                        placeholder="Enter category name"
                        value="<?php echo htmlspecialchars($editCategory['category_name'] ?? ''); ?>"
                        required
                    >

                </div>


                <div class="form-buttons">

                    <button type="submit" class="primary-button">

                        <?php
                        echo $editCategory
                            ? "Update Category"
                            : "Save Category";
                        ?>

                    </button>


                    <?php if ($editCategory): ?>

                        <a href="category.php" class="cancel-button">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>



        <!-- CATEGORY LIST -->

        <section class="content-card category-list-card">


            <div class="section-header">

                <h3>Registered Categories</h3>

                <span>
                    <?php echo count($categories); ?> categories displayed
                </span>

            </div>



            <!-- SEARCH -->

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search categories..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

                <button type="submit">
                    Search
                </button>

                <a href="category.php">
                    Clear
                </a>

            </form>



            <!-- TABLE -->

            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Category ID</th>

                            <th>Category Name</th>

                            <th>Number of Products</th>

                            <th>Date Created</th>

                            <th>Actions</th>

                        </tr>

                    </thead>



                    <tbody>

                        <?php if (count($categories) > 0): ?>


                            <?php foreach ($categories as $category): ?>


                                <tr>


                                    <td>
                                        #<?php echo $category['id']; ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $category['category_name']
                                        );
                                        ?>
                                    </td>


                                    <td>

                                        <?php
                                        echo $productCount[$category['id']] ?? 0;
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime($category['created_at'])
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <div class="action-buttons">


                                            <a
                                                href="category.php?edit=<?php echo $category['id']; ?>"
                                                class="edit-button"
                                            >
                                                Edit
                                            </a>



                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Delete this category? Products assigned to it will become uncategorised.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo htmlspecialchars($csrfToken); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $category['id']; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="delete-button"
                                                >
                                                    Delete
                                                </button>

                                            </form>


                                        </div>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="5" class="empty-message">

                                    No categories found. Add your first category.

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