<?php

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<div
    class="offcanvas-lg offcanvas-start sidebar"
    tabindex="-1"
    id="mobileSidebar">

    <div class="offcanvas-header d-lg-none">

        <h5 class="offcanvas-title fw-bold">
            <i class="bi bi-shop"></i>
            SmartPOS
        </h5>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="offcanvas"></button>

    </div>

    <div class="offcanvas-body p-0">

        <div class="sidebar-content">

            <div class="sidebar-title">
                MAIN MENU
            </div>

            <a
                href="/smart-pos/dashboard/index.php"
                class="sidebar-link">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>

            <a
                href="/smart-pos/billing/index.php"
                class="sidebar-link">
                <i class="bi bi-cart3"></i>
                POS / Sales
            </a>

            <div class="sidebar-title mt-3">
                MANAGEMENT
            </div>

            <a
                href="/smart-pos/products/index.php"
                class="sidebar-link">
                <i class="bi bi-box-seam"></i>
                Products
            </a>

            <a
                href="/smart-pos/categories/index.php"
                class="sidebar-link">
                <i class="bi bi-tags"></i>
                Categories
            </a>

            <a
                href="/smart-pos/inventory/index.php"
                class="sidebar-link">
                <i class="bi bi-boxes"></i>
                Inventory
            </a>

            <a
                href="/smart-pos/customers/index.php"
                class="sidebar-link">
                <i class="bi bi-people"></i>
                Customers
            </a>

            <a
                href="/smart-pos/suppliers/index.php"
                class="sidebar-link">
                <i class="bi bi-truck"></i>
                Suppliers
            </a>

            <a href="/smart-pos/expenses/index.php" class="sidebar-link">
                <i class="bi bi-wallet2"></i>
                Expenses
            </a>

            <div class="sidebar-title mt-3">
                TRANSACTIONS
            </div>

            <a
                href="/smart-pos/purchases/index.php"
                class="sidebar-link">
                <i class="bi bi-bag-plus"></i>
                Purchases
            </a>

            <a
                href="/smart-pos/reports/index.php"
                class="sidebar-link">
                <i class="bi bi-bar-chart"></i>
                Reports
            </a>

            <div class="sidebar-title mt-3">
                SYSTEM
            </div>

            <a
                href="/smart-pos/users/index.php"
                class="sidebar-link">
                <i class="bi bi-person-gear"></i>
                Users
            </a>

        </div>

    </div>

</div>