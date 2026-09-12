<?php

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";
require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$pageTitle = "Billing";


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken = csrfToken();


/*
|--------------------------------------------------------------------------
| LOAD CUSTOMERS
|--------------------------------------------------------------------------
*/

$customers = [];

$customerSql = "
    SELECT
        id,
        customer_code,
        name,
        phone,
        email,
        address,
        status,
        created_at,
        updated_at
    FROM customers
    WHERE status = 'active'
    ORDER BY name ASC
";

$customerResult = $conn->query($customerSql);

if (!$customerResult) {

    die("Customer SQL Error: " .
        htmlspecialchars($conn->error));
}

while ($row = $customerResult->fetch_assoc()) {

    $customers[] = $row;
}

$customerResult->free();


/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

$productSql = "
    SELECT
        id,
        category_id,
        sku,
        barcode,
        name,
        description,
        purchase_price,
        selling_price,
        stock_quantity,
        reorder_level,
        unit,
        image,
        status
    FROM products
    WHERE status = 'active'
    AND stock_quantity > 0
    ORDER BY name ASC
";

$productResult = $conn->query($productSql);

if ($productResult) {

    while ($row = $productResult->fetch_assoc()) {

        $row["purchase_price"] = (float) $row["purchase_price"];
        $row["selling_price"] = (float) $row["selling_price"];
        $row["stock_quantity"] = (float) $row["stock_quantity"];

        $products[] = $row;
    }

    $productResult->free();
}


/*
|--------------------------------------------------------------------------
| SUCCESS / ERROR MESSAGE
|--------------------------------------------------------------------------
*/

$successMessage = $_GET["success"] ?? "";
$errorMessage   = $_GET["error"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | SmartPOS</title>


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">


    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }


        /* ---------------------------------------------------------
           MAIN WRAPPER
        --------------------------------------------------------- */

        .billing-page {
            min-height: 100vh;
            padding: 25px;
        }


        /* ---------------------------------------------------------
           HEADER
        --------------------------------------------------------- */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 14px;

            padding: 20px 24px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .header h1 {
            margin: 0;

            font-size: 25px;

            font-weight: 700;

            color: #111827;
        }

        .header p {
            margin: 5px 0 0;

            color: #6b7280;

            font-size: 14px;
        }


        /* ---------------------------------------------------------
           BACK DASHBOARD BUTTON
        --------------------------------------------------------- */

        .back-dashboard-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 11px 16px;

            border-radius: 9px;

            background: #ffffff;

            color: #2563eb;

            border: 1px solid #2563eb;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            transition: 0.2s;
        }

        .back-dashboard-btn:hover {

            background: #2563eb;

            color: #ffffff;
        }


        /* ---------------------------------------------------------
           ALERTS
        --------------------------------------------------------- */

        .alert-box {

            margin-bottom: 20px;
        }


        /* ---------------------------------------------------------
           POS GRID
        --------------------------------------------------------- */

        .pos-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr) 400px;

            gap: 20px;

            align-items: start;
        }


        /* ---------------------------------------------------------
           CARD
        --------------------------------------------------------- */

        .panel {

            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 14px;

            padding: 20px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.04);
        }


        .panel-title {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 18px;
        }

        .panel-title h2 {

            margin: 0;

            font-size: 18px;

            font-weight: 700;

            color: #111827;
        }


        /* ---------------------------------------------------------
           SEARCH
        --------------------------------------------------------- */

        .search-wrapper {

            position: relative;

            margin-bottom: 20px;
        }

        .search-wrapper i {

            position: absolute;

            left: 15px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #9ca3af;

            font-size: 18px;
        }

        .search-input {

            width: 100%;

            padding:
                13px 15px 13px 45px;

            border:
                1px solid #d1d5db;

            border-radius: 10px;

            outline: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .search-input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }


        /* ---------------------------------------------------------
           PRODUCTS GRID
        --------------------------------------------------------- */

        .products-grid {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;

            max-height: 650px;

            overflow-y: auto;

            padding-right: 3px;
        }


        /* ---------------------------------------------------------
           PRODUCT CARD
        --------------------------------------------------------- */

        .product-card {

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            background: #ffffff;

            overflow: hidden;

            cursor: pointer;

            transition:
                transform 0.2s,
                box-shadow 0.2s,
                border-color 0.2s;
        }

        .product-card:hover {

            transform:
                translateY(-2px);

            border-color: #2563eb;

            box-shadow:
                0 8px 20px rgba(37, 99, 235, 0.10);
        }


        .product-image {

            width: 100%;

            height: 145px;

            background: #f3f4f6;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;
        }

        .product-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }

        .product-placeholder {

            font-size: 42px;

            color: #9ca3af;
        }


        .product-info {

            padding: 13px;
        }

        .product-name {

            font-size: 15px;

            font-weight: 700;

            color: #111827;

            margin-bottom: 5px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .product-sku {

            font-size: 12px;

            color: #6b7280;

            margin-bottom: 8px;
        }

        .product-price {

            color: #2563eb;

            font-weight: 700;

            font-size: 16px;
        }

        .product-stock {

            font-size: 12px;

            color: #6b7280;

            margin-top: 4px;
        }


        /* ---------------------------------------------------------
           EMPTY PRODUCTS
        --------------------------------------------------------- */

        .empty-products {

            grid-column: 1 / -1;

            text-align: center;

            padding: 60px 20px;

            color: #6b7280;
        }

        .empty-products i {

            display: block;

            font-size: 45px;

            margin-bottom: 10px;

            color: #9ca3af;
        }


        /* ---------------------------------------------------------
           FORM
        --------------------------------------------------------- */

        .form-group {

            margin-bottom: 16px;
        }

        .form-label {

            display: block;

            font-size: 13px;

            font-weight: 600;

            color: #374151;

            margin-bottom: 7px;
        }

        .form-control,
        .form-select {

            width: 100%;

            padding: 11px 13px;

            border:
                1px solid #d1d5db;

            border-radius: 9px;

            outline: none;

            font-size: 14px;
        }

        .form-control:focus,
        .form-select:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }


        /* ---------------------------------------------------------
           CART
        --------------------------------------------------------- */

        .cart-container {

            max-height: 390px;

            overflow-y: auto;

            margin-bottom: 15px;
        }

        .empty-cart {

            text-align: center;

            padding: 45px 15px;

            color: #9ca3af;
        }

        .empty-cart i {

            display: block;

            font-size: 40px;

            margin-bottom: 10px;
        }


        .cart-item {

            display: grid;

            grid-template-columns:
                1fr auto;

            gap: 10px;

            padding: 13px 0;

            border-bottom:
                1px solid #eef0f3;
        }

        .cart-item:last-child {

            border-bottom: none;
        }


        .cart-item-name {

            font-size: 14px;

            font-weight: 700;

            color: #111827;
        }

        .cart-item-price {

            font-size: 12px;

            color: #6b7280;

            margin-top: 3px;
        }


        .cart-controls {

            display: flex;

            align-items: center;

            gap: 6px;

            margin-top: 8px;
        }

        .quantity-btn {

            width: 28px;

            height: 28px;

            border: 1px solid #d1d5db;

            background: #ffffff;

            border-radius: 6px;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;
        }

        .quantity-btn:hover {

            background: #f3f4f6;
        }

        .quantity-value {

            min-width: 25px;

            text-align: center;

            font-size: 13px;

            font-weight: 700;
        }

        .remove-btn {

            border: none;

            background: transparent;

            color: #dc2626;

            cursor: pointer;

            font-size: 16px;

            margin-left: 4px;
        }


        .cart-item-total {

            text-align: right;

            font-size: 14px;

            font-weight: 700;

            color: #111827;
        }


        /* ---------------------------------------------------------
           TOTALS
        --------------------------------------------------------- */

        .totals {

            border-top:
                1px solid #e5e7eb;

            padding-top: 15px;
        }

        .total-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 9px;

            font-size: 14px;

            color: #6b7280;
        }

        .total-row strong {

            color: #111827;
        }

        .grand-total {

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-top:
                1px dashed #d1d5db;

            padding-top: 13px;

            margin-top: 12px;

            font-size: 19px;

            font-weight: 800;

            color: #111827;
        }

        .grand-total span:last-child {

            color: #2563eb;
        }


        /* ---------------------------------------------------------
           COMPLETE SALE BUTTON
        --------------------------------------------------------- */

        .complete-sale-btn {

            width: 100%;

            border: none;

            background: #2563eb;

            color: #ffffff;

            padding: 14px;

            border-radius: 10px;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            margin-top: 17px;

            transition: 0.2s;
        }

        .complete-sale-btn:hover {

            background: #1d4ed8;
        }

        .complete-sale-btn:disabled {

            background: #9ca3af;

            cursor: not-allowed;
        }


        /* ---------------------------------------------------------
           CANCEL BILL BUTTON
        --------------------------------------------------------- */

        .cancel-bill-btn {

            width: 100%;

            border: 1px solid #dc2626;

            background: #ffffff;

            color: #dc2626;

            padding: 12px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            margin-top: 10px;

            transition: 0.2s;
        }

        .cancel-bill-btn:hover {

            background: #dc2626;

            color: #ffffff;
        }


        /* ---------------------------------------------------------
           MODAL
        --------------------------------------------------------- */

        .modal-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(15, 23, 42, 0.60);

            z-index: 9999;

            align-items: center;

            justify-content: center;

            padding: 20px;
        }

        .modal-overlay.show {

            display: flex;
        }

        .success-modal {

            width: 100%;

            max-width: 500px;

            background: #ffffff;

            border-radius: 16px;

            padding: 30px;

            text-align: center;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.20);
        }


        .success-icon {

            width: 70px;

            height: 70px;

            border-radius: 50%;

            margin: 0 auto 18px;

            background: #dcfce7;

            color: #16a34a;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 36px;
        }

        .success-modal h2 {

            margin: 0 0 8px;

            font-size: 23px;

            color: #111827;
        }

        .success-modal p {

            color: #6b7280;

            margin-bottom: 20px;
        }


        .success-details {

            background: #f8fafc;

            border-radius: 10px;

            padding: 15px;

            margin-bottom: 20px;

            text-align: left;
        }

        .success-detail-row {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding: 6px 0;

            font-size: 14px;
        }

        .success-detail-row span:first-child {

            color: #6b7280;
        }

        .success-detail-row span:last-child {

            font-weight: 700;

            color: #111827;
        }


        /* ---------------------------------------------------------
           PRINT BUTTONS
        --------------------------------------------------------- */

        .print-buttons {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

            margin-bottom: 10px;
        }

        .print-btn {

            border: none;

            border-radius: 9px;

            padding: 12px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 700;

            transition: 0.2s;
        }

        .receipt-btn {

            background: #111827;

            color: #ffffff;
        }

        .receipt-btn:hover {

            background: #000000;
        }

        .pdf-btn {

            background: #2563eb;

            color: #ffffff;
        }

        .pdf-btn:hover {

            background: #1d4ed8;
        }

        .new-sale-btn {

            width: 100%;

            border:
                1px solid #d1d5db;

            background: #ffffff;

            color: #374151;

            border-radius: 9px;

            padding: 11px;

            cursor: pointer;

            font-weight: 600;

            margin-top: 4px;
        }

        .new-sale-btn:hover {

            background: #f3f4f6;
        }


        /* ---------------------------------------------------------
           LOADING
        --------------------------------------------------------- */

        .loading {

            opacity: 0.6;

            pointer-events: none;
        }


        /* ---------------------------------------------------------
           RESPONSIVE
        --------------------------------------------------------- */

        @media (max-width: 1100px) {

            .pos-grid {

                grid-template-columns:
                    minmax(0, 1fr) 350px;
            }

            .products-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }


        @media (max-width: 850px) {

            .pos-grid {

                grid-template-columns: 1fr;
            }

            .products-grid {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

                max-height: none;
            }
        }


        @media (max-width: 600px) {

            .billing-page {

                padding: 12px;
            }

            .header {

                align-items: flex-start;

                gap: 12px;

                padding: 16px;

                flex-direction: column;
            }

            .header h1 {

                font-size: 21px;
            }

            .back-dashboard-btn {

                padding: 9px 12px;

                font-size: 13px;

                white-space: nowrap;
            }

            .panel {

                padding: 15px;
            }

            .products-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

                gap: 10px;
            }

            .product-image {

                height: 110px;
            }

            .product-info {

                padding: 10px;
            }

            .product-name {

                font-size: 13px;
            }

            .product-price {

                font-size: 14px;
            }

            .print-buttons {

                grid-template-columns: 1fr;
            }

            .success-modal {

                padding: 22px;

                max-height: 90vh;

                overflow-y: auto;
            }
        }


        @media (max-width: 380px) {

            .products-grid {

                grid-template-columns: 1fr;
            }
        }
    </style>

</head>


<body>


    <div class="billing-page">


        <!-- =========================================================
         HEADER
    ========================================================== -->

        <div class="header">

            <div>

                <h1>
                    <i class="bi bi-cart3"></i>
                    SmartPOS Billing
                </h1>

                <p>
                    Create a new sale
                </p>

            </div>


            <a
                href="/smart-pos/dashboard/index.php"
                class="back-dashboard-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>

        </div>


        <!-- =========================================================
         ALERTS
    ========================================================== -->

        <?php if ($successMessage): ?>

            <div class="alert-box">

                <div class="alert alert-success alert-dismissible fade show">

                    <i class="bi bi-check-circle"></i>

                    <?= e($successMessage) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"></button>

                </div>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage): ?>

            <div class="alert-box">

                <div class="alert alert-danger alert-dismissible fade show">

                    <i class="bi bi-exclamation-triangle"></i>

                    <?= e($errorMessage) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"></button>

                </div>

            </div>

        <?php endif; ?>


        <!-- =========================================================
         POS GRID
    ========================================================== -->

        <div class="pos-grid">


            <!-- =====================================================
             PRODUCTS
        ====================================================== -->

            <div class="panel">

                <div class="panel-title">

                    <h2>
                        <i class="bi bi-box-seam"></i>
                        Products
                    </h2>

                    <span
                        class="badge bg-primary"
                        id="productCount">
                        <?= count($products) ?>
                    </span>

                </div>


                <!-- SEARCH -->

                <div class="search-wrapper">

                    <i class="bi bi-search"></i>

                    <input
                        type="text"
                        id="productSearch"
                        class="search-input"
                        placeholder="Search product by name, SKU or barcode..."
                        autocomplete="off">

                </div>


                <!-- PRODUCTS -->

                <div
                    class="products-grid"
                    id="productsGrid">

                    <?php if (empty($products)): ?>

                        <div class="empty-products">

                            <i class="bi bi-box-seam"></i>

                            <strong>
                                No products available
                            </strong>

                            <div>
                                Add active products with stock first.
                            </div>

                        </div>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php

                            $image = trim(
                                (string) ($product["image"] ?? "")
                            );

                            $imageUrl = "";

                            if ($image !== "") {

                                if (
                                    filter_var(
                                        $image,
                                        FILTER_VALIDATE_URL
                                    )
                                ) {

                                    $imageUrl = $image;
                                } else {

                                    $imageUrl =
                                        "/smart-pos/" .
                                        ltrim(
                                            $image,
                                            "/"
                                        );
                                }
                            }

                            ?>

                            <div
                                class="product-card"
                                data-id="<?= (int) $product["id"] ?>"
                                data-name="<?= e($product["name"]) ?>"
                                data-sku="<?= e($product["sku"] ?? "") ?>"
                                data-barcode="<?= e($product["barcode"] ?? "") ?>"
                                data-price="<?= e($product["selling_price"]) ?>"
                                data-stock="<?= e($product["stock_quantity"]) ?>"
                                onclick="addToCart(<?= (int) $product["id"] ?>)">


                                <div class="product-image">

                                    <?php if ($imageUrl): ?>

                                        <img
                                            src="<?= e($imageUrl) ?>"
                                            alt="<?= e($product["name"]) ?>"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">

                                        <i
                                            class="bi bi-box-seam product-placeholder"
                                            style="display:none;"></i>

                                    <?php else: ?>

                                        <i
                                            class="bi bi-box-seam product-placeholder"></i>

                                    <?php endif; ?>

                                </div>


                                <div class="product-info">

                                    <div class="product-name">

                                        <?= e($product["name"]) ?>

                                    </div>


                                    <?php if (!empty($product["sku"])): ?>

                                        <div class="product-sku">

                                            SKU:
                                            <?= e($product["sku"]) ?>

                                        </div>

                                    <?php elseif (!empty($product["barcode"])): ?>

                                        <div class="product-sku">

                                            Barcode:
                                            <?= e($product["barcode"]) ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="product-sku">
                                            &nbsp;
                                        </div>

                                    <?php endif; ?>


                                    <div class="product-price">

                                        Rs.
                                        <?= number_format(
                                            (float) $product["selling_price"],
                                            2
                                        ) ?>

                                    </div>


                                    <div class="product-stock">

                                        Stock:
                                        <?= e(
                                            $product["stock_quantity"]
                                        ) ?>

                                        <?= e(
                                            $product["unit"] ?? "pcs"
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
             CART / BILL
        ====================================================== -->

            <div class="panel">

                <div class="panel-title">

                    <h2>
                        <i class="bi bi-receipt"></i>
                        Current Bill
                    </h2>

                    <span
                        class="badge bg-secondary"
                        id="cartCount">
                        0 items
                    </span>

                </div>


                <!-- CUSTOMER -->

                <div class="form-group">

                    <label
                        for="customerSelect"
                        class="form-label">
                        Customer
                    </label>

                    <select
                        id="customerSelect"
                        class="form-select">

                        <option value="">
                            Walk-in Customer
                        </option>

                        <?php if (empty($customers)): ?>

                            <option value="" disabled>
                                No customers found
                            </option>

                        <?php else: ?>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= (int) $customer["id"] ?>">

                                    <?= e($customer["customer_code"] ?? "") ?>
                                    -
                                    <?= e($customer["name"] ?? "") ?>
<!-- 
                                    <?php if (!empty($customer["phone"])): ?>

                                        - <?= e($customer["phone"]) ?> -->

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label
                        for="paymentMethod"
                        class="form-label">
                        Payment Method
                    </label>

                    <select
                        id="paymentMethod"
                        class="form-select">

                        <!-- IMPORTANT:
                         These values MUST match process.php -->

                        <option value="cash">
                            💵 Cash
                        </option>

                        <option value="card">
                            💳 Card
                        </option>

                        <option value="bank_transfer">
                            🏦 Bank Transfer
                        </option>

                        <option value="mobile">
                            📱 Online Payment
                        </option>

                        <option value="other">
                            Other
                        </option>

                    </select>

                </div>


                <!-- CART -->

                <div
                    class="cart-container"
                    id="cartContainer">

                    <div class="empty-cart">

                        <i class="bi bi-cart-x"></i>

                        <div>
                            No products added
                        </div>

                        <small>
                            Click a product to add it to the bill.
                        </small>

                    </div>

                </div>


                <!-- TOTALS -->

                <div class="totals">

                    <div class="total-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            Rs.
                            <span id="subtotal">
                                0.00
                            </span>
                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            Discount
                        </span>

                        <strong>
                            Rs.
                            <span id="discount">
                                0.00
                            </span>
                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            Tax
                        </span>

                        <strong>
                            Rs.
                            <span id="tax">
                                0.00
                            </span>
                        </strong>

                    </div>


                    <div class="grand-total">

                        <span>
                            Total
                        </span>

                        <span>
                            Rs.
                            <span id="total">
                                0.00
                            </span>
                        </span>

                    </div>

                </div>


                <!-- COMPLETE SALE -->

                <button
                    type="button"
                    id="completeSaleBtn"
                    class="complete-sale-btn"
                    onclick="completeSale()"
                    disabled>

                    <i class="bi bi-check-circle"></i>

                    Complete Sale

                </button>

            </div>

        </div>

    </div>


    <!-- =============================================================
     SUCCESS MODAL
============================================================== -->

    <div
        id="successModal"
        class="modal-overlay">

        <div class="success-modal">


            <div class="success-icon">

                <i class="bi bi-check-lg"></i>

            </div>


            <h2>
                Sale Completed!
            </h2>


            <p>
                The bill has been successfully created.
            </p>


            <!-- SUCCESS DETAILS -->

            <div class="success-details">


                <div class="success-detail-row">

                    <span>
                        Invoice Number
                    </span>

                    <span id="successInvoice">
                        -
                    </span>

                </div>


                <div class="success-detail-row">

                    <span>
                        Payment Method
                    </span>

                    <span id="successPayment">
                        -
                    </span>

                </div>


                <div class="success-detail-row">

                    <span>
                        Total
                    </span>

                    <span id="successTotal">
                        Rs. 0.00
                    </span>

                </div>


            </div>


            <!-- PRINT BUTTONS -->

            <div class="print-buttons">

                <button
                    type="button"
                    class="print-btn receipt-btn"
                    onclick="printReceipt()">

                    <i class="bi bi-printer"></i>

                    Print 80mm Bill

                </button>


                <button
                    type="button"
                    class="print-btn pdf-btn"
                    onclick="saveProfessionalPDF()">

                    <i class="bi bi-file-earmark-pdf"></i>

                    Save Professional PDF

                </button>

            </div>


            <!-- =====================================================
             CANCEL BILL
        ====================================================== -->

            <form
                method="POST"
                action="cancel.php"
                onsubmit="return confirmCancelBill();">

                <input
                    type="hidden"
                    name="id"
                    id="cancelSaleId"
                    value="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>">


                <button
                    type="submit"
                    class="cancel-bill-btn">

                    <i class="bi bi-x-circle"></i>

                    Cancel This Bill

                </button>

            </form>


            <!-- NEW SALE -->

            <button
                type="button"
                class="new-sale-btn"
                onclick="newSale()">

                <i class="bi bi-plus-circle"></i>

                New Sale

            </button>


        </div>

    </div>


    <!-- =============================================================
     JAVASCRIPT
============================================================== -->

    <script>
        /*
    |--------------------------------------------------------------------------
    | PRODUCTS
    |--------------------------------------------------------------------------
    */

        const products = <?= json_encode(
                                $products,
                                JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_HEX_TAG |
                                    JSON_HEX_AMP |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT
                            ) ?>;


        /*
        |--------------------------------------------------------------------------
        | CART
        |--------------------------------------------------------------------------
        */

        let cart = [];


        /*
        |--------------------------------------------------------------------------
        | LAST SALE
        |--------------------------------------------------------------------------
        */

        let lastSale = null;


        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const productSearch =
            document.getElementById("productSearch");

        const productsGrid =
            document.getElementById("productsGrid");

        const customerSelect =
            document.getElementById("customerSelect");

        const paymentMethod =
            document.getElementById("paymentMethod");

        const cartContainer =
            document.getElementById("cartContainer");

        const subtotalElement =
            document.getElementById("subtotal");

        const discountElement =
            document.getElementById("discount");

        const taxElement =
            document.getElementById("tax");

        const totalElement =
            document.getElementById("total");

        const cartCountElement =
            document.getElementById("cartCount");

        const completeSaleBtn =
            document.getElementById("completeSaleBtn");

        const successModal =
            document.getElementById("successModal");


        /*
        |--------------------------------------------------------------------------
        | FORMAT MONEY
        |--------------------------------------------------------------------------
        */

        function money(value) {
            return Number(value || 0)
                .toLocaleString(
                    "en-LK", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );
        }


        /*
        |--------------------------------------------------------------------------
        | FIND PRODUCT
        |--------------------------------------------------------------------------
        */

        function findProduct(productId) {
            return products.find(
                product =>
                Number(product.id) === Number(productId)
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ADD TO CART
        |--------------------------------------------------------------------------
        */

        function addToCart(productId) {
            const product =
                findProduct(productId);

            if (!product) {

                alert("Product not found.");

                return;
            }


            const existing =
                cart.find(
                    item =>
                    Number(item.id) ===
                    Number(productId)
                );


            if (existing) {

                if (
                    existing.quantity >=
                    Number(product.stock_quantity)
                ) {

                    alert(
                        "Cannot add more than available stock."
                    );

                    return;
                }

                existing.quantity++;

            } else {

                cart.push({

                    id: Number(product.id),

                    name: product.name,

                    selling_price: Number(product.selling_price),

                    stock_quantity: Number(product.stock_quantity),

                    quantity: 1

                });
            }


            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | CHANGE QUANTITY
        |--------------------------------------------------------------------------
        */

        function changeQuantity(productId, change) {
            const item =
                cart.find(
                    item =>
                    Number(item.id) ===
                    Number(productId)
                );

            if (!item) {
                return;
            }


            const newQuantity =
                item.quantity + change;


            if (newQuantity <= 0) {

                removeFromCart(productId);

                return;
            }


            if (
                newQuantity >
                Number(item.stock_quantity)
            ) {

                alert(
                    "Cannot exceed available stock."
                );

                return;
            }


            item.quantity =
                newQuantity;


            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE FROM CART
        |--------------------------------------------------------------------------
        */

        function removeFromCart(productId) {
            cart =
                cart.filter(
                    item =>
                    Number(item.id) !==
                    Number(productId)
                );

            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | RENDER CART
        |--------------------------------------------------------------------------
        */

        function renderCart() {
            if (cart.length === 0) {

                cartContainer.innerHTML = `

                <div class="empty-cart">

                    <i class="bi bi-cart-x"></i>

                    <div>
                        No products added
                    </div>

                    <small>
                        Click a product to add it to the bill.
                    </small>

                </div>

            `;

                subtotalElement.textContent =
                    "0.00";

                discountElement.textContent =
                    "0.00";

                taxElement.textContent =
                    "0.00";

                totalElement.textContent =
                    "0.00";

                cartCountElement.textContent =
                    "0 items";

                completeSaleBtn.disabled =
                    true;

                return;
            }


            let subtotal = 0;

            let totalQuantity = 0;


            let html = "";


            cart.forEach(item => {

                const itemTotal =
                    item.quantity *
                    item.selling_price;


                subtotal += itemTotal;

                totalQuantity +=
                    item.quantity;


                html += `

                <div class="cart-item">

                    <div>

                        <div class="cart-item-name">
                            ${escapeHtml(item.name)}
                        </div>

                        <div class="cart-item-price">
                            Rs. ${money(item.selling_price)}
                            each
                        </div>


                        <div class="cart-controls">

                            <button
                                type="button"
                                class="quantity-btn"
                                onclick="changeQuantity(${item.id}, -1)"
                            >
                                −
                            </button>


                            <span class="quantity-value">
                                ${item.quantity}
                            </span>


                            <button
                                type="button"
                                class="quantity-btn"
                                onclick="changeQuantity(${item.id}, 1)"
                            >
                                +
                            </button>


                            <button
                                type="button"
                                class="remove-btn"
                                onclick="removeFromCart(${item.id})"
                                title="Remove"
                            >
                                <i class="bi bi-trash"></i>
                            </button>

                        </div>

                    </div>


                    <div class="cart-item-total">

                        Rs.
                        ${money(itemTotal)}

                    </div>

                </div>

            `;

            });


            cartContainer.innerHTML =
                html;


            const discount = 0;

            const tax = 0;

            const total =
                subtotal -
                discount +
                tax;


            subtotalElement.textContent =
                money(subtotal);

            discountElement.textContent =
                money(discount);

            taxElement.textContent =
                money(tax);

            totalElement.textContent =
                money(total);


            cartCountElement.textContent =
                totalQuantity +
                (
                    totalQuantity === 1 ?
                    " item" :
                    " items"
                );


            completeSaleBtn.disabled =
                false;
        }


        /*
        |--------------------------------------------------------------------------
        | SEARCH PRODUCTS
        |--------------------------------------------------------------------------
        */

        productSearch.addEventListener(
            "input",
            function() {
                const search =
                    this.value
                    .trim()
                    .toLowerCase();


                const cards =
                    productsGrid.querySelectorAll(
                        ".product-card"
                    );


                let visibleCount = 0;


                cards.forEach(card => {

                    const name =
                        (
                            card.dataset.name ||
                            ""
                        ).toLowerCase();

                    const sku =
                        (
                            card.dataset.sku ||
                            ""
                        ).toLowerCase();

                    const barcode =
                        (
                            card.dataset.barcode ||
                            ""
                        ).toLowerCase();


                    const matched =
                        name.includes(search) ||
                        sku.includes(search) ||
                        barcode.includes(search);


                    card.style.display =
                        matched ?
                        "" :
                        "none";


                    if (matched) {
                        visibleCount++;
                    }

                });


                document.getElementById(
                        "productCount"
                    ).textContent =
                    visibleCount;
            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESCAPE HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value) {
            const div =
                document.createElement("div");

            div.textContent =
                value ?? "";

            return div.innerHTML;
        }


        /*
        |--------------------------------------------------------------------------
        | COMPLETE SALE
        |--------------------------------------------------------------------------
        */

        async function completeSale() {
            if (cart.length === 0) {

                alert(
                    "Please add at least one product."
                );

                return;
            }


            const customerId =
                customerSelect.value || null;


            const selectedPayment =
                paymentMethod.value;


            if (!selectedPayment) {

                alert(
                    "Please select a payment method."
                );

                return;
            }


            const confirmed =
                confirm(
                    "Are you sure you want to complete this sale?"
                );


            if (!confirmed) {
                return;
            }


            completeSaleBtn.disabled =
                true;

            completeSaleBtn.classList.add(
                "loading"
            );

            completeSaleBtn.innerHTML = `

            <span
                class="spinner-border spinner-border-sm me-2"
            ></span>

            Processing...

        `;


            try {

                const response =
                    await fetch(
                        "process.php", {
                            method: "POST",

                            headers: {
                                "Content-Type": "application/json",

                                "Accept": "application/json"
                            },

                            body: JSON.stringify({

                                customer_id: customerId,

                                payment_method: selectedPayment,

                                discount: 0,

                                tax: 0,

                                items: cart.map(
                                    item => ({
                                        id: item.id,

                                        quantity: item.quantity
                                    })
                                )

                            })
                        }
                    );


                const result =
                    await response.json();


                if (!response.ok || !result.success) {

                    throw new Error(
                        result.message ||
                        "Failed to complete sale."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SAVE LAST SALE
                |--------------------------------------------------------------------------
                */

                lastSale =
                    result.data;


                /*
                |--------------------------------------------------------------------------
                | SHOW SUCCESS DATA
                |--------------------------------------------------------------------------
                */

                document.getElementById(
                        "successInvoice"
                    ).textContent =
                    lastSale.invoice_number ||
                    "-";


                document.getElementById(
                        "successPayment"
                    ).textContent =
                    formatPaymentMethod(
                        lastSale.payment_method ||
                        selectedPayment
                    );


                document.getElementById(
                        "successTotal"
                    ).textContent =
                    "Rs. " +
                    money(lastSale.total);


                document.getElementById(
                        "cancelSaleId"
                    ).value =
                    lastSale.sale_id;


                /*
                |--------------------------------------------------------------------------
                | SHOW MODAL
                |--------------------------------------------------------------------------
                */

                successModal.classList.add(
                    "show"
                );


                /*
                |--------------------------------------------------------------------------
                | CLEAR CART
                |--------------------------------------------------------------------------
                */

                cart = [];

                renderCart();

                customerSelect.value = "";

                paymentMethod.value = "cash";

                productSearch.value = "";


                /*
                |--------------------------------------------------------------------------
                | RESET PRODUCT SEARCH
                |--------------------------------------------------------------------------
                */

                productsGrid
                    .querySelectorAll(
                        ".product-card"
                    )
                    .forEach(card => {

                        card.style.display = "";

                    });


                document.getElementById(
                        "productCount"
                    ).textContent =
                    products.length;


            } catch (error) {

                console.error(error);

                alert(
                    error.message ||
                    "Something went wrong while completing the sale."
                );

            } finally {

                completeSaleBtn.disabled =
                    cart.length === 0;

                completeSaleBtn.classList.remove(
                    "loading"
                );

                completeSaleBtn.innerHTML = `

                <i class="bi bi-check-circle"></i>

                Complete Sale

            `;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHOD DISPLAY
        |--------------------------------------------------------------------------
        */

        function formatPaymentMethod(method) {
            const methods = {

                cash: "Cash",

                card: "Card",

                bank_transfer: "Bank Transfer",

                mobile: "Online Payment",

                other: "Other"

            };


            return methods[method] ||
                method;
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT RECEIPT
        |--------------------------------------------------------------------------
        */

        function printReceipt() {
            if (
                !lastSale ||
                !lastSale.sale_id
            ) {

                alert(
                    "No completed bill available."
                );

                return;
            }


            window.open(
                "print.php?id=" +
                encodeURIComponent(
                    lastSale.sale_id
                ) +
                "&type=receipt",

                "_blank"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PROFESSIONAL PDF
        |--------------------------------------------------------------------------
        */

        function saveProfessionalPDF() {
            if (
                !lastSale ||
                !lastSale.sale_id
            ) {

                alert(
                    "No completed bill available."
                );

                return;
            }


            window.open(
                "print.php?id=" +
                encodeURIComponent(
                    lastSale.sale_id
                ) +
                "&type=a4",

                "_blank"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CANCEL BILL CONFIRMATION
        |--------------------------------------------------------------------------
        */

        function confirmCancelBill() {
            if (
                !lastSale ||
                !lastSale.sale_id
            ) {

                alert(
                    "No completed bill is available to cancel."
                );

                return false;
            }


            document.getElementById(
                    "cancelSaleId"
                ).value =
                lastSale.sale_id;


            const invoiceNumber =
                lastSale.invoice_number ||
                "this bill";


            return confirm(

                "Are you sure you want to cancel " +
                invoiceNumber +
                "?\n\n" +

                "The sold products will be returned to stock.\n\n" +

                "This action cannot be undone."

            );
        }


        /*
        |--------------------------------------------------------------------------
        | NEW SALE
        |--------------------------------------------------------------------------
        */

        function newSale() {
            successModal.classList.remove(
                "show"
            );


            cart = [];

            lastSale = null;


            customerSelect.value = "";


            paymentMethod.value =
                "cash";


            productSearch.value =
                "";


            productsGrid
                .querySelectorAll(
                    ".product-card"
                )
                .forEach(card => {

                    card.style.display = "";

                });


            document.getElementById(
                    "productCount"
                ).textContent =
                products.length;


            renderCart();


            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE MODAL WHEN CLICKING OUTSIDE
        |--------------------------------------------------------------------------
        */

        successModal.addEventListener(
            "click",
            function(event) {
                if (
                    event.target ===
                    successModal
                ) {

                    /*
                     * Do NOT close automatically after
                     * successful sale.
                     *
                     * User should decide whether to
                     * print, cancel or create new sale.
                     */

                    return;
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESC KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "keydown",
            function(event) {
                if (
                    event.key === "Escape" &&
                    successModal.classList.contains("show")
                ) {

                    /*
                     * Prevent accidental closing.
                     */

                    event.preventDefault();
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | INITIAL RENDER
        |--------------------------------------------------------------------------
        */

        renderCart();
    </script>


    <!-- Bootstrap JS -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>