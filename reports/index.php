<?php

/*
|--------------------------------------------------------------------------
| SmartPOS - Reports Dashboard
|--------------------------------------------------------------------------
| File: reports/index.php
|
| Purpose:
| - Main reports dashboard
| - Provides access to all SmartPOS reports
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Reports";

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div>

                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-bar-chart-line me-2"></i>
                        Reports
                    </h2>

                    <p class="text-muted mb-0">
                        Analyze sales, purchases, inventory, profit and expenses.
                    </p>

                </div>

            </div>


            <!-- =========================================================
                 REPORT CARDS
            ========================================================== -->

            <div class="row g-4">

                <!-- Sales Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-primary-subtle text-primary rounded-3 p-3 me-3">

                                    <i class="bi bi-graph-up fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Sales Report
                                    </h5>

                                    <small class="text-muted">
                                        Analyze completed sales
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                View sales by date, customer, cashier,
                                payment method and invoice.
                            </p>

                            <a
                                href="sales.php"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-bar-chart me-1"></i>
                                View Sales Report
                            </a>

                        </div>

                    </div>

                </div>


                <!-- Purchase Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-success-subtle text-success rounded-3 p-3 me-3">

                                    <i class="bi bi-cart-check fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Purchase Report
                                    </h5>

                                    <small class="text-muted">
                                        Analyze supplier purchases
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                View purchases by date, supplier,
                                invoice and purchase status.
                            </p>

                            <a
                                href="purchases.php"
                                class="btn btn-success w-100"
                            >
                                <i class="bi bi-receipt me-1"></i>
                                View Purchase Report
                            </a>

                        </div>

                    </div>

                </div>


                <!-- Inventory Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-info-subtle text-info rounded-3 p-3 me-3">

                                    <i class="bi bi-boxes fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Inventory Report
                                    </h5>

                                    <small class="text-muted">
                                        Monitor stock levels
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                View current stock, low-stock products,
                                out-of-stock products and stock value.
                            </p>

                            <a
                                href="inventory.php"
                                class="btn btn-info text-white w-100"
                            >
                                <i class="bi bi-box-seam me-1"></i>
                                View Inventory Report
                            </a>

                        </div>

                    </div>

                </div>


                <!-- Profit Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-warning-subtle text-warning rounded-3 p-3 me-3">

                                    <i class="bi bi-currency-dollar fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Profit Report
                                    </h5>

                                    <small class="text-muted">
                                        Analyze business profit
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                Compare sales revenue and product costs
                                to calculate gross profit.
                            </p>

                            <a
                                href="profit.php"
                                class="btn btn-warning w-100"
                            >
                                <i class="bi bi-calculator me-1"></i>
                                View Profit Report
                            </a>

                        </div>

                    </div>

                </div>


                <!-- Expense Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-danger-subtle text-danger rounded-3 p-3 me-3">

                                    <i class="bi bi-wallet2 fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Expense Report
                                    </h5>

                                    <small class="text-muted">
                                        Monitor business expenses
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                Analyze expenses by date, category
                                and amount.
                            </p>

                            <a
                                href="expenses.php"
                                class="btn btn-danger w-100"
                            >
                                <i class="bi bi-cash-stack me-1"></i>
                                View Expense Report
                            </a>

                        </div>

                    </div>

                </div>


                <!-- Stock Movement Report -->

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center mb-3">

                                <div class="bg-secondary-subtle text-secondary rounded-3 p-3 me-3">

                                    <i class="bi bi-arrow-left-right fs-3"></i>

                                </div>

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Stock Movements
                                    </h5>

                                    <small class="text-muted">
                                        Track inventory movements
                                    </small>

                                </div>

                            </div>

                            <p class="text-muted">
                                Review purchases, sales, adjustments,
                                returns and other stock movements.
                            </p>

                            <a
                                href="../inventory/movements.php"
                                class="btn btn-secondary w-100"
                            >
                                <i class="bi bi-clock-history me-1"></i>
                                View Stock Movements
                            </a>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =========================================================
                 INFORMATION CARD
            ========================================================== -->

            <div class="card border-0 shadow-sm mt-4">

                <div class="card-body p-4">

                    <div class="d-flex align-items-start">

                        <i class="bi bi-info-circle text-primary fs-4 me-3"></i>

                        <div>

                            <h6 class="fw-bold mb-1">
                                About Reports
                            </h6>

                            <p class="text-muted mb-0">
                                Reports are generated from the SmartPOS
                                transaction and inventory data. Use date
                                filters to analyze specific periods and
                                print reports for business records.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php

$conn->close();

include "../includes/footer.php";

?>