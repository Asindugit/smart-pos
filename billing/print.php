<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$saleId =
    isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

$type =
    $_GET["type"] ?? "receipt";


if ($saleId <= 0) {
    die("Invalid sale ID.");
}


if (
    !in_array(
        $type,
        ["receipt", "a4"],
        true
    )
) {
    $type = "receipt";
}


/*
|--------------------------------------------------------------------------
| Sale
|--------------------------------------------------------------------------
*/

$saleStmt = $conn->prepare(
    "
    SELECT
        s.id,
        s.invoice_number,
        s.sale_date,
        s.subtotal,
        s.discount,
        s.tax,
        s.total,
        s.payment_method,
        s.status,

        c.name AS customer_name,
        c.phone AS customer_phone,

        u.full_name AS cashier_name

    FROM sales s

    LEFT JOIN customers c
        ON c.id = s.customer_id

    LEFT JOIN users u
        ON u.id = s.cashier_id

    WHERE s.id = ?

    LIMIT 1
    "
);


if (!$saleStmt) {
    die("Unable to prepare sale query.");
}


$saleStmt->bind_param(
    "i",
    $saleId
);


$saleStmt->execute();


$saleResult =
    $saleStmt->get_result();


if ($saleResult->num_rows === 0) {
    die("Sale not found.");
}


$sale =
    $saleResult->fetch_assoc();


$saleStmt->close();


/*
|--------------------------------------------------------------------------
| Sale Items
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare(
    "
    SELECT
        si.quantity,
        si.unit_price,
        si.discount,
        si.total,

        p.name AS product_name,
        p.sku

    FROM sale_items si

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE si.sale_id = ?

    ORDER BY si.id ASC
    "
);


if (!$itemStmt) {
    die("Unable to prepare item query.");
}


$itemStmt->bind_param(
    "i",
    $saleId
);


$itemStmt->execute();


$itemResult =
    $itemStmt->get_result();


$items = [];


while (
    $row =
    $itemResult->fetch_assoc()
) {

    $items[] = $row;
}


$itemStmt->close();


/*
|--------------------------------------------------------------------------
| Helpers
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


function numberValue($value)
{
    return number_format(
        (float) $value,
        2
    );
}


$isReceipt =
    $type === "receipt";


/*
|--------------------------------------------------------------------------
| Receipt Information
|--------------------------------------------------------------------------
|
| Change these details according to your business.
|
*/

$businessName =
    "SmartPOS";

$businessTagline =
    "Point of Sale System";

$businessPhone =
    "Tel: 077 123 4567";

$businessAddress =
    "Your Business Address";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>

        <?= $isReceipt
            ? "Receipt"
            : "Invoice"
        ?>

        -

        <?= e(
            $sale["invoice_number"]
        ) ?>

    </title>


    <style>
        /*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;
        }


        body {

            background: #e5e7eb;

            color: #111827;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        /*
|--------------------------------------------------------------------------
| PRINT CONTROLS
|--------------------------------------------------------------------------
*/

        .print-controls {

            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 9999;

            display: flex;

            gap: 8px;
        }


        .print-controls button {

            border: none;

            border-radius: 7px;

            padding: 10px 15px;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;
        }


        .print-button {

            background: #111827;

            color: #ffffff;
        }


        .print-button:hover {

            background: #000000;
        }


        .close-button {

            background: #ffffff;

            color: #111827;

            border: 1px solid #d1d5db !important;
        }


        .close-button:hover {

            background: #f3f4f6;
        }


        /*
|--------------------------------------------------------------------------
| 80MM RECEIPT
|--------------------------------------------------------------------------
*/

        .receipt {

            width: 80mm;

            margin: 30px auto;

            padding: 5mm 4mm;

            background: #ffffff;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 11px;

            line-height: 1.35;

            color: #111111;

            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.12);
        }


        /*
|--------------------------------------------------------------------------
| RECEIPT HEADER
|--------------------------------------------------------------------------
*/

        .receipt-header {

            text-align: center;

            padding-bottom: 10px;

            margin-bottom: 10px;

            border-bottom: 1px dashed #333333;
        }


        .receipt-business-name {

            font-size: 21px;

            font-weight: 800;

            letter-spacing: 0.5px;

            text-transform: uppercase;
        }


        .receipt-tagline {

            margin-top: 2px;

            font-size: 10px;

            color: #555555;
        }


        .receipt-business-details {

            margin-top: 6px;

            font-size: 9.5px;

            line-height: 1.45;

            color: #333333;
        }


        /*
|--------------------------------------------------------------------------
| RECEIPT META
|--------------------------------------------------------------------------
*/

        .receipt-meta {

            padding: 8px 0;

            border-bottom: 1px dashed #333333;

            margin-bottom: 8px;
        }


        .receipt-meta-row {

            display: flex;

            justify-content: space-between;

            gap: 8px;

            margin-bottom: 3px;
        }


        .receipt-meta-row:last-child {

            margin-bottom: 0;
        }


        .receipt-meta-label {

            color: #555555;

            white-space: nowrap;
        }


        .receipt-meta-value {

            font-weight: 600;

            text-align: right;

            overflow-wrap: anywhere;
        }


        /*
|--------------------------------------------------------------------------
| CUSTOMER
|--------------------------------------------------------------------------
*/

        .receipt-customer {

            padding: 7px 0;

            border-bottom: 1px dashed #333333;

            margin-bottom: 8px;
        }


        .customer-label {

            font-size: 9px;

            text-transform: uppercase;

            color: #666666;

            letter-spacing: 0.5px;
        }


        .customer-name {

            margin-top: 2px;

            font-size: 11px;

            font-weight: 700;
        }


        .customer-phone {

            margin-top: 1px;

            font-size: 9.5px;

            color: #444444;
        }


        /*
|--------------------------------------------------------------------------
| ITEMS HEADER
|--------------------------------------------------------------------------
*/

        .receipt-items-header {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr) 35px 62px;

            gap: 4px;

            padding-bottom: 5px;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            border-bottom: 1px solid #111111;
        }


        .receipt-items-header .right {

            text-align: right;
        }


        /*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

        .receipt-items {

            padding: 3px 0 5px;

            border-bottom: 1px dashed #333333;
        }


        .receipt-item {

            padding: 6px 0;

            border-bottom: 1px dotted #bbbbbb;
        }


        .receipt-item:last-child {

            border-bottom: none;
        }


        .receipt-item-main {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr) 35px 62px;

            gap: 4px;

            align-items: start;
        }


        .receipt-product-name {

            font-size: 10.5px;

            font-weight: 600;

            overflow-wrap: anywhere;
        }


        .receipt-qty {

            text-align: right;

            font-size: 10px;

            white-space: nowrap;
        }


        .receipt-amount {

            text-align: right;

            font-size: 10px;

            font-weight: 600;

            white-space: nowrap;
        }


        .receipt-product-details {

            margin-top: 2px;

            font-size: 8.5px;

            color: #666666;
        }


        /*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

        .receipt-summary {

            padding-top: 8px;

            padding-bottom: 8px;

            border-bottom: 1px dashed #333333;
        }


        .receipt-summary-row {

            display: flex;

            justify-content: space-between;

            gap: 10px;

            margin: 3px 0;

            font-size: 10px;
        }


        .receipt-summary-label {

            color: #444444;
        }


        .receipt-summary-value {

            text-align: right;

            white-space: nowrap;
        }


        .receipt-total {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 10px;

            margin-top: 8px;

            padding-top: 8px;

            border-top: 2px solid #111111;

            font-size: 15px;

            font-weight: 800;
        }


        .receipt-total-value {

            font-size: 15px;

            white-space: nowrap;
        }


        /*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

        .receipt-payment {

            padding: 8px 0;

            border-bottom: 1px dashed #333333;
        }


        .payment-row {

            display: flex;

            justify-content: space-between;

            gap: 10px;

            margin: 3px 0;

            font-size: 10px;
        }


        .payment-method {

            font-weight: 700;

            text-transform: uppercase;
        }


        /*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

        .receipt-status {

            text-align: center;

            padding: 7px 0 3px;
        }


        .status-paid {

            display: inline-block;

            padding: 3px 10px;

            border: 1px solid #111111;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        /*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

        .receipt-footer {

            text-align: center;

            padding-top: 10px;

            font-size: 9px;

            color: #444444;

            line-height: 1.5;
        }


        .receipt-footer-main {

            font-size: 11px;

            font-weight: 700;

            color: #111111;

            margin-bottom: 3px;
        }


        .receipt-footer-small {

            font-size: 8.5px;

            color: #666666;
        }


        /*
|--------------------------------------------------------------------------
| RECEIPT CUT LINE
|--------------------------------------------------------------------------
*/

        .receipt-cut-line {

            margin-top: 12px;

            border-top: 1px dashed #999999;

            text-align: center;

            font-size: 7px;

            color: #999999;

            padding-top: 3px;
        }


        /*
|--------------------------------------------------------------------------
| A4 INVOICE
|--------------------------------------------------------------------------
*/

        .invoice {

            width: 210mm;

            min-height: 297mm;

            margin: 30px auto;

            padding: 18mm;

            background: #ffffff;

            box-shadow:
                0 5px 25px rgba(0, 0, 0, 0.15);
        }


        .invoice-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            border-bottom: 2px solid #111827;

            padding-bottom: 18px;

            margin-bottom: 25px;
        }


        .company-name {

            font-size: 28px;

            font-weight: bold;

            margin-bottom: 5px;
        }


        .company-details {

            color: #6b7280;

            font-size: 13px;

            line-height: 1.5;
        }


        .invoice-heading {

            text-align: right;
        }


        .invoice-heading h1 {

            margin: 0;

            font-size: 32px;

            letter-spacing: 1px;
        }


        .invoice-number {

            margin-top: 8px;

            color: #4b5563;

            font-size: 13px;
        }


        .info-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 30px;

            margin-bottom: 25px;
        }


        .info-box {

            border: 1px solid #e5e7eb;

            border-radius: 8px;

            padding: 15px;
        }


        .info-title {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            color: #6b7280;

            margin-bottom: 7px;

            font-weight: bold;
        }


        .info-value {

            font-weight: bold;

            font-size: 14px;
        }


        .items-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 15px;
        }


        .items-table th {

            background: #111827;

            color: white;

            text-align: left;

            padding: 11px;

            font-size: 12px;
        }


        .items-table td {

            padding: 11px;

            border-bottom: 1px solid #e5e7eb;

            font-size: 13px;
        }


        .text-right {

            text-align: right;
        }


        .invoice-bottom {

            display: flex;

            justify-content: flex-end;

            margin-top: 25px;
        }


        .totals {

            width: 320px;
        }


        .total-row {

            display: flex;

            justify-content: space-between;

            padding: 7px 0;

            font-size: 14px;
        }


        .grand-total {

            border-top: 2px solid #111827;

            margin-top: 7px;

            padding-top: 12px;

            font-size: 20px;

            font-weight: bold;
        }


        .payment-badge {

            display: inline-block;

            margin-top: 8px;

            padding: 5px 10px;

            border-radius: 20px;

            background: #f3f4f6;

            font-size: 12px;

            font-weight: bold;
        }


        .invoice-footer {

            margin-top: 50px;

            padding-top: 15px;

            border-top: 1px solid #e5e7eb;

            text-align: center;

            color: #6b7280;

            font-size: 12px;
        }


        /*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

        @page {

            margin: 0;
        }


        @media print {

            html,
            body {

                margin: 0;

                padding: 0;

                background: #ffffff;
            }


            .print-controls {

                display: none !important;
            }


            .receipt {

                width: 80mm;

                margin: 0;

                padding: 4mm 3.5mm;

                box-shadow: none;
            }


            .invoice {

                width: 210mm;

                min-height: 297mm;

                margin: 0;

                box-shadow: none;
            }
        }


        /*
|--------------------------------------------------------------------------
| 80MM PRINT PAGE
|--------------------------------------------------------------------------
*/

        <?php if ($isReceipt): ?>@page {

            size: 80mm auto;

            margin: 0;
        }

        <?php else: ?>@page {

            size: A4;

            margin: 0;
        }

        <?php endif; ?>
    </style>

</head>


<body>


    <!--
|--------------------------------------------------------------------------
| PRINT CONTROLS
|--------------------------------------------------------------------------
-->

    <div class="print-controls">


        <button
            type="button"
            class="print-button"
            onclick="window.print()">

            <?= $isReceipt
                ? "🧾 Print Receipt"
                : "📄 Print / Save PDF"
            ?>

        </button>


        <button
            type="button"
            class="close-button"
            onclick="window.close()">

            Close

        </button>


    </div>


    <?php if ($isReceipt): ?>


        <!--
|--------------------------------------------------------------------------
| PROFESSIONAL 80MM RECEIPT
|--------------------------------------------------------------------------
-->

        <div class="receipt">


            <!-- HEADER -->

            <div class="receipt-header">


                <div class="receipt-business-name">

                    <?= e(
                        $businessName
                    ) ?>

                </div>


                <div class="receipt-tagline">

                    <?= e(
                        $businessTagline
                    ) ?>

                </div>


                <div class="receipt-business-details">

                    <?= e(
                        $businessAddress
                    ) ?>

                    <br>

                    <?= e(
                        $businessPhone
                    ) ?>

                </div>


            </div>


            <!-- SALE INFORMATION -->

            <div class="receipt-meta">


                <div class="receipt-meta-row">

                    <span class="receipt-meta-label">

                        Invoice No.

                    </span>


                    <span class="receipt-meta-value">

                        <?= e(
                            $sale["invoice_number"]
                        ) ?>

                    </span>

                </div>


                <div class="receipt-meta-row">

                    <span class="receipt-meta-label">

                        Date

                    </span>


                    <span class="receipt-meta-value">

                        <?= e(
                            date(
                                "d/m/Y H:i",
                                strtotime(
                                    $sale["sale_date"]
                                )
                            )
                        ) ?>

                    </span>

                </div>


                <div class="receipt-meta-row">

                    <span class="receipt-meta-label">

                        Cashier

                    </span>


                    <span class="receipt-meta-value">

                        <?= e(
                            $sale["cashier_name"]
                                ?: "Cashier"
                        ) ?>

                    </span>

                </div>


            </div>


            <!-- CUSTOMER -->

            <div class="receipt-customer">


                <div class="customer-label">

                    Customer

                </div>


                <div class="customer-name">

                    <?= e(
                        $sale["customer_name"]
                            ?: "Walk-in Customer"
                    ) ?>

                </div>


                <?php if (
                    !empty($sale["customer_phone"])
                ): ?>


                    <div class="customer-phone">

                        <?= e(
                            $sale["customer_phone"]
                        ) ?>

                    </div>


                <?php endif; ?>


            </div>


            <!-- ITEMS HEADER -->

            <div class="receipt-items-header">


                <div>
                    Item
                </div>


                <div class="right">
                    Qty
                </div>


                <div class="right">
                    Amount
                </div>


            </div>


            <!-- ITEMS -->

            <div class="receipt-items">


                <?php foreach (
                    $items
                    as $item
                ): ?>


                    <?php

                    $lineTotal =
                        $item["total"] !== null
                        ? (float) $item["total"]
                        : (
                            (float) $item["quantity"] *
                            (float) $item["unit_price"]
                        );

                    ?>


                    <div class="receipt-item">


                        <div class="receipt-item-main">


                            <div class="receipt-product-name">

                                <?= e(
                                    $item["product_name"]
                                ) ?>

                            </div>


                            <div class="receipt-qty">

                                <?= e(
                                    $item["quantity"]
                                ) ?>

                            </div>


                            <div class="receipt-amount">

                                <?= money(
                                    $lineTotal
                                ) ?>

                            </div>


                        </div>


                        <div class="receipt-product-details">

                            <?= money(
                                $item["unit_price"]
                            ) ?>

                            × unit price

                            <?php if (
                                !empty($item["sku"])
                            ): ?>

                                &nbsp; | &nbsp;

                                SKU:
                                <?= e(
                                    $item["sku"]
                                ) ?>

                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


            <!-- SUMMARY -->

            <div class="receipt-summary">


                <div class="receipt-summary-row">


                    <span class="receipt-summary-label">

                        Subtotal

                    </span>


                    <span class="receipt-summary-value">

                        <?= money(
                            $sale["subtotal"]
                        ) ?>

                    </span>


                </div>


                <?php if (
                    (float)$sale["discount"] > 0
                ): ?>


                    <div class="receipt-summary-row">


                        <span class="receipt-summary-label">

                            Discount

                        </span>


                        <span class="receipt-summary-value">

                            - <?= money(
                                    $sale["discount"]
                                ) ?>

                        </span>


                    </div>


                <?php endif; ?>


                <?php if (
                    (float)$sale["tax"] > 0
                ): ?>


                    <div class="receipt-summary-row">


                        <span class="receipt-summary-label">

                            Tax

                        </span>


                        <span class="receipt-summary-value">

                            <?= money(
                                $sale["tax"]
                            ) ?>

                        </span>


                    </div>


                <?php endif; ?>


                <!-- TOTAL -->

                <div class="receipt-total">


                    <span>

                        TOTAL

                    </span>


                    <span class="receipt-total-value">

                        <?= money(
                            $sale["total"]
                        ) ?>

                    </span>


                </div>


            </div>


            <!-- PAYMENT -->

            <div class="receipt-payment">


                <div class="payment-row">


                    <span>

                        Payment Method

                    </span>


                    <span class="payment-method">

                        <?= e(
                            $sale["payment_method"]
                        ) ?>

                    </span>


                </div>


            </div>


            <!-- STATUS -->

            <div class="receipt-status">


                <span class="status-paid">

                    <?= e(
                        $sale["status"]
                            ?: "Completed"
                    ) ?>

                </span>


            </div>


            <!-- FOOTER -->

            <div class="receipt-footer">


                <div class="receipt-footer-main">

                    Thank You!

                </div>


                <div>

                    Thank you for your business.

                </div>


                <div class="receipt-footer-small">

                    Please keep this receipt for your records.

                    <br>

                    Powered by SmartPOS

                </div>


                <div class="receipt-cut-line">

                    --------------------------------

                </div>


            </div>


        </div>


    <?php else: ?>


        <!--
|--------------------------------------------------------------------------
| A4 PROFESSIONAL INVOICE
|--------------------------------------------------------------------------
-->

        <div class="invoice">


            <div class="invoice-header">


                <div>


                    <div class="company-name">

                        SmartPOS

                    </div>


                    <div class="company-details">

                        Point of Sale System

                        <br>

                        Professional Sales Invoice

                    </div>


                </div>


                <div class="invoice-heading">


                    <h1>

                        INVOICE

                    </h1>


                    <div class="invoice-number">

                        <?= e(
                            $sale["invoice_number"]
                        ) ?>

                    </div>


                </div>


            </div>


            <div class="info-grid">


                <div class="info-box">


                    <div class="info-title">

                        Bill To

                    </div>


                    <div class="info-value">

                        <?= e(
                            $sale["customer_name"]
                                ?: "Walk-in Customer"
                        ) ?>

                    </div>


                    <?php if (
                        !empty($sale["customer_phone"])
                    ): ?>


                        <div class="company-details">

                            <?= e(
                                $sale["customer_phone"]
                            ) ?>

                        </div>


                    <?php endif; ?>


                </div>


                <div class="info-box">


                    <div class="info-title">

                        Invoice Details

                    </div>


                    <div>

                        <strong>
                            Invoice:
                        </strong>

                        <?= e(
                            $sale["invoice_number"]
                        ) ?>

                    </div>


                    <div>

                        <strong>
                            Date:
                        </strong>

                        <?= e(
                            date(
                                "Y-m-d H:i",
                                strtotime(
                                    $sale["sale_date"]
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <strong>
                            Cashier:
                        </strong>

                        <?= e(
                            $sale["cashier_name"]
                                ?: "Cashier"
                        ) ?>

                    </div>


                    <span class="payment-badge">

                        Payment:

                        <?= e(
                            $sale["payment_method"]
                        ) ?>

                    </span>


                </div>


            </div>


            <table class="items-table">


                <thead>


                    <tr>


                        <th>
                            #
                        </th>


                        <th>
                            Product
                        </th>


                        <th>
                            SKU
                        </th>


                        <th class="text-right">
                            Qty
                        </th>


                        <th class="text-right">
                            Unit Price
                        </th>


                        <th class="text-right">
                            Total
                        </th>


                    </tr>


                </thead>


                <tbody>


                    <?php

                    $counter = 1;

                    foreach (
                        $items
                        as $item
                    ):


                        $lineTotal =
                            $item["total"] !== null
                            ? (float) $item["total"]
                            : (
                                (float) $item["quantity"] *
                                (float) $item["unit_price"]
                            );

                    ?>


                        <tr>


                            <td>

                                <?= $counter++ ?>

                            </td>


                            <td>

                                <?= e(
                                    $item["product_name"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $item["sku"]
                                ) ?>

                            </td>


                            <td class="text-right">

                                <?= e(
                                    $item["quantity"]
                                ) ?>

                            </td>


                            <td class="text-right">

                                <?= money(
                                    $item["unit_price"]
                                ) ?>

                            </td>


                            <td class="text-right">

                                <strong>

                                    <?= money(
                                        $lineTotal
                                    ) ?>

                                </strong>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


            <div class="invoice-bottom">


                <div class="totals">


                    <div class="total-row">


                        <span>
                            Subtotal
                        </span>


                        <strong>

                            <?= money(
                                $sale["subtotal"]
                            ) ?>

                        </strong>


                    </div>


                    <div class="total-row">


                        <span>
                            Discount
                        </span>


                        <strong>

                            <?= money(
                                $sale["discount"]
                            ) ?>

                        </strong>


                    </div>


                    <div class="total-row">


                        <span>
                            Tax
                        </span>


                        <strong>

                            <?= money(
                                $sale["tax"]
                            ) ?>

                        </strong>


                    </div>


                    <div class="total-row grand-total">


                        <span>
                            TOTAL
                        </span>


                        <strong>

                            <?= money(
                                $sale["total"]
                            ) ?>

                        </strong>


                    </div>


                </div>


            </div>


            <div class="invoice-footer">


                <strong>

                    Thank you for your business!

                </strong>


                <br>
                <br>


                This is a computer-generated invoice.


                <br>


                Powered by SmartPOS


            </div>


        </div>


    <?php endif; ?>


</body>

</html>