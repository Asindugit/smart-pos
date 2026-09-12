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
| SWAP DATES
|--------------------------------------------------------------------------
*/

if ($dateFrom > $dateTo) {

    $temp = $dateFrom;

    $dateFrom = $dateTo;

    $dateTo = $temp;
}


/*
|--------------------------------------------------------------------------
| DATETIME RANGE
|--------------------------------------------------------------------------
*/

$dateFromDateTime =
    $dateFrom . " 00:00:00";


$dateToDateTime =
    date(
        'Y-m-d 00:00:00',
        strtotime($dateTo . ' +1 day')
    );


/*
|--------------------------------------------------------------------------
| REPORT SUMMARY
|--------------------------------------------------------------------------
*/

$totalOrders = 0;
$totalSales = 0;
$totalDiscount = 0;
$totalTax = 0;
$cancelledOrders = 0;


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
    die(
        "Summary query failed: " .
        $conn->error
    );
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
            (int) $summary['total_orders'];

        $totalSales =
            (float) $summary['total_sales'];

        $totalDiscount =
            (float) $summary['total_discount'];

        $totalTax =
            (float) $summary['total_tax'];

        $cancelledOrders =
            (int) $summary['cancelled_orders'];
    }
}


$summaryStmt->close();


/*
|--------------------------------------------------------------------------
| CHECK PAYMENTS TABLE
|--------------------------------------------------------------------------
*/

$paymentsTableExists = false;


$tableCheck =
    $conn->query(
        "SHOW TABLES LIKE 'payments'"
    );


if (
    $tableCheck &&
    $tableCheck->num_rows > 0
) {
    $paymentsTableExists = true;
}


/*
|--------------------------------------------------------------------------
| PAYMENT SUMMARY
|--------------------------------------------------------------------------
*/

$paymentSummary = [];


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

$cashierSummary = [];


$cashierStmt = $conn->prepare("
    SELECT

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
| SALES DETAILS
|--------------------------------------------------------------------------
*/

$sales = [];


if ($paymentsTableExists) {

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

    die(
        "Sales query failed: " .
        $conn->error
    );
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
| CALCULATE TOTALS FROM DISPLAYED SALES
|--------------------------------------------------------------------------
*/

$reportSubtotal = 0;
$reportDiscount = 0;
$reportTax = 0;
$reportTotal = 0;


foreach ($sales as $sale) {

    $reportSubtotal +=
        (float) $sale['subtotal'];

    $reportDiscount +=
        (float) $sale['discount'];

    $reportTax +=
        (float) $sale['tax'];

    $reportTotal +=
        (float) $sale['total'];
}


$generatedDate =
    date('d M Y, h:i A');


?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        SmartPOS - Sales Report
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 20px;

            background: #f1f5f9;

            color: #111827;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 12px;
        }


        .report {

            max-width: 1400px;

            margin: 0 auto;

            background: #ffffff;

            padding: 28px;

            box-shadow:
                0 2px 12px
                rgba(0, 0, 0, 0.08);
        }


        /* HEADER */

        .report-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            border-bottom:
                3px solid #111827;

            padding-bottom: 18px;

            margin-bottom: 20px;
        }


        .company-name {

            font-size: 28px;

            font-weight: 800;

            letter-spacing: 0.5px;

            margin-bottom: 4px;
        }


        .report-title {

            font-size: 18px;

            font-weight: 700;

            margin-top: 8px;
        }


        .report-subtitle {

            color: #64748b;

            margin-top: 5px;
        }


        .report-info {

            text-align: right;

            color: #475569;

            line-height: 1.7;
        }


        .report-info strong {

            color: #111827;
        }


        /* SUMMARY */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 10px;

            margin-bottom: 22px;
        }


        .summary-box {

            border:
                1px solid #e2e8f0;

            padding: 13px;

            border-radius: 6px;

            background: #f8fafc;
        }


        .summary-label {

            color: #64748b;

            font-size: 10px;

            text-transform:
                uppercase;

            font-weight: 700;

            letter-spacing:
                0.5px;

            margin-bottom: 6px;
        }


        .summary-value {

            font-size: 17px;

            font-weight: 800;
        }


        /* SECTION */

        .section {

            margin-top: 22px;

            margin-bottom: 22px;
        }


        .section-title {

            font-size: 14px;

            font-weight: 800;

            text-transform:
                uppercase;

            border-bottom:
                2px solid #111827;

            padding-bottom: 7px;

            margin-bottom: 10px;
        }


        /* SMALL TABLE */

        .small-table {

            width: 100%;

            border-collapse:
                collapse;
        }


        .small-table th {

            background: #f1f5f9;

            font-weight: 700;

            text-align: left;
        }


        .small-table th,
        .small-table td {

            border:
                1px solid #e2e8f0;

            padding: 7px 9px;
        }


        .text-right {
            text-align: right;
        }


        .text-center {
            text-align: center;
        }


        /* SALES TABLE */

        .sales-table {

            width: 100%;

            border-collapse:
                collapse;

            margin-top: 5px;
        }


        .sales-table th {

            background: #111827;

            color: #ffffff;

            font-size: 9px;

            text-transform:
                uppercase;

            letter-spacing:
                0.3px;

            padding: 8px 5px;

            text-align: left;
        }


        .sales-table td {

            border-bottom:
                1px solid #e2e8f0;

            padding: 7px 5px;

            vertical-align:
                middle;

            font-size: 9px;
        }


        .sales-table tr:nth-child(even) td {

            background: #f8fafc;
        }


        .sales-table .amount {

            text-align: right;

            white-space:
                nowrap;
        }


        .invoice {

            font-weight: 700;
        }


        .status {

            display: inline-block;

            padding: 3px 7px;

            border-radius: 20px;

            font-size: 8px;

            font-weight: 700;

            text-transform:
                uppercase;
        }


        .status-completed {

            background: #dcfce7;

            color: #166534;
        }


        .status-cancelled {

            background: #fee2e2;

            color: #991b1b;
        }


        .status-pending {

            background: #fef3c7;

            color: #92400e;
        }


        .status-other {

            background: #e2e8f0;

            color: #334155;
        }


        .sales-total-row td {

            background: #f1f5f9 !important;

            border-top:
                2px solid #111827;

            font-weight: 800;

            padding-top: 10px;

            padding-bottom: 10px;
        }


        /* FOOTER */

        .report-footer {

            border-top:
                2px solid #111827;

            margin-top: 25px;

            padding-top: 12px;

            display: flex;

            justify-content:
                space-between;

            color: #64748b;

            font-size: 10px;
        }


        .no-data {

            text-align: center;

            padding: 25px;

            color: #64748b;
        }


        /* PRINT */

        @media print {

            body {

                background:
                    #ffffff;

                padding: 0;
            }


            .report {

                max-width: none;

                box-shadow: none;

                padding: 0;
            }


            .no-print {

                display: none !important;
            }


            @page {

                size: A4 landscape;

                margin: 10mm;
            }


            .sales-table {

                page-break-inside:
                    auto;
            }


            .sales-table tr {

                page-break-inside:
                    avoid;

                page-break-after:
                    auto;
            }


            .sales-table thead {

                display: table-header-group;
            }


            .section {

                page-break-inside:
                    auto;
            }


            .summary-grid {

                page-break-inside:
                    avoid;
            }


            .report-header {

                page-break-inside:
                    avoid;
            }

        }

    </style>

</head>


<body>


<div class="report">


    <!-- ==========================================================
         HEADER
    =========================================================== -->

    <div class="report-header">

        <div>

            <div class="company-name">
                SmartPOS
            </div>

            <div class="report-title">
                SALES REPORT
            </div>

            <div class="report-subtitle">

                Sales performance and transaction report

            </div>

        </div>


        <div class="report-info">

            <div>
                <strong>Report Period:</strong>
                <?= e($dateFrom) ?>
                -
                <?= e($dateTo) ?>
            </div>

            <div>
                <strong>Generated:</strong>
                <?= e($generatedDate) ?>
            </div>

            <div>
                <strong>Generated By:</strong>
                <?= e(
                    $_SESSION['user_name']
                    ?? 'System User'
                ) ?>
            </div>

        </div>

    </div>


    <!-- ==========================================================
         SUMMARY
    =========================================================== -->

    <div class="summary-grid">


        <div class="summary-box">

            <div class="summary-label">
                Completed Orders
            </div>

            <div class="summary-value">
                <?= number_format($totalOrders) ?>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-label">
                Total Sales
            </div>

            <div class="summary-value">
                LKR <?= money($totalSales) ?>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-label">
                Discount
            </div>

            <div class="summary-value">
                LKR <?= money($totalDiscount) ?>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-label">
                Tax
            </div>

            <div class="summary-value">
                LKR <?= money($totalTax) ?>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-label">
                Cancelled Orders
            </div>

            <div class="summary-value">
                <?= number_format($cancelledOrders) ?>
            </div>

        </div>


    </div>


    <!-- ==========================================================
         PAYMENT SUMMARY
    =========================================================== -->

    <?php if (!empty($paymentSummary)): ?>

        <div class="section">

            <div class="section-title">
                Payment Summary
            </div>


            <table class="small-table">

                <thead>

                    <tr>

                        <th>
                            Payment Method
                        </th>

                        <th class="text-center">
                            Transactions
                        </th>

                        <th class="text-right">
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
                                <?= e(
                                    paymentMethodLabel(
                                        $payment[
                                            'payment_method'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td class="text-center">

                                <?= number_format(
                                    (int)
                                    $payment[
                                        'payment_count'
                                    ]
                                ) ?>

                            </td>

                            <td class="text-right">

                                LKR
                                <?= money(
                                    $payment[
                                        'payment_total'
                                    ]
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         CASHIER SUMMARY
    =========================================================== -->

    <?php if (!empty($cashierSummary)): ?>

        <div class="section">

            <div class="section-title">
                Cashier Summary
            </div>


            <table class="small-table">

                <thead>

                    <tr>

                        <th>
                            Cashier
                        </th>

                        <th class="text-center">
                            Orders
                        </th>

                        <th class="text-right">
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
                                <?= e(
                                    $cashier[
                                        'cashier_name'
                                    ]
                                ) ?>
                            </td>

                            <td class="text-center">

                                <?= number_format(
                                    (int)
                                    $cashier[
                                        'order_count'
                                    ]
                                ) ?>

                            </td>

                            <td class="text-right">

                                LKR
                                <?= money(
                                    $cashier[
                                        'sales_total'
                                    ]
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         SALES DETAILS
    =========================================================== -->

    <div class="section">

        <div class="section-title">
            Sales Transactions
        </div>


        <?php if (empty($sales)): ?>

            <div class="no-data">

                No sales transactions found
                for the selected period.

            </div>

        <?php else: ?>


            <table class="sales-table">

                <thead>

                    <tr>

                        <th>
                            Invoice
                        </th>

                        <th>
                            Date / Time
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

                        <th class="amount">
                            Subtotal
                        </th>

                        <th class="amount">
                            Discount
                        </th>

                        <th class="amount">
                            Tax
                        </th>

                        <th class="amount">
                            Total
                        </th>

                        <th>
                            Status
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
                                    $sale['status']
                                    ?? ''
                                )
                            );


                        if (
                            $status ===
                            'completed'
                        ) {

                            $statusClass =
                                'status-completed';

                        } elseif (
                            $status ===
                            'cancelled'
                        ) {

                            $statusClass =
                                'status-cancelled';

                        } elseif (
                            $status ===
                            'pending'
                        ) {

                            $statusClass =
                                'status-pending';

                        } else {

                            $statusClass =
                                'status-other';
                        }

                        ?>


                        <tr>


                            <!-- INVOICE -->

                            <td>

                                <span
                                    class="invoice"
                                >

                                    <?= e(
                                        $sale[
                                            'invoice_number'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?php

                                $timestamp =
                                    strtotime(
                                        $sale[
                                            'sale_date'
                                        ]
                                    );

                                ?>

                                <?php if ($timestamp): ?>

                                    <?= date(
                                        'd/m/Y',
                                        $timestamp
                                    ) ?>

                                    <br>

                                    <span
                                        style="color:#64748b;"
                                    >

                                        <?= date(
                                            'h:i A',
                                            $timestamp
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- CUSTOMER -->

                            <td>
                                <?= e(
                                    $sale[
                                        'customer_name'
                                    ]
                                ) ?>
                            </td>


                            <!-- CASHIER -->

                            <td>
                                <?= e(
                                    $sale[
                                        'cashier_name'
                                    ]
                                ) ?>
                            </td>


                            <!-- PAYMENT -->

                            <td>
                                <?= e(
                                    paymentMethodLabel(
                                        $sale[
                                            'payment_method'
                                        ]
                                    )
                                ) ?>
                            </td>


                            <!-- SUBTOTAL -->

                            <td class="amount">

                                <?= money(
                                    $sale[
                                        'subtotal'
                                    ]
                                ) ?>

                            </td>


                            <!-- DISCOUNT -->

                            <td class="amount">

                                <?= money(
                                    $sale[
                                        'discount'
                                    ]
                                ) ?>

                            </td>


                            <!-- TAX -->

                            <td class="amount">

                                <?= money(
                                    $sale[
                                        'tax'
                                    ]
                                ) ?>

                            </td>


                            <!-- TOTAL -->

                            <td
                                class="amount"
                            >

                                <strong>

                                    <?= money(
                                        $sale[
                                            'total'
                                        ]
                                    ) ?>

                                </strong>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= e(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $status
                                            ?: 'Unknown'
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    <!-- REPORT TOTAL -->

                    <tr class="sales-total-row">

                        <td
                            colspan="5"
                            style="text-align:right;"
                        >

                            REPORT TOTAL

                        </td>


                        <td class="amount">

                            <?= money(
                                $reportSubtotal
                            ) ?>

                        </td>


                        <td class="amount">

                            <?= money(
                                $reportDiscount
                            ) ?>

                        </td>


                        <td class="amount">

                            <?= money(
                                $reportTax
                            ) ?>

                        </td>


                        <td class="amount">

                            <?= money(
                                $reportTotal
                            ) ?>

                        </td>


                        <td></td>

                    </tr>


                </tbody>

            </table>


        <?php endif; ?>

    </div>


    <!-- ==========================================================
         FOOTER
    =========================================================== -->

    <div class="report-footer">

        <div>
            SmartPOS Sales Management System
        </div>

        <div>
            This is a computer-generated report.
        </div>

    </div>


</div>


<!-- ==============================================================
     AUTO PRINT
=============================================================== -->

<script>

window.addEventListener(
    "load",
    function () {

        setTimeout(
            function () {

                window.print();

            },
            500
        );

    }
);

</script>


</body>

</html>