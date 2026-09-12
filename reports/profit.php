<?php


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$pageTitle = "Profit Report";


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


function money($amount)
{
    return "LKR " . number_format(
        (float) $amount,
        2
    );
}


/*
|--------------------------------------------------------------------------
| DATE FILTERS
|--------------------------------------------------------------------------
*/

$fromDate = $_GET['from_date'] ?? date('Y-m-01');

$toDate = $_GET['to_date'] ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| VALIDATE FROM DATE
|--------------------------------------------------------------------------
*/

$fromDateObject = DateTime::createFromFormat(
    'Y-m-d',
    $fromDate
);


if (
    !$fromDateObject ||
    $fromDateObject->format('Y-m-d') !== $fromDate
) {

    $fromDate = date('Y-m-01');
}


/*
|--------------------------------------------------------------------------
| VALIDATE TO DATE
|--------------------------------------------------------------------------
*/

$toDateObject = DateTime::createFromFormat(
    'Y-m-d',
    $toDate
);


if (
    !$toDateObject ||
    $toDateObject->format('Y-m-d') !== $toDate
) {

    $toDate = date('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| SWAP DATES IF NECESSARY
|--------------------------------------------------------------------------
*/

if ($fromDate > $toDate) {

    $temporary = $fromDate;

    $fromDate = $toDate;

    $toDate = $temporary;
}


/*
|--------------------------------------------------------------------------
| IMPORTANT DATETIME RANGE
|--------------------------------------------------------------------------
|
| Example:
|
| From = 2026-09-01
| To   = 2026-09-09
|
| Search:
|
| >= 2026-09-01 00:00:00
| <  2026-09-10 00:00:00
|
| This includes the complete To Date.
|
|--------------------------------------------------------------------------
*/

$fromDateTime =
    $fromDate . " 00:00:00";


$toDateTime =
    date(
        'Y-m-d 00:00:00',
        strtotime($toDate . ' +1 day')
    );


/*
|--------------------------------------------------------------------------
| SUMMARY DEFAULTS
|--------------------------------------------------------------------------
*/

$summary = [

    'orders' => 0,

    'gross_sales' => 0,

    'discount' => 0,

    'net_sales' => 0,

    'tax' => 0,

    'cogs' => 0,

    'gross_profit' => 0

];


$profitMargin = 0;


/*
|--------------------------------------------------------------------------
| OVERALL PROFIT SUMMARY
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Sales totals are calculated separately from sale_items.
| This prevents sale subtotal/discount/tax from being
| duplicated when one sale has multiple items.
|
|--------------------------------------------------------------------------
*/


$summarySql = "

    SELECT

        COUNT(*) AS orders,

        COALESCE(
            SUM(s.subtotal),
            0
        ) AS gross_sales,

        COALESCE(
            SUM(s.discount),
            0
        ) AS discount,

        COALESCE(
            SUM(s.tax),
            0
        ) AS tax,

        COALESCE(
            (
                SELECT
                    SUM(
                        si.quantity *
                        si.unit_cost
                    )

                FROM sale_items si

                INNER JOIN sales sx
                    ON sx.id = si.sale_id

                WHERE sx.status = 'completed'

                  AND sx.sale_date >= ?

                  AND sx.sale_date < ?
            ),
            0
        ) AS cogs

    FROM sales s

    WHERE s.status = 'completed'

      AND s.sale_date >= ?

      AND s.sale_date < ?

";


$summaryStmt =
    $conn->prepare(
        $summarySql
    );


if (!$summaryStmt) {

    die("Unable to prepare profit summary query: "
        . $conn->error);
}


/*
|--------------------------------------------------------------------------
| Bind Dates
|--------------------------------------------------------------------------
*/

$summaryStmt->bind_param(
    "ssss",
    $fromDateTime,
    $toDateTime,
    $fromDateTime,
    $toDateTime
);


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$summaryStmt->execute()) {

    $summaryStmt->close();

    die("Unable to generate profit summary: "
        . $conn->error);
}


$summaryResult =
    $summaryStmt->get_result();


if ($summaryResult) {

    $summaryData =
        $summaryResult->fetch_assoc();


    if ($summaryData) {

        $summary['orders'] =
            (int) (
                $summaryData['orders']
                ?? 0
            );


        $summary['gross_sales'] =
            (float) (
                $summaryData['gross_sales']
                ?? 0
            );


        $summary['discount'] =
            (float) (
                $summaryData['discount']
                ?? 0
            );


        $summary['tax'] =
            (float) (
                $summaryData['tax']
                ?? 0
            );


        $summary['cogs'] =
            (float) (
                $summaryData['cogs']
                ?? 0
            );
    }


    $summaryResult->free();
}


$summaryStmt->close();


/*
|--------------------------------------------------------------------------
| NET SALES
|--------------------------------------------------------------------------
*/

$summary['net_sales'] =
    $summary['gross_sales']
    -
    $summary['discount'];


/*
|--------------------------------------------------------------------------
| GROSS PROFIT
|--------------------------------------------------------------------------
*/

$summary['gross_profit'] =
    $summary['net_sales']
    -
    $summary['cogs'];


/*
|--------------------------------------------------------------------------
| PROFIT MARGIN
|--------------------------------------------------------------------------
*/

if ($summary['net_sales'] > 0) {

    $profitMargin =
        (
            $summary['gross_profit']
            /
            $summary['net_sales']
        )
        * 100;
}


/*
|--------------------------------------------------------------------------
| PRODUCT PROFITABILITY
|--------------------------------------------------------------------------
*/

$productProfitRows = [];


$productSql = "

    SELECT

        p.id,

        p.name,

        p.sku,

        COALESCE(
            SUM(si.quantity),
            0
        ) AS quantity_sold,

        COALESCE(
            SUM(
                si.quantity *
                si.unit_price
            ),
            0
        ) AS sales_value,

        COALESCE(
            SUM(
                si.quantity *
                si.unit_cost
            ),
            0
        ) AS cogs,

        COALESCE(
            SUM(
                si.quantity *
                (
                    si.unit_price -
                    si.unit_cost
                )
            ),
            0
        ) AS gross_profit

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE s.status = 'completed'

      AND s.sale_date >= ?

      AND s.sale_date < ?

    GROUP BY

        p.id,

        p.name,

        p.sku

    ORDER BY
        gross_profit DESC

";


$productStmt =
    $conn->prepare(
        $productSql
    );


if (!$productStmt) {

    die("Unable to prepare product profit query: "
        . $conn->error);
}


$productStmt->bind_param(
    "ss",
    $fromDateTime,
    $toDateTime
);


if (!$productStmt->execute()) {

    $productStmt->close();

    die("Unable to generate product profit report: "
        . $conn->error);
}


$productResult =
    $productStmt->get_result();


if ($productResult) {

    while (
        $row =
        $productResult->fetch_assoc()
    ) {

        $productProfitRows[] =
            $row;
    }


    $productResult->free();
}


$productStmt->close();


/*
|--------------------------------------------------------------------------
| DAILY PROFIT
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Group by DATE(s.sale_date), not the complete DATETIME.
|
|--------------------------------------------------------------------------
*/

$dailyProfitRows = [];


$dailySql = "

    SELECT

        DATE(s.sale_date) AS sale_day,

        COUNT(DISTINCT s.id)
            AS orders,

        COALESCE(
            SUM(
                s.subtotal
            ),
            0
        ) AS gross_sales,

        COALESCE(
            SUM(
                s.discount
            ),
            0
        ) AS discount,

        COALESCE(
            SUM(
                s.tax
            ),
            0
        ) AS tax,

        COALESCE(
            SUM(
                si.quantity *
                si.unit_cost
            ),
            0
        ) AS cogs

    FROM sales s

    INNER JOIN sale_items si
        ON si.sale_id = s.id

    WHERE s.status = 'completed'

      AND s.sale_date >= ?

      AND s.sale_date < ?

    GROUP BY
        DATE(s.sale_date)

    ORDER BY
        sale_day DESC

";


/*
|--------------------------------------------------------------------------
| NOTE:
| sale-level values can duplicate here if an invoice
| has multiple sale_items.
|
| Therefore we will use a separate query below for
| daily sales totals and another for daily COGS.
|--------------------------------------------------------------------------
*/


$dailySalesSql = "

    SELECT

        DATE(s.sale_date) AS sale_day,

        COUNT(*) AS orders,

        COALESCE(
            SUM(s.subtotal),
            0
        ) AS gross_sales,

        COALESCE(
            SUM(s.discount),
            0
        ) AS discount,

        COALESCE(
            SUM(s.tax),
            0
        ) AS tax

    FROM sales s

    WHERE s.status = 'completed'

      AND s.sale_date >= ?

      AND s.sale_date < ?

    GROUP BY
        DATE(s.sale_date)

    ORDER BY
        sale_day DESC

";


$dailySalesStmt =
    $conn->prepare(
        $dailySalesSql
    );


if (!$dailySalesStmt) {

    die("Unable to prepare daily sales query: "
        . $conn->error);
}


$dailySalesStmt->bind_param(
    "ss",
    $fromDateTime,
    $toDateTime
);


$dailySalesStmt->execute();


$dailySalesResult =
    $dailySalesStmt->get_result();


$dailyData = [];


while (
    $dailySalesResult &&
    $row = $dailySalesResult->fetch_assoc()
) {

    $dailyData[$row['sale_day']] = [

        'sale_day' =>
        $row['sale_day'],

        'orders' =>
        (int) $row['orders'],

        'gross_sales' =>
        (float) $row['gross_sales'],

        'discount' =>
        (float) $row['discount'],

        'tax' =>
        (float) $row['tax'],

        'cogs' =>
        0
    ];
}


$dailySalesStmt->close();


/*
|--------------------------------------------------------------------------
| DAILY COGS
|--------------------------------------------------------------------------
*/

$dailyCogsSql = "

    SELECT

        DATE(s.sale_date) AS sale_day,

        COALESCE(
            SUM(
                si.quantity *
                si.unit_cost
            ),
            0
        ) AS cogs

    FROM sales s

    INNER JOIN sale_items si
        ON si.sale_id = s.id

    WHERE s.status = 'completed'

      AND s.sale_date >= ?

      AND s.sale_date < ?

    GROUP BY
        DATE(s.sale_date)

";


$dailyCogsStmt =
    $conn->prepare(
        $dailyCogsSql
    );


if (!$dailyCogsStmt) {

    die("Unable to prepare daily COGS query: "
        . $conn->error);
}


$dailyCogsStmt->bind_param(
    "ss",
    $fromDateTime,
    $toDateTime
);


$dailyCogsStmt->execute();


$dailyCogsResult =
    $dailyCogsStmt->get_result();


while (
    $dailyCogsResult &&
    $row = $dailyCogsResult->fetch_assoc()
) {

    $day = $row['sale_day'];


    if (
        !isset(
            $dailyData[$day]
        )
    ) {

        $dailyData[$day] = [

            'sale_day' =>
            $day,

            'orders' =>
            0,

            'gross_sales' =>
            0,

            'discount' =>
            0,

            'tax' =>
            0,

            'cogs' =>
            0
        ];
    }


    $dailyData[$day]['cogs'] =
        (float) $row['cogs'];
}


$dailyCogsStmt->close();


/*
|--------------------------------------------------------------------------
| CREATE DAILY PROFIT ROWS
|--------------------------------------------------------------------------
*/

foreach ($dailyData as $day => $row) {

    $dailyNetSales =
        (float) $row['gross_sales']
        -
        (float) $row['discount'];


    $dailyGrossProfit =
        $dailyNetSales
        -
        (float) $row['cogs'];


    $dailyMargin = 0;


    if ($dailyNetSales > 0) {

        $dailyMargin =
            (
                $dailyGrossProfit
                /
                $dailyNetSales
            )
            * 100;
    }


    $row['net_sales'] =
        $dailyNetSales;


    $row['gross_profit'] =
        $dailyGrossProfit;


    $row['margin'] =
        $dailyMargin;


    $dailyProfitRows[] =
        $row;
}


/*
|--------------------------------------------------------------------------
| SORT DAILY DATA DESC
|--------------------------------------------------------------------------
*/

usort(
    $dailyProfitRows,
    function ($a, $b) {

        return strcmp(
            $b['sale_day'],
            $a['sale_day']
        );
    }
);


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
                class="
                    d-flex
                    flex-column
                    flex-lg-row
                    justify-content-between
                    align-items-lg-center
                    gap-3
                    mb-4
                ">

                <div>

                    <h2 class="fw-bold mb-1">

                        <i
                            class="
                                bi
                                bi-graph-up-arrow
                                me-2
                            "></i>

                        Profit Report

                    </h2>


                    <p class="text-muted mb-0">

                        Analyze historical cost,
                        net sales and gross profit.

                    </p>

                </div>


                <div class="d-flex gap-2">

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


                    <button
                        type="button"
                        class="btn btn-dark"
                        onclick="window.print()">

                        <i
                            class="
                                bi
                                bi-printer
                                me-1
                            "></i>

                        Print Report

                    </button>

                </div>

            </div>


            <!-- ==========================================================
                 DATE FILTER
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div
                    class="
                        card-header
                        bg-white
                        border-0
                        py-3
                    ">

                    <h5 class="mb-0 fw-semibold">

                        <i
                            class="
                                bi
                                bi-calendar3
                                me-2
                            "></i>

                        Report Period

                    </h5>

                </div>


                <div class="card-body">

                    <form
                        method="GET"
                        action="profit.php">

                        <div
                            class="
                                row
                                g-3
                                align-items-end
                            ">

                            <div class="col-md-4">

                                <label
                                    for="from_date"
                                    class="form-label fw-semibold">
                                    From Date
                                </label>


                                <input
                                    type="date"
                                    id="from_date"
                                    name="from_date"
                                    class="form-control"
                                    value="<?= e($fromDate) ?>"
                                    required>

                            </div>


                            <div class="col-md-4">

                                <label
                                    for="to_date"
                                    class="form-label fw-semibold">
                                    To Date
                                </label>


                                <input
                                    type="date"
                                    id="to_date"
                                    name="to_date"
                                    class="form-control"
                                    value="<?= e($toDate) ?>"
                                    required>

                            </div>


                            <div class="col-md-4">

                                <button
                                    type="submit"
                                    class="
                                        btn
                                        btn-primary
                                        w-100
                                    ">

                                    <i
                                        class="
                                            bi
                                            bi-bar-chart
                                            me-1
                                        "></i>

                                    Generate Report

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- ==========================================================
                 REPORT PERIOD
            =========================================================== -->

            <div
                class="
                    alert
                    alert-light
                    border
                    mb-4
                ">

                <i
                    class="
                        bi
                        bi-calendar-range
                        me-2
                    "></i>


                <strong>
                    Report Period:
                </strong>


                <?= e($fromDate) ?>


                <span class="mx-2">
                    →
                </span>


                <?= e($toDate) ?>

            </div>


            <!-- ==========================================================
                 HISTORICAL COST NOTICE
            =========================================================== -->

            <div
                class="
                    alert
                    alert-success
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div
                    class="
                        d-flex
                        align-items-start
                    ">

                    <i
                        class="
                            bi
                            bi-check-circle-fill
                            fs-4
                            me-3
                        "></i>


                    <div>

                        <strong>
                            Historical Cost Enabled
                        </strong>


                        <div class="small mt-1">

                            COGS is calculated using the
                            <strong>
                                unit cost recorded when each sale was made
                            </strong>.

                            Previous sales therefore remain
                            accurate even when the current product
                            purchase price changes.

                        </div>

                    </div>

                </div>

            </div>


            <!-- ==========================================================
                 SUMMARY CARDS
            =========================================================== -->

            <div class="row g-4 mb-4">


                <!-- ORDERS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            border-0
                            shadow-sm
                            h-100
                        ">

                        <div class="card-body">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-start
                                ">

                                <div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                            mb-1
                                        ">
                                        Completed Orders
                                    </div>


                                    <h3
                                        class="
                                            fw-bold
                                            mb-0
                                        ">

                                        <?= number_format(
                                            $summary['orders']
                                        ) ?>

                                    </h3>

                                </div>


                                <div
                                    class="
                                        bg-primary
                                        bg-opacity-10
                                        text-primary
                                        rounded
                                        p-3
                                    ">

                                    <i
                                        class="
                                            bi
                                            bi-receipt
                                            fs-4
                                        "></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- NET SALES -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            border-0
                            shadow-sm
                            h-100
                        ">

                        <div class="card-body">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-start
                                ">

                                <div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                            mb-1
                                        ">
                                        Net Sales
                                    </div>


                                    <h4
                                        class="
                                            fw-bold
                                            mb-0
                                        ">

                                        <?= money(
                                            $summary['net_sales']
                                        ) ?>

                                    </h4>

                                </div>


                                <div
                                    class="
                                        bg-info
                                        bg-opacity-10
                                        text-info
                                        rounded
                                        p-3
                                    ">

                                    <i
                                        class="
                                            bi
                                            bi-cash-stack
                                            fs-4
                                        "></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- COGS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            border-0
                            shadow-sm
                            h-100
                        ">

                        <div class="card-body">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-start
                                ">

                                <div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                            mb-1
                                        ">
                                        Cost of Goods Sold
                                    </div>


                                    <h4
                                        class="
                                            fw-bold
                                            mb-0
                                        ">

                                        <?= money(
                                            $summary['cogs']
                                        ) ?>

                                    </h4>

                                </div>


                                <div
                                    class="
                                        bg-warning
                                        bg-opacity-10
                                        text-warning
                                        rounded
                                        p-3
                                    ">

                                    <i
                                        class="
                                            bi
                                            bi-box-seam
                                            fs-4
                                        "></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- GROSS PROFIT -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            border-0
                            shadow-sm
                            h-100
                        ">

                        <div class="card-body">

                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-start
                                ">

                                <div>

                                    <div
                                        class="
                                            text-muted
                                            small
                                            mb-1
                                        ">
                                        Gross Profit
                                    </div>


                                    <h4
                                        class="
                                            fw-bold
                                            mb-0
                                            <?= $summary['gross_profit'] >= 0
                                                ? 'text-success'
                                                : 'text-danger'
                                            ?>
                                        ">

                                        <?= money(
                                            $summary['gross_profit']
                                        ) ?>

                                    </h4>

                                </div>


                                <div
                                    class="
                                        <?= $summary['gross_profit'] >= 0
                                            ? 'text-success'
                                            : 'text-danger'
                                        ?>
                                        bg-opacity-10
                                        rounded
                                        p-3
                                    ">

                                    <i
                                        class="
                                            bi
                                            bi-graph-up-arrow
                                            fs-4
                                        "></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


            </div>


            <!-- ==========================================================
                 FINANCIAL BREAKDOWN
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div
                    class="
                        card-header
                        bg-white
                        border-0
                        py-3
                    ">

                    <h5 class="fw-semibold mb-0">

                        <i
                            class="
                                bi
                                bi-calculator
                                me-2
                            "></i>

                        Financial Breakdown

                    </h5>

                </div>


                <div class="card-body">

                    <div class="row g-4">


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                    mb-1
                                ">
                                Gross Sales
                            </div>


                            <h5 class="fw-bold mb-0">

                                <?= money(
                                    $summary['gross_sales']
                                ) ?>

                            </h5>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                    mb-1
                                ">
                                Discounts
                            </div>


                            <h5
                                class="
                                    fw-bold
                                    text-danger
                                    mb-0
                                ">

                                - <?= money(
                                        $summary['discount']
                                    ) ?>

                            </h5>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                    mb-1
                                ">
                                Tax
                            </div>


                            <h5 class="fw-bold mb-0">

                                <?= money(
                                    $summary['tax']
                                ) ?>

                            </h5>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                    mb-1
                                ">
                                Profit Margin
                            </div>


                            <h5
                                class="
                                    fw-bold
                                    <?= $profitMargin >= 0
                                        ? 'text-success'
                                        : 'text-danger'
                                    ?>
                                ">

                                <?= number_format(
                                    $profitMargin,
                                    2
                                ) ?>%

                            </h5>

                        </div>


                    </div>

                </div>

            </div>


            <!-- ==========================================================
                 PROFIT MARGIN
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div class="card-body p-4">

                    <div
                        class="
                            row
                            align-items-center
                        ">

                        <div class="col-md-8">

                            <h5 class="fw-semibold mb-1">

                                Gross Profit Margin

                            </h5>


                            <p class="text-muted mb-0">

                                Percentage of net sales remaining
                                after historical COGS.

                            </p>

                        </div>


                        <div
                            class="
                                col-md-4
                                text-md-end
                                mt-3
                                mt-md-0
                            ">

                            <span
                                class="
                                    display-6
                                    fw-bold
                                    <?= $profitMargin >= 0
                                        ? 'text-success'
                                        : 'text-danger'
                                    ?>
                                ">

                                <?= number_format(
                                    $profitMargin,
                                    2
                                ) ?>%

                            </span>

                        </div>

                    </div>


                    <div
                        class="progress mt-3"
                        style="height: 10px;">

                        <div
                            class="
                                progress-bar
                                <?= $profitMargin >= 0
                                    ? 'bg-success'
                                    : 'bg-danger'
                                ?>
                            "
                            role="progressbar"
                            style="
                                width:
                                <?= min(
                                    max(
                                        $profitMargin,
                                        0
                                    ),
                                    100
                                ) ?>%;
                            "></div>

                    </div>

                </div>

            </div>


            <!-- ==========================================================
                 PRODUCT PROFITABILITY
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div
                    class="
                        card-header
                        bg-white
                        border-0
                        py-3
                    ">

                    <h5
                        class="
                            mb-1
                            fw-semibold
                        ">

                        <i
                            class="
                                bi
                                bi-box-seam
                                me-2
                            "></i>

                        Product Profitability

                    </h5>


                    <div
                        class="
                            text-muted
                            small
                        ">

                        Profitability by product using historical unit cost.

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (
                        !empty($productProfitRows)
                    ): ?>

                        <div class="table-responsive">

                            <table
                                class="
                                    table
                                    table-hover
                                    align-middle
                                    mb-0
                                ">

                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            Product
                                        </th>

                                        <th>
                                            SKU
                                        </th>

                                        <th class="text-end">
                                            Qty Sold
                                        </th>

                                        <th class="text-end">
                                            Sales
                                        </th>

                                        <th class="text-end">
                                            COGS
                                        </th>

                                        <th class="text-end">
                                            Gross Profit
                                        </th>

                                        <th class="text-end pe-4">
                                            Margin
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $productProfitRows
                                        as $row
                                    ): ?>


                                        <?php

                                        $productSales =
                                            (float)
                                            $row['sales_value'];


                                        $productCogs =
                                            (float)
                                            $row['cogs'];


                                        $productProfit =
                                            (float)
                                            $row['gross_profit'];


                                        $productMargin = 0;


                                        if (
                                            $productSales > 0
                                        ) {

                                            $productMargin =
                                                (
                                                    $productProfit
                                                    /
                                                    $productSales
                                                )
                                                * 100;
                                        }

                                        ?>


                                        <tr>

                                            <td class="ps-4">

                                                <div
                                                    class="fw-semibold">

                                                    <?= e(
                                                        $row['name']
                                                    ) ?>

                                                </div>

                                            </td>


                                            <td>

                                                <span
                                                    class="text-muted">

                                                    <?= e(
                                                        $row['sku']
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= number_format(
                                                    (float)
                                                    $row['quantity_sold'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $productSales
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $productCogs
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <strong
                                                    class="
                                                        <?= $productProfit >= 0
                                                            ? 'text-success'
                                                            : 'text-danger'
                                                        ?>
                                                    ">

                                                    <?= money(
                                                        $productProfit
                                                    ) ?>

                                                </strong>

                                            </td>


                                            <td
                                                class="
                                                    text-end
                                                    pe-4
                                                ">

                                                <span
                                                    class="
                                                        badge
                                                        <?= $productMargin >= 0
                                                            ? 'text-bg-success'
                                                            : 'text-bg-danger'
                                                        ?>
                                                    ">

                                                    <?= number_format(
                                                        $productMargin,
                                                        2
                                                    ) ?>%

                                                </span>

                                            </td>

                                        </tr>


                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div
                            class="
                                text-center
                                py-5
                            ">

                            <i
                                class="
                                    bi
                                    bi-bar-chart
                                    fs-1
                                    text-muted
                                "></i>


                            <h5 class="mt-3">
                                No Profit Data Found
                            </h5>


                            <p
                                class="
                                    text-muted
                                    mb-0
                                ">

                                No completed sales were found
                                for the selected period.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- ==========================================================
                 DAILY PROFIT
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div
                    class="
                        card-header
                        bg-white
                        border-0
                        py-3
                    ">

                    <h5
                        class="
                            mb-1
                            fw-semibold
                        ">

                        <i
                            class="
                                bi
                                bi-calendar-week
                                me-2
                            "></i>

                        Daily Profit

                    </h5>


                    <div
                        class="
                            text-muted
                            small
                        ">

                        Daily net sales, historical COGS
                        and gross profit.

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (
                        !empty($dailyProfitRows)
                    ): ?>

                        <div class="table-responsive">

                            <table
                                class="
                                    table
                                    table-hover
                                    align-middle
                                    mb-0
                                ">

                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            Date
                                        </th>

                                        <th class="text-end">
                                            Orders
                                        </th>

                                        <th class="text-end">
                                            Gross Sales
                                        </th>

                                        <th class="text-end">
                                            Discount
                                        </th>

                                        <th class="text-end">
                                            Net Sales
                                        </th>

                                        <th class="text-end">
                                            COGS
                                        </th>

                                        <th class="text-end">
                                            Gross Profit
                                        </th>

                                        <th class="text-end pe-4">
                                            Margin
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $dailyProfitRows
                                        as $row
                                    ): ?>


                                        <?php

                                        $dailyProfit =
                                            (float)
                                            $row['gross_profit'];

                                        ?>


                                        <tr>

                                            <td class="ps-4">

                                                <i
                                                    class="
                                                        bi
                                                        bi-calendar3
                                                        me-2
                                                        text-muted
                                                    "></i>


                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $row['sale_day']
                                                        )
                                                    )
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= number_format(
                                                    (int)
                                                    $row['orders']
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $row['gross_sales']
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $row['discount']
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $row['net_sales']
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <?= money(
                                                    $row['cogs']
                                                ) ?>

                                            </td>


                                            <td
                                                class="text-end">

                                                <strong
                                                    class="
                                                        <?= $dailyProfit >= 0
                                                            ? 'text-success'
                                                            : 'text-danger'
                                                        ?>
                                                    ">

                                                    <?= money(
                                                        $dailyProfit
                                                    ) ?>

                                                </strong>

                                            </td>


                                            <td
                                                class="
                                                    text-end
                                                    pe-4
                                                ">

                                                <span
                                                    class="
                                                        badge
                                                        <?= $row['margin'] >= 0
                                                            ? 'text-bg-success'
                                                            : 'text-bg-danger'
                                                        ?>
                                                    ">

                                                    <?= number_format(
                                                        $row['margin'],
                                                        2
                                                    ) ?>%

                                                </span>

                                            </td>

                                        </tr>


                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div
                            class="
                                text-center
                                py-5
                            ">

                            <i
                                class="
                                    bi
                                    bi-calendar-x
                                    fs-1
                                    text-muted
                                "></i>


                            <h5 class="mt-3">
                                No Daily Data Found
                            </h5>


                            <p
                                class="
                                    text-muted
                                    mb-0
                                ">

                                There are no completed sales
                                for this period.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- ==========================================================
                 REPORT INFORMATION
            =========================================================== -->

            <div
                class="
                    card
                    border-0
                    shadow-sm
                    mb-4
                ">

                <div class="card-body p-4">

                    <h5
                        class="
                            fw-semibold
                            mb-3
                        ">

                        <i
                            class="
                                bi
                                bi-info-circle
                                me-2
                            "></i>

                        Report Information

                    </h5>


                    <div class="row g-4">


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                ">
                                Report Period
                            </div>


                            <div class="fw-semibold">

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $fromDate
                                        )
                                    )
                                ) ?>

                                -

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $toDate
                                        )
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                ">
                                Calculation
                            </div>


                            <div class="fw-semibold">

                                Net Sales - COGS

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                ">
                                Cost Method
                            </div>


                            <div class="fw-semibold">

                                Historical Unit Cost

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div
                                class="
                                    text-muted
                                    small
                                ">
                                Profit Type
                            </div>


                            <div class="fw-semibold">

                                Gross Profit

                            </div>

                        </div>


                    </div>

                </div>

            </div>


        </div>

    </main>

</div>


<!-- ==============================================================
     PRINT CSS
=============================================================== -->

<style>
    @media print {

        @page {

            size: A4 landscape;

            margin: 10mm;

        }


        body {

            background: #ffffff !important;

        }


        .navbar,
        .sidebar,
        .btn,
        form,
        .alert,
        footer,
        .no-print {

            display: none !important;

        }


        .main-wrapper {

            display: block !important;

            margin: 0 !important;

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

            border: 1px solid #dee2e6 !important;

            break-inside: avoid;

        }


        .table {

            font-size: 10px;

        }


        .table th,
        .table td {

            padding: 5px !important;

        }


        .card-header {

            background: #ffffff !important;

        }


        h2 {

            font-size: 22px;

        }


        .display-6 {

            font-size: 25px;

        }


        .progress {

            border: 1px solid #ddd;

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


/*
|--------------------------------------------------------------------------
| CLOSE DATABASE
|--------------------------------------------------------------------------
*/

$conn->close();

?>