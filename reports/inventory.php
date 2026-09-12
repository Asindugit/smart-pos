<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Inventory Report";


// ==========================================================================
// FILTERS
// ==========================================================================

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$stockFilter = isset($_GET['stock'])
    ? trim($_GET['stock'])
    : 'all';


// ==========================================================================
// VALID STOCK FILTER
// ==========================================================================

$allowedStockFilters = [
    'all',
    'low',
    'out',
    'normal'
];

if (!in_array($stockFilter, $allowedStockFilters, true)) {
    $stockFilter = 'all';
}


// ==========================================================================
// INVENTORY SUMMARY
// ==========================================================================

$summaryStmt = $conn->prepare("
    SELECT

        COUNT(*) AS total_products,

        COALESCE(
            SUM(stock_quantity),
            0
        ) AS total_stock,

        COUNT(
            CASE
                WHEN stock_quantity <= reorder_level
                     AND stock_quantity > 0
                THEN 1
            END
        ) AS low_stock_products,

        COUNT(
            CASE
                WHEN stock_quantity <= 0
                THEN 1
            END
        ) AS out_of_stock_products,

        COALESCE(
            SUM(
                stock_quantity * purchase_price
            ),
            0
        ) AS inventory_cost_value,

        COALESCE(
            SUM(
                stock_quantity * selling_price
            ),
            0
        ) AS inventory_selling_value

    FROM products

    WHERE status = 'active'
");

if (!$summaryStmt) {
    die("Failed to prepare inventory summary query.");
}

$summaryStmt->execute();

$summaryResult = $summaryStmt->get_result();

$summary = $summaryResult->fetch_assoc();

$summaryStmt->close();


// ==========================================================================
// CALCULATE ESTIMATED INVENTORY PROFIT
// ==========================================================================

$inventoryCostValue = (float) $summary['inventory_cost_value'];

$inventorySellingValue = (float) $summary['inventory_selling_value'];

$estimatedProfit =
    $inventorySellingValue -
    $inventoryCostValue;


// ==========================================================================
// CATEGORY SUMMARY
// ==========================================================================

$categoryStmt = $conn->prepare("
    SELECT

        COALESCE(
            c.name,
            'Uncategorized'
        ) AS category_name,

        COUNT(p.id) AS product_count,

        COALESCE(
            SUM(p.stock_quantity),
            0
        ) AS total_stock,

        COALESCE(
            SUM(
                p.stock_quantity * p.purchase_price
            ),
            0
        ) AS cost_value,

        COALESCE(
            SUM(
                p.stock_quantity * p.selling_price
            ),
            0
        ) AS selling_value

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE p.status = 'active'

    GROUP BY
        p.category_id,
        c.name

    ORDER BY
        selling_value DESC
");

if (!$categoryStmt) {
    die("Failed to prepare category summary query.");
}

$categoryStmt->execute();

$categoryResult = $categoryStmt->get_result();

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
}

$categoryStmt->close();


// ==========================================================================
// BUILD PRODUCT QUERY
// ==========================================================================

$productSql = "
    SELECT

        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.stock_quantity,
        p.reorder_level,
        p.unit,
        p.purchase_price,
        p.selling_price,

        COALESCE(
            c.name,
            'Uncategorized'
        ) AS category_name,

        (
            p.stock_quantity * p.purchase_price
        ) AS stock_cost_value,

        (
            p.stock_quantity * p.selling_price
        ) AS stock_selling_value

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE p.status = 'active'
";


// ==========================================================================
// SEARCH CONDITION
// ==========================================================================

$params = [];
$types = "";

if ($search !== '') {

    $productSql .= "
        AND (
            p.name LIKE ?
            OR p.sku LIKE ?
            OR p.barcode LIKE ?
            OR c.name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}


// ==========================================================================
// STOCK FILTER
// ==========================================================================

if ($stockFilter === 'low') {

    $productSql .= "
        AND p.stock_quantity <= p.reorder_level
        AND p.stock_quantity > 0
    ";

} elseif ($stockFilter === 'out') {

    $productSql .= "
        AND p.stock_quantity <= 0
    ";

} elseif ($stockFilter === 'normal') {

    $productSql .= "
        AND p.stock_quantity > p.reorder_level
    ";
}


// ==========================================================================
// ORDER
// ==========================================================================

$productSql .= "
    ORDER BY
        p.stock_quantity ASC,
        p.name ASC
";


// ==========================================================================
// PREPARE PRODUCT QUERY
// ==========================================================================

$productStmt = $conn->prepare($productSql);

if (!$productStmt) {
    die("Failed to prepare inventory products query.");
}


// ==========================================================================
// BIND DYNAMIC PARAMETERS
// ==========================================================================

if (!empty($params)) {

    $bindParams = [];

    $bindParams[] = $types;

    foreach ($params as $key => $value) {

        $bindParams[] = &$params[$key];
    }

    call_user_func_array(
        [$productStmt, 'bind_param'],
        $bindParams
    );
}


// ==========================================================================
// EXECUTE PRODUCT QUERY
// ==========================================================================

$productStmt->execute();

$productResult = $productStmt->get_result();

$products = [];

while ($row = $productResult->fetch_assoc()) {

    $products[] = $row;
}

$productStmt->close();


// ==========================================================================
// HELPER FUNCTION - STOCK STATUS
// ==========================================================================

function getStockStatus($stock, $reorderLevel)
{
    $stock = (float) $stock;
    $reorderLevel = (float) $reorderLevel;

    if ($stock <= 0) {

        return [
            'label' => 'Out of Stock',
            'class' => 'text-bg-danger',
            'icon' => 'bi-x-circle'
        ];
    }

    if ($stock <= $reorderLevel) {

        return [
            'label' => 'Low Stock',
            'class' => 'text-bg-warning',
            'icon' => 'bi-exclamation-triangle'
        ];
    }

    return [
        'label' => 'Normal',
        'class' => 'text-bg-success',
        'icon' => 'bi-check-circle'
    ];
}

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>


<style>

/* ================================================================
   INVENTORY REPORT
================================================================ */

.report-stat-card {
    border: 0;
    border-radius: 14px;

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.report-stat-card:hover {
    transform: translateY(-2px);
}

.report-stat-icon {
    width: 48px;
    height: 48px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    font-size: 1.35rem;
}

.report-table th {
    white-space: nowrap;
    font-size: 0.82rem;
}

.report-table td {
    vertical-align: middle;
    font-size: 0.88rem;
}

.print-title {
    display: none;
}


/* ================================================================
   PRINT
================================================================ */

@media print {

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    body {
        background: #fff !important;
    }

    .navbar,
    .sidebar,
    .no-print {
        display: none !important;
    }

    .main-wrapper {
        margin-left: 0 !important;
    }

    .main-content {
        width: 100% !important;
    }

    .container-fluid {
        padding: 0 !important;
    }

    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    .print-title {
        display: block;
        margin-bottom: 20px;
    }

    .report-stat-card:hover {
        transform: none;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .report-table {
        width: 100% !important;
    }

    .report-table th,
    .report-table td {
        font-size: 9px !important;
    }

}

</style>


<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4">


            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div
                class="
                    d-flex
                    flex-column
                    flex-md-row
                    justify-content-between
                    align-items-md-center
                    gap-3
                    mb-4
                    no-print
                "
            >

                <div>

                    <h2 class="fw-bold mb-1">

                        <i class="bi bi-boxes me-2"></i>

                        Inventory Report

                    </h2>

                    <p class="text-muted mb-0">

                        Monitor current stock levels and inventory value.

                    </p>

                </div>


                <div class="d-flex gap-2">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Reports

                    </a>


                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >

                        <i class="bi bi-printer me-1"></i>

                        Print Report

                    </button>

                </div>

            </div>


            <!-- =========================================================
                 PRINT TITLE
            ========================================================== -->

            <div class="print-title">

                <h2 class="fw-bold">
                    SmartPOS - Inventory Report
                </h2>

                <p class="mb-0">

                    Generated:
                    <?= date('Y-m-d H:i:s') ?>

                </p>

            </div>


            <!-- =========================================================
                 SUMMARY CARDS
            ========================================================== -->

            <div class="row g-4 mb-4">


                <!-- Total Products -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card report-stat-card shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-primary-subtle
                                        text-primary
                                        me-3
                                    "
                                >

                                    <i class="bi bi-box-seam"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Active Products
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        <?= number_format(
                                            (int)$summary['total_products']
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Total Stock -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card report-stat-card shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-success-subtle
                                        text-success
                                        me-3
                                    "
                                >

                                    <i class="bi bi-boxes"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Total Stock
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        <?= number_format(
                                            (float)$summary['total_stock'],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Low Stock -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card report-stat-card shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-warning-subtle
                                        text-warning
                                        me-3
                                    "
                                >

                                    <i class="bi bi-exclamation-triangle"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Low Stock
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        <?= number_format(
                                            (int)$summary['low_stock_products']
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Out of Stock -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card report-stat-card shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-danger-subtle
                                        text-danger
                                        me-3
                                    "
                                >

                                    <i class="bi bi-x-circle"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Out of Stock
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        <?= number_format(
                                            (int)$summary['out_of_stock_products']
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =========================================================
                 INVENTORY VALUE
            ========================================================== -->

            <div class="row g-4 mb-4">


                <!-- Cost Value -->

                <div class="col-12 col-md-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-2">

                                <i
                                    class="
                                        bi
                                        bi-cash-stack
                                        text-primary
                                        fs-4
                                        me-2
                                    "
                                ></i>

                                <h6 class="fw-bold mb-0">
                                    Inventory Cost Value
                                </h6>

                            </div>

                            <div class="fs-4 fw-bold">

                                LKR
                                <?= number_format(
                                    $inventoryCostValue,
                                    2
                                ) ?>

                            </div>

                            <small class="text-muted">

                                Based on current stock × purchase price.

                            </small>

                        </div>

                    </div>

                </div>


                <!-- Selling Value -->

                <div class="col-12 col-md-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-2">

                                <i
                                    class="
                                        bi
                                        bi-cart-check
                                        text-success
                                        fs-4
                                        me-2
                                    "
                                ></i>

                                <h6 class="fw-bold mb-0">
                                    Potential Selling Value
                                </h6>

                            </div>

                            <div class="fs-4 fw-bold">

                                LKR
                                <?= number_format(
                                    $inventorySellingValue,
                                    2
                                ) ?>

                            </div>

                            <small class="text-muted">

                                Based on current stock × selling price.

                            </small>

                        </div>

                    </div>

                </div>


                <!-- Estimated Profit -->

                <div class="col-12 col-md-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-2">

                                <i
                                    class="
                                        bi
                                        bi-graph-up-arrow
                                        text-warning
                                        fs-4
                                        me-2
                                    "
                                ></i>

                                <h6 class="fw-bold mb-0">
                                    Estimated Gross Profit
                                </h6>

                            </div>

                            <div class="fs-4 fw-bold">

                                LKR
                                <?= number_format(
                                    $estimatedProfit,
                                    2
                                ) ?>

                            </div>

                            <small class="text-muted">

                                Potential selling value minus cost value.

                            </small>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =========================================================
                 FILTERS
            ========================================================== -->

            <div class="card border-0 shadow-sm mb-4 no-print">

                <div class="card-body p-4">

                    <form
                        method="GET"
                        action="inventory.php"
                    >

                        <div class="row g-3 align-items-end">


                            <!-- Search -->

                            <div class="col-12 col-md-5">

                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >
                                    Search Product
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="bi bi-search"></i>

                                    </span>

                                    <input
                                        type="text"
                                        name="search"
                                        id="search"
                                        class="form-control"
                                        placeholder="Name, SKU, barcode or category"
                                        value="<?= htmlspecialchars($search) ?>"
                                    >

                                </div>

                            </div>


                            <!-- Stock Filter -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="stock"
                                    class="form-label fw-semibold"
                                >
                                    Stock Status
                                </label>

                                <select
                                    name="stock"
                                    id="stock"
                                    class="form-select"
                                >

                                    <option
                                        value="all"
                                        <?= $stockFilter === 'all' ? 'selected' : '' ?>
                                    >
                                        All Products
                                    </option>

                                    <option
                                        value="normal"
                                        <?= $stockFilter === 'normal' ? 'selected' : '' ?>
                                    >
                                        Normal Stock
                                    </option>

                                    <option
                                        value="low"
                                        <?= $stockFilter === 'low' ? 'selected' : '' ?>
                                    >
                                        Low Stock
                                    </option>

                                    <option
                                        value="out"
                                        <?= $stockFilter === 'out' ? 'selected' : '' ?>
                                    >
                                        Out of Stock
                                    </option>

                                </select>

                            </div>


                            <!-- Buttons -->

                            <div class="col-12 col-md-3">

                                <div class="d-flex gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-filter me-1"></i>

                                        Filter

                                    </button>


                                    <a
                                        href="inventory.php"
                                        class="btn btn-outline-secondary"
                                    >

                                        <i class="bi bi-arrow-clockwise me-1"></i>

                                        Reset

                                    </a>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =========================================================
                 CATEGORY SUMMARY
            ========================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white border-0 p-4">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-tags me-2"></i>

                        Category Inventory Summary

                    </h5>

                    <p class="text-muted small mb-0">

                        Current stock and inventory value by category.

                    </p>

                </div>


                <div class="card-body p-0">

                    <?php if (empty($categories)): ?>

                        <div class="text-center text-muted py-4">

                            No category inventory data available.

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            Category
                                        </th>

                                        <th class="text-center">
                                            Products
                                        </th>

                                        <th class="text-end">
                                            Stock
                                        </th>

                                        <th class="text-end">
                                            Cost Value
                                        </th>

                                        <th class="text-end pe-4">
                                            Selling Value
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($categories as $category): ?>

                                        <tr>

                                            <td class="ps-4">

                                                <i class="bi bi-tag me-2"></i>

                                                <?= htmlspecialchars(
                                                    $category['category_name']
                                                ) ?>

                                            </td>


                                            <td class="text-center">

                                                <?= number_format(
                                                    (int)$category['product_count']
                                                ) ?>

                                            </td>


                                            <td class="text-end">

                                                <?= number_format(
                                                    (float)$category['total_stock'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end">

                                                LKR
                                                <?= number_format(
                                                    (float)$category['cost_value'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end pe-4 fw-semibold">

                                                LKR
                                                <?= number_format(
                                                    (float)$category['selling_value'],
                                                    2
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =========================================================
                 PRODUCT INVENTORY
            ========================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 p-4">

                    <div
                        class="
                            d-flex
                            flex-column
                            flex-md-row
                            justify-content-between
                            align-items-md-center
                            gap-2
                        "
                    >

                        <div>

                            <h5 class="fw-bold mb-1">

                                <i class="bi bi-box-seam me-2"></i>

                                Product Inventory

                            </h5>

                            <p class="text-muted small mb-0">

                                Current stock details for active products.

                            </p>

                        </div>


                        <span class="badge text-bg-secondary">

                            <?= number_format(
                                count($products)
                            ) ?>

                            Products

                        </span>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (empty($products)): ?>

                        <div class="text-center py-5 px-3">

                            <i
                                class="
                                    bi
                                    bi-box-seam
                                    fs-1
                                    text-muted
                                "
                            ></i>

                            <h5 class="mt-3">
                                No Products Found
                            </h5>

                            <p class="text-muted mb-0">

                                No active products match the selected
                                search or stock filter.

                            </p>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table
                                class="
                                    table
                                    table-hover
                                    mb-0
                                    report-table
                                "
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            #
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            SKU
                                        </th>

                                        <th>
                                            Category
                                        </th>

                                        <th class="text-end">
                                            Stock
                                        </th>

                                        <th class="text-end">
                                            Reorder Level
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th class="text-end">
                                            Purchase Price
                                        </th>

                                        <th class="text-end">
                                            Selling Price
                                        </th>

                                        <th class="text-end">
                                            Stock Value
                                        </th>

                                        <th class="text-end pe-4 no-print">
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($products as $index => $product): ?>

                                        <?php

                                        $stockStatus = getStockStatus(
                                            $product['stock_quantity'],
                                            $product['reorder_level']
                                        );

                                        ?>

                                        <tr>

                                            <td class="ps-4">

                                                <?= $index + 1 ?>

                                            </td>


                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $product['name']
                                                    ) ?>

                                                </strong>

                                                <?php if (
                                                    !empty($product['barcode'])
                                                ): ?>

                                                    <div class="small text-muted">

                                                        Barcode:
                                                        <?= htmlspecialchars(
                                                            $product['barcode']
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $product['category_name']
                                                ) ?>

                                            </td>


                                            <td class="text-end fw-bold">

                                                <?= number_format(
                                                    (float)$product['stock_quantity'],
                                                    2
                                                ) ?>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        $product['unit']
                                                    ) ?>

                                                </small>

                                            </td>


                                            <td class="text-end">

                                                <?= number_format(
                                                    (float)$product['reorder_level'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td>

                                                <span
                                                    class="
                                                        badge
                                                        <?= htmlspecialchars(
                                                            $stockStatus['class']
                                                        ) ?>
                                                    "
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            <?= htmlspecialchars(
                                                                $stockStatus['icon']
                                                            ) ?>
                                                            me-1
                                                        "
                                                    ></i>

                                                    <?= htmlspecialchars(
                                                        $stockStatus['label']
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td class="text-end">

                                                LKR
                                                <?= number_format(
                                                    (float)$product['purchase_price'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end">

                                                LKR
                                                <?= number_format(
                                                    (float)$product['selling_price'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end fw-semibold">

                                                LKR
                                                <?= number_format(
                                                    (float)$product['stock_cost_value'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end pe-4 no-print">

                                                <a
                                                    href="../inventory/movements.php?product_id=<?= (int)$product['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="View Stock Movements"
                                                >

                                                    <i class="bi bi-clock-history"></i>

                                                </a>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =========================================================
                 REPORT INFORMATION
            ========================================================== -->

            <div class="text-muted small mt-3">

                <i class="bi bi-info-circle me-1"></i>

                Inventory values are calculated using the current stock
                quantity and the product purchase/selling prices.
                Estimated gross profit represents potential value only
                and does not include operating expenses.

            </div>

        </div>

    </main>

</div>


<?php

$conn->close();

include "../includes/footer.php";

?>