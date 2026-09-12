<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Inventory";

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$stockFilter = isset($_GET["stock"])
    ? trim($_GET["stock"])
    : "";

$allowedStockFilters = [
    "low",
    "out",
    "normal"
];

if (!in_array($stockFilter, $allowedStockFilters, true)) {
    $stockFilter = "";
}


/*
|--------------------------------------------------------------------------
| Inventory Statistics
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$totalStock = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;


/*
|--------------------------------------------------------------------------
| Total Active Products
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'active'
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $totalProducts = (int) $row["total"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Total Stock
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(stock_quantity), 0) AS total_stock
    FROM products
    WHERE status = 'active'
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $totalStock = (float) $row["total_stock"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Low Stock Products
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'active'
    AND stock_quantity <= reorder_level
    AND stock_quantity > 0
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $lowStockProducts = (int) $row["total"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Out Of Stock Products
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'active'
    AND stock_quantity <= 0
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $outOfStockProducts = (int) $row["total"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.category_id,
        p.stock_quantity,
        p.reorder_level,
        p.unit,
        p.status,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 'active'
";

$params = [];
$types = "";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            p.name LIKE ?
            OR p.sku LIKE ?
            OR p.barcode LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}


/*
|--------------------------------------------------------------------------
| Stock Filter
|--------------------------------------------------------------------------
*/

if ($stockFilter === "low") {

    $sql .= "
        AND p.stock_quantity <= p.reorder_level
        AND p.stock_quantity > 0
    ";

}

if ($stockFilter === "out") {

    $sql .= "
        AND p.stock_quantity <= 0
    ";

}

if ($stockFilter === "normal") {

    $sql .= "
        AND p.stock_quantity > p.reorder_level
    ";

}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        CASE
            WHEN p.stock_quantity <= 0 THEN 1
            WHEN p.stock_quantity <= p.reorder_level THEN 2
            ELSE 3
        END,
        p.name ASC
";


$stmt = $conn->prepare($sql);

$products = [];

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
}


include "../includes/header.php";
include "../includes/navbar.php";

?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- PAGE HEADER -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="dashboard-title-icon">

                            <i class="bi bi-boxes"></i>

                        </div>

                        <h2 class="mb-0 fw-bold">
                            Inventory
                        </h2>

                    </div>

                    <p class="text-muted mb-0">
                        Monitor stock levels and manage inventory movements.
                    </p>

                </div>

                <div>

                    <a
                        href="adjust.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-sliders me-1"></i>

                        Adjust Stock

                    </a>

                </div>

            </div>


            <!-- SUCCESS MESSAGE -->

            <?php if (isset($_GET["success"])): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($_GET["success"]) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- ERROR MESSAGE -->

            <?php if (isset($_GET["error"])): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars($_GET["error"]) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- INVENTORY STATISTICS -->

            <div class="row g-4 mb-4">

                <!-- TOTAL PRODUCTS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card stat-card-blue h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div class="stat-label mb-2">
                                        Active Products
                                    </div>

                                    <div class="stat-value fw-bold">
                                        <?= number_format($totalProducts) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-blue">

                                    <i class="bi bi-box-seam"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-blue mt-3">

                                <i class="bi bi-box"></i>

                                Products in inventory

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TOTAL STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card stat-card-green h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div class="stat-label mb-2">
                                        Total Stock
                                    </div>

                                    <div class="stat-value fw-bold">
                                        <?= number_format($totalStock, 2) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-green">

                                    <i class="bi bi-stack"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-green mt-3">

                                <i class="bi bi-boxes"></i>

                                Current stock quantity

                            </div>

                        </div>

                    </div>

                </div>


                <!-- LOW STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card stat-card-red h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div class="stat-label mb-2">
                                        Low Stock
                                    </div>

                                    <div class="stat-value fw-bold">
                                        <?= number_format($lowStockProducts) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-red">

                                    <i class="bi bi-exclamation-triangle"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-red mt-3">

                                <i class="bi bi-arrow-down-circle"></i>

                                Below reorder level

                            </div>

                        </div>

                    </div>

                </div>


                <!-- OUT OF STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card stat-card-cyan h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div class="stat-label mb-2">
                                        Out of Stock
                                    </div>

                                    <div class="stat-value fw-bold">
                                        <?= number_format($outOfStockProducts) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-cyan">

                                    <i class="bi bi-x-circle"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-cyan mt-3">

                                <i class="bi bi-exclamation-circle"></i>

                                Products requiring stock

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- FILTERS -->

            <div class="card dashboard-card mb-4">

                <div class="card-body">

                    <form
                        method="GET"
                        action="index.php"
                    >

                        <div class="row g-3 align-items-end">

                            <div class="col-12 col-md-6">

                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >
                                    Search
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="bi bi-search"></i>

                                    </span>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="search"
                                        name="search"
                                        value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Search product, SKU or barcode"
                                    >

                                </div>

                            </div>


                            <div class="col-12 col-md-3">

                                <label
                                    for="stock"
                                    class="form-label fw-semibold"
                                >
                                    Stock Status
                                </label>

                                <select
                                    class="form-select"
                                    id="stock"
                                    name="stock"
                                >

                                    <option value="">
                                        All Stock
                                    </option>

                                    <option
                                        value="normal"
                                        <?= $stockFilter === "normal" ? "selected" : "" ?>
                                    >
                                        Normal Stock
                                    </option>

                                    <option
                                        value="low"
                                        <?= $stockFilter === "low" ? "selected" : "" ?>
                                    >
                                        Low Stock
                                    </option>

                                    <option
                                        value="out"
                                        <?= $stockFilter === "out" ? "selected" : "" ?>
                                    >
                                        Out of Stock
                                    </option>

                                </select>

                            </div>


                            <div class="col-12 col-md-3 d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >

                                    <i class="bi bi-search me-1"></i>

                                    Filter

                                </button>

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                    title="Clear filters"
                                >

                                    <i class="bi bi-x-lg"></i>

                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- INVENTORY TABLE -->

            <div class="card dashboard-card">

                <div class="dashboard-section-header">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">

                            <i class="bi bi-boxes"></i>

                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Inventory Overview
                            </h5>

                            <small class="text-muted">
                                <?= count($products) ?> product(s) found
                            </small>

                        </div>

                    </div>

                    <a
                        href="movements.php"
                        class="btn btn-outline-primary dashboard-view-btn"
                    >

                        <i class="bi bi-clock-history me-1"></i>

                        Movement History

                    </a>

                </div>


                <?php if (empty($products)): ?>

                    <div class="card-body">

                        <div class="empty-state text-center py-5">

                            <div class="empty-icon mb-3">

                                <i class="bi bi-boxes"></i>

                            </div>

                            <h5 class="fw-bold">
                                No inventory found
                            </h5>

                            <p class="text-muted mb-3">
                                There are no products matching your filter.
                            </p>

                            <a
                                href="../products/add.php"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-plus-lg me-1"></i>

                                Add Product

                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table align-middle dashboard-table">

                            <thead>

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Current Stock
                                    </th>

                                    <th>
                                        Reorder Level
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th class="text-end">
                                        Actions
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($products as $product): ?>

                                    <?php

                                    $stock =
                                        (float) $product["stock_quantity"];

                                    $reorderLevel =
                                        (float) $product["reorder_level"];

                                    if ($stock <= 0) {

                                        $inventoryStatus = "out";

                                    } elseif ($stock <= $reorderLevel) {

                                        $inventoryStatus = "low";

                                    } else {

                                        $inventoryStatus = "normal";

                                    }

                                    ?>

                                    <tr>

                                        <!-- PRODUCT -->

                                        <td>

                                            <div
                                                class="d-flex align-items-center gap-2"
                                            >

                                                <div
                                                    class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:42px;height:42px;"
                                                >

                                                    <i
                                                        class="bi bi-box-seam text-primary"
                                                    ></i>

                                                </div>

                                                <div>

                                                    <div class="fw-semibold">

                                                        <?= htmlspecialchars(
                                                            $product["name"]
                                                        ) ?>

                                                    </div>

                                                    <?php if (!empty($product["barcode"])): ?>

                                                        <small class="text-muted">

                                                            Barcode:
                                                            <?= htmlspecialchars(
                                                                $product["barcode"]
                                                            ) ?>

                                                        </small>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- SKU -->

                                        <td>

                                            <span
                                                class="badge bg-light text-dark border"
                                            >

                                                <?= htmlspecialchars(
                                                    $product["sku"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- CATEGORY -->

                                        <td>

                                            <?php if (!empty($product["category_name"])): ?>

                                                <?= htmlspecialchars(
                                                    $product["category_name"]
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    Uncategorised
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- CURRENT STOCK -->

                                        <td>

                                            <?php if ($inventoryStatus === "out"): ?>

                                                <span
                                                    class="text-danger fw-bold"
                                                >

                                                    0.00
                                                    <?= htmlspecialchars(
                                                        $product["unit"]
                                                    ) ?>

                                                </span>

                                                <div>

                                                    <small class="text-danger">

                                                        <i
                                                            class="bi bi-x-circle"
                                                        ></i>

                                                        Out of stock

                                                    </small>

                                                </div>

                                            <?php elseif ($inventoryStatus === "low"): ?>

                                                <span
                                                    class="text-warning fw-bold"
                                                >

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $product["unit"]
                                                    ) ?>

                                                </span>

                                                <div>

                                                    <small class="text-warning">

                                                        <i
                                                            class="bi bi-exclamation-triangle"
                                                        ></i>

                                                        Low stock

                                                    </small>

                                                </div>

                                            <?php else: ?>

                                                <span class="fw-semibold">

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $product["unit"]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- REORDER LEVEL -->

                                        <td>

                                            <?= number_format(
                                                $reorderLevel,
                                                2
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $product["unit"]
                                            ) ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php if ($inventoryStatus === "out"): ?>

                                                <span class="badge text-bg-danger">
                                                    Out of Stock
                                                </span>

                                            <?php elseif ($inventoryStatus === "low"): ?>

                                                <span class="badge text-bg-warning">
                                                    Low Stock
                                                </span>

                                            <?php else: ?>

                                                <span class="badge text-bg-success">
                                                    Normal
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td class="text-end">

                                            <div class="btn-group">

                                                <a
                                                    href="adjust.php?product_id=<?= (int) $product["id"] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Adjust Stock"
                                                >

                                                    <i class="bi bi-sliders"></i>

                                                </a>

                                                <a
                                                    href="movements.php?product_id=<?= (int) $product["id"] ?>"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="View Movements"
                                                >

                                                    <i class="bi bi-clock-history"></i>

                                                </a>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php

$conn->close();

include "../includes/footer.php";

?>