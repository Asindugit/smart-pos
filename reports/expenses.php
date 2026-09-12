<?php


// ---------------------------------------------------------
// Authentication
// ---------------------------------------------------------

require_once "../config/auth.php";

requireLogin();


// ---------------------------------------------------------
// Database
// ---------------------------------------------------------

require_once "../config/database.php";


// ---------------------------------------------------------
// Page settings
// ---------------------------------------------------------

$pageTitle = "Expense Report";


// ---------------------------------------------------------
// Helper functions
// ---------------------------------------------------------

function money($amount)
{
    return number_format((float) $amount, 2);
}

function cleanDate($date)
{
    $dateObject = DateTime::createFromFormat("Y-m-d", $date);

    if (
        $dateObject &&
        $dateObject->format("Y-m-d") === $date
    ) {
        return $date;
    }

    return null;
}

function expenseCategory($category)
{
    $category = trim((string) $category);

    if ($category === "") {
        return "Other";
    }

    return $category;
}


// ---------------------------------------------------------
// Date filters
// ---------------------------------------------------------

$fromDate = $_GET["from_date"] ?? date("Y-m-01");
$toDate   = $_GET["to_date"] ?? date("Y-m-d");


// ---------------------------------------------------------
// Validate dates
// ---------------------------------------------------------

$validFromDate = cleanDate($fromDate);
$validToDate   = cleanDate($toDate);

if ($validFromDate === null) {
    $fromDate = date("Y-m-01");
} else {
    $fromDate = $validFromDate;
}

if ($validToDate === null) {
    $toDate = date("Y-m-d");
} else {
    $toDate = $validToDate;
}


// ---------------------------------------------------------
// Swap dates if required
// ---------------------------------------------------------

if ($fromDate > $toDate) {

    $temporary = $fromDate;

    $fromDate = $toDate;
    $toDate = $temporary;
}


// ---------------------------------------------------------
// Summary statistics
// ---------------------------------------------------------

$totalExpenses = 0;
$expenseCount = 0;
$averageExpense = 0;


// ---------------------------------------------------------
// Get summary
// ---------------------------------------------------------

$summarySql = "
    SELECT
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total_expenses,
        COALESCE(AVG(amount), 0) AS average_expense
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$summaryStmt = $conn->prepare($summarySql);

if ($summaryStmt) {

    $summaryStmt->bind_param(
        "ss",
        $fromDate,
        $toDate
    );

    $summaryStmt->execute();

    $summaryResult = $summaryStmt->get_result();

    if ($summaryResult) {

        $summary = $summaryResult->fetch_assoc();

        if ($summary) {

            $expenseCount = (int) ($summary["expense_count"] ?? 0);

            $totalExpenses = (float) ($summary["total_expenses"] ?? 0);

            $averageExpense = (float) ($summary["average_expense"] ?? 0);
        }
    }

    $summaryStmt->close();
}


// ---------------------------------------------------------
// Category summary
// ---------------------------------------------------------

$categorySummary = [];

$categorySql = "
    SELECT
        COALESCE(NULLIF(TRIM(category), ''), 'Other') AS category,
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'Other')
    ORDER BY total_amount DESC, category ASC
";

$categoryStmt = $conn->prepare($categorySql);

if ($categoryStmt) {

    $categoryStmt->bind_param(
        "ss",
        $fromDate,
        $toDate
    );

    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();

    if ($categoryResult) {

        while ($row = $categoryResult->fetch_assoc()) {

            $categorySummary[] = $row;
        }
    }

    $categoryStmt->close();
}


// ---------------------------------------------------------
// Detailed expenses
// ---------------------------------------------------------

$expenses = [];

$expensesSql = "
    SELECT
        e.id,
        e.title,
        e.category,
        e.amount,
        e.expense_date,
        e.description,
        e.created_at,
        u.full_name AS created_by_name
    FROM expenses e
    LEFT JOIN users u
        ON u.id = e.created_by
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date DESC, e.id DESC
";

$expensesStmt = $conn->prepare($expensesSql);

if ($expensesStmt) {

    $expensesStmt->bind_param(
        "ss",
        $fromDate,
        $toDate
    );

    $expensesStmt->execute();

    $expensesResult = $expensesStmt->get_result();

    if ($expensesResult) {

        while ($row = $expensesResult->fetch_assoc()) {

            $expenses[] = $row;
        }
    }

    $expensesStmt->close();
}


// ---------------------------------------------------------
// Calculate category percentages
// ---------------------------------------------------------

foreach ($categorySummary as &$category) {

    $categoryAmount = (float) ($category["total_amount"] ?? 0);

    if ($totalExpenses > 0) {

        $category["percentage"] =
            ($categoryAmount / $totalExpenses) * 100;

    } else {

        $category["percentage"] = 0;
    }
}

unset($category);


// ---------------------------------------------------------
// Include header
// ---------------------------------------------------------

include "../includes/header.php";


// ---------------------------------------------------------
// Navbar
// ---------------------------------------------------------

include "../includes/navbar.php";

?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">


            <!-- =====================================================
                 PAGE HEADER
            ====================================================== -->

            <div
                class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"
            >

                <div>

                    <h1 class="h3 fw-bold mb-1">

                        <i class="bi bi-wallet2 text-primary me-2"></i>

                        Expense Report

                    </h1>

                    <p class="text-muted mb-0">

                        Analyze business expenses by date and category.

                    </p>

                </div>


                <div class="d-flex flex-wrap gap-2">

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


            <!-- =====================================================
                 DATE FILTER
            ====================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white border-0 py-3">

                    <div class="d-flex align-items-center">

                        <div
                            class="bg-primary-subtle text-primary rounded p-2 me-3"
                        >

                            <i class="bi bi-calendar-range"></i>

                        </div>

                        <div>

                            <h5 class="mb-0 fw-semibold">
                                Report Period
                            </h5>

                            <small class="text-muted">
                                Select the date range for the expense report.
                            </small>

                        </div>

                    </div>

                </div>


                <div class="card-body">

                    <form
                        method="GET"
                        action=""
                        class="row g-3 align-items-end"
                    >

                        <div class="col-md-4">

                            <label
                                for="from_date"
                                class="form-label fw-semibold"
                            >
                                From Date
                            </label>

                            <input
                                type="date"
                                id="from_date"
                                name="from_date"
                                class="form-control"
                                value="<?= htmlspecialchars($fromDate) ?>"
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                for="to_date"
                                class="form-label fw-semibold"
                            >
                                To Date
                            </label>

                            <input
                                type="date"
                                id="to_date"
                                name="to_date"
                                class="form-control"
                                value="<?= htmlspecialchars($toDate) ?>"
                            >

                        </div>


                        <div class="col-md-4">

                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-funnel me-1"></i>

                                    Apply Filter

                                </button>


                                <a
                                    href="expenses.php"
                                    class="btn btn-outline-secondary"
                                >

                                    <i class="bi bi-arrow-counterclockwise"></i>

                                    Reset

                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =====================================================
                 SUMMARY CARDS
            ====================================================== -->

            <div class="row g-4 mb-4">


                <!-- Total Expenses -->

                <div class="col-sm-6 col-xl-4">

                    <div
                        class="card border-0 shadow-sm h-100"
                    >

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Total Expenses
                                    </p>

                                    <h3 class="fw-bold mb-0">
                                        LKR <?= money($totalExpenses) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-danger-subtle text-danger rounded p-3"
                                >

                                    <i class="bi bi-wallet2 fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Number of Expenses -->

                <div class="col-sm-6 col-xl-4">

                    <div
                        class="card border-0 shadow-sm h-100"
                    >

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Expense Records
                                    </p>

                                    <h3 class="fw-bold mb-0">
                                        <?= number_format($expenseCount) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-primary-subtle text-primary rounded p-3"
                                >

                                    <i class="bi bi-receipt fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Average -->

                <div class="col-sm-6 col-xl-4">

                    <div
                        class="card border-0 shadow-sm h-100"
                    >

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Average Expense
                                    </p>

                                    <h3 class="fw-bold mb-0">
                                        LKR <?= money($averageExpense) ?>
                                    </h3>

                                </div>

                                <div
                                    class="bg-warning-subtle text-warning rounded p-3"
                                >

                                    <i class="bi bi-calculator fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 REPORT PERIOD INFORMATION
            ====================================================== -->

            <div class="alert alert-light border mb-4">

                <div class="d-flex align-items-start">

                    <i
                        class="bi bi-info-circle text-primary fs-5 me-3"
                    ></i>

                    <div>

                        <strong>Report Period:</strong>

                        <?= htmlspecialchars($fromDate) ?>

                        <span class="mx-2">to</span>

                        <?= htmlspecialchars($toDate) ?>

                        <div class="small text-muted mt-1">

                            Total expenses recorded during the selected
                            period are shown below.

                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 CATEGORY SUMMARY
            ====================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white border-0 py-3">

                    <div class="d-flex align-items-center">

                        <div
                            class="bg-warning-subtle text-warning rounded p-2 me-3"
                        >

                            <i class="bi bi-pie-chart"></i>

                        </div>

                        <div>

                            <h5 class="mb-0 fw-semibold">
                                Expense by Category
                            </h5>

                            <small class="text-muted">
                                Breakdown of expenses by category.
                            </small>

                        </div>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (!empty($categorySummary)): ?>

                        <div class="table-responsive">

                            <table
                                class="table table-hover align-middle mb-0"
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Category
                                        </th>

                                        <th class="text-center">
                                            Records
                                        </th>

                                        <th class="text-end">
                                            Amount
                                        </th>

                                        <th
                                            class="text-end"
                                            style="width: 35%;"
                                        >
                                            Percentage
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($categorySummary as $category): ?>

                                        <tr>

                                            <td>

                                                <div
                                                    class="d-flex align-items-center"
                                                >

                                                    <span
                                                        class="bg-light rounded p-2 me-2"
                                                    >

                                                        <i class="bi bi-tag"></i>

                                                    </span>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            expenseCategory($category["category"])
                                                        ) ?>

                                                    </strong>

                                                </div>

                                            </td>


                                            <td class="text-center">

                                                <?= number_format(
                                                    (int) $category["expense_count"]
                                                ) ?>

                                            </td>


                                            <td class="text-end fw-semibold">

                                                LKR
                                                <?= money($category["total_amount"]) ?>

                                            </td>


                                            <td>

                                                <div
                                                    class="d-flex align-items-center gap-2"
                                                >

                                                    <div
                                                        class="progress flex-grow-1"
                                                        style="height: 8px;"
                                                    >

                                                        <div
                                                            class="progress-bar"
                                                            role="progressbar"
                                                            style="width: <?= min(100, (float) $category["percentage"]) ?>%;"
                                                            aria-valuenow="<?= (float) $category["percentage"] ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100"
                                                        ></div>

                                                    </div>

                                                    <small
                                                        class="text-muted"
                                                        style="min-width: 55px;"
                                                    >

                                                        <?= number_format(
                                                            (float) $category["percentage"],
                                                            1
                                                        ) ?>%

                                                    </small>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>


                                <tfoot class="table-light">

                                    <tr>

                                        <th>
                                            Total
                                        </th>

                                        <th class="text-center">
                                            <?= number_format($expenseCount) ?>
                                        </th>

                                        <th class="text-end">
                                            LKR <?= money($totalExpenses) ?>
                                        </th>

                                        <th class="text-end">
                                            100.0%
                                        </th>

                                    </tr>

                                </tfoot>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i
                                class="bi bi-wallet2 text-muted fs-1"
                            ></i>

                            <h5 class="mt-3">
                                No expense data
                            </h5>

                            <p class="text-muted mb-0">

                                No expenses were recorded during the
                                selected date range.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
                 DETAILED EXPENSE REPORT
            ====================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 py-3">

                    <div class="d-flex align-items-center">

                        <div
                            class="bg-primary-subtle text-primary rounded p-2 me-3"
                        >

                            <i class="bi bi-list-ul"></i>

                        </div>

                        <div>

                            <h5 class="mb-0 fw-semibold">
                                Expense Details
                            </h5>

                            <small class="text-muted">
                                Detailed expense records for the selected
                                period.
                            </small>

                        </div>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (!empty($expenses)): ?>

                        <div class="table-responsive">

                            <table
                                class="table table-hover align-middle mb-0"
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Expense
                                        </th>

                                        <th>
                                            Category
                                        </th>

                                        <th>
                                            Description
                                        </th>

                                        <th>
                                            Created By
                                        </th>

                                        <th class="text-end">
                                            Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($expenses as $expense): ?>

                                        <tr>

                                            <td class="text-nowrap">

                                                <div class="fw-semibold">

                                                    <?= htmlspecialchars(
                                                        date(
                                                            "d M Y",
                                                            strtotime($expense["expense_date"])
                                                        )
                                                    ) ?>

                                                </div>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        date(
                                                            "D",
                                                            strtotime($expense["expense_date"])
                                                        )
                                                    ) ?>

                                                </small>

                                            </td>


                                            <td>

                                                <div class="fw-semibold">

                                                    <?= htmlspecialchars(
                                                        $expense["title"]
                                                    ) ?>

                                                </div>

                                            </td>


                                            <td>

                                                <span class="badge text-bg-light">

                                                    <i class="bi bi-tag me-1"></i>

                                                    <?= htmlspecialchars(
                                                        expenseCategory(
                                                            $expense["category"]
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?php if (
                                                    !empty($expense["description"])
                                                ): ?>

                                                    <span
                                                        class="text-muted"
                                                        title="<?= htmlspecialchars($expense["description"]) ?>"
                                                    >

                                                        <?= htmlspecialchars(
                                                            strlen($expense["description"]) > 60
                                                                ? substr(
                                                                    $expense["description"],
                                                                    0,
                                                                    60
                                                                ) . "..."
                                                                : $expense["description"]
                                                        ) ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?php if (
                                                    !empty($expense["created_by_name"])
                                                ): ?>

                                                    <span>

                                                        <i
                                                            class="bi bi-person-circle me-1 text-muted"
                                                        ></i>

                                                        <?= htmlspecialchars(
                                                            $expense["created_by_name"]
                                                        ) ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        System
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td class="text-end">

                                                <strong class="text-danger">

                                                    LKR
                                                    <?= money($expense["amount"]) ?>

                                                </strong>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>


                                <tfoot class="table-light">

                                    <tr>

                                        <th colspan="5" class="text-end">

                                            Total Expenses

                                        </th>

                                        <th class="text-end text-danger">

                                            LKR
                                            <?= money($totalExpenses) ?>

                                        </th>

                                    </tr>

                                </tfoot>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <div
                                class="mb-3"
                            >

                                <i
                                    class="bi bi-wallet2 text-muted"
                                    style="font-size: 3rem;"
                                ></i>

                            </div>

                            <h5 class="fw-semibold">
                                No Expenses Found
                            </h5>

                            <p class="text-muted mb-0">

                                There are no expense records for the
                                selected date range.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
                 REPORT INFORMATION
            ====================================================== -->

            <div class="card border-0 shadow-sm mt-4">

                <div class="card-body">

                    <div class="row g-4">

                        <div class="col-md-4">

                            <div class="d-flex">

                                <i
                                    class="bi bi-calendar3 text-primary fs-5 me-3"
                                ></i>

                                <div>

                                    <div class="fw-semibold">
                                        Report Period
                                    </div>

                                    <div class="text-muted small">

                                        <?= htmlspecialchars($fromDate) ?>
                                        -
                                        <?= htmlspecialchars($toDate) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="d-flex">

                                <i
                                    class="bi bi-receipt text-primary fs-5 me-3"
                                ></i>

                                <div>

                                    <div class="fw-semibold">
                                        Total Records
                                    </div>

                                    <div class="text-muted small">

                                        <?= number_format($expenseCount) ?>

                                        expense record(s)

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="d-flex">

                                <i
                                    class="bi bi-currency-exchange text-primary fs-5 me-3"
                                ></i>

                                <div>

                                    <div class="fw-semibold">
                                        Total Expense
                                    </div>

                                    <div class="text-muted small">

                                        LKR <?= money($totalExpenses) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


        </div>

    </main>

</div>


<style>

/* =========================================================
   PRINT STYLES
   ========================================================= */

@media print {

    @page {

        size: A4 landscape;

        margin: 12mm;

    }


    body {

        background: #fff !important;

        font-size: 11px;

    }


    .navbar,
    .sidebar,
    .offcanvas,
    .btn,
    form,
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

        width: 100% !important;

        padding: 0 !important;

        margin: 0 !important;

    }


    .card {

        border: 1px solid #ddd !important;

        box-shadow: none !important;

        break-inside: avoid;

    }


    .card-header {

        background: #f8f9fa !important;

    }


    .shadow-sm {

        box-shadow: none !important;

    }


    .table {

        font-size: 10px;

    }


    .table th,
    .table td {

        padding: 5px 7px;

    }


    .progress {

        display: none !important;

    }


    h1 {

        font-size: 20px;

    }


    h5 {

        font-size: 14px;

    }


    .alert {

        border: 1px solid #ddd !important;

    }

}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 575.98px) {

    .card-body {

        padding: 1rem !important;

    }


    h1 {

        font-size: 1.5rem;

    }


    .table {

        font-size: 0.875rem;

    }

}

</style>


<?php

// ---------------------------------------------------------
// Close database connection
// ---------------------------------------------------------

$conn->close();


// ---------------------------------------------------------
// Footer
// ---------------------------------------------------------

include "../includes/footer.php";

?>