<?php

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle = "POS / Sales";

$error = trim($_GET["error"] ?? "");
$success = trim($_GET["success"] ?? "");


/*
|--------------------------------------------------------------------------
| Load Active Customers
|--------------------------------------------------------------------------
*/

$customers = [];

$customerStmt = $conn->prepare("
    SELECT
        id,
        customer_code,
        name,
        phone
    FROM customers
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($customerStmt) {

    if ($customerStmt->execute()) {

        $customerResult = $customerStmt->get_result();

        while ($customer = $customerResult->fetch_assoc()) {

            $customers[] = $customer;
        }

        $customerResult->free();
    }

    $customerStmt->close();
}


/*
|--------------------------------------------------------------------------
| Load Active Products
|--------------------------------------------------------------------------
*/

$products = [];

$productStmt = $conn->prepare("
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.selling_price,
        p.stock_quantity,
        p.reorder_level,
        p.unit,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.name ASC
");

if (!$productStmt) {

    $error = "Unable to prepare product query.";

} else {

    if (!$productStmt->execute()) {

        $error = "Unable to load products.";

    } else {

        $productResult = $productStmt->get_result();

        while ($product = $productResult->fetch_assoc()) {

            $products[] = [
                "id" => (int) ($product["id"] ?? 0),

                "sku" => (string) ($product["sku"] ?? ""),

                "barcode" => (string) ($product["barcode"] ?? ""),

                "name" => (string) ($product["name"] ?? ""),

                "selling_price" =>
                    (float) ($product["selling_price"] ?? 0),

                "stock_quantity" =>
                    (float) ($product["stock_quantity"] ?? 0),

                "reorder_level" =>
                    (float) ($product["reorder_level"] ?? 0),

                "unit" =>
                    (string) ($product["unit"] ?? "pcs"),

                "category_name" =>
                    (string) ($product["category_name"] ?? "")
            ];
        }

        $productResult->free();
    }

    $productStmt->close();
}


/*
|--------------------------------------------------------------------------
| Product JSON
|--------------------------------------------------------------------------
*/

$productsJson = json_encode(
    $products,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
);

if ($productsJson === false) {

    $productsJson = "[]";
}

?>

<?php include "../includes/header.php"; ?>

<?php include "../includes/navbar.php"; ?>


<style>

/*
|--------------------------------------------------------------------------
| POS Layout
|--------------------------------------------------------------------------
*/

.pos-page {
    min-height: calc(100vh - 100px);
}


/*
|--------------------------------------------------------------------------
| POS Cards
|--------------------------------------------------------------------------
*/

.pos-card {
    border: 0;
    box-shadow:
        0 0.125rem 0.5rem rgba(0, 0, 0, 0.06);
    border-radius: 12px;
    overflow: hidden;
}

.pos-card .card-header {
    background: #ffffff;
    border-bottom: 1px solid #eeeeee;
}


/*
|--------------------------------------------------------------------------
| Product Search Area
|--------------------------------------------------------------------------
*/

.product-search-wrapper {
    position: relative;
}

.product-search-wrapper .search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    z-index: 2;
}

.product-search-input {
    padding-left: 42px;
}


/*
|--------------------------------------------------------------------------
| Filter Button
|--------------------------------------------------------------------------
*/

.product-filter-button {
    min-width: 110px;
    height: 48px;
}


/*
|--------------------------------------------------------------------------
| Filter Panel
|--------------------------------------------------------------------------
*/

.product-filter-panel {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 18px;
}

.product-filter-panel .form-label {
    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Product Results
|--------------------------------------------------------------------------
*/

.product-results {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 3px;
}


/*
|--------------------------------------------------------------------------
| Product Result
|--------------------------------------------------------------------------
*/

.product-result {
    border: 1px solid #eeeeee;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
}

.product-result:hover {
    border-color: #0d6efd;
    transform: translateY(-1px);
    box-shadow:
        0 4px 12px rgba(0, 0, 0, 0.06);
}

.product-result:last-child {
    margin-bottom: 0;
}

.product-result-name {
    font-weight: 600;
    line-height: 1.3;
}

.product-result-meta {
    font-size: 12px;
    color: #6c757d;
}

.product-result-price {
    font-weight: 700;
    color: #198754;
    white-space: nowrap;
}

.product-result.out-of-stock {
    opacity: 0.65;
}


/*
|--------------------------------------------------------------------------
| Stock Badges
|--------------------------------------------------------------------------
*/

.stock-badge {
    font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

.cart-items {
    max-height: 520px;
    overflow-y: auto;
}

.cart-item {
    border-bottom: 1px solid #eeeeee;
    padding: 16px 0;
}

.cart-item:last-child {
    border-bottom: 0;
}

.cart-item-name {
    font-weight: 600;
}

.cart-item-meta {
    font-size: 12px;
    color: #6c757d;
}


/*
|--------------------------------------------------------------------------
| Quantity Controls
|--------------------------------------------------------------------------
*/

.quantity-control {
    display: inline-flex;
    align-items: center;
    border: 1px solid #dee2e6;
    border-radius: 7px;
    overflow: hidden;
}

.quantity-control button {
    border: 0;
    background: #f8f9fa;
    width: 34px;
    height: 34px;
}

.quantity-control button:hover {
    background: #e9ecef;
}

.quantity-control input {
    width: 50px;
    height: 34px;
    border: 0;
    border-left: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    text-align: center;
    outline: none;
}


/*
|--------------------------------------------------------------------------
| Cart Summary
|--------------------------------------------------------------------------
*/

.pos-summary {
    border-top: 1px solid #eeeeee;
    padding-top: 18px;
}

.pos-grand-total {
    font-size: 26px;
    font-weight: 800;
    color: #198754;
}


/*
|--------------------------------------------------------------------------
| Empty Cart
|--------------------------------------------------------------------------
*/

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-cart-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #f1f3f5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
}


/*
|--------------------------------------------------------------------------
| Payment
|--------------------------------------------------------------------------
*/

.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.payment-method {
    position: relative;
}

.payment-method input {
    position: absolute;
    opacity: 0;
}

.payment-method label {
    display: block;
    padding: 12px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    background: #ffffff;
    transition: 0.2s ease;
}

.payment-method input:checked + label {
    border-color: #0d6efd;
    background: #eaf3ff;
    color: #0d6efd;
    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Change
|--------------------------------------------------------------------------
*/

.change-display {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px;
}

.change-display.positive {
    background: #eaf7ef;
    color: #198754;
}

.change-display.negative {
    background: #fff0f0;
    color: #dc3545;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 991.98px) {

    .product-results {
        max-height: 350px;
    }

    .cart-items {
        max-height: none;
    }

}


@media (max-width: 575.98px) {

    .payment-methods {
        grid-template-columns: 1fr 1fr;
    }

    .pos-grand-total {
        font-size: 22px;
    }

    .product-filter-button {
        min-width: 95px;
    }

}

</style>


<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4 pos-page">


            <!-- ==========================================================
                 PAGE HEADER
            =========================================================== -->

            <div class="dashboard-header mb-4">

                <div>

                    <div
                        class="d-flex align-items-center gap-2 mb-1"
                    >

                        <span class="dashboard-title-icon">
                            <i class="bi bi-cart3"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            POS / Sales
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Create sales, process payments and manage your cart.
                    </p>

                </div>

            </div>


            <!-- ==========================================================
                 SUCCESS MESSAGE
            =========================================================== -->

            <?php if ($success !== ""): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- ==========================================================
                 ERROR MESSAGE
            =========================================================== -->

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <div class="row g-4">


                <!-- ======================================================
                     LEFT SIDE
                ======================================================= -->

                <div class="col-12 col-xl-7">

                    <div class="card pos-card h-100">


                        <!-- Product Header -->

                        <div class="card-header p-4">

                            <div
                                class="d-flex justify-content-between align-items-center gap-3"
                            >

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Select Products
                                    </h5>

                                    <small class="text-muted">
                                        Search and filter products.
                                    </small>

                                </div>


                                <span
                                    id="productCountBadge"
                                    class="badge bg-primary"
                                >

                                    <?= count($products) ?>

                                    Products

                                </span>

                            </div>

                        </div>


                        <div class="card-body p-4">


                            <!-- ==================================================
                                 SEARCH + FILTER
                            =================================================== -->

                            <div class="row g-2 mb-3">

                                <div class="col">

                                    <div class="product-search-wrapper">

                                        <i
                                            class="bi bi-search search-icon"
                                        ></i>

                                        <input
                                            type="text"
                                            id="productSearch"
                                            class="form-control form-control-lg product-search-input"
                                            placeholder="Search product, SKU or barcode..."
                                            autocomplete="off"
                                        >

                                    </div>

                                </div>


                                <div class="col-auto">

                                    <button
                                        type="button"
                                        id="filterButton"
                                        class="btn btn-outline-primary product-filter-button"
                                    >

                                        <i
                                            class="bi bi-funnel me-1"
                                        ></i>

                                        Filter

                                    </button>

                                </div>

                            </div>


                            <!-- ==================================================
                                 FILTER PANEL
                            =================================================== -->

                            <div
                                id="productFilterPanel"
                                class="product-filter-panel d-none"
                            >

                                <div class="row g-3">


                                    <!-- Stock Status -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="stockFilter"
                                            class="form-label fw-semibold"
                                        >

                                            Stock Status

                                        </label>

                                        <select
                                            id="stockFilter"
                                            class="form-select"
                                        >

                                            <option value="all">
                                                All Products
                                            </option>

                                            <option value="in_stock">
                                                In Stock
                                            </option>

                                            <option value="low_stock">
                                                Low Stock
                                            </option>

                                            <option value="out_of_stock">
                                                Out of Stock
                                            </option>

                                        </select>

                                    </div>


                                    <!-- Category -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="categoryFilter"
                                            class="form-label fw-semibold"
                                        >

                                            Category

                                        </label>

                                        <select
                                            id="categoryFilter"
                                            class="form-select"
                                        >

                                            <option value="">
                                                All Categories
                                            </option>

                                        </select>

                                    </div>


                                    <!-- Filter Buttons -->

                                    <div
                                        class="col-12 d-flex justify-content-end gap-2"
                                    >

                                        <button
                                            type="button"
                                            id="clearFiltersButton"
                                            class="btn btn-outline-secondary"
                                        >

                                            <i
                                                class="bi bi-arrow-counterclockwise me-1"
                                            ></i>

                                            Clear Filters

                                        </button>

                                        <button
                                            type="button"
                                            id="closeFilterButton"
                                            class="btn btn-primary"
                                        >

                                            <i
                                                class="bi bi-check-lg me-1"
                                            ></i>

                                            Apply

                                        </button>

                                    </div>

                                </div>

                            </div>


                            <!-- ==================================================
                                 Product Results
                            =================================================== -->

                            <div
                                id="productResults"
                                class="product-results"
                            ></div>


                            <!-- ==================================================
                                 No Results
                            =================================================== -->

                            <div
                                id="noProductResults"
                                class="empty-cart d-none"
                            >

                                <div class="empty-cart-icon">

                                    <i class="bi bi-search"></i>

                                </div>

                                <h6 class="fw-bold">
                                    No Products Found
                                </h6>

                                <p class="mb-0">
                                    Try another search or filter.
                                </p>

                            </div>


                            <!-- ==================================================
                                 No Active Products
                            =================================================== -->

                            <?php if (empty($products)): ?>

                                <div
                                    id="emptyProductDatabase"
                                    class="empty-cart"
                                >

                                    <div class="empty-cart-icon">

                                        <i class="bi bi-box-seam"></i>

                                    </div>

                                    <h6 class="fw-bold">
                                        No Active Products
                                    </h6>

                                    <p class="mb-3">
                                        Add active products before creating a sale.
                                    </p>

                                    <a
                                        href="../products/add.php"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-plus-circle me-1"></i>

                                        Add Product

                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- ======================================================
                     RIGHT SIDE
                ======================================================= -->

                <div class="col-12 col-xl-5">


                    <!-- ==================================================
                         CART
                    =================================================== -->

                    <div class="card pos-card mb-4">

                        <div class="card-header p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Current Cart
                                    </h5>

                                    <small
                                        class="text-muted"
                                        id="cartItemCount"
                                    >
                                        0 items
                                    </small>

                                </div>


                                <button
                                    type="button"
                                    id="clearCartButton"
                                    class="btn btn-outline-danger btn-sm"
                                >

                                    <i class="bi bi-trash me-1"></i>

                                    Clear

                                </button>

                            </div>

                        </div>


                        <div class="card-body p-4">

                            <div
                                id="cartItems"
                                class="cart-items"
                            ></div>


                            <div
                                id="emptyCart"
                                class="empty-cart"
                            >

                                <div class="empty-cart-icon">

                                    <i class="bi bi-cart3"></i>

                                </div>

                                <h6 class="fw-bold">
                                    Cart is Empty
                                </h6>

                                <p class="mb-0">
                                    Search for a product and add it to the cart.
                                </p>

                            </div>


                            <div
                                id="cartSummary"
                                class="pos-summary d-none"
                            >

                                <div
                                    class="d-flex justify-content-between mb-2"
                                >

                                    <span class="text-muted">
                                        Subtotal
                                    </span>

                                    <strong id="subtotalDisplay">
                                        LKR 0.00
                                    </strong>

                                </div>


                                <div
                                    class="d-flex justify-content-between mb-2"
                                >

                                    <span class="text-muted">
                                        Discount
                                    </span>

                                    <strong id="discountDisplay">
                                        LKR 0.00
                                    </strong>

                                </div>


                                <div
                                    class="d-flex justify-content-between mb-3"
                                >

                                    <span class="text-muted">
                                        Tax
                                    </span>

                                    <strong id="taxDisplay">
                                        LKR 0.00
                                    </strong>

                                </div>


                                <div
                                    class="d-flex justify-content-between align-items-center"
                                >

                                    <span class="fw-bold">
                                        Grand Total
                                    </span>

                                    <span
                                        id="grandTotalDisplay"
                                        class="pos-grand-total"
                                    >
                                        LKR 0.00
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ==================================================
                         CHECKOUT
                    =================================================== -->

                    <div class="card pos-card">

                        <div class="card-header p-4">

                            <h5 class="fw-bold mb-1">
                                Checkout
                            </h5>

                            <small class="text-muted">
                                Customer and payment information.
                            </small>

                        </div>


                        <div class="card-body p-4">


                            <!-- Customer -->

                            <div class="mb-4">

                                <label
                                    for="customerId"
                                    class="form-label fw-semibold"
                                >
                                    Customer
                                </label>

                                <select
                                    id="customerId"
                                    class="form-select"
                                >

                                    <option value="">
                                        Walk-in Customer
                                    </option>

                                    <?php foreach (
                                        $customers as $customer
                                    ): ?>

                                        <option
                                            value="<?= (int) $customer["id"] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $customer["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                            <?php if (
                                                !empty($customer["phone"])
                                            ): ?>

                                                -
                                                <?= htmlspecialchars(
                                                    $customer["phone"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- Discount / Tax -->

                            <div class="row g-3 mb-4">

                                <div class="col-12 col-md-6">

                                    <label
                                        for="discount"
                                        class="form-label fw-semibold"
                                    >
                                        Discount
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            id="discount"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="0"
                                        >

                                    </div>

                                </div>


                                <div class="col-12 col-md-6">

                                    <label
                                        for="tax"
                                        class="form-label fw-semibold"
                                    >
                                        Tax
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            id="tax"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="0"
                                        >

                                    </div>

                                </div>

                            </div>


                            <!-- Payment Method -->

                            <div class="mb-4">

                                <label class="form-label fw-semibold">
                                    Payment Method
                                </label>

                                <div class="payment-methods">


                                    <div class="payment-method">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            id="paymentCash"
                                            value="cash"
                                            checked
                                        >

                                        <label for="paymentCash">

                                            <i
                                                class="bi bi-cash-stack me-1"
                                            ></i>

                                            Cash

                                        </label>

                                    </div>


                                    <div class="payment-method">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            id="paymentCard"
                                            value="card"
                                        >

                                        <label for="paymentCard">

                                            <i
                                                class="bi bi-credit-card me-1"
                                            ></i>

                                            Card

                                        </label>

                                    </div>


                                    <div class="payment-method">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            id="paymentBank"
                                            value="bank_transfer"
                                        >

                                        <label for="paymentBank">

                                            <i
                                                class="bi bi-bank me-1"
                                            ></i>

                                            Bank

                                        </label>

                                    </div>


                                    <div class="payment-method">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            id="paymentMobile"
                                            value="mobile"
                                        >

                                        <label for="paymentMobile">

                                            <i
                                                class="bi bi-phone me-1"
                                            ></i>

                                            Mobile

                                        </label>

                                    </div>

                                </div>

                            </div>


                            <!-- Cash -->

                            <div
                                id="cashReceivedWrapper"
                                class="mb-4"
                            >

                                <label
                                    for="cashReceived"
                                    class="form-label fw-semibold"
                                >
                                    Cash Received
                                </label>

                                <div class="input-group input-group-lg">

                                    <span class="input-group-text">
                                        LKR
                                    </span>

                                    <input
                                        type="number"
                                        id="cashReceived"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                    >

                                </div>

                            </div>


                            <!-- Change -->

                            <div
                                id="changeDisplay"
                                class="change-display mb-4"
                            >

                                <div
                                    class="d-flex justify-content-between align-items-center"
                                >

                                    <span>
                                        Change
                                    </span>

                                    <strong id="changeAmount">
                                        LKR 0.00
                                    </strong>

                                </div>

                            </div>


                            <!-- Complete Sale -->

                            <button
                                type="button"
                                id="completeSaleButton"
                                class="btn btn-success btn-lg w-100"
                                disabled
                            >

                                <i
                                    class="bi bi-check-circle me-2"
                                ></i>

                                Complete Sale

                            </button>


                            <div class="text-center mt-3">

                                <small class="text-muted">
                                    Stock will be checked again when the
                                    sale is processed.
                                </small>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>


<script>

/*
|--------------------------------------------------------------------------
| SmartPOS POS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /*
        |--------------------------------------------------------------------------
        | Products From PHP
        |--------------------------------------------------------------------------
        */

        const products = <?= $productsJson ?>;


        /*
        |--------------------------------------------------------------------------
        | Cart
        |--------------------------------------------------------------------------
        */

        let cart = [];


        /*
        |--------------------------------------------------------------------------
        | Elements
        |--------------------------------------------------------------------------
        */

        const productSearch =
            document.getElementById(
                "productSearch"
            );

        const productResults =
            document.getElementById(
                "productResults"
            );

        const noProductResults =
            document.getElementById(
                "noProductResults"
            );

        const productCountBadge =
            document.getElementById(
                "productCountBadge"
            );

        const filterButton =
            document.getElementById(
                "filterButton"
            );

        const productFilterPanel =
            document.getElementById(
                "productFilterPanel"
            );

        const closeFilterButton =
            document.getElementById(
                "closeFilterButton"
            );

        const clearFiltersButton =
            document.getElementById(
                "clearFiltersButton"
            );

        const stockFilter =
            document.getElementById(
                "stockFilter"
            );

        const categoryFilter =
            document.getElementById(
                "categoryFilter"
            );

        const cartItems =
            document.getElementById(
                "cartItems"
            );

        const emptyCart =
            document.getElementById(
                "emptyCart"
            );

        const cartSummary =
            document.getElementById(
                "cartSummary"
            );

        const cartItemCount =
            document.getElementById(
                "cartItemCount"
            );

        const clearCartButton =
            document.getElementById(
                "clearCartButton"
            );

        const subtotalDisplay =
            document.getElementById(
                "subtotalDisplay"
            );

        const discountDisplay =
            document.getElementById(
                "discountDisplay"
            );

        const taxDisplay =
            document.getElementById(
                "taxDisplay"
            );

        const grandTotalDisplay =
            document.getElementById(
                "grandTotalDisplay"
            );

        const discountInput =
            document.getElementById(
                "discount"
            );

        const taxInput =
            document.getElementById(
                "tax"
            );

        const cashReceivedInput =
            document.getElementById(
                "cashReceived"
            );

        const changeDisplay =
            document.getElementById(
                "changeDisplay"
            );

        const changeAmount =
            document.getElementById(
                "changeAmount"
            );

        const completeSaleButton =
            document.getElementById(
                "completeSaleButton"
            );

        const cashReceivedWrapper =
            document.getElementById(
                "cashReceivedWrapper"
            );

        const customerId =
            document.getElementById(
                "customerId"
            );


        /*
        |--------------------------------------------------------------------------
        | Format Currency
        |--------------------------------------------------------------------------
        */

        function formatCurrency(value) {

            return "LKR " +
                Number(value || 0).toLocaleString(
                    "en-LK",
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Escape HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value) {

            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }


        /*
        |--------------------------------------------------------------------------
        | Build Category Filter
        |--------------------------------------------------------------------------
        */

        function buildCategoryFilter() {

            const categories = [];

            products.forEach(
                function (product) {

                    const category =
                        String(
                            product.category_name ?? ""
                        ).trim();


                    if (
                        category !== "" &&
                        !categories.includes(category)
                    ) {

                        categories.push(category);
                    }
                }
            );


            categories.sort(
                function (a, b) {

                    return a.localeCompare(b);
                }
            );


            categoryFilter.innerHTML = `
                <option value="">
                    All Categories
                </option>
            `;


            categories.forEach(
                function (category) {

                    const option =
                        document.createElement(
                            "option"
                        );

                    option.value =
                        category;

                    option.textContent =
                        category;

                    categoryFilter.appendChild(
                        option
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Stock Filter
        |--------------------------------------------------------------------------
        */

        function matchesStockFilter(
            product
        ) {

            const stock =
                Number(
                    product.stock_quantity ?? 0
                );

            const reorderLevel =
                Number(
                    product.reorder_level ?? 0
                );


            const selected =
                stockFilter.value;


            if (
                selected === "in_stock"
            ) {

                return stock > 0;
            }


            if (
                selected === "low_stock"
            ) {

                return (
                    stock > 0 &&
                    stock <= reorderLevel
                );
            }


            if (
                selected === "out_of_stock"
            ) {

                return stock <= 0;
            }


            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Search + Filters
        |--------------------------------------------------------------------------
        */

        function searchProducts() {

            const keyword =
                productSearch.value
                    .trim()
                    .toLowerCase();


            const selectedCategory =
                categoryFilter.value;


            let filteredProducts =
                products.filter(
                    function (product) {


                        /*
                        |--------------------------------------------------------------------------
                        | Search
                        |--------------------------------------------------------------------------
                        */

                        if (
                            keyword !== ""
                        ) {

                            const name =
                                String(
                                    product.name ?? ""
                                ).toLowerCase();

                            const sku =
                                String(
                                    product.sku ?? ""
                                ).toLowerCase();

                            const barcode =
                                String(
                                    product.barcode ?? ""
                                ).toLowerCase();


                            const matchesSearch =
                                name.includes(keyword) ||
                                sku.includes(keyword) ||
                                barcode.includes(keyword);


                            if (!matchesSearch) {

                                return false;
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Stock Filter
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !matchesStockFilter(
                                product
                            )
                        ) {

                            return false;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Category Filter
                        |--------------------------------------------------------------------------
                        */

                        if (
                            selectedCategory !== "" &&
                            String(
                                product.category_name ?? ""
                            ) !== selectedCategory
                        ) {

                            return false;
                        }


                        return true;
                    }
                );


            /*
            |--------------------------------------------------------------------------
            | Initial Display Limit
            |--------------------------------------------------------------------------
            */

            if (
                keyword === "" &&
                stockFilter.value === "all" &&
                categoryFilter.value === ""
            ) {

                filteredProducts =
                    filteredProducts.slice(
                        0,
                        30
                    );
            }


            renderProductResults(
                filteredProducts
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Render Product Results
        |--------------------------------------------------------------------------
        */

        function renderProductResults(
            filteredProducts
        ) {

            productResults.innerHTML = "";

            noProductResults.classList.add(
                "d-none"
            );


            /*
            |--------------------------------------------------------------------------
            | Product Count
            |--------------------------------------------------------------------------
            */

            productCountBadge.textContent =
                filteredProducts.length +
                (
                    filteredProducts.length === 1
                        ? " Product"
                        : " Products"
                );


            /*
            |--------------------------------------------------------------------------
            | No Results
            |--------------------------------------------------------------------------
            */

            if (
                filteredProducts.length === 0
            ) {

                noProductResults.classList.remove(
                    "d-none"
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Render
            |--------------------------------------------------------------------------
            */

            filteredProducts.forEach(
                function (product) {

                    const stock =
                        Number(
                            product.stock_quantity ?? 0
                        );


                    const reorderLevel =
                        Number(
                            product.reorder_level ?? 0
                        );


                    const price =
                        Number(
                            product.selling_price ?? 0
                        );


                    const result =
                        document.createElement(
                            "div"
                        );


                    result.className =
                        "product-result";


                    if (
                        stock <= 0
                    ) {

                        result.classList.add(
                            "out-of-stock"
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Stock Badge
                    |--------------------------------------------------------------------------
                    */

                    let stockBadge = "";


                    if (
                        stock <= 0
                    ) {

                        stockBadge = `
                            <span class="badge bg-danger stock-badge">
                                Out of Stock
                            </span>
                        `;

                    } else if (
                        stock <= reorderLevel
                    ) {

                        stockBadge = `
                            <span class="badge bg-warning text-dark stock-badge">
                                Low Stock
                            </span>
                        `;

                    } else {

                        stockBadge = `
                            <span class="badge bg-success stock-badge">
                                In Stock
                            </span>
                        `;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Category
                    |--------------------------------------------------------------------------
                    */

                    const category =
                        product.category_name
                            ? escapeHtml(
                                product.category_name
                            )
                            : "Uncategorized";


                    /*
                    |--------------------------------------------------------------------------
                    | Barcode
                    |--------------------------------------------------------------------------
                    */

                    const barcodeHtml =
                        product.barcode
                            ? `
                                <span class="ms-2">
                                    Barcode:
                                    ${escapeHtml(
                                        product.barcode
                                    )}
                                </span>
                              `
                            : "";


                    /*
                    |--------------------------------------------------------------------------
                    | Add Button
                    |--------------------------------------------------------------------------
                    */

                    const addButtonHtml =
                        stock > 0
                            ? `
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary mt-2 add-product-button"
                                >

                                    <i class="bi bi-plus"></i>

                                    Add

                                </button>
                              `
                            : `
                                <span class="badge bg-danger mt-2">
                                    Out of Stock
                                </span>
                              `;


                    /*
                    |--------------------------------------------------------------------------
                    | HTML
                    |--------------------------------------------------------------------------
                    */

                    result.innerHTML = `

                        <div
                            class="d-flex justify-content-between align-items-start gap-3"
                        >

                            <div class="flex-grow-1">

                                <div
                                    class="product-result-name"
                                >

                                    ${escapeHtml(
                                        product.name
                                    )}

                                </div>


                                <div
                                    class="product-result-meta mt-1"
                                >

                                    Category:
                                    ${category}

                                </div>


                                <div
                                    class="product-result-meta mt-1"
                                >

                                    SKU:
                                    ${escapeHtml(
                                        product.sku || "-"
                                    )}

                                    ${barcodeHtml}

                                </div>


                                <div
                                    class="product-result-meta mt-1"
                                >

                                    Stock:
                                    <strong>
                                        ${stock.toFixed(2)}
                                    </strong>

                                    ${escapeHtml(
                                        product.unit || "pcs"
                                    )}

                                    <span class="ms-2">
                                        ${stockBadge}
                                    </span>

                                </div>

                            </div>


                            <div class="text-end">

                                <div
                                    class="product-result-price"
                                >

                                    ${formatCurrency(
                                        price
                                    )}

                                </div>

                                ${addButtonHtml}

                            </div>

                        </div>

                    `;


                    /*
                    |--------------------------------------------------------------------------
                    | Add Button
                    |--------------------------------------------------------------------------
                    */

                    const addButton =
                        result.querySelector(
                            ".add-product-button"
                        );


                    if (addButton) {

                        addButton.addEventListener(
                            "click",
                            function (event) {

                                event.stopPropagation();

                                addToCart(
                                    product.id
                                );
                            }
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Product Card Click
                    |--------------------------------------------------------------------------
                    */

                    result.addEventListener(
                        "click",
                        function () {

                            if (
                                stock <= 0
                            ) {

                                alert(
                                    "This product is out of stock."
                                );

                                return;
                            }


                            addToCart(
                                product.id
                            );
                        }
                    );


                    productResults.appendChild(
                        result
                    );

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add Product To Cart
        |--------------------------------------------------------------------------
        */

        function addToCart(
            productId
        ) {

            const product =
                products.find(
                    function (item) {

                        return Number(
                            item.id
                        ) === Number(
                            productId
                        );
                    }
                );


            if (!product) {

                alert(
                    "Product could not be found."
                );

                return;
            }


            const stock =
                Number(
                    product.stock_quantity ?? 0
                );


            if (
                stock <= 0
            ) {

                alert(
                    "This product is out of stock."
                );

                return;
            }


            const existingItem =
                cart.find(
                    function (item) {

                        return Number(
                            item.product_id
                        ) === Number(
                            product.id
                        );
                    }
                );


            if (
                existingItem
            ) {

                if (
                    existingItem.quantity + 1 >
                    existingItem.available_stock
                ) {

                    alert(
                        "Cannot add more than available stock."
                    );

                    return;
                }


                existingItem.quantity += 1;

            } else {

                cart.push({

                    product_id:
                        Number(
                            product.id
                        ),

                    name:
                        String(
                            product.name ?? ""
                        ),

                    sku:
                        String(
                            product.sku ?? ""
                        ),

                    unit:
                        String(
                            product.unit ?? "pcs"
                        ),

                    selling_price:
                        Number(
                            product.selling_price ?? 0
                        ),

                    available_stock:
                        stock,

                    quantity:
                        1
                });
            }


            renderCart();

            productSearch.focus();
        }


        /*
        |--------------------------------------------------------------------------
        | Remove From Cart
        |--------------------------------------------------------------------------
        */

        function removeFromCart(
            productId
        ) {

            cart =
                cart.filter(
                    function (item) {

                        return Number(
                            item.product_id
                        ) !== Number(
                            productId
                        );
                    }
                );


            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | Change Quantity
        |--------------------------------------------------------------------------
        */

        function changeQuantity(
            productId,
            quantity
        ) {

            const item =
                cart.find(
                    function (cartItem) {

                        return Number(
                            cartItem.product_id
                        ) === Number(
                            productId
                        );
                    }
                );


            if (!item) {

                return;
            }


            let newQuantity =
                Number(quantity);


            if (
                !Number.isFinite(
                    newQuantity
                )
            ) {

                newQuantity = 1;
            }


            newQuantity =
                Math.floor(
                    newQuantity
                );


            if (
                newQuantity < 1
            ) {

                newQuantity = 1;
            }


            if (
                newQuantity >
                item.available_stock
            ) {

                alert(
                    "Quantity cannot exceed available stock."
                );

                newQuantity =
                    item.available_stock;
            }


            item.quantity =
                newQuantity;


            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Totals
        |--------------------------------------------------------------------------
        */

        function calculateTotals() {

            let subtotal = 0;


            cart.forEach(
                function (item) {

                    subtotal +=
                        Number(
                            item.quantity
                        ) *
                        Number(
                            item.selling_price
                        );
                }
            );


            let discount =
                Number(
                    discountInput.value
                );


            let tax =
                Number(
                    taxInput.value
                );


            if (
                !Number.isFinite(discount) ||
                discount < 0
            ) {

                discount = 0;
            }


            if (
                !Number.isFinite(tax) ||
                tax < 0
            ) {

                tax = 0;
            }


            if (
                discount > subtotal
            ) {

                discount =
                    subtotal;

                discountInput.value =
                    discount.toFixed(2);
            }


            const grandTotal =
                Math.max(
                    0,
                    subtotal -
                    discount +
                    tax
                );


            return {

                subtotal:
                    subtotal,

                discount:
                    discount,

                tax:
                    tax,

                grandTotal:
                    grandTotal
            };
        }


        /*
        |--------------------------------------------------------------------------
        | Render Cart
        |--------------------------------------------------------------------------
        */

        function renderCart() {

            cartItems.innerHTML = "";


            if (
                cart.length === 0
            ) {

                emptyCart.classList.remove(
                    "d-none"
                );

                cartSummary.classList.add(
                    "d-none"
                );

                cartItemCount.textContent =
                    "0 items";

                completeSaleButton.disabled =
                    true;

                updateChange();

                return;
            }


            emptyCart.classList.add(
                "d-none"
            );

            cartSummary.classList.remove(
                "d-none"
            );


            let totalQuantity = 0;


            cart.forEach(
                function (item) {

                    totalQuantity +=
                        Number(
                            item.quantity
                        );


                    const itemTotal =
                        Number(
                            item.quantity
                        ) *
                        Number(
                            item.selling_price
                        );


                    const itemElement =
                        document.createElement(
                            "div"
                        );


                    itemElement.className =
                        "cart-item";


                    itemElement.innerHTML = `

                        <div
                            class="d-flex justify-content-between gap-3"
                        >

                            <div class="flex-grow-1">

                                <div
                                    class="cart-item-name"
                                >

                                    ${escapeHtml(
                                        item.name
                                    )}

                                </div>


                                <div
                                    class="cart-item-meta"
                                >

                                    ${escapeHtml(
                                        item.sku || "-"
                                    )}

                                    ·

                                    ${formatCurrency(
                                        item.selling_price
                                    )}

                                    /

                                    ${escapeHtml(
                                        item.unit
                                    )}

                                </div>


                                <div
                                    class="d-flex align-items-center gap-2 mt-3"
                                >

                                    <div
                                        class="quantity-control"
                                    >

                                        <button
                                            type="button"
                                            class="decrease-button"
                                        >

                                            <i
                                                class="bi bi-dash"
                                            ></i>

                                        </button>


                                        <input
                                            type="number"
                                            min="1"
                                            max="${item.available_stock}"
                                            value="${item.quantity}"
                                            class="quantity-input"
                                        >


                                        <button
                                            type="button"
                                            class="increase-button"
                                        >

                                            <i
                                                class="bi bi-plus"
                                            ></i>

                                        </button>

                                    </div>


                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger remove-button"
                                        title="Remove"
                                    >

                                        <i
                                            class="bi bi-trash"
                                        ></i>

                                    </button>

                                </div>

                            </div>


                            <div class="text-end">

                                <strong>

                                    ${formatCurrency(
                                        itemTotal
                                    )}

                                </strong>

                            </div>

                        </div>

                    `;


                    itemElement
                        .querySelector(
                            ".decrease-button"
                        )
                        .addEventListener(
                            "click",
                            function () {

                                changeQuantity(
                                    item.product_id,
                                    item.quantity - 1
                                );
                            }
                        );


                    itemElement
                        .querySelector(
                            ".increase-button"
                        )
                        .addEventListener(
                            "click",
                            function () {

                                changeQuantity(
                                    item.product_id,
                                    item.quantity + 1
                                );
                            }
                        );


                    itemElement
                        .querySelector(
                            ".quantity-input"
                        )
                        .addEventListener(
                            "change",
                            function () {

                                changeQuantity(
                                    item.product_id,
                                    this.value
                                );
                            }
                        );


                    itemElement
                        .querySelector(
                            ".remove-button"
                        )
                        .addEventListener(
                            "click",
                            function () {

                                removeFromCart(
                                    item.product_id
                                );
                            }
                        );


                    cartItems.appendChild(
                        itemElement
                    );

                }
            );


            cartItemCount.textContent =
                totalQuantity +
                (
                    totalQuantity === 1
                        ? " item"
                        : " items"
                );


            const totals =
                calculateTotals();


            subtotalDisplay.textContent =
                formatCurrency(
                    totals.subtotal
                );


            discountDisplay.textContent =
                formatCurrency(
                    totals.discount
                );


            taxDisplay.textContent =
                formatCurrency(
                    totals.tax
                );


            grandTotalDisplay.textContent =
                formatCurrency(
                    totals.grandTotal
                );


            completeSaleButton.disabled =
                false;


            updateChange();
        }


        /*
        |--------------------------------------------------------------------------
        | Update Change
        |--------------------------------------------------------------------------
        */

        function updateChange() {

            const selectedPayment =
                document.querySelector(
                    'input[name="payment_method"]:checked'
                );


            const totals =
                calculateTotals();


            if (
                !selectedPayment ||
                selectedPayment.value !== "cash"
            ) {

                changeAmount.textContent =
                    formatCurrency(0);

                changeDisplay.classList.remove(
                    "positive",
                    "negative"
                );

                return;
            }


            const cashReceived =
                Number(
                    cashReceivedInput.value
                );


            if (
                !Number.isFinite(cashReceived) ||
                cashReceived < 0
            ) {

                changeAmount.textContent =
                    formatCurrency(0);

                changeDisplay.classList.remove(
                    "positive",
                    "negative"
                );

                return;
            }


            const change =
                cashReceived -
                totals.grandTotal;


            changeDisplay.classList.remove(
                "positive",
                "negative"
            );


            if (
                change >= 0
            ) {

                changeAmount.textContent =
                    formatCurrency(
                        change
                    );

                changeDisplay.classList.add(
                    "positive"
                );

            } else {

                changeAmount.textContent =
                    "Due " +
                    formatCurrency(
                        Math.abs(change)
                    );

                changeDisplay.classList.add(
                    "negative"
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        function updatePaymentMethod() {

            const selectedPayment =
                document.querySelector(
                    'input[name="payment_method"]:checked'
                );


            if (!selectedPayment) {

                return;
            }


            if (
                selectedPayment.value === "cash"
            ) {

                cashReceivedWrapper
                    .classList.remove(
                        "d-none"
                    );

            } else {

                cashReceivedWrapper
                    .classList.add(
                        "d-none"
                    );

                cashReceivedInput.value =
                    "0";
            }


            updateChange();
        }


        /*
        |--------------------------------------------------------------------------
        | Filter Button
        |--------------------------------------------------------------------------
        */

        filterButton.addEventListener(
            "click",
            function () {

                productFilterPanel.classList.toggle(
                    "d-none"
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Apply Filter
        |--------------------------------------------------------------------------
        */

        closeFilterButton.addEventListener(
            "click",
            function () {

                searchProducts();

                productFilterPanel.classList.add(
                    "d-none"
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Clear Filters
        |--------------------------------------------------------------------------
        */

        clearFiltersButton.addEventListener(
            "click",
            function () {

                stockFilter.value =
                    "all";

                categoryFilter.value =
                    "";

                productSearch.value =
                    "";

                searchProducts();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Filter Changes
        |--------------------------------------------------------------------------
        */

        stockFilter.addEventListener(
            "change",
            searchProducts
        );


        categoryFilter.addEventListener(
            "change",
            searchProducts
        );


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        productSearch.addEventListener(
            "input",
            searchProducts
        );


        /*
        |--------------------------------------------------------------------------
        | Clear Cart
        |--------------------------------------------------------------------------
        */

        clearCartButton.addEventListener(
            "click",
            function () {

                if (
                    cart.length === 0
                ) {

                    return;
                }


                const confirmed =
                    confirm(
                        "Are you sure you want to clear the cart?"
                    );


                if (!confirmed) {

                    return;
                }


                cart = [];

                renderCart();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Discount
        |--------------------------------------------------------------------------
        */

        discountInput.addEventListener(
            "input",
            function () {

                renderCart();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Tax
        |--------------------------------------------------------------------------
        */

        taxInput.addEventListener(
            "input",
            function () {

                renderCart();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Cash
        |--------------------------------------------------------------------------
        */

        cashReceivedInput.addEventListener(
            "input",
            updateChange
        );


        /*
        |--------------------------------------------------------------------------
        | Payment Methods
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                'input[name="payment_method"]'
            )
            .forEach(
                function (radio) {

                    radio.addEventListener(
                        "change",
                        updatePaymentMethod
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Complete Sale
        |--------------------------------------------------------------------------
        */

        completeSaleButton.addEventListener(
            "click",
            function () {


                if (
                    cart.length === 0
                ) {

                    alert(
                        "Please add at least one product."
                    );

                    return;
                }


                const totals =
                    calculateTotals();


                const selectedPayment =
                    document.querySelector(
                        'input[name="payment_method"]:checked'
                    );


                if (!selectedPayment) {

                    alert(
                        "Please select a payment method."
                    );

                    return;
                }


                let cashReceived =
                    Number(
                        cashReceivedInput.value
                    );


                if (
                    selectedPayment.value === "cash"
                ) {

                    if (
                        !Number.isFinite(
                            cashReceived
                        ) ||
                        cashReceived < 0
                    ) {

                        alert(
                            "Please enter a valid cash amount."
                        );

                        cashReceivedInput.focus();

                        return;
                    }


                    if (
                        cashReceived <
                        totals.grandTotal
                    ) {

                        alert(
                            "Cash received must be equal to or greater than the grand total."
                        );

                        cashReceivedInput.focus();

                        return;
                    }

                } else {

                    cashReceived =
                        totals.grandTotal;
                }


                const confirmed =
                    confirm(
                        "Are you sure you want to complete this sale?"
                    );


                if (!confirmed) {

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Create POST Form
                |--------------------------------------------------------------------------
                */

                const form =
                    document.createElement(
                        "form"
                    );


                form.method =
                    "POST";


                form.action =
                    "process.php";


                /*
                |--------------------------------------------------------------------------
                | Cart
                |--------------------------------------------------------------------------
                */

                const cartInput =
                    document.createElement(
                        "input"
                    );


                cartInput.type =
                    "hidden";


                cartInput.name =
                    "cart";


                cartInput.value =
                    JSON.stringify(
                        cart.map(
                            function (item) {

                                return {

                                    product_id:
                                        item.product_id,

                                    quantity:
                                        item.quantity
                                };
                            }
                        )
                    );


                form.appendChild(
                    cartInput
                );


                /*
                |--------------------------------------------------------------------------
                | Customer
                |--------------------------------------------------------------------------
                */

                const customerInput =
                    document.createElement(
                        "input"
                    );


                customerInput.type =
                    "hidden";


                customerInput.name =
                    "customer_id";


                customerInput.value =
                    customerId.value;


                form.appendChild(
                    customerInput
                );


                /*
                |--------------------------------------------------------------------------
                | Discount
                |--------------------------------------------------------------------------
                */

                const discountFormInput =
                    document.createElement(
                        "input"
                    );


                discountFormInput.type =
                    "hidden";


                discountFormInput.name =
                    "discount";


                discountFormInput.value =
                    totals.discount;


                form.appendChild(
                    discountFormInput
                );


                /*
                |--------------------------------------------------------------------------
                | Tax
                |--------------------------------------------------------------------------
                */

                const taxFormInput =
                    document.createElement(
                        "input"
                    );


                taxFormInput.type =
                    "hidden";


                taxFormInput.name =
                    "tax";


                taxFormInput.value =
                    totals.tax;


                form.appendChild(
                    taxFormInput
                );


                /*
                |--------------------------------------------------------------------------
                | Payment
                |--------------------------------------------------------------------------
                */

                const paymentInput =
                    document.createElement(
                        "input"
                    );


                paymentInput.type =
                    "hidden";


                paymentInput.name =
                    "payment_method";


                paymentInput.value =
                    selectedPayment.value;


                form.appendChild(
                    paymentInput
                );


                /*
                |--------------------------------------------------------------------------
                | Cash Received
                |--------------------------------------------------------------------------
                */

                const cashInput =
                    document.createElement(
                        "input"
                    );


                cashInput.type =
                    "hidden";


                cashInput.name =
                    "cash_received";


                cashInput.value =
                    cashReceived;


                form.appendChild(
                    cashInput
                );


                /*
                |--------------------------------------------------------------------------
                | Disable Button
                |--------------------------------------------------------------------------
                */

                completeSaleButton.disabled =
                    true;


                completeSaleButton.innerHTML =
                    `
                        <span
                            class="spinner-border spinner-border-sm me-2"
                            role="status"
                            aria-hidden="true"
                        ></span>

                        Processing Sale...
                    `;


                /*
                |--------------------------------------------------------------------------
                | Submit
                |--------------------------------------------------------------------------
                */

                document.body.appendChild(
                    form
                );


                form.submit();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | INITIALIZE
        |--------------------------------------------------------------------------
        */

        buildCategoryFilter();

        searchProducts();

        updatePaymentMethod();

        renderCart();

    }
);

</script>


<?php

$conn->close();

?>


<?php include "../includes/footer.php"; ?>