<?php

require_once "../config/auth.php";

requireLogin();

require_once "../config/database.php";

$pageTitle = "Dashboard";


// ============================================================
// TODAY'S SALES
// ============================================================

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(total), 0) AS total_sales
     FROM sales
     WHERE DATE(sale_date) = CURDATE()
     AND status = 'completed'"
);

$stmt->execute();

$result = $stmt->get_result();

$todaySales = $result->fetch_assoc()['total_sales'];

$stmt->close();


// ============================================================
// TODAY'S ORDERS
// ============================================================

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_orders
     FROM sales
     WHERE DATE(sale_date) = CURDATE()
     AND status = 'completed'"
);

$stmt->execute();

$result = $stmt->get_result();

$todayOrders = $result->fetch_assoc()['total_orders'];

$stmt->close();


// ============================================================
// TOTAL PRODUCTS
// ============================================================

$result = $conn->query(
    "SELECT COUNT(*) AS total_products
     FROM products
     WHERE status = 'active'"
);

$totalProducts = $result->fetch_assoc()['total_products'];


// ============================================================
// LOW STOCK PRODUCTS
// ============================================================

$result = $conn->query(
    "SELECT COUNT(*) AS low_stock
     FROM products
     WHERE status = 'active'
     AND stock_quantity <= reorder_level"
);

$lowStock = $result->fetch_assoc()['low_stock'];


// ============================================================
// TOTAL CUSTOMERS
// ============================================================

$result = $conn->query(
    "SELECT COUNT(*) AS total_customers
     FROM customers
     WHERE status = 'active'"
);

$totalCustomers = $result->fetch_assoc()['total_customers'];


// ============================================================
// RECENT SALES
// ============================================================

$recentSales = $conn->query(
    "SELECT
        s.id,
        s.invoice_number,
        s.total,
        s.sale_date,
        u.full_name AS cashier
     FROM sales s
     LEFT JOIN users u
        ON s.cashier_id = u.id
     WHERE s.status = 'completed'
     ORDER BY s.sale_date DESC
     LIMIT 5"
);


// ============================================================
// LOW STOCK PRODUCTS LIST
// ============================================================

$lowStockProducts = $conn->query(
    "SELECT
        id,
        name,
        sku,
        stock_quantity,
        reorder_level,
        unit
     FROM products
     WHERE status = 'active'
     AND stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC
     LIMIT 5"
);


require_once "../includes/header.php";

require_once "../includes/navbar.php";

?>

<div class="main-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4 px-4">


            <!-- ==================================================
                 PAGE HEADER
                 ================================================== -->

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">
                            <i class="bi bi-speedometer2"></i>
                        </span>

                        <h2 class="fw-bold mb-0">
                            Dashboard
                        </h2>

                    </div>

                    <p class="text-muted mb-0 ms-1">

                        Welcome back,
                        <strong>
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </strong>.

                        Here's what's happening today.

                    </p>

                </div>


                <div class="dashboard-date mt-3 mt-md-0">

                    <i class="bi bi-calendar3"></i>

                    <?= date("d M Y") ?>

                </div>

            </div>


            <!-- ==================================================
                 STATISTICS
                 ================================================== -->

            <div class="row g-4 mb-4">


                <!-- TODAY SALES -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card dashboard-card stat-card stat-card-blue h-100">

                        <div class="card-body">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-start">

                                <div>

                                    <p class="stat-label mb-2">
                                        Today's Sales
                                    </p>

                                    <h3 class="stat-value mb-0">
                                        Rs.
                                        <?= number_format($todaySales, 2) ?>
                                    </h3>

                                </div>

                                <div class="stat-icon stat-icon-blue">

                                    <i class="bi bi-cash-stack"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-blue mt-3">

                                <i class="bi bi-arrow-up-circle-fill"></i>

                                Today's completed sales

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TODAY ORDERS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card dashboard-card stat-card stat-card-green h-100">

                        <div class="card-body">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-start">

                                <div>

                                    <p class="stat-label mb-2">
                                        Today's Orders
                                    </p>

                                    <h3 class="stat-value mb-0">
                                        <?= number_format($todayOrders) ?>
                                    </h3>

                                </div>

                                <div class="stat-icon stat-icon-green">

                                    <i class="bi bi-receipt"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-green mt-3">

                                <i class="bi bi-cart-check-fill"></i>

                                Completed transactions

                            </div>

                        </div>

                    </div>

                </div>


                <!-- PRODUCTS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card dashboard-card stat-card stat-card-cyan h-100">

                        <div class="card-body">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-start">

                                <div>

                                    <p class="stat-label mb-2">
                                        Products
                                    </p>

                                    <h3 class="stat-value mb-0">
                                        <?= number_format($totalProducts) ?>
                                    </h3>

                                </div>

                                <div class="stat-icon stat-icon-cyan">

                                    <i class="bi bi-box-seam"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-cyan mt-3">

                                <i class="bi bi-box-fill"></i>

                                Active products

                            </div>

                        </div>

                    </div>

                </div>


                <!-- LOW STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card dashboard-card stat-card stat-card-red h-100">

                        <div class="card-body">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-start">

                                <div>

                                    <p class="stat-label mb-2">
                                        Low Stock
                                    </p>

                                    <h3 class="stat-value mb-0">
                                        <?= number_format($lowStock) ?>
                                    </h3>

                                </div>

                                <div class="stat-icon stat-icon-red">

                                    <i class="bi bi-exclamation-triangle-fill"></i>

                                </div>

                            </div>

                            <div class="stat-footer stat-footer-red mt-3">

                                <i class="bi bi-arrow-down-circle-fill"></i>

                                Need attention

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 MAIN DASHBOARD ROW
                 ================================================== -->

            <div class="row g-4">


                <!-- ==================================================
                     RECENT SALES
                     ================================================== -->

                <div class="col-12 col-xl-8">

                    <div class="card dashboard-card h-100">

                        <div class="card-header dashboard-section-header">

                            <div>

                                <div class="d-flex align-items-center gap-2">

                                    <div class="section-icon section-icon-blue">

                                        <i class="bi bi-receipt"></i>

                                    </div>

                                    <div>

                                        <h5 class="fw-bold mb-0">
                                            Recent Sales
                                        </h5>

                                        <p class="text-muted small mb-0">
                                            Latest completed transactions
                                        </p>

                                    </div>

                                </div>

                            </div>


                            <!-- <a
                                href="../billing/index.php"
                                class="btn btn-primary btn-sm dashboard-view-btn"
                            >

                                View All

                                <i class="bi bi-arrow-right ms-1"></i>

                            </a> -->

                        </div>


                        <div class="card-body p-0">

                            <div class="table-responsive">

                                <table class="table dashboard-table align-middle">

                                    <thead>

                                        <tr>

                                            <th>
                                                Invoice
                                            </th>

                                            <th>
                                                Cashier
                                            </th>

                                            <th>
                                                Date
                                            </th>

                                            <th class="text-end">
                                                Total
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php if ($recentSales->num_rows > 0): ?>

                                        <?php while ($sale = $recentSales->fetch_assoc()): ?>

                                            <tr>

                                                <td>

                                                    <span class="invoice-badge">

                                                        <i class="bi bi-receipt me-1"></i>

                                                        <?= htmlspecialchars(
                                                            $sale['invoice_number']
                                                        ) ?>

                                                    </span>

                                                </td>


                                                <td>

                                                    <div class="cashier-info">

                                                        <span class="cashier-avatar">

                                                            <i class="bi bi-person"></i>

                                                        </span>

                                                        <span>

                                                            <?= htmlspecialchars(
                                                                $sale['cashier'] ?? 'Unknown'
                                                            ) ?>

                                                        </span>

                                                    </div>

                                                </td>


                                                <td>

                                                    <span class="sale-date">

                                                        <i class="bi bi-clock me-1"></i>

                                                        <?= date(
                                                            "d M Y, h:i A",
                                                            strtotime($sale['sale_date'])
                                                        ) ?>

                                                    </span>

                                                </td>


                                                <td class="text-end">

                                                    <span class="sale-total">

                                                        Rs.
                                                        <?= number_format(
                                                            $sale['total'],
                                                            2
                                                        ) ?>

                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endwhile; ?>

                                    <?php else: ?>

                                        <tr>

                                            <td
                                                colspan="4"
                                                class="text-center py-5"
                                            >

                                                <div class="empty-state">

                                                    <div class="empty-icon">

                                                        <i class="bi bi-receipt"></i>

                                                    </div>

                                                    <h6 class="fw-bold mt-3">
                                                        No Sales Yet
                                                    </h6>

                                                    <p class="text-muted small mb-0">
                                                        No completed sales have been recorded.
                                                    </p>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     LOW STOCK
                     ================================================== -->

                <div class="col-12 col-xl-4">

                    <div class="card dashboard-card h-100">

                        <div class="card-header dashboard-section-header">

                            <div>

                                <div class="d-flex align-items-center gap-2">

                                    <div class="section-icon section-icon-red">

                                        <i class="bi bi-exclamation-triangle"></i>

                                    </div>

                                    <div>

                                        <h5 class="fw-bold mb-0">
                                            Low Stock
                                        </h5>

                                        <p class="text-muted small mb-0">
                                            Products needing restocking
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="card-body px-4">

                        <?php if ($lowStockProducts->num_rows > 0): ?>

                            <?php while (
                                $product = $lowStockProducts->fetch_assoc()
                            ): ?>

                                <div class="stock-item">

                                    <div class="stock-product">

                                        <div class="stock-product-icon">

                                            <i class="bi bi-box-seam"></i>

                                        </div>

                                        <div>

                                            <div class="fw-semibold stock-name">

                                                <?= htmlspecialchars(
                                                    $product['name']
                                                ) ?>

                                            </div>

                                            <small class="text-muted">

                                                SKU:
                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                ) ?>

                                            </small>

                                        </div>

                                    </div>


                                    <div class="text-end">

                                        <div class="stock-danger">

                                            <?= number_format(
                                                $product['stock_quantity'],
                                                2
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $product['unit']
                                            ) ?>

                                        </div>

                                        <small class="stock-min">

                                            Min:
                                            <?= number_format(
                                                $product['reorder_level'],
                                                2
                                            ) ?>

                                        </small>

                                    </div>

                                </div>

                            <?php endwhile; ?>


                            <div class="mt-3">

                                <a
                                    href="../inventory/index.php"
                                    class="btn btn-outline-danger btn-sm w-100"
                                >

                                    <i class="bi bi-boxes me-1"></i>

                                    View Inventory

                                    <i class="bi bi-arrow-right ms-1"></i>

                                </a>

                            </div>

                        <?php else: ?>

                            <div class="empty-stock">

                                <div class="empty-stock-icon">

                                    <i class="bi bi-check-lg"></i>

                                </div>

                                <h6 class="fw-bold mt-3">
                                    Stock Looks Good
                                </h6>

                                <p class="text-muted small mb-0">
                                    All products have sufficient stock.
                                </p>

                            </div>

                        <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 QUICK ACTIONS
                 ================================================== -->

            <div class="mt-4">

                <div class="mb-3">

                    <h5 class="fw-bold mb-1">
                        Quick Actions
                    </h5>

                    <p class="text-muted small mb-0">
                        Frequently used POS management functions
                    </p>

                </div>


                <div class="row g-3">


                    <div class="col-6 col-md-3">

                        <a
                            href="../sales/index.php"
                            class="quick-action quick-action-blue"
                        >

                            <span class="quick-action-icon">

                                <i class="bi bi-cart-plus"></i>

                            </span>

                            <span>

                                <strong>New Sale</strong>

                                <small>
                                    Create transaction
                                </small>

                            </span>

                        </a>

                    </div>


                    <div class="col-6 col-md-3">

                        <a
                            href="../products/index.php"
                            class="quick-action quick-action-green"
                        >

                            <span class="quick-action-icon">

                                <i class="bi bi-box-seam"></i>

                            </span>

                            <span>

                                <strong>Products</strong>

                                <small>
                                    Manage products
                                </small>

                            </span>

                        </a>

                    </div>


                    <div class="col-6 col-md-3">

                        <a
                            href="../customers/index.php"
                            class="quick-action quick-action-cyan"
                        >

                            <span class="quick-action-icon">

                                <i class="bi bi-people"></i>

                            </span>

                            <span>

                                <strong>Customers</strong>

                                <small>
                                    Manage customers
                                </small>

                            </span>

                        </a>

                    </div>


                    <div class="col-6 col-md-3">

                        <a
                            href="../reports/index.php"
                            class="quick-action quick-action-purple"
                        >

                            <span class="quick-action-icon">

                                <i class="bi bi-bar-chart-line"></i>

                            </span>

                            <span>

                                <strong>Reports</strong>

                                <small>
                                    View analytics
                                </small>

                            </span>

                        </a>

                    </div>

                </div>

            </div>


        </div>

    </main>

</div>


<?php

$conn->close();

require_once "../includes/footer.php";

?>