<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Purchase Report";


// ==========================================================================
// DATE FILTERS
// ==========================================================================

$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== ''
    ? $_GET['date_from']
    : date('Y-m-01');

$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== ''
    ? $_GET['date_to']
    : date('Y-m-d');


// ==========================================================================
// VALIDATE DATES
// ==========================================================================

$dateFromObj = DateTime::createFromFormat('Y-m-d', $dateFrom);
$dateToObj   = DateTime::createFromFormat('Y-m-d', $dateTo);

if (
    !$dateFromObj ||
    $dateFromObj->format('Y-m-d') !== $dateFrom
) {
    $dateFrom = date('Y-m-01');
}

if (
    !$dateToObj ||
    $dateToObj->format('Y-m-d') !== $dateTo
) {
    $dateTo = date('Y-m-d');
}


// ==========================================================================
// MAKE SURE DATE FROM IS NOT AFTER DATE TO
// ==========================================================================

if ($dateFrom > $dateTo) {

    $temp = $dateFrom;

    $dateFrom = $dateTo;
    $dateTo = $temp;
}


// ==========================================================================
// PURCHASE SUMMARY
// ==========================================================================

$summaryStmt = $conn->prepare("
    SELECT

        COUNT(
            CASE
                WHEN status = 'completed'
                THEN 1
            END
        ) AS completed_orders,

        COUNT(
            CASE
                WHEN status = 'cancelled'
                THEN 1
            END
        ) AS cancelled_orders,

        COUNT(*) AS total_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN total
                    ELSE 0
                END
            ),
            0
        ) AS total_purchases,

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
        ) AS total_tax

    FROM purchases

    WHERE purchase_date BETWEEN ? AND ?
");

if (!$summaryStmt) {
    die("Failed to prepare purchase summary query.");
}

$summaryStmt->bind_param(
    "ss",
    $dateFrom,
    $dateTo
);

$summaryStmt->execute();

$summaryResult = $summaryStmt->get_result();

$summary = $summaryResult->fetch_assoc();

$summaryStmt->close();


// ==========================================================================
// SUPPLIER SUMMARY
// ==========================================================================

$supplierStmt = $conn->prepare("
    SELECT

        COALESCE(
            s.name,
            'Unknown Supplier'
        ) AS supplier_name,

        COUNT(p.id) AS total_orders,

        COALESCE(
            SUM(p.total),
            0
        ) AS total_amount

    FROM purchases p

    LEFT JOIN suppliers s
        ON s.id = p.supplier_id

    WHERE p.purchase_date BETWEEN ? AND ?
      AND p.status = 'completed'

    GROUP BY
        p.supplier_id,
        s.name

    ORDER BY total_amount DESC
");

if (!$supplierStmt) {
    die("Failed to prepare supplier summary query.");
}

$supplierStmt->bind_param(
    "ss",
    $dateFrom,
    $dateTo
);

$supplierStmt->execute();

$supplierResult = $supplierStmt->get_result();

$suppliers = [];

while ($row = $supplierResult->fetch_assoc()) {

    $suppliers[] = $row;
}

$supplierStmt->close();


// ==========================================================================
// DETAILED PURCHASES
// ==========================================================================

$purchaseStmt = $conn->prepare("
    SELECT

        p.id,
        p.invoice_number,
        p.purchase_date,
        p.subtotal,
        p.discount,
        p.tax,
        p.total,
        p.status,

        COALESCE(
            s.name,
            'No Supplier'
        ) AS supplier_name,

        COALESCE(
            u.full_name,
            'Unknown'
        ) AS created_by

    FROM purchases p

    LEFT JOIN suppliers s
        ON s.id = p.supplier_id

    LEFT JOIN users u
        ON u.id = p.created_by

    WHERE p.purchase_date BETWEEN ? AND ?

    ORDER BY
        p.purchase_date DESC,
        p.id DESC
");

if (!$purchaseStmt) {
    die("Failed to prepare purchase query.");
}

$purchaseStmt->bind_param(
    "ss",
    $dateFrom,
    $dateTo
);

$purchaseStmt->execute();

$purchaseResult = $purchaseStmt->get_result();

$purchases = [];

while ($row = $purchaseResult->fetch_assoc()) {

    $purchases[] = $row;
}

$purchaseStmt->close();

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>


<style>

/* ================================================================
   PURCHASE REPORT
================================================================ */

.report-stat-card {
    border: 0;
    border-radius: 14px;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.report-stat-card:hover {
    transform: translateY(-2px);
}

.report-stat-icon {
    width: 48px;
    height: 48px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    font-size: 1.35rem;
}

.report-table th {
    white-space: nowrap;
    font-size: 0.82rem;
}

.report-table td {
    vertical-align: middle;
    font-size: 0.88rem;
}

.print-title {
    display: none;
}


/* ================================================================
   PRINT
================================================================ */

@media print {

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    body {
        background: #fff !important;
    }

    .navbar,
    .sidebar,
    .no-print {
        display: none !important;
    }

    .main-wrapper {
        margin-left: 0 !important;
    }

    .main-content {
        width: 100% !important;
    }

    .container-fluid {
        padding: 0 !important;
    }

    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    .print-title {
        display: block;
        margin-bottom: 20px;
    }

    .report-stat-card:hover {
        transform: none;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .report-table {
        width: 100% !important;
    }

    .report-table th,
    .report-table td {
        font-size: 10px !important;
    }

}

</style>


<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">

        <div class="container-fluid py-4">


            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div
                class="
                    d-flex
                    flex-column
                    flex-md-row
                    justify-content-between
                    align-items-md-center
                    gap-3
                    mb-4
                    no-print
                "
            >

                <div>

                    <h2 class="fw-bold mb-1">

                        <i class="bi bi-cart-check me-2"></i>

                        Purchase Report

                    </h2>

                    <p class="text-muted mb-0">

                        Analyze purchases and supplier transactions.

                    </p>

                </div>


                <div class="d-flex gap-2">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Reports

                    </a>


                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >

                        <i class="bi bi-printer me-1"></i>

                        Print Report

                    </button>

                </div>

            </div>


            <!-- =========================================================
                 PRINT TITLE
            ========================================================== -->

            <div class="print-title">

                <h2 class="fw-bold">
                    SmartPOS - Purchase Report
                </h2>

                <p class="mb-0">

                    Period:

                    <?= htmlspecialchars($dateFrom) ?>

                    to

                    <?= htmlspecialchars($dateTo) ?>

                </p>

            </div>


            <!-- =========================================================
                 DATE FILTER
            ========================================================== -->

            <div class="card border-0 shadow-sm mb-4 no-print">

                <div class="card-body p-4">

                    <form
                        method="GET"
                        action="purchases.php"
                    >

                        <div class="row g-3 align-items-end">


                            <!-- Date From -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="date_from"
                                    class="form-label fw-semibold"
                                >
                                    Date From
                                </label>

                                <input
                                    type="date"
                                    name="date_from"
                                    id="date_from"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateFrom) ?>"
                                    required
                                >

                            </div>


                            <!-- Date To -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="date_to"
                                    class="form-label fw-semibold"
                                >
                                    Date To
                                </label>

                                <input
                                    type="date"
                                    name="date_to"
                                    id="date_to"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateTo) ?>"
                                    required
                                >

                            </div>


                            <!-- Buttons -->

                            <div class="col-12 col-md-4">

                                <div class="d-flex gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-filter me-1"></i>

                                        Apply Filter

                                    </button>


                                    <a
                                        href="purchases.php"
                                        class="btn btn-outline-secondary"
                                    >

                                        <i class="bi bi-arrow-clockwise me-1"></i>

                                        Reset

                                    </a>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =========================================================
                 REPORT PERIOD
            ========================================================== -->

            <div class="mb-3">

                <span class="text-muted">

                    <i class="bi bi-calendar3 me-1"></i>

                    Report Period:

                    <strong>
                        <?= htmlspecialchars($dateFrom) ?>
                    </strong>

                    to

                    <strong>
                        <?= htmlspecialchars($dateTo) ?>
                    </strong>

                </span>

            </div>


            <!-- =========================================================
                 SUMMARY CARDS
            ========================================================== -->

            <div class="row g-4 mb-4">


                <!-- Total Purchases -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            report-stat-card
                            shadow-sm
                            h-100
                        "
                    >

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-primary-subtle
                                        text-primary
                                        me-3
                                    "
                                >

                                    <i class="bi bi-cart-check"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Total Purchases
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        LKR
                                        <?= number_format(
                                            (float)$summary['total_purchases'],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Completed Purchases -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            report-stat-card
                            shadow-sm
                            h-100
                        "
                    >

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-success-subtle
                                        text-success
                                        me-3
                                    "
                                >

                                    <i class="bi bi-check-circle"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Completed Purchases
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        <?= number_format(
                                            (int)$summary['completed_orders']
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Discount -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            report-stat-card
                            shadow-sm
                            h-100
                        "
                    >

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-warning-subtle
                                        text-warning
                                        me-3
                                    "
                                >

                                    <i class="bi bi-tag"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Total Discount
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        LKR
                                        <?= number_format(
                                            (float)$summary['total_discount'],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Tax -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div
                        class="
                            card
                            report-stat-card
                            shadow-sm
                            h-100
                        "
                    >

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="
                                        report-stat-icon
                                        bg-info-subtle
                                        text-info
                                        me-3
                                    "
                                >

                                    <i class="bi bi-percent"></i>

                                </div>


                                <div>

                                    <div class="text-muted small">
                                        Total Tax
                                    </div>

                                    <div class="fs-4 fw-bold">

                                        LKR
                                        <?= number_format(
                                            (float)$summary['total_tax'],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =========================================================
                 SUPPLIER SUMMARY
            ========================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white border-0 p-4">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-truck me-2"></i>

                        Supplier Performance

                    </h5>

                    <p class="text-muted small mb-0">

                        Completed purchases grouped by supplier.

                    </p>

                </div>


                <div class="card-body pt-0">

                    <?php if (empty($suppliers)): ?>

                        <div class="text-center text-muted py-4">

                            <i class="bi bi-truck fs-1 d-block mb-2"></i>

                            No supplier purchase data found.

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Supplier
                                        </th>

                                        <th class="text-center">
                                            Purchases
                                        </th>

                                        <th class="text-end">
                                            Total Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($suppliers as $supplier): ?>

                                        <tr>

                                            <td>

                                                <i class="bi bi-building me-2"></i>

                                                <?= htmlspecialchars(
                                                    $supplier['supplier_name']
                                                ) ?>

                                            </td>


                                            <td class="text-center">

                                                <?= number_format(
                                                    (int)$supplier['total_orders']
                                                ) ?>

                                            </td>


                                            <td class="text-end fw-semibold">

                                                LKR
                                                <?= number_format(
                                                    (float)$supplier['total_amount'],
                                                    2
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =========================================================
                 PURCHASE TRANSACTIONS
            ========================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 p-4">

                    <div
                        class="
                            d-flex
                            flex-column
                            flex-md-row
                            justify-content-between
                            align-items-md-center
                            gap-2
                        "
                    >

                        <div>

                            <h5 class="fw-bold mb-1">

                                <i class="bi bi-receipt me-2"></i>

                                Purchase Transactions

                            </h5>

                            <p class="text-muted small mb-0">

                                Detailed purchase transactions
                                for the selected period.

                            </p>

                        </div>


                        <div>

                            <span class="badge text-bg-secondary">

                                <?= number_format(
                                    count($purchases)
                                ) ?>

                                Transactions

                            </span>

                        </div>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (empty($purchases)): ?>

                        <div class="text-center py-5 px-3">

                            <i
                                class="
                                    bi
                                    bi-receipt-cutoff
                                    fs-1
                                    text-muted
                                "
                            ></i>

                            <h5 class="mt-3">
                                No Purchases Found
                            </h5>

                            <p class="text-muted mb-0">

                                There are no purchase transactions
                                for the selected date range.

                            </p>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table
                                class="
                                    table
                                    table-hover
                                    mb-0
                                    report-table
                                "
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            #
                                        </th>

                                        <th>
                                            Invoice
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Supplier
                                        </th>

                                        <th>
                                            Created By
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

                                        <th class="text-end pe-4 no-print">
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($purchases as $index => $purchase): ?>

                                        <tr>

                                            <td class="ps-4">

                                                <?= $index + 1 ?>

                                            </td>


                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $purchase['invoice_number']
                                                    ) ?>

                                                </strong>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $purchase['purchase_date']
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $purchase['supplier_name']
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $purchase['created_by']
                                                ) ?>

                                            </td>


                                            <td class="text-end">

                                                LKR
                                                <?= number_format(
                                                    (float)$purchase['subtotal'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end text-danger">

                                                LKR
                                                <?= number_format(
                                                    (float)$purchase['discount'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end">

                                                LKR
                                                <?= number_format(
                                                    (float)$purchase['tax'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td class="text-end fw-bold">

                                                LKR
                                                <?= number_format(
                                                    (float)$purchase['total'],
                                                    2
                                                ) ?>

                                            </td>


                                            <td>

                                                <?php if (
                                                    $purchase['status']
                                                    === 'completed'
                                                ): ?>

                                                    <span class="badge text-bg-success">

                                                        Completed

                                                    </span>

                                                <?php elseif (
                                                    $purchase['status']
                                                    === 'cancelled'
                                                ): ?>

                                                    <span class="badge text-bg-danger">

                                                        Cancelled

                                                    </span>

                                                <?php elseif (
                                                    $purchase['status']
                                                    === 'pending'
                                                ): ?>

                                                    <span class="badge text-bg-warning">

                                                        Pending

                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge text-bg-secondary">

                                                        <?= htmlspecialchars(
                                                            ucfirst(
                                                                $purchase['status']
                                                            )
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td class="text-end pe-4 no-print">

                                                <a
                                                    href="../purchases/view.php?id=<?= (int)$purchase['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="View Purchase"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =========================================================
                 REPORT INFORMATION
            ========================================================== -->

            <div class="text-muted small mt-3">

                <i class="bi bi-info-circle me-1"></i>

                Purchase totals include completed purchases only.
                Cancelled purchases remain visible for transaction history
                but are excluded from the total purchase amount.

            </div>

        </div>

    </main>

</div>


<?php

$conn->close();

include "../includes/footer.php";

?>