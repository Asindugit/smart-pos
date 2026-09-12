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
    $saleId === false ||
    $saleId <= 0
) {

    die("Invalid sale.");
}


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
            s.sale_date,
            s.subtotal,
            s.discount,
            s.tax,
            s.total,
            s.status,

            c.name AS customer_name,
            c.phone AS customer_phone,

            u.name AS cashier_name

        FROM sales s

        LEFT JOIN customers c
            ON c.id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.cashier_id

        WHERE s.id = ?

        LIMIT 1
    ");


if (!$saleStmt) {

    die("Unable to load sale.");
}


$saleStmt->bind_param(
    "i",
    $saleId
);


if (!$saleStmt->execute()) {

    $saleStmt->close();

    die("Unable to load sale.");
}


$saleResult =
    $saleStmt->get_result();


$sale =
    $saleResult->fetch_assoc();


$saleResult->free();

$saleStmt->close();


if (!$sale) {

    die("Sale not found.");
}


/*
|--------------------------------------------------------------------------
| Load Sale Items
|--------------------------------------------------------------------------
*/

$items = [];


$itemStmt =
    $conn->prepare("
        SELECT
            si.product_id,
            si.quantity,
            si.unit_price,
            si.discount,
            si.total,

            p.name AS product_name,
            p.sku,
            p.unit

        FROM sale_items si

        LEFT JOIN products p
            ON p.id = si.product_id

        WHERE si.sale_id = ?

        ORDER BY si.id ASC
    ");


if (!$itemStmt) {

    die("Unable to load sale items.");
}


$itemStmt->bind_param(
    "i",
    $saleId
);


if (!$itemStmt->execute()) {

    $itemStmt->close();

    die("Unable to load sale items.");
}


$itemResult =
    $itemStmt->get_result();


while (
    $item = $itemResult->fetch_assoc()
) {

    $items[] = $item;
}


$itemResult->free();

$itemStmt->close();


/*
|--------------------------------------------------------------------------
| Payment
|--------------------------------------------------------------------------
*/

$payment = null;


$paymentStmt =
    $conn->prepare("
        SELECT
            payment_method,
            amount,
            cash_received,
            change_amount,
            reference_number

        FROM payments

        WHERE sale_id = ?

        ORDER BY id DESC

        LIMIT 1
    ");


if ($paymentStmt) {

    $paymentStmt->bind_param(
        "i",
        $saleId
    );


    if ($paymentStmt->execute()) {

        $paymentResult =
            $paymentStmt->get_result();


        $payment =
            $paymentResult->fetch_assoc();


        $paymentResult->free();
    }


    $paymentStmt->close();
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function money($value)
{
    return "LKR " .
        number_format(
            (float) $value,
            2
        );
}


function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| Payment Name
|--------------------------------------------------------------------------
*/

$paymentName = "-";


if ($payment) {

    switch (
        $payment["payment_method"]
    ) {

        case "cash":

            $paymentName = "Cash";

            break;

        case "card":

            $paymentName = "Card";

            break;

        case "bank_transfer":

            $paymentName =
                "Bank Transfer";

            break;

        case "mobile":

            $paymentName =
                "Mobile Payment";

            break;

        default:

            $paymentName =
                $payment["payment_method"];

            break;
    }
}

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
        Receipt - <?= e($sale["invoice_number"]) ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 25px;

            background: #f1f3f5;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #222;
        }


        .receipt {

            width: 80mm;

            max-width: 100%;

            margin: 0 auto;

            background: #ffffff;

            padding: 18px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.08);
        }


        .center {
            text-align: center;
        }


        .shop-name {

            font-size: 21px;

            font-weight: 800;

            margin-bottom: 4px;
        }


        .shop-subtitle {

            font-size: 12px;

            color: #666;

        }


        .line {

            border-top:
                1px dashed #555;

            margin:
                12px 0;
        }


        .invoice-info {

            font-size: 12px;

            line-height: 1.7;
        }


        .invoice-info div {

            display: flex;

            justify-content:
                space-between;

            gap: 10px;
        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            font-size: 12px;
        }


        th {

            text-align: left;

            border-bottom:
                1px solid #222;

            padding:
                5px 0;
        }


        td {

            padding:
                6px 0;

            vertical-align:
                top;
        }


        .right {

            text-align: right;
        }


        .item-name {

            font-weight: 600;
        }


        .item-meta {

            font-size: 10px;

            color: #777;

            margin-top: 2px;
        }


        .summary {

            font-size: 12px;
        }


        .summary-row {

            display: flex;

            justify-content:
                space-between;

            margin:
                5px 0;
        }


        .grand-total {

            font-size: 17px;

            font-weight: 800;

            border-top:
                1px solid #222;

            padding-top: 8px;

            margin-top: 8px;
        }


        .footer {

            text-align: center;

            font-size: 11px;

            color: #666;

            margin-top: 18px;
        }


        .print-buttons {

            width: 80mm;

            max-width: 100%;

            margin:
                15px auto;

            display: flex;

            gap: 8px;
        }


        .print-buttons button {

            flex: 1;

            padding: 11px;

            border: 0;

            border-radius: 6px;

            cursor: pointer;

            font-weight: 600;
        }


        .print-btn {

            background: #198754;

            color: #fff;
        }


        .back-btn {

            background: #6c757d;

            color: #fff;
        }


        @media print {

            body {

                padding: 0;

                background: #fff;
            }


            .receipt {

                width: 80mm;

                box-shadow: none;

                padding: 5mm;
            }


            .print-buttons {

                display: none;
            }


            @page {

                size: 80mm auto;

                margin: 0;
            }
        }

    </style>

</head>


<body>


    <div class="receipt">


        <!-- ==========================================================
             SHOP
        =========================================================== -->

        <div class="center">

            <div class="shop-name">
                SMART POS
            </div>

            <div class="shop-subtitle">
                Sales Receipt
            </div>

        </div>


        <div class="line"></div>


        <!-- ==========================================================
             INVOICE
        =========================================================== -->

        <div class="invoice-info">

            <div>

                <span>
                    Invoice
                </span>

                <strong>
                    <?= e(
                        $sale["invoice_number"]
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Date
                </span>

                <span>
                    <?= e(
                        $sale["sale_date"]
                    ) ?>
                </span>

            </div>


            <div>

                <span>
                    Customer
                </span>

                <span>
                    <?= e(
                        $sale["customer_name"]
                        ?: "Walk-in Customer"
                    ) ?>
                </span>

            </div>


            <?php if (
                !empty(
                    $sale["customer_phone"]
                )
            ): ?>

                <div>

                    <span>
                        Phone
                    </span>

                    <span>
                        <?= e(
                            $sale["customer_phone"]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <div>

                <span>
                    Cashier
                </span>

                <span>
                    <?= e(
                        $sale["cashier_name"]
                        ?: "-"
                    ) ?>
                </span>

            </div>

        </div>


        <div class="line"></div>


        <!-- ==========================================================
             ITEMS
        =========================================================== -->

        <table>

            <thead>

                <tr>

                    <th>
                        Item
                    </th>

                    <th class="right">
                        Amount
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php foreach (
                    $items as $item
                ): ?>

                    <tr>

                        <td>

                            <div class="item-name">

                                <?= e(
                                    $item["product_name"]
                                ) ?>

                            </div>


                            <div class="item-meta">

                                <?= e(
                                    $item["quantity"]
                                ) ?>

                                ×

                                <?= money(
                                    $item["unit_price"]
                                ) ?>

                                <?php if (
                                    !empty(
                                        $item["sku"]
                                    )
                                ): ?>

                                    <br>

                                    SKU:
                                    <?= e(
                                        $item["sku"]
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        </td>


                        <td class="right">

                            <?= money(
                                $item["total"]
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>


        <div class="line"></div>


        <!-- ==========================================================
             TOTALS
        =========================================================== -->

        <div class="summary">


            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <span>
                    <?= money(
                        $sale["subtotal"]
                    ) ?>
                </span>

            </div>


            <?php if (
                (float) $sale["discount"] > 0
            ): ?>

                <div class="summary-row">

                    <span>
                        Discount
                    </span>

                    <span>
                        -<?= money(
                            $sale["discount"]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <?php if (
                (float) $sale["tax"] > 0
            ): ?>

                <div class="summary-row">

                    <span>
                        Tax
                    </span>

                    <span>
                        <?= money(
                            $sale["tax"]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <div
                class="summary-row grand-total"
            >

                <span>
                    TOTAL
                </span>

                <span>
                    <?= money(
                        $sale["total"]
                    ) ?>
                </span>

            </div>

        </div>


        <div class="line"></div>


        <!-- ==========================================================
             PAYMENT
        =========================================================== -->

        <div class="summary">

            <div class="summary-row">

                <span>
                    Payment
                </span>

                <span>
                    <?= e(
                        $paymentName
                    ) ?>
                </span>

            </div>


            <?php if (
                $payment &&
                $payment["payment_method"] === "cash"
            ): ?>

                <div class="summary-row">

                    <span>
                        Cash Received
                    </span>

                    <span>
                        <?= money(
                            $payment["cash_received"]
                        ) ?>
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Change
                    </span>

                    <span>
                        <?= money(
                            $payment["change_amount"]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- ==========================================================
             FOOTER
        =========================================================== -->

        <div class="footer">

            Thank you for your purchase!

            <br>

            Please come again.

        </div>

    </div>


    <!-- ==============================================================
         BUTTONS
    =============================================================== -->

    <div class="print-buttons">

        <button
            type="button"
            class="print-btn"
            onclick="window.print()"
        >

            🖨 Print Bill

        </button>


        <button
            type="button"
            class="back-btn"
            onclick="window.location.href='billing.php'"
        >

            ← New Bill

        </button>

    </div>


</body>

</html>


<?php

$conn->close();

?>