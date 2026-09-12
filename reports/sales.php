<?php

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$pageTitle = "Sales Report";


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
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


function money($value)
{
    return number_format(
        (float) $value,
        2
    );
}


function paymentMethodLabel($method)
{
    if (!$method || $method === '-') {
        return "No Payment";
    }

    $method = strtolower(trim($method));

    switch ($method) {

        case "cash":
            return "Cash";

        case "card":
            return "Card";

        case "credit":
            return "Credit";

        case "bank":
        case "bank_transfer":
        case "bank transfer":
            return "Bank Transfer";

        case "qr":
        case "qr_payment":
            return "QR Payment";

        default:
            return ucwords(
                str_replace(
                    "_",
                    " ",
                    $method
                )
            );
    }
}


function paymentMethodIcon($method)
{
    if (!$method || $method === '-') {
        return "bi-question-circle";
    }

    $method = strtolower(trim($method));

    switch ($method) {

        case "cash":
            return "bi-cash-stack";

        case "card":
            return "bi-credit-card";

        case "credit":
            return "bi-person-lines-fill";

        case "bank":
        case "bank_transfer":
        case "bank transfer":
            return "bi-bank";

        case "qr":
        case "qr_payment":
            return "bi-qr-code";

        default:
            return "bi-wallet2";
    }
}


/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

$dateFrom = $_GET['date_from']
    ?? date('Y-m-01');

$dateTo = $_GET['date_to']
    ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| VALIDATE DATES
|--------------------------------------------------------------------------
*/

$fromObject = DateTime::createFromFormat(
    'Y-m-d',
    $dateFrom
);

$toObject = DateTime::createFromFormat(
    'Y-m-d',
    $dateTo
);


if (
    !$fromObject ||
    $fromObject->format('Y-m-d') !== $dateFrom
) {
    $dateFrom = date('Y-m-01');
}


if (
    !$toObject ||
    $toObject->format('Y-m-d') !== $dateTo
) {
    $dateTo = date('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| SWAP DATES IF NEEDED
|--------------------------------------------------------------------------
*/

if ($dateFrom > $dateTo) {

    $temp = $dateFrom;

    $dateFrom = $dateTo;

    $dateTo = $temp;
}


/*
|--------------------------------------------------------------------------
| IMPORTANT DATETIME RANGE
|--------------------------------------------------------------------------
|
| If sale_date is DATETIME:
|
| From:
| 2026-09-01 00:00:00
|
| To:
| 2026-09-10 00:00:00
|
| We use:
|
| >= from
| < next day of to
|
| This includes the complete To date.
|
|--------------------------------------------------------------------------
*/

$dateFromDateTime =
    $dateFrom . " 00:00:00";


$dateToDateTime =
    date(
        'Y-m-d 00:00:00',
        strtotime(
            $dateTo . ' +1 day'
        )
    );


/*
|--------------------------------------------------------------------------
| INITIAL VALUES
|--------------------------------------------------------------------------
*/

$totalOrders = 0;
$totalSales = 0;
$totalDiscount = 0;
$totalTax = 0;
$cancelledOrders = 0;

$paymentSummary = [];
$cashierSummary = [];
$sales = [];


/*
|--------------------------------------------------------------------------
| SALES SUMMARY
|--------------------------------------------------------------------------
*/

$summaryStmt = $conn->prepare("
    SELECT

        COUNT(
            CASE
                WHEN status = 'completed'
                THEN 1
            END
        ) AS total_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN total
                    ELSE 0
                END
            ),
            0
        ) AS total_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN discount
                    ELSE 0
                END
            ),
            0
        ) AS total_discount,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN tax
                    ELSE 0
                END
            ),
            0
        ) AS total_tax,

        COUNT(
            CASE
                WHEN status = 'cancelled'
                THEN 1
            END
        ) AS cancelled_orders

    FROM sales

    WHERE sale_date >= ?
      AND sale_date < ?
");


if (!$summaryStmt) {

    die("Sales summary query preparation failed: "
        . $conn->error);
}


$summaryStmt->bind_param(
    "ss",
    $dateFromDateTime,
    $dateToDateTime
);


$summaryStmt->execute();


$summaryResult =
    $summaryStmt->get_result();


if ($summaryResult) {

    $summary =
        $summaryResult->fetch_assoc();

    if ($summary) {

        $totalOrders =
            (int) ($summary['total_orders'] ?? 0);

        $totalSales =
            (float) ($summary['total_sales'] ?? 0);

        $totalDiscount =
            (float) ($summary['total_discount'] ?? 0);

        $totalTax =
            (float) ($summary['total_tax'] ?? 0);

        $cancelledOrders =
            (int) ($summary['cancelled_orders'] ?? 0);
    }
}


$summaryStmt->close();


/*
|--------------------------------------------------------------------------
| PAYMENT SUMMARY
|--------------------------------------------------------------------------
|
| Only run if payments table exists.
|
|--------------------------------------------------------------------------
*/

$paymentsTableExists = false;


$tableCheckResult = $conn->query("
    SHOW TABLES LIKE 'payments'
");


if (
    $tableCheckResult &&
    $tableCheckResult->num_rows > 0
) {
    $paymentsTableExists = true;
}


if ($paymentsTableExists) {

    $paymentStmt = $conn->prepare("
        SELECT

            p.payment_method,

            COUNT(*) AS payment_count,

            COALESCE(
                SUM(p.amount),
                0
            ) AS payment_total

        FROM payments p

        INNER JOIN sales s
            ON s.id = p.sale_id

        WHERE s.sale_date >= ?
          AND s.sale_date < ?

          AND s.status = 'completed'

        GROUP BY p.payment_method

        ORDER BY payment_total DESC
    ");


    if ($paymentStmt) {

        $paymentStmt->bind_param(
            "ss",
            $dateFromDateTime,
            $dateToDateTime
        );


        $paymentStmt->execute();


        $paymentResult =
            $paymentStmt->get_result();


        while (
            $paymentResult &&
            $row = $paymentResult->fetch_assoc()
        ) {

            $paymentSummary[] = $row;
        }


        $paymentStmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| CASHIER SUMMARY
|--------------------------------------------------------------------------
*/

$cashierStmt = $conn->prepare("
    SELECT

        s.cashier_id,

        COALESCE(
            u.full_name,
            'Unknown'
        ) AS cashier_name,

        COUNT(s.id) AS order_count,

        COALESCE(
            SUM(s.total),
            0
        ) AS sales_total

    FROM sales s

    LEFT JOIN users u
        ON u.id = s.cashier_id

    WHERE s.sale_date >= ?
      AND s.sale_date < ?

      AND s.status = 'completed'

    GROUP BY
        s.cashier_id,
        u.full_name

    ORDER BY sales_total DESC
");


if ($cashierStmt) {

    $cashierStmt->bind_param(
        "ss",
        $dateFromDateTime,
        $dateToDateTime
    );


    $cashierStmt->execute();


    $cashierResult =
        $cashierStmt->get_result();


    while (
        $cashierResult &&
        $row = $cashierResult->fetch_assoc()
    ) {

        $cashierSummary[] = $row;
    }


    $cashierStmt->close();
}


/*
|--------------------------------------------------------------------------
| DETAILED SALES
|--------------------------------------------------------------------------
*/

if ($paymentsTableExists) {

    /*
    |--------------------------------------------------------------------------
    | WITH PAYMENT INFORMATION
    |--------------------------------------------------------------------------
    */

    $salesStmt = $conn->prepare("
        SELECT

            s.id,

            s.invoice_number,

            s.sale_date,

            s.subtotal,

            s.discount,

            s.tax,

            s.total,

            s.status,

            COALESCE(
                c.name,
                'Walk-in Customer'
            ) AS customer_name,

            COALESCE(
                u.full_name,
                'Unknown'
            ) AS cashier_name,

            COALESCE(
                (
                    SELECT
                        GROUP_CONCAT(
                            DISTINCT p.payment_method
                            ORDER BY p.payment_method
                            SEPARATOR ', '
                        )

                    FROM payments p

                    WHERE p.sale_id = s.id
                ),
                '-'
            ) AS payment_method

        FROM sales s

        LEFT JOIN customers c
            ON c.id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.cashier_id

        WHERE s.sale_date >= ?
          AND s.sale_date < ?

        ORDER BY
            s.sale_date DESC,
            s.id DESC
    ");
} else {

    /*
    |--------------------------------------------------------------------------
    | WITHOUT PAYMENTS TABLE
    |--------------------------------------------------------------------------
    |
    | This fallback allows the report to work even if the
    | payments table has not been created yet.
    |
    |--------------------------------------------------------------------------
    */

    $salesStmt = $conn->prepare("
        SELECT

            s.id,

            s.invoice_number,

            s.sale_date,

            s.subtotal,

            s.discount,

            s.tax,

            s.total,

            s.status,

            COALESCE(
                c.name,
                'Walk-in Customer'
            ) AS customer_name,

            COALESCE(
                u.full_name,
                'Unknown'
            ) AS cashier_name,

            '-' AS payment_method

        FROM sales s

        LEFT JOIN customers c
            ON c.id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.cashier_id

        WHERE s.sale_date >= ?
          AND s.sale_date < ?

        ORDER BY
            s.sale_date DESC,
            s.id DESC
    ");
}


if (!$salesStmt) {

    die("Sales details query preparation failed: "
        . $conn->error);
}


$salesStmt->bind_param(
    "ss",
    $dateFromDateTime,
    $dateToDateTime
);


$salesStmt->execute();


$salesResult =
    $salesStmt->get_result();


while (
    $salesResult &&
    $row = $salesResult->fetch_assoc()
) {

    $sales[] = $row;
}


$salesStmt->close();


/*
|--------------------------------------------------------------------------
| TOTAL SALES COUNT
|--------------------------------------------------------------------------
*/

$salesCount =
    count($sales);


/*
|--------------------------------------------------------------------------
| INCLUDE HEADER
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";

?>


<div class="main-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>


    <main class="main-content">

        <?php require_once "../includes/navbar.php"; ?>


        <div class="container-fluid py-4">


            <!-- ==========================================================
                 PAGE HEADER
            =========================================================== -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div>

                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-bar-chart-line me-2"></i>
                        Sales Report
                    </h2>

                    <p class="text-muted mb-0">
                        View sales performance and transaction details.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary">

                    <i
                        class="
                                bi
                                bi-arrow-left
                                me-1
                            "></i>

                    Reports

                </a>


                <a
                    href="sales_pdf.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
                    target="_blank"
                    class="btn btn-dark no-print">
                    <i class="bi bi-file-earmark-pdf me-2"></i>
                    Print / PDF Report
                </a>

            </div>


            <!-- ==========================================================
                 DATE FILTER
            =========================================================== -->

            <div class="card border-0 shadow-sm mb-4 no-print">

                <div class="card-body">

                    <form
                        method="GET"
                        action="sales.php"
                        class="row g-3 align-items-end">

                        <div class="col-md-4">

                            <label
                                for="date_from"
                                class="form-label fw-semibold">
                                From Date
                            </label>

                            <input
                                type="date"
                                id="date_from"
                                name="date_from"
                                class="form-control"
                                value="<?= e($dateFrom) ?>"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label
                                for="date_to"
                                class="form-label fw-semibold">
                                To Date
                            </label>

                            <input
                                type="date"
                                id="date_to"
                                name="date_to"
                                class="form-control"
                                value="<?= e($dateTo) ?>"
                                required>

                        </div>


                        <div class="col-md-4">

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                <i class="bi bi-search me-2"></i>

                                Generate Report

                            </button>

                        </div>

                    </form>

                </div>

            </div>


            <!-- ==========================================================
                 REPORT PERIOD
            =========================================================== -->

            <div class="alert alert-light border mb-4">

                <div class="d-flex align-items-center">

                    <i class="bi bi-calendar3 fs-5 me-2"></i>

                    <div>

                        <strong>Report Period:</strong>

                        <?= e($dateFrom) ?>

                        <span class="mx-2">
                            →
                        </span>

                        <?= e($dateTo) ?>

                    </div>

                </div>

            </div>


            <!-- ==========================================================
                 SUMMARY CARDS
            =========================================================== -->

            <div class="row g-3 mb-4">


                <!-- TOTAL ORDERS -->

                <div class="col-xl-3 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        Completed Orders
                                    </div>

                                    <h3 class="fw-bold mb-0 mt-1">
                                        <?= number_format($totalOrders) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-primary-subtle text-primary rounded-circle p-3">

                                    <i class="bi bi-cart-check fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TOTAL SALES -->

                <div class="col-xl-3 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        Total Sales
                                    </div>

                                    <h3 class="fw-bold mb-0 mt-1">
                                        LKR <?= money($totalSales) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-success-subtle text-success rounded-circle p-3">

                                    <i class="bi bi-cash-stack fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- DISCOUNT -->

                <div class="col-xl-3 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        Total Discount
                                    </div>

                                    <h3 class="fw-bold mb-0 mt-1">
                                        LKR <?= money($totalDiscount) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-warning-subtle text-warning rounded-circle p-3">

                                    <i class="bi bi-percent fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- CANCELLED -->

                <div class="col-xl-3 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        Cancelled Orders
                                    </div>

                                    <h3 class="fw-bold mb-0 mt-1">
                                        <?= number_format($cancelledOrders) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-danger-subtle text-danger rounded-circle p-3">

                                    <i class="bi bi-x-circle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


            </div>


            <!-- ==========================================================
                 TAX / DISCOUNT INFORMATION
            =========================================================== -->

            <div class="row g-3 mb-4">


                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted small">
                                Total Tax
                            </div>

                            <div class="fs-4 fw-bold text-primary">
                                LKR <?= money($totalTax) ?>
                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted small">
                                Transactions Found
                            </div>

                            <div class="fs-4 fw-bold">
                                <?= number_format($salesCount) ?>
                            </div>

                        </div>

                    </div>

                </div>


            </div>


            <!-- ==========================================================
                 PAYMENT SUMMARY
            =========================================================== -->

            <?php if (!empty($paymentSummary)): ?>

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white border-0 py-3">

                        <h5 class="fw-bold mb-0">

                            <i class="bi bi-wallet2 me-2"></i>

                            Payment Summary

                        </h5>

                    </div>


                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Payment Method
                                    </th>

                                    <th class="text-center">
                                        Transactions
                                    </th>

                                    <th class="text-end">
                                        Amount
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $paymentSummary
                                    as $payment
                                ): ?>

                                    <tr>

                                        <td>

                                            <i
                                                class="bi <?= e(
                                                                paymentMethodIcon(
                                                                    $payment['payment_method']
                                                                )
                                                            ) ?> me-2"></i>

                                            <?= e(
                                                paymentMethodLabel(
                                                    $payment['payment_method']
                                                )
                                            ) ?>

                                        </td>


                                        <td class="text-center">

                                            <?= number_format(
                                                (int) $payment['payment_count']
                                            ) ?>

                                        </td>


                                        <td class="text-end fw-semibold">

                                            LKR
                                            <?= money(
                                                $payment['payment_total']
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endif; ?>


            <!-- ==========================================================
                 CASHIER SUMMARY
            =========================================================== -->

            <?php if (!empty($cashierSummary)): ?>

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white border-0 py-3">

                        <h5 class="fw-bold mb-0">

                            <i class="bi bi-person-badge me-2"></i>

                            Cashier Summary

                        </h5>

                    </div>


                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Cashier
                                    </th>

                                    <th class="text-center">
                                        Orders
                                    </th>

                                    <th class="text-end">
                                        Sales
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $cashierSummary
                                    as $cashier
                                ): ?>

                                    <tr>

                                        <td>

                                            <i
                                                class="bi bi-person-circle me-2"></i>

                                            <?= e(
                                                $cashier['cashier_name']
                                            ) ?>

                                        </td>


                                        <td class="text-center">

                                            <?= number_format(
                                                (int) $cashier['order_count']
                                            ) ?>

                                        </td>


                                        <td class="text-end fw-semibold">

                                            LKR
                                            <?= money(
                                                $cashier['sales_total']
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endif; ?>


            <!-- ==========================================================
                 SALES TRANSACTIONS
            =========================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div
                    class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">

                    <h5 class="fw-bold mb-0">

                        <i class="bi bi-receipt me-2"></i>

                        Sales Transactions

                    </h5>


                    <span class="badge bg-primary">

                        <?= number_format($salesCount) ?>

                        Transactions

                    </span>

                </div>


                <?php if (empty($sales)): ?>

                    <div class="card-body text-center py-5">

                        <div class="mb-3">

                            <i
                                class="bi bi-receipt-cutoff text-muted"
                                style="font-size: 3rem;"></i>

                        </div>


                        <h5 class="fw-semibold">
                            No Sales Found
                        </h5>


                        <p class="text-muted mb-0">

                            There are no sales transactions
                            between

                            <strong>
                                <?= e($dateFrom) ?>
                            </strong>

                            and

                            <strong>
                                <?= e($dateTo) ?>
                            </strong>.

                        </p>

                    </div>

                <?php else: ?>


                    <div class="table-responsive">

                        <table
                            class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Invoice
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Cashier
                                    </th>

                                    <th>
                                        Payment
                                    </th>

                                    <th class="text-end">
                                        Subtotal
                                    </th>

                                    <th class="text-end">
                                        Discount
                                    </th>

                                    <th class="text-end">
                                        Tax
                                    </th>

                                    <th class="text-end">
                                        Total
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th class="text-center no-print">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $sales
                                    as $sale
                                ): ?>


                                    <?php

                                    $status =
                                        strtolower(
                                            trim(
                                                $sale['status'] ?? ''
                                            )
                                        );


                                    $statusClass =
                                        'secondary';

                                    $statusIcon =
                                        'bi-question-circle';


                                    if (
                                        $status === 'completed'
                                    ) {

                                        $statusClass =
                                            'success';

                                        $statusIcon =
                                            'bi-check-circle';
                                    } elseif (
                                        $status === 'cancelled'
                                    ) {

                                        $statusClass =
                                            'danger';

                                        $statusIcon =
                                            'bi-x-circle';
                                    } elseif (
                                        $status === 'pending'
                                    ) {

                                        $statusClass =
                                            'warning';

                                        $statusIcon =
                                            'bi-clock';
                                    }

                                    ?>


                                    <tr>


                                        <!-- INVOICE -->

                                        <td>

                                            <span
                                                class="fw-semibold text-primary">

                                                <?= e(
                                                    $sale['invoice_number']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- DATE -->

                                        <td>

                                            <?php

                                            $saleDate =
                                                !empty($sale['sale_date'])
                                                ? strtotime(
                                                    $sale['sale_date']
                                                )
                                                : false;

                                            ?>

                                            <?php if ($saleDate): ?>

                                                <div class="fw-semibold">

                                                    <?= date(
                                                        'd M Y',
                                                        $saleDate
                                                    ) ?>

                                                </div>

                                                <small
                                                    class="text-muted">

                                                    <?= date(
                                                        'h:i A',
                                                        $saleDate
                                                    ) ?>

                                                </small>

                                            <?php else: ?>

                                                -

                                            <?php endif; ?>

                                        </td>


                                        <!-- CUSTOMER -->

                                        <td>

                                            <span>

                                                <?= e(
                                                    $sale['customer_name']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- CASHIER -->

                                        <td>

                                            <?= e(
                                                $sale['cashier_name']
                                            ) ?>

                                        </td>


                                        <!-- PAYMENT -->

                                        <td>

                                            <?php

                                            $paymentMethod =
                                                $sale['payment_method']
                                                ?? '-';

                                            ?>

                                            <?php if (
                                                $paymentMethod !== '-'
                                            ): ?>

                                                <span
                                                    class="badge bg-light text-dark border">

                                                    <i
                                                        class="bi <?= e(
                                                                        paymentMethodIcon(
                                                                            $paymentMethod
                                                                        )
                                                                    ) ?> me-1"></i>

                                                    <?= e(
                                                        paymentMethodLabel(
                                                            $paymentMethod
                                                        )
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="text-muted">
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- SUBTOTAL -->

                                        <td class="text-end">

                                            LKR
                                            <?= money(
                                                $sale['subtotal']
                                            ) ?>

                                        </td>


                                        <!-- DISCOUNT -->

                                        <td class="text-end">

                                            LKR
                                            <?= money(
                                                $sale['discount']
                                            ) ?>

                                        </td>


                                        <!-- TAX -->

                                        <td class="text-end">

                                            LKR
                                            <?= money(
                                                $sale['tax']
                                            ) ?>

                                        </td>


                                        <!-- TOTAL -->

                                        <td
                                            class="text-end fw-bold">

                                            LKR
                                            <?= money(
                                                $sale['total']
                                            ) ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="badge text-bg-<?= e(
                                                                            $statusClass
                                                                        ) ?>">

                                                <i
                                                    class="bi <?= e(
                                                                    $statusIcon
                                                                ) ?> me-1"></i>

                                                <?= e(
                                                    ucfirst(
                                                        $status ?: 'Unknown'
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- ACTION -->

                                        <td
                                            class="text-center no-print">

                                            <a
                                                href="../sales/view.php?id=<?= (int) $sale['id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="View Sale">

                                                <i
                                                    class="bi bi-eye"></i>

                                            </a>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>

                            </tbody>


                            <!-- TOTAL -->

                            <tfoot class="table-light">

                                <tr>

                                    <th colspan="5" class="text-end">
                                        Report Total
                                    </th>


                                    <th class="text-end">

                                        LKR
                                        <?= money(
                                            array_sum(
                                                array_column(
                                                    $sales,
                                                    'subtotal'
                                                )
                                            )
                                        ) ?>

                                    </th>


                                    <th class="text-end">

                                        LKR
                                        <?= money(
                                            array_sum(
                                                array_column(
                                                    $sales,
                                                    'discount'
                                                )
                                            )
                                        ) ?>

                                    </th>


                                    <th class="text-end">

                                        LKR
                                        <?= money(
                                            array_sum(
                                                array_column(
                                                    $sales,
                                                    'tax'
                                                )
                                            )
                                        ) ?>

                                    </th>


                                    <th class="text-end">

                                        LKR
                                        <?= money(
                                            array_sum(
                                                array_column(
                                                    $sales,
                                                    'total'
                                                )
                                            )
                                        ) ?>

                                    </th>


                                    <th colspan="2"></th>

                                </tr>

                            </tfoot>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


        </div>

    </main>

</div>


<!-- ==============================================================
     PRINT CSS
=============================================================== -->

<style>
    @media print {

        body {
            background: #ffffff !important;
        }

        .navbar,
        .sidebar,
        .offcanvas,
        .no-print,
        footer {
            display: none !important;
        }

        .main-wrapper {
            display: block !important;
        }

        .main-content {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }

        .table {
            font-size: 11px;
        }

        .table th,
        .table td {
            padding: 6px !important;
        }

        @page {
            size: landscape;
            margin: 10mm;
        }

    }
</style>


<?php

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

require_once "../includes/footer.php";

?>