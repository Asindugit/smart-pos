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
| CSRF
|--------------------------------------------------------------------------
*/

require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle = "Billing";


/*
|--------------------------------------------------------------------------
| Load Customers
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

        $result =
            $customerStmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $customers[] = $row;
        }


        $result->free();
    }


    $customerStmt->close();
}


/*
|--------------------------------------------------------------------------
| Load Products
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


if ($productStmt) {

    if ($productStmt->execute()) {

        $result =
            $productStmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $products[] = [

                "id" =>
                    (int) ($row["id"] ?? 0),

                "sku" =>
                    (string) ($row["sku"] ?? ""),

                "barcode" =>
                    (string) ($row["barcode"] ?? ""),

                "name" =>
                    (string) ($row["name"] ?? ""),

                "selling_price" =>
                    (float) ($row["selling_price"] ?? 0),

                "stock_quantity" =>
                    (float) ($row["stock_quantity"] ?? 0),

                "reorder_level" =>
                    (float) ($row["reorder_level"] ?? 0),

                "unit" =>
                    (string) ($row["unit"] ?? "pcs"),

                "category_name" =>
                    (string) ($row["category_name"] ?? "")
            ];
        }


        $result->free();
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
| Billing Page
|--------------------------------------------------------------------------
*/

.billing-page {
    min-height: calc(100vh - 100px);
}


/*
|--------------------------------------------------------------------------
| Card
|--------------------------------------------------------------------------
*/

.billing-card {
    border: 0;
    border-radius: 14px;
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}


.billing-card-header {
    background: #ffffff;
    border-bottom: 1px solid #eeeeee;
    padding: 18px 20px;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

.search-wrapper {
    position: relative;
}


.search-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}


.search-wrapper input {
    padding-left: 42px;
}


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

.products-area {
    max-height: 650px;
    overflow-y: auto;
}


.product-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    background: #ffffff;
    cursor: pointer;
    transition: 0.2s ease;
    height: 100%;
}


.product-card:hover {
    border-color: #0d6efd;
    transform: translateY(-2px);
    box-shadow:
        0 5px 15px rgba(0, 0, 0, 0.07);
}


.product-name {
    font-weight: 600;
    font-size: 15px;
}


.product-price {
    font-weight: 700;
    color: #198754;
    font-size: 17px;
}


.product-meta {
    font-size: 12px;
    color: #6c757d;
}


.product-stock {
    font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

.cart-area {
    max-height: 430px;
    overflow-y: auto;
}


.cart-item {
    border-bottom: 1px solid #eeeeee;
    padding: 15px 0;
}


.cart-item:last-child {
    border-bottom: 0;
}


.cart-item-name {
    font-weight: 600;
}


.cart-item-info {
    font-size: 12px;
    color: #6c757d;
}


/*
|--------------------------------------------------------------------------
| Quantity
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
    width: 32px;
    height: 32px;
    border: 0;
    background: #f8f9fa;
}


.quantity-control input {
    width: 45px;
    height: 32px;
    border: 0;
    border-left: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    text-align: center;
    outline: none;
}


/*
|--------------------------------------------------------------------------
| Total
|--------------------------------------------------------------------------
*/

.total-box {
    border-top: 1px solid #eeeeee;
    padding-top: 15px;
}


.grand-total {
    font-size: 28px;
    font-weight: 800;
    color: #198754;
}


/*
|--------------------------------------------------------------------------
| Payment
|--------------------------------------------------------------------------
*/

.payment-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}


.payment-option input {
    display: none;
}


.payment-option label {
    display: block;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    background: #fff;
}


.payment-option input:checked + label {
    background: #eaf3ff;
    border-color: #0d6efd;
    color: #0d6efd;
    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty-state {
    padding: 50px 20px;
    text-align: center;
    color: #6c757d;
}


.empty-state i {
    font-size: 45px;
    margin-bottom: 15px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 991px) {

    .products-area {
        max-height: 500px;
    }

}


@media (max-width: 575px) {

    .grand-total {
        font-size: 23px;
    }

}

</style>


<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4 billing-page">


            <!-- ==========================================================
                 HEADER
            =========================================================== -->

            <div
                class="d-flex justify-content-between align-items-center mb-4"
            >

                <div>

                    <h3 class="fw-bold mb-1">

                        <i class="bi bi-receipt me-2"></i>

                        Billing

                    </h3>

                    <p class="text-muted mb-0">

                        Create a bill and print the customer receipt.

                    </p>

                </div>

            </div>


            <div class="row g-4">


                <!-- ======================================================
                     PRODUCTS
                ======================================================= -->

                <div class="col-12 col-xl-7">

                    <div class="card billing-card">


                        <div class="billing-card-header">

                            <div
                                class="d-flex justify-content-between align-items-center gap-3"
                            >

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Products
                                    </h5>

                                    <small class="text-muted">
                                        Select a product to add it to the bill.
                                    </small>

                                </div>


                                <span
                                    id="productCount"
                                    class="badge bg-primary"
                                >
                                    <?= count($products) ?>
                                    Products
                                </span>

                            </div>


                            <!-- Search -->

                            <div class="search-wrapper mt-3">

                                <i class="bi bi-search"></i>

                                <input
                                    type="text"
                                    id="productSearch"
                                    class="form-control form-control-lg"
                                    placeholder="Search product / SKU / barcode..."
                                    autocomplete="off"
                                >

                            </div>

                        </div>


                        <div class="card-body">

                            <?php if (empty($products)): ?>

                                <div class="empty-state">

                                    <i class="bi bi-box-seam"></i>

                                    <h5>
                                        No Products Available
                                    </h5>

                                    <p>
                                        Please add active products first.
                                    </p>

                                </div>

                            <?php else: ?>

                                <div
                                    id="productsArea"
                                    class="products-area"
                                >

                                    <div
                                        id="productGrid"
                                        class="row g-3"
                                    ></div>

                                </div>


                                <div
                                    id="noProducts"
                                    class="empty-state d-none"
                                >

                                    <i class="bi bi-search"></i>

                                    <h6>
                                        No products found
                                    </h6>

                                    <p class="mb-0">
                                        Try another search.
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- ======================================================
                     BILL
                ======================================================= -->

                <div class="col-12 col-xl-5">

                    <div class="card billing-card">


                        <div class="billing-card-header">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <h5 class="fw-bold mb-1">
                                        Current Bill
                                    </h5>

                                    <small
                                        class="text-muted"
                                        id="cartCount"
                                    >
                                        0 items
                                    </small>

                                </div>


                                <button
                                    type="button"
                                    id="clearCart"
                                    class="btn btn-sm btn-outline-danger"
                                >

                                    <i class="bi bi-trash"></i>

                                    Clear

                                </button>

                            </div>

                        </div>


                        <div class="card-body">


                            <!-- ==================================================
                                 CUSTOMER
                            =================================================== -->

                            <div class="mb-3">

                                <label
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
                                                !empty(
                                                    $customer["phone"]
                                                )
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


                            <!-- ==================================================
                                 CART
                            =================================================== -->

                            <div
                                id="cartArea"
                                class="cart-area"
                            ></div>


                            <div
                                id="emptyCart"
                                class="empty-state"
                            >

                                <i class="bi bi-cart3"></i>

                                <h6>
                                    Bill is Empty
                                </h6>

                                <p class="mb-0">
                                    Select products to create a bill.
                                </p>

                            </div>


                            <!-- ==================================================
                                 BILL SUMMARY
                            =================================================== -->

                            <div
                                id="billSummary"
                                class="total-box d-none"
                            >

                                <div
                                    class="d-flex justify-content-between mb-2"
                                >

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong id="subtotal">
                                        LKR 0.00
                                    </strong>

                                </div>


                                <div class="mb-3">

                                    <label
                                        class="form-label"
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


                                <div class="mb-3">

                                    <label
                                        class="form-label"
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


                                <div
                                    class="d-flex justify-content-between align-items-center mb-3"
                                >

                                    <strong>
                                        Grand Total
                                    </strong>

                                    <span
                                        id="grandTotal"
                                        class="grand-total"
                                    >
                                        LKR 0.00
                                    </span>

                                </div>


                                <!-- ==================================================
                                     PAYMENT
                                =================================================== -->

                                <div class="mb-3">

                                    <label
                                        class="form-label fw-semibold"
                                    >
                                        Payment Method
                                    </label>


                                    <div class="payment-grid">


                                        <div
                                            class="payment-option"
                                        >

                                            <input
                                                type="radio"
                                                name="payment_method"
                                                id="cash"
                                                value="cash"
                                                checked
                                            >

                                            <label for="cash">

                                                <i
                                                    class="bi bi-cash-stack me-1"
                                                ></i>

                                                Cash

                                            </label>

                                        </div>


                                        <div
                                            class="payment-option"
                                        >

                                            <input
                                                type="radio"
                                                name="payment_method"
                                                id="card"
                                                value="card"
                                            >

                                            <label for="card">

                                                <i
                                                    class="bi bi-credit-card me-1"
                                                ></i>

                                                Card

                                            </label>

                                        </div>


                                        <div
                                            class="payment-option"
                                        >

                                            <input
                                                type="radio"
                                                name="payment_method"
                                                id="bank"
                                                value="bank_transfer"
                                            >

                                            <label for="bank">

                                                <i
                                                    class="bi bi-bank me-1"
                                                ></i>

                                                Bank

                                            </label>

                                        </div>


                                        <div
                                            class="payment-option"
                                        >

                                            <input
                                                type="radio"
                                                name="payment_method"
                                                id="mobile"
                                                value="mobile"
                                            >

                                            <label for="mobile">

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
                                    id="cashBox"
                                    class="mb-3"
                                >

                                    <label
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
                                    id="changeBox"
                                    class="alert alert-secondary d-flex justify-content-between"
                                >

                                    <span>
                                        Change
                                    </span>

                                    <strong id="change">
                                        LKR 0.00
                                    </strong>

                                </div>


                                <!-- ==================================================
                                     COMPLETE BILL
                                =================================================== -->

                                <button
                                    type="button"
                                    id="completeBill"
                                    class="btn btn-success btn-lg w-100"
                                >

                                    <i
                                        class="bi bi-check-circle me-2"
                                    ></i>

                                    Complete Bill

                                </button>

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
| Products
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

const productGrid =
    document.getElementById(
        "productGrid"
    );

const noProducts =
    document.getElementById(
        "noProducts"
    );

const productCount =
    document.getElementById(
        "productCount"
    );

const cartArea =
    document.getElementById(
        "cartArea"
    );

const emptyCart =
    document.getElementById(
        "emptyCart"
    );

const billSummary =
    document.getElementById(
        "billSummary"
    );

const cartCount =
    document.getElementById(
        "cartCount"
    );

const subtotalElement =
    document.getElementById(
        "subtotal"
    );

const discountInput =
    document.getElementById(
        "discount"
    );

const taxInput =
    document.getElementById(
        "tax"
    );

const grandTotalElement =
    document.getElementById(
        "grandTotal"
    );

const cashReceivedInput =
    document.getElementById(
        "cashReceived"
    );

const cashBox =
    document.getElementById(
        "cashBox"
    );

const changeElement =
    document.getElementById(
        "change"
    );

const changeBox =
    document.getElementById(
        "changeBox"
    );

const customerId =
    document.getElementById(
        "customerId"
    );

const completeBill =
    document.getElementById(
        "completeBill"
    );

const clearCart =
    document.getElementById(
        "clearCart"
    );


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

function money(value)
{
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

function escapeHtml(value)
{
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


/*
|--------------------------------------------------------------------------
| Render Products
|--------------------------------------------------------------------------
*/

function renderProducts(list)
{

    if (!productGrid) {
        return;
    }


    productGrid.innerHTML = "";


    if (list.length === 0) {

        noProducts.classList.remove(
            "d-none"
        );

        productCount.textContent =
            "0 Products";

        return;
    }


    noProducts.classList.add(
        "d-none"
    );


    productCount.textContent =
        list.length +
        (
            list.length === 1
                ? " Product"
                : " Products"
        );


    list.forEach(
        function(product)
        {

            const stock =
                Number(
                    product.stock_quantity || 0
                );


            const reorder =
                Number(
                    product.reorder_level || 0
                );


            let stockBadge;


            if (stock <= 0) {

                stockBadge = `
                    <span class="badge bg-danger product-stock">
                        Out of Stock
                    </span>
                `;

            } else if (
                reorder > 0 &&
                stock <= reorder
            ) {

                stockBadge = `
                    <span class="badge bg-warning text-dark product-stock">
                        Low Stock: ${stock}
                    </span>
                `;

            } else {

                stockBadge = `
                    <span class="badge bg-success product-stock">
                        Stock: ${stock}
                    </span>
                `;
            }


            const col =
                document.createElement(
                    "div"
                );


            col.className =
                "col-12 col-sm-6 col-lg-4";


            col.innerHTML = `

                <div class="product-card">

                    <div
                        class="d-flex justify-content-between gap-2"
                    >

                        <div
                            class="product-name"
                        >

                            ${escapeHtml(
                                product.name
                            )}

                        </div>

                        ${stockBadge}

                    </div>


                    <div
                        class="product-meta mt-2"
                    >

                        SKU:
                        ${escapeHtml(
                            product.sku || "-"
                        )}

                    </div>


                    ${
                        product.barcode
                            ? `
                                <div
                                    class="product-meta mt-1"
                                >
                                    Barcode:
                                    ${escapeHtml(
                                        product.barcode
                                    )}
                                </div>
                              `
                            : ""
                    }


                    <div
                        class="d-flex justify-content-between align-items-end mt-3"
                    >

                        <div>

                            <div
                                class="product-price"
                            >

                                ${money(
                                    product.selling_price
                                )}

                            </div>

                            <small
                                class="text-muted"
                            >

                                /
                                ${escapeHtml(
                                    product.unit || "pcs"
                                )}

                            </small>

                        </div>


                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            ${stock <= 0 ? "disabled" : ""}
                        >

                            <i
                                class="bi bi-plus-lg me-1"
                            ></i>

                            Add

                        </button>

                    </div>

                </div>

            `;


            if (stock > 0) {

                col
                    .querySelector(
                        ".product-card"
                    )
                    .addEventListener(
                        "click",
                        function()
                        {
                            addProduct(
                                product.id
                            );
                        }
                    );
            }


            productGrid.appendChild(
                col
            );

        }
    );
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

function searchProducts()
{

    const keyword =
        productSearch.value
            .trim()
            .toLowerCase();


    const filtered =
        products.filter(
            function(product)
            {

                if (keyword === "") {

                    return true;
                }


                const name =
                    String(
                        product.name || ""
                    ).toLowerCase();


                const sku =
                    String(
                        product.sku || ""
                    ).toLowerCase();


                const barcode =
                    String(
                        product.barcode || ""
                    ).toLowerCase();


                return (
                    name.includes(keyword) ||
                    sku.includes(keyword) ||
                    barcode.includes(keyword)
                );
            }
        );


    renderProducts(
        filtered
    );
}


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

function addProduct(productId)
{

    const product =
        products.find(
            function(item)
            {
                return Number(item.id) ===
                    Number(productId);
            }
        );


    if (!product) {

        return;
    }


    const stock =
        Number(
            product.stock_quantity || 0
        );


    if (stock <= 0) {

        alert(
            "This product is out of stock."
        );

        return;
    }


    const existing =
        cart.find(
            function(item)
            {
                return Number(
                    item.product_id
                ) ===
                Number(
                    product.id
                );
            }
        );


    if (existing) {

        if (
            existing.quantity >=
            existing.stock
        ) {

            alert(
                "Available stock limit reached."
            );

            return;
        }


        existing.quantity += 1;

    } else {

        cart.push({

            product_id:
                Number(product.id),

            name:
                product.name,

            sku:
                product.sku,

            price:
                Number(
                    product.selling_price
                ),

            stock:
                stock,

            unit:
                product.unit || "pcs",

            quantity:
                1
        });
    }


    renderCart();
}


/*
|--------------------------------------------------------------------------
| Remove Product
|--------------------------------------------------------------------------
*/

function removeProduct(productId)
{

    cart =
        cart.filter(
            function(item)
            {
                return Number(
                    item.product_id
                ) !==
                Number(productId);
            }
        );


    renderCart();
}


/*
|--------------------------------------------------------------------------
| Change Quantity
|--------------------------------------------------------------------------
*/

function updateQuantity(
    productId,
    quantity
)
{

    const item =
        cart.find(
            function(cartItem)
            {
                return Number(
                    cartItem.product_id
                ) ===
                Number(productId);
            }
        );


    if (!item) {
        return;
    }


    quantity =
        parseFloat(quantity);


    if (
        !Number.isFinite(quantity) ||
        quantity <= 0
    ) {

        quantity = 1;
    }


    if (
        quantity > item.stock
    ) {

        alert(
            "Quantity cannot exceed available stock."
        );

        quantity =
            item.stock;
    }


    item.quantity =
        Math.round(
            quantity * 100
        ) / 100;


    renderCart();
}


/*
|--------------------------------------------------------------------------
| Calculate
|--------------------------------------------------------------------------
*/

function calculate()
{

    let subtotal = 0;


    cart.forEach(
        function(item)
        {

            subtotal +=
                Number(item.price) *
                Number(item.quantity);

        }
    );


    subtotal =
        Math.round(
            subtotal * 100
        ) / 100;


    let discount =
        parseFloat(
            discountInput.value
        );


    let tax =
        parseFloat(
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
            subtotal.toFixed(2);
    }


    const total =
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

        total:
            Math.round(
                total * 100
            ) / 100
    };
}


/*
|--------------------------------------------------------------------------
| Render Cart
|--------------------------------------------------------------------------
*/

function renderCart()
{

    cartArea.innerHTML = "";


    if (cart.length === 0) {

        emptyCart.classList.remove(
            "d-none"
        );

        billSummary.classList.add(
            "d-none"
        );

        cartCount.textContent =
            "0 items";

        return;
    }


    emptyCart.classList.add(
        "d-none"
    );

    billSummary.classList.remove(
        "d-none"
    );


    let totalItems = 0;


    cart.forEach(
        function(item)
        {

            totalItems +=
                item.quantity;


            const itemTotal =
                Number(item.price) *
                Number(item.quantity);


            const row =
                document.createElement(
                    "div"
                );


            row.className =
                "cart-item";


            row.innerHTML = `

                <div
                    class="d-flex justify-content-between gap-3"
                >

                    <div
                        class="flex-grow-1"
                    >

                        <div class="cart-item-name">

                            ${escapeHtml(
                                item.name
                            )}

                        </div>


                        <div
                            class="cart-item-info mt-1"
                        >

                            ${escapeHtml(
                                item.sku || "-"
                            )}

                            ·

                            ${money(
                                item.price
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
                                    class="minus"
                                >

                                    <i
                                        class="bi bi-dash"
                                    ></i>

                                </button>


                                <input
                                    type="number"
                                    min="1"
                                    max="${item.stock}"
                                    step="0.01"
                                    value="${item.quantity}"
                                    class="qty"
                                >


                                <button
                                    type="button"
                                    class="plus"
                                >

                                    <i
                                        class="bi bi-plus"
                                    ></i>

                                </button>

                            </div>


                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger remove"
                            >

                                <i
                                    class="bi bi-trash"
                                ></i>

                            </button>

                        </div>

                    </div>


                    <div class="text-end">

                        <strong>

                            ${money(
                                itemTotal
                            )}

                        </strong>

                    </div>

                </div>

            `;


            row
                .querySelector(".minus")
                .addEventListener(
                    "click",
                    function()
                    {

                        updateQuantity(
                            item.product_id,
                            item.quantity - 1
                        );

                    }
                );


            row
                .querySelector(".plus")
                .addEventListener(
                    "click",
                    function()
                    {

                        updateQuantity(
                            item.product_id,
                            item.quantity + 1
                        );

                    }
                );


            row
                .querySelector(".qty")
                .addEventListener(
                    "change",
                    function()
                    {

                        updateQuantity(
                            item.product_id,
                            this.value
                        );

                    }
                );


            row
                .querySelector(".remove")
                .addEventListener(
                    "click",
                    function()
                    {

                        removeProduct(
                            item.product_id
                        );

                    }
                );


            cartArea.appendChild(
                row
            );

        }
    );


    cartCount.textContent =
        totalItems +
        (
            totalItems === 1
                ? " item"
                : " items"
        );


    updateTotals();
}


/*
|--------------------------------------------------------------------------
| Update Totals
|--------------------------------------------------------------------------
*/

function updateTotals()
{

    const data =
        calculate();


    subtotalElement.textContent =
        money(
            data.subtotal
        );


    grandTotalElement.textContent =
        money(
            data.total
        );


    updateChange();
}


/*
|--------------------------------------------------------------------------
| Update Change
|--------------------------------------------------------------------------
*/

function updateChange()
{

    const payment =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    const data =
        calculate();


    if (
        !payment ||
        payment.value !== "cash"
    ) {

        changeElement.textContent =
            money(0);

        changeBox.className =
            "alert alert-secondary d-flex justify-content-between";

        return;
    }


    const received =
        parseFloat(
            cashReceivedInput.value
        );


    if (
        !Number.isFinite(received)
    ) {

        changeElement.textContent =
            money(0);

        return;
    }


    const change =
        received -
        data.total;


    if (change >= 0) {

        changeElement.textContent =
            money(change);

        changeBox.className =
            "alert alert-success d-flex justify-content-between";

    } else {

        changeElement.textContent =
            "Due " +
            money(
                Math.abs(change)
            );

        changeBox.className =
            "alert alert-danger d-flex justify-content-between";
    }
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

function updatePayment()
{

    const payment =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (
        payment &&
        payment.value === "cash"
    ) {

        cashBox.classList.remove(
            "d-none"
        );

    } else {

        cashBox.classList.add(
            "d-none"
        );

        cashReceivedInput.value =
            "0";
    }


    updateChange();
}


/*
|--------------------------------------------------------------------------
| Search Event
|--------------------------------------------------------------------------
*/

if (productSearch) {

    productSearch.addEventListener(
        "input",
        searchProducts
    );
}


/*
|--------------------------------------------------------------------------
| Discount
|--------------------------------------------------------------------------
*/

discountInput.addEventListener(
    "input",
    updateTotals
);


/*
|--------------------------------------------------------------------------
| Tax
|--------------------------------------------------------------------------
*/

taxInput.addEventListener(
    "input",
    updateTotals
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
| Payment
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        'input[name="payment_method"]'
    )
    .forEach(
        function(input)
        {

            input.addEventListener(
                "change",
                updatePayment
            );

        }
    );


/*
|--------------------------------------------------------------------------
| Clear Cart
|--------------------------------------------------------------------------
*/

clearCart.addEventListener(
    "click",
    function()
    {

        if (
            cart.length === 0
        ) {

            return;
        }


        if (
            !confirm(
                "Clear the current bill?"
            )
        ) {

            return;
        }


        cart = [];

        renderCart();

    }
);


/*
|--------------------------------------------------------------------------
| Complete Bill
|--------------------------------------------------------------------------
*/

completeBill.addEventListener(
    "click",
    function()
    {

        if (
            cart.length === 0
        ) {

            alert(
                "Please select at least one product."
            );

            return;
        }


        const totals =
            calculate();


        const payment =
            document.querySelector(
                'input[name="payment_method"]:checked'
            );


        if (!payment) {

            alert(
                "Please select a payment method."
            );

            return;
        }


        let cashReceived =
            parseFloat(
                cashReceivedInput.value
            );


        if (
            payment.value === "cash"
        ) {

            if (
                !Number.isFinite(
                    cashReceived
                ) ||
                cashReceived < totals.total
            ) {

                alert(
                    "Cash received must be equal to or greater than the bill total."
                );

                cashReceivedInput.focus();

                return;
            }

        } else {

            cashReceived =
                totals.total;
        }


        if (
            !confirm(
                "Complete this bill?"
            )
        ) {

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Create Form
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
        | CSRF
        |--------------------------------------------------------------------------
        */

        const csrf =
            document.createElement(
                "input"
            );


        csrf.type =
            "hidden";


        csrf.name =
            "csrf_token";


        csrf.value =
            <?= json_encode(csrfToken()) ?>;


        form.appendChild(
            csrf
        );


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
                    function(item)
                    {

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

        const discountForm =
            document.createElement(
                "input"
            );


        discountForm.type =
            "hidden";


        discountForm.name =
            "discount";


        discountForm.value =
            totals.discount;


        form.appendChild(
            discountForm
        );


        /*
        |--------------------------------------------------------------------------
        | Tax
        |--------------------------------------------------------------------------
        */

        const taxForm =
            document.createElement(
                "input"
            );


        taxForm.type =
            "hidden";


        taxForm.name =
            "tax";


        taxForm.value =
            totals.tax;


        form.appendChild(
            taxForm
        );


        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        const paymentForm =
            document.createElement(
                "input"
            );


        paymentForm.type =
            "hidden";


        paymentForm.name =
            "payment_method";


        paymentForm.value =
            payment.value;


        form.appendChild(
            paymentForm
        );


        /*
        |--------------------------------------------------------------------------
        | Cash Received
        |--------------------------------------------------------------------------
        */

        const cashForm =
            document.createElement(
                "input"
            );


        cashForm.type =
            "hidden";


        cashForm.name =
            "cash_received";


        cashForm.value =
            cashReceived;


        form.appendChild(
            cashForm
        );


        /*
        |--------------------------------------------------------------------------
        | Processing
        |--------------------------------------------------------------------------
        */

        completeBill.disabled =
            true;


        completeBill.innerHTML =
            `
                <span
                    class="spinner-border spinner-border-sm me-2"
                ></span>

                Processing...
            `;


        document.body.appendChild(
            form
        );


        form.submit();

    }
);


/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

renderProducts(
    products.slice(0, 30)
);


renderCart();


updatePayment();

</script>


<?php

$conn->close();

?>


<?php include "../includes/footer.php"; ?>