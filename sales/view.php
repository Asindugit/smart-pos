<?php



require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle =
    "Sale Details";


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function money($value)
{
    return "LKR " .
        number_format(
            (float) $value,
            2
        );
}


/*
|--------------------------------------------------------------------------
| Sale ID
|--------------------------------------------------------------------------
*/

$saleId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (
    !$saleId ||
    $saleId <= 0
) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Invalid sale ID."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$success =
    trim(
        $_GET["success"] ?? ""
    );


$error =
    trim(
        $_GET["error"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| Load Sale
|--------------------------------------------------------------------------
*/

$saleStmt =
    $conn->prepare("
        SELECT
            s.id,
            s.invoice_number,
            s.customer_id,
            s.cashier_id,
            s.sale_date,
            s.subtotal,
            s.discount,
            s.tax,
            s.total,
            s.status,
            s.notes,
            s.created_at,

            COALESCE(
                c.name,
                'Walk-in Customer'
            ) AS customer_name,

            COALESCE(
                c.phone,
                ''
            ) AS customer_phone,

            COALESCE(
                c.email,
                ''
            ) AS customer_email,

            COALESCE(
                c.address,
                ''
            ) AS customer_address,

            COALESCE(
                u.full_name,
                'Unknown'
            ) AS cashier_name,

            COALESCE(
                u.username,
                ''
            ) AS cashier_username

        FROM sales s

        LEFT JOIN customers c
            ON s.customer_id = c.id

        LEFT JOIN users u
            ON s.cashier_id = u.id

        WHERE s.id = ?

        LIMIT 1
    ");


if (!$saleStmt) {

    $conn->close();

    die(
        "Unable to prepare sale query."
    );
}


$saleStmt->bind_param(
    "i",
    $saleId
);


if (
    !$saleStmt->execute()
) {

    $saleStmt->close();
    $conn->close();

    die(
        "Unable to load sale information."
    );
}


$saleResult =
    $saleStmt->get_result();


$sale =
    $saleResult->fetch_assoc();


$saleResult->free();


$saleStmt->close();


if (!$sale) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Sale not found."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Sale Items
|--------------------------------------------------------------------------
*/

$itemStmt =
    $conn->prepare("
        SELECT
            si.id,
            si.product_id,
            si.quantity,
            si.unit_price,
            si.discount,
            si.total,

            COALESCE(
                p.name,
                'Deleted Product'
            ) AS product_name,

            COALESCE(
                p.sku,
                '-'
            ) AS sku,

            COALESCE(
                p.unit,
                'pcs'
            ) AS unit

        FROM sale_items si

        LEFT JOIN products p
            ON si.product_id = p.id

        WHERE si.sale_id = ?

        ORDER BY si.id ASC
    ");


if (!$itemStmt) {

    $conn->close();

    die(
        "Unable to prepare sale items query."
    );
}


$itemStmt->bind_param(
    "i",
    $saleId
);


if (
    !$itemStmt->execute()
) {

    $itemStmt->close();
    $conn->close();

    die(
        "Unable to load sale items."
    );
}


$itemResult =
    $itemStmt->get_result();


$items = [];


while (
    $row =
    $itemResult->fetch_assoc()
) {

    $items[] = $row;
}


$itemResult->free();


$itemStmt->close();


/*
|--------------------------------------------------------------------------
| Load Payment
|--------------------------------------------------------------------------
*/

$paymentStmt =
    $conn->prepare("
        SELECT
            id,
            payment_method,
            amount,
            cash_received,
            change_amount,
            reference_number,
            paid_at

        FROM payments

        WHERE sale_id = ?

        ORDER BY id DESC

        LIMIT 1
    ");


if (!$paymentStmt) {

    $conn->close();

    die(
        "Unable to prepare payment query."
    );
}


$paymentStmt->bind_param(
    "i",
    $saleId
);


if (
    !$paymentStmt->execute()
) {

    $paymentStmt->close();
    $conn->close();

    die(
        "Unable to load payment information."
    );
}


$paymentResult =
    $paymentStmt->get_result();


$payment =
    $paymentResult->fetch_assoc();


$paymentResult->free();


$paymentStmt->close();


/*
|--------------------------------------------------------------------------
| Payment Values
|--------------------------------------------------------------------------
*/

$paymentMethod =
    $payment["payment_method"]
    ?? null;


$paymentAmount =
    (float) (
        $payment["amount"]
        ?? $sale["total"]
    );


$cashReceived =
    (float) (
        $payment["cash_received"]
        ?? $paymentAmount
    );


$changeAmount =
    (float) (
        $payment["change_amount"]
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| Payment Label
|--------------------------------------------------------------------------
*/

$paymentLabels = [

    "cash" =>
        "Cash",

    "card" =>
        "Card",

    "bank_transfer" =>
        "Bank Transfer",

    "mobile" =>
        "Mobile Payment"

];


$paymentMethodLabel =
    $paymentLabels[
        $paymentMethod
    ]
    ?? (
        $paymentMethod
            ? ucwords(
                str_replace(
                    "_",
                    " ",
                    $paymentMethod
                )
            )
            : "Not recorded"
    );


/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

$statusClass =
    "text-bg-secondary";


$statusLabel =
    ucfirst(
        $sale["status"]
    );


switch (
    $sale["status"]
) {

    case "completed":

        $statusClass =
            "text-bg-success";

        break;


    case "cancelled":

        $statusClass =
            "text-bg-danger";

        break;


    case "refunded":

        $statusClass =
            "text-bg-warning";

        break;


    case "pending":

        $statusClass =
            "text-bg-warning";

        break;
}


/*
|--------------------------------------------------------------------------
| Dates
|--------------------------------------------------------------------------
*/

$saleDateFormatted =
    date(
        "d M Y",
        strtotime(
            $sale["sale_date"]
        )
    );


$createdDateFormatted =
    date(
        "d M Y, h:i A",
        strtotime(
            $sale["created_at"]
        )
    );


$paymentDateFormatted =
    "-";


if (
    !empty(
        $payment["paid_at"]
    )
) {

    $paymentDateFormatted =
        date(
            "d M Y, h:i A",
            strtotime(
                $payment["paid_at"]
            )
        );
}


/*
|--------------------------------------------------------------------------
| Close Database
|--------------------------------------------------------------------------
*/

$conn->close();

?>


<?php include "../includes/header.php"; ?>

<?php include "../includes/navbar.php"; ?>


<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4">


            <!-- ==========================================================
                 PAGE HEADER
                 ========================================================== -->

            <div
                class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print"
            >

                <div>

                    <div
                        class="d-flex align-items-center gap-2 mb-2"
                    >

                        <a
                            href="index.php"
                            class="btn btn-sm btn-outline-secondary"
                        >

                            <i
                                class="bi bi-arrow-left"
                            ></i>

                        </a>

                        <span
                            class="text-muted small"
                        >
                            Sales
                        </span>

                        <i
                            class="bi bi-chevron-right text-muted small"
                        ></i>

                        <span
                            class="text-muted small"
                        >
                            Sale Details
                        </span>

                    </div>


                    <h1
                        class="h3 fw-bold mb-1"
                    >

                        Sale Details

                    </h1>


                    <p
                        class="text-muted mb-0"
                    >

                        View invoice and payment information.

                    </p>

                </div>


                <div
                    class="d-flex flex-wrap gap-2"
                >

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i
                            class="bi bi-cart3 me-1"
                        ></i>

                        POS / Sales

                    </a>


                    <!-- A4 INVOICE -->

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >

                        <i
                            class="bi bi-file-earmark-text me-1"
                        ></i>

                        Print Invoice

                    </button>


                    <!-- SMALL BILL -->

                    <a
                        href="receipt.php?id=<?= (int) $saleId ?>"
                        target="_blank"
                        class="btn btn-success"
                    >

                        <i
                            class="bi bi-receipt me-1"
                        ></i>

                        Print Small Bill

                    </a>

                </div>

            </div>


            <!-- ==========================================================
                 SUCCESS
                 ========================================================== -->

            <?php if (
                $success !== ""
            ): ?>

                <div
                    class="alert alert-success alert-dismissible fade show no-print"
                    role="alert"
                >

                    <i
                        class="bi bi-check-circle-fill me-2"
                    ></i>

                    <?= e(
                        $success
                    ) ?>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- ==========================================================
                 ERROR
                 ========================================================== -->

            <?php if (
                $error !== ""
            ): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show no-print"
                    role="alert"
                >

                    <i
                        class="bi bi-exclamation-triangle-fill me-2"
                    ></i>

                    <?= e(
                        $error
                    ) ?>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- ==========================================================
                 INVOICE
                 ========================================================== -->

            <div
                class="invoice-container"
            >


                <!-- ======================================================
                     INVOICE HEADER
                     ====================================================== -->

                <div
                    class="card border-0 shadow-sm mb-4"
                >

                    <div
                        class="card-body p-4"
                    >

                        <div
                            class="row g-4 align-items-start"
                        >


                            <!-- BUSINESS -->

                            <div
                                class="col-md-6"
                            >

                                <div
                                    class="d-flex align-items-center gap-3"
                                >

                                    <div
                                        class="invoice-logo bg-primary bg-opacity-10 text-primary rounded-3 p-3"
                                    >

                                        <i
                                            class="bi bi-shop fs-3"
                                        ></i>

                                    </div>


                                    <div>

                                        <h2
                                            class="h4 fw-bold mb-1"
                                        >

                                            SmartPOS

                                        </h2>


                                        <p
                                            class="text-muted mb-0"
                                        >

                                            Point of Sale System

                                        </p>

                                    </div>

                                </div>

                            </div>


                            <!-- INVOICE -->

                            <div
                                class="col-md-6"
                            >

                                <div
                                    class="text-md-end"
                                >

                                    <h2
                                        class="h3 fw-bold mb-2"
                                    >

                                        INVOICE

                                    </h2>


                                    <div
                                        class="mb-2"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Invoice:
                                        </span>

                                        <strong
                                            class="ms-1"
                                        >

                                            <?= e(
                                                $sale["invoice_number"]
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div
                                        class="mb-2"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Date:
                                        </span>

                                        <span
                                            class="ms-1"
                                        >

                                            <?= e(
                                                $saleDateFormatted
                                            ) ?>

                                        </span>

                                    </div>


                                    <span
                                        class="badge <?= e($statusClass) ?> px-3 py-2"
                                    >

                                        <?= e(
                                            $statusLabel
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <!-- ======================================================
                     CUSTOMER + SALE INFORMATION
                     ====================================================== -->

                <div
                    class="row g-4 mb-4"
                >


                    <!-- CUSTOMER -->

                    <div
                        class="col-md-6"
                    >

                        <div
                            class="card border-0 shadow-sm h-100"
                        >

                            <div
                                class="card-header bg-white border-0 pt-4 px-4"
                            >

                                <h5
                                    class="fw-bold mb-0"
                                >

                                    <i
                                        class="bi bi-person me-2 text-primary"
                                    ></i>

                                    Customer

                                </h5>

                            </div>


                            <div
                                class="card-body px-4 pb-4"
                            >

                                <h6
                                    class="fw-semibold mb-1"
                                >

                                    <?= e(
                                        $sale["customer_name"]
                                    ) ?>

                                </h6>


                                <?php if (
                                    $sale["customer_phone"] !== ""
                                ): ?>

                                    <div
                                        class="text-muted small mb-1"
                                    >

                                        <i
                                            class="bi bi-telephone me-1"
                                        ></i>

                                        <?= e(
                                            $sale["customer_phone"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $sale["customer_email"] !== ""
                                ): ?>

                                    <div
                                        class="text-muted small mb-1"
                                    >

                                        <i
                                            class="bi bi-envelope me-1"
                                        ></i>

                                        <?= e(
                                            $sale["customer_email"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $sale["customer_address"] !== ""
                                ): ?>

                                    <div
                                        class="text-muted small"
                                    >

                                        <i
                                            class="bi bi-geo-alt me-1"
                                        ></i>

                                        <?= e(
                                            $sale["customer_address"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>



                    <!-- SALE INFO -->

                    <div
                        class="col-md-6"
                    >

                        <div
                            class="card border-0 shadow-sm h-100"
                        >

                            <div
                                class="card-header bg-white border-0 pt-4 px-4"
                            >

                                <h5
                                    class="fw-bold mb-0"
                                >

                                    <i
                                        class="bi bi-info-circle me-2 text-primary"
                                    ></i>

                                    Sale Information

                                </h5>

                            </div>


                            <div
                                class="card-body px-4 pb-4"
                            >

                                <div
                                    class="row g-3"
                                >

                                    <div
                                        class="col-sm-6"
                                    >

                                        <div
                                            class="text-muted small"
                                        >
                                            Cashier
                                        </div>

                                        <div
                                            class="fw-semibold"
                                        >

                                            <?= e(
                                                $sale["cashier_name"]
                                            ) ?>

                                        </div>

                                    </div>


                                    <div
                                        class="col-sm-6"
                                    >

                                        <div
                                            class="text-muted small"
                                        >
                                            Username
                                        </div>

                                        <div
                                            class="fw-semibold"
                                        >

                                            <?= e(
                                                $sale["cashier_username"]
                                            ) ?>

                                        </div>

                                    </div>


                                    <div
                                        class="col-sm-6"
                                    >

                                        <div
                                            class="text-muted small"
                                        >
                                            Sale Date
                                        </div>

                                        <div
                                            class="fw-semibold"
                                        >

                                            <?= e(
                                                $saleDateFormatted
                                            ) ?>

                                        </div>

                                    </div>


                                    <div
                                        class="col-sm-6"
                                    >

                                        <div
                                            class="text-muted small"
                                        >
                                            Created
                                        </div>

                                        <div
                                            class="fw-semibold"
                                        >

                                            <?= e(
                                                $createdDateFormatted
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <!-- ======================================================
                     SALE ITEMS
                     ====================================================== -->

                <div
                    class="card border-0 shadow-sm mb-4"
                >

                    <div
                        class="card-header bg-white border-0 pt-4 px-4"
                    >

                        <div
                            class="d-flex justify-content-between align-items-center"
                        >

                            <h5
                                class="fw-bold mb-0"
                            >

                                <i
                                    class="bi bi-cart-check me-2 text-primary"
                                ></i>

                                Sale Items

                            </h5>


                            <span
                                class="badge text-bg-light border"
                            >

                                <?= count(
                                    $items
                                ) ?>

                                <?= count($items) === 1
                                    ? "Item"
                                    : "Items" ?>

                            </span>

                        </div>

                    </div>


                    <div
                        class="card-body p-0"
                    >

                        <?php if (
                            empty($items)
                        ): ?>

                            <div
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-cart-x display-5 text-muted"
                                ></i>

                                <h6
                                    class="fw-semibold mt-3"
                                >

                                    No sale items found

                                </h6>

                            </div>

                        <?php else: ?>

                            <div
                                class="table-responsive"
                            >

                                <table
                                    class="table table-hover align-middle mb-0"
                                >

                                    <thead
                                        class="table-light"
                                    >

                                        <tr>

                                            <th
                                                class="ps-4"
                                            >
                                                #
                                            </th>

                                            <th>
                                                Product
                                            </th>

                                            <th>
                                                SKU
                                            </th>

                                            <th
                                                class="text-center"
                                            >
                                                Qty
                                            </th>

                                            <th
                                                class="text-end"
                                            >
                                                Unit Price
                                            </th>

                                            <th
                                                class="text-end"
                                            >
                                                Discount
                                            </th>

                                            <th
                                                class="text-end pe-4"
                                            >
                                                Total
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php
                                        $itemNumber = 1;
                                        ?>


                                        <?php foreach (
                                            $items as $item
                                        ): ?>

                                            <tr>

                                                <td
                                                    class="ps-4 text-muted"
                                                >

                                                    <?= $itemNumber ?>

                                                </td>


                                                <td>

                                                    <div
                                                        class="fw-semibold"
                                                    >

                                                        <?= e(
                                                            $item["product_name"]
                                                        ) ?>

                                                    </div>


                                                    <div
                                                        class="text-muted small"
                                                    >

                                                        Unit:
                                                        <?= e(
                                                            $item["unit"]
                                                        ) ?>

                                                    </div>

                                                </td>


                                                <td>

                                                    <?= e(
                                                        $item["sku"]
                                                    ) ?>

                                                </td>


                                                <td
                                                    class="text-center"
                                                >

                                                    <?= number_format(
                                                        (float) $item["quantity"],
                                                        2
                                                    ) ?>

                                                </td>


                                                <td
                                                    class="text-end"
                                                >

                                                    <?= money(
                                                        $item["unit_price"]
                                                    ) ?>

                                                </td>


                                                <td
                                                    class="text-end"
                                                >

                                                    <?= money(
                                                        $item["discount"]
                                                    ) ?>

                                                </td>


                                                <td
                                                    class="text-end fw-semibold pe-4"
                                                >

                                                    <?= money(
                                                        $item["total"]
                                                    ) ?>

                                                </td>

                                            </tr>


                                            <?php
                                            $itemNumber++;
                                            ?>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>



                <!-- ======================================================
                     PAYMENT + TOTAL
                     ====================================================== -->

                <div
                    class="row g-4 mb-4"
                >


                    <!-- PAYMENT -->

                    <div
                        class="col-lg-6"
                    >

                        <div
                            class="card border-0 shadow-sm h-100"
                        >

                            <div
                                class="card-header bg-white border-0 pt-4 px-4"
                            >

                                <h5
                                    class="fw-bold mb-0"
                                >

                                    <i
                                        class="bi bi-credit-card me-2 text-primary"
                                    ></i>

                                    Payment Information

                                </h5>

                            </div>


                            <div
                                class="card-body px-4 pb-4"
                            >

                                <div
                                    class="d-flex justify-content-between border-bottom py-3"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Payment Method
                                    </span>

                                    <span
                                        class="badge text-bg-primary px-3 py-2"
                                    >

                                        <?= e(
                                            $paymentMethodLabel
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="d-flex justify-content-between border-bottom py-3"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Amount
                                    </span>

                                    <strong>

                                        <?= money(
                                            $paymentAmount
                                        ) ?>

                                    </strong>

                                </div>


                                <?php if (
                                    $paymentMethod === "cash"
                                ): ?>

                                    <div
                                        class="d-flex justify-content-between border-bottom py-3"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Cash Received
                                        </span>

                                        <strong>

                                            <?= money(
                                                $cashReceived
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div
                                        class="d-flex justify-content-between border-bottom py-3"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Change
                                        </span>

                                        <strong
                                            class="text-success"
                                        >

                                            <?= money(
                                                $changeAmount
                                            ) ?>

                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <div
                                    class="d-flex justify-content-between py-3"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Payment Date
                                    </span>

                                    <span>

                                        <?= e(
                                            $paymentDateFormatted
                                        ) ?>

                                    </span>

                                </div>


                                <?php if (
                                    !empty(
                                        $payment["reference_number"]
                                    )
                                ): ?>

                                    <div
                                        class="d-flex justify-content-between border-top py-3"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Reference
                                        </span>

                                        <strong>

                                            <?= e(
                                                $payment["reference_number"]
                                            ) ?>

                                        </strong>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>



                    <!-- TOTALS -->

                    <div
                        class="col-lg-6"
                    >

                        <div
                            class="card border-0 shadow-sm h-100"
                        >

                            <div
                                class="card-header bg-white border-0 pt-4 px-4"
                            >

                                <h5
                                    class="fw-bold mb-0"
                                >

                                    <i
                                        class="bi bi-calculator me-2 text-primary"
                                    ></i>

                                    Sale Summary

                                </h5>

                            </div>


                            <div
                                class="card-body px-4 pb-4"
                            >

                                <div
                                    class="d-flex justify-content-between py-2"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Subtotal
                                    </span>

                                    <span>

                                        <?= money(
                                            $sale["subtotal"]
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="d-flex justify-content-between py-2"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Discount
                                    </span>

                                    <span
                                        class="text-danger"
                                    >

                                        -
                                        <?= money(
                                            $sale["discount"]
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="d-flex justify-content-between py-2"
                                >

                                    <span
                                        class="text-muted"
                                    >
                                        Tax
                                    </span>

                                    <span>

                                        <?= money(
                                            $sale["tax"]
                                        ) ?>

                                    </span>

                                </div>


                                <hr>


                                <div
                                    class="d-flex justify-content-between align-items-center"
                                >

                                    <span
                                        class="h5 fw-bold mb-0"
                                    >
                                        Grand Total
                                    </span>

                                    <span
                                        class="h4 fw-bold text-primary mb-0"
                                    >

                                        <?= money(
                                            $sale["total"]
                                        ) ?>

                                    </span>

                                </div>


                                <div
                                    class="bg-light rounded-3 p-3 mt-4"
                                >

                                    <div
                                        class="d-flex justify-content-between"
                                    >

                                        <span
                                            class="text-muted"
                                        >
                                            Paid
                                        </span>

                                        <strong>

                                            <?= money(
                                                $paymentAmount
                                            ) ?>

                                        </strong>

                                    </div>


                                    <?php if (
                                        $paymentMethod === "cash"
                                    ): ?>

                                        <div
                                            class="d-flex justify-content-between mt-2"
                                        >

                                            <span
                                                class="text-muted"
                                            >
                                                Change
                                            </span>

                                            <strong
                                                class="text-success"
                                            >

                                                <?= money(
                                                    $changeAmount
                                                ) ?>

                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <!-- ======================================================
                     NOTES
                     ====================================================== -->

                <?php if (
                    !empty(
                        trim(
                            $sale["notes"] ?? ""
                        )
                    )
                ): ?>

                    <div
                        class="card border-0 shadow-sm mb-4"
                    >

                        <div
                            class="card-header bg-white border-0 pt-4 px-4"
                        >

                            <h5
                                class="fw-bold mb-0"
                            >

                                <i
                                    class="bi bi-sticky me-2 text-primary"
                                ></i>

                                Notes

                            </h5>

                        </div>


                        <div
                            class="card-body px-4 pb-4"
                        >

                            <?= nl2br(
                                e(
                                    $sale["notes"]
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>



                <!-- ======================================================
                     ACTIONS
                     ====================================================== -->

                <div
                    class="card border-0 shadow-sm no-print"
                >

                    <div
                        class="card-body p-4"
                    >

                        <div
                            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
                        >

                            <div>

                                <h6
                                    class="fw-bold mb-1"
                                >

                                    Sale Actions

                                </h6>


                                <p
                                    class="text-muted small mb-0"
                                >

                                    Print an A4 invoice or a small customer bill.

                                </p>

                            </div>


                            <div
                                class="d-flex flex-wrap gap-2"
                            >

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                >

                                    <i
                                        class="bi bi-cart3 me-1"
                                    ></i>

                                    Back to POS

                                </a>


                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    onclick="window.print()"
                                >

                                    <i
                                        class="bi bi-file-earmark-text me-1"
                                    ></i>

                                    Print Invoice

                                </button>


                                <a
                                    href="receipt.php?id=<?= (int) $saleId ?>"
                                    target="_blank"
                                    class="btn btn-success"
                                >

                                    <i
                                        class="bi bi-receipt me-1"
                                    ></i>

                                    Print Small Bill

                                </a>


                                <?php if (
                                    $sale["status"] === "completed"
                                ): ?>

                                    <a
                                        href="cancel.php?id=<?= (int) $saleId ?>"
                                        class="btn btn-outline-danger"
                                        onclick="return confirm('Are you sure you want to cancel this sale? This will reverse the sold stock.');"
                                    >

                                        <i
                                            class="bi bi-x-circle me-1"
                                        ></i>

                                        Cancel Sale

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>


            </div>

        </div>

    </main>

</div>


<?php include "../includes/footer.php"; ?>


<style>

/*
|--------------------------------------------------------------------------
| Invoice
|--------------------------------------------------------------------------
*/

.invoice-container {
    max-width: 1400px;

    margin: 0 auto;
}


/*
|--------------------------------------------------------------------------
| Cards
|--------------------------------------------------------------------------
*/

.invoice-container .card {
    border-radius: 0.75rem;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.invoice-container .table th {

    font-size: 0.8rem;

    text-transform: uppercase;

    letter-spacing: 0.03em;

    white-space: nowrap;
}


.invoice-container .table td {

    vertical-align: middle;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (
    max-width: 767.98px
) {

    .invoice-container .table {

        min-width: 850px;

    }

}


/*
|--------------------------------------------------------------------------
| A4 Print
|--------------------------------------------------------------------------
*/

@media print {

    @page {

        size: A4;

        margin: 12mm;

    }


    body {

        background: #ffffff !important;

    }


    .navbar,
    .sidebar,
    .offcanvas,
    .btn,
    .alert,
    .no-print {

        display: none !important;

    }


    .main-wrapper {

        margin-left: 0 !important;

    }


    .main-content {

        width: 100% !important;

        padding: 0 !important;

    }


    .container-fluid {

        padding: 0 !important;

    }


    .invoice-container {

        max-width: 100% !important;

    }


    .card {

        box-shadow: none !important;

        border:
            1px solid #dee2e6 !important;

        break-inside: avoid;

    }


    .table-responsive {

        overflow: visible !important;

    }


    .table {

        width: 100% !important;

    }


    a {

        color: #000 !important;

        text-decoration: none !important;

    }

}

</style>