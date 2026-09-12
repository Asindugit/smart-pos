<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Purchases";

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$supplierId = filter_input(
    INPUT_GET,
    "supplier_id",
    FILTER_VALIDATE_INT
);

$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$success = trim($_GET["success"] ?? "");
$error = "";

if (!in_array(
    $status,
    ["pending", "completed", "cancelled"],
    true
)) {
    $status = "";
}

/*
|--------------------------------------------------------------------------
| Load Suppliers
|--------------------------------------------------------------------------
*/

$suppliers = [];

$supplierResult = $conn->query("
    SELECT
        id,
        name,
        company_name
    FROM suppliers
    ORDER BY name ASC
");

if ($supplierResult) {

    while ($supplier = $supplierResult->fetch_assoc()) {

        $suppliers[] = $supplier;
    }

    $supplierResult->free();
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    "total_purchases" => 0,
    "completed_purchases" => 0,
    "pending_purchases" => 0,
    "cancelled_purchases" => 0,
    "purchase_value" => 0
];

$statsResult = $conn->query("
    SELECT

        COUNT(*) AS total_purchases,

        SUM(
            CASE
                WHEN status = 'completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_purchases,

        SUM(
            CASE
                WHEN status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_purchases,

        SUM(
            CASE
                WHEN status = 'cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled_purchases,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN total
                    ELSE 0
                END
            ),
            0
        ) AS purchase_value

    FROM purchases
");

if ($statsResult) {

    $statsData = $statsResult->fetch_assoc();

    if ($statsData) {

        $stats = $statsData;
    }

    $statsResult->free();
}

/*
|--------------------------------------------------------------------------
| Build Purchase Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.invoice_number,
        p.purchase_date,
        p.subtotal,
        p.discount,
        p.tax,
        p.total,
        p.status,
        p.notes,
        p.created_at,

        s.name AS supplier_name,
        s.company_name,

        u.full_name AS created_by_name

    FROM purchases p

    LEFT JOIN suppliers s
        ON s.id = p.supplier_id

    LEFT JOIN users u
        ON u.id = p.created_by

    WHERE 1 = 1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            p.invoice_number LIKE ?
            OR s.name LIKE ?
            OR s.company_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== "") {

    $sql .= "
        AND p.status = ?
    ";

    $params[] = $status;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Supplier Filter
|--------------------------------------------------------------------------
*/

if ($supplierId && $supplierId > 0) {

    $sql .= "
        AND p.supplier_id = ?
    ";

    $params[] = $supplierId;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== "") {

    $dateObject = DateTime::createFromFormat(
        "Y-m-d",
        $dateFrom
    );

    if (
        $dateObject &&
        $dateObject->format("Y-m-d") === $dateFrom
    ) {

        $sql .= "
            AND p.purchase_date >= ?
        ";

        $params[] = $dateFrom;

        $types .= "s";

    } else {

        $dateFrom = "";
    }
}

/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== "") {

    $dateObject = DateTime::createFromFormat(
        "Y-m-d",
        $dateTo
    );

    if (
        $dateObject &&
        $dateObject->format("Y-m-d") === $dateTo
    ) {

        $sql .= "
            AND p.purchase_date <= ?
        ";

        $params[] = $dateTo;

        $types .= "s";

    } else {

        $dateTo = "";
    }
}

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.purchase_date DESC,
        p.id DESC
";

/*
|--------------------------------------------------------------------------
| Execute Purchase Query
|--------------------------------------------------------------------------
*/

$purchases = [];

$stmt = $conn->prepare($sql);

if (!$stmt) {

    $error =
        "Unable to load purchases.";

} else {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    if (!$stmt->execute()) {

        $error =
            "Unable to load purchases.";

    } else {

        $result =
            $stmt->get_result();

        while ($purchase = $result->fetch_assoc()) {

            $purchases[] = $purchase;
        }

        $result->free();
    }

    $stmt->close();
}

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- Page Header -->

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">
                            <i class="bi bi-bag-plus"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            Purchases
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Manage supplier purchases and stock receiving.
                    </p>

                </div>

                <a
                    href="add.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-circle me-1"></i>
                    New Purchase
                </a>

            </div>


            <!-- Success -->

            <?php if ($success !== ""): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- Error -->

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- Statistics -->

            <div class="row g-4 mb-4">

                <div class="col-12 col-sm-6 col-xl">

                    <div class="card stat-card stat-card-blue h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <div class="stat-label">
                                        Total Purchases
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format(
                                            (int) $stats["total_purchases"]
                                        ) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-blue">

                                    <i class="bi bi-bag"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12 col-sm-6 col-xl">

                    <div class="card stat-card stat-card-green h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <div class="stat-label">
                                        Completed
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format(
                                            (int) $stats["completed_purchases"]
                                        ) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-green">

                                    <i class="bi bi-check-circle"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12 col-sm-6 col-xl">

                    <div class="card stat-card stat-card-cyan h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <div class="stat-label">
                                        Pending
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format(
                                            (int) $stats["pending_purchases"]
                                        ) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-cyan">

                                    <i class="bi bi-clock"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12 col-sm-6 col-xl">

                    <div class="card stat-card stat-card-red h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <div class="stat-label">
                                        Cancelled
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format(
                                            (int) $stats["cancelled_purchases"]
                                        ) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-red">

                                    <i class="bi bi-x-circle"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12 col-xl">

                    <div class="card stat-card h-100">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <div class="stat-label">
                                        Purchase Value
                                    </div>

                                    <div
                                        class="stat-value fw-bold mt-1"
                                        style="font-size: 21px;"
                                    >
                                        LKR
                                        <?= number_format(
                                            (float) $stats["purchase_value"],
                                            2
                                        ) ?>
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-blue">

                                    <i class="bi bi-cash-stack"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Filters -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white border-0 p-4">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">

                            <i class="bi bi-funnel"></i>

                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Search & Filters
                            </h5>

                            <small class="text-muted">
                                Find purchases by invoice, supplier, status or date.
                            </small>

                        </div>

                    </div>

                </div>

                <div class="card-body p-4">

                    <form method="GET">

                        <div class="row g-3">

                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >
                                    Search
                                </label>

                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    class="form-control"
                                    placeholder="Invoice or supplier"
                                    value="<?= htmlspecialchars($search) ?>"
                                >

                            </div>


                            <div class="col-12 col-md-6 col-xl-2">

                                <label
                                    for="status"
                                    class="form-label fw-semibold"
                                >
                                    Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Statuses
                                    </option>

                                    <option
                                        value="completed"
                                        <?= $status === "completed" ? "selected" : "" ?>
                                    >
                                        Completed
                                    </option>

                                    <option
                                        value="pending"
                                        <?= $status === "pending" ? "selected" : "" ?>
                                    >
                                        Pending
                                    </option>

                                    <option
                                        value="cancelled"
                                        <?= $status === "cancelled" ? "selected" : "" ?>
                                    >
                                        Cancelled
                                    </option>

                                </select>

                            </div>


                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="supplier_id"
                                    class="form-label fw-semibold"
                                >
                                    Supplier
                                </label>

                                <select
                                    id="supplier_id"
                                    name="supplier_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Suppliers
                                    </option>

                                    <?php foreach ($suppliers as $supplier): ?>

                                        <option
                                            value="<?= (int) $supplier["id"] ?>"
                                            <?= (int) $supplierId === (int) $supplier["id"] ? "selected" : "" ?>
                                        >

                                            <?= htmlspecialchars(
                                                $supplier["name"]
                                            ) ?>

                                            <?php if (
                                                !empty(
                                                    $supplier["company_name"]
                                                )
                                            ): ?>

                                                -
                                                <?= htmlspecialchars(
                                                    $supplier["company_name"]
                                                ) ?>

                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-12 col-md-6 col-xl-2">

                                <label
                                    for="date_from"
                                    class="form-label fw-semibold"
                                >
                                    From
                                </label>

                                <input
                                    type="date"
                                    id="date_from"
                                    name="date_from"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateFrom) ?>"
                                >

                            </div>


                            <div class="col-12 col-md-6 col-xl-2">

                                <label
                                    for="date_to"
                                    class="form-label fw-semibold"
                                >
                                    To
                                </label>

                                <input
                                    type="date"
                                    id="date_to"
                                    name="date_to"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateTo) ?>"
                                >

                            </div>


                            <div class="col-12">

                                <div class="d-flex flex-wrap gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-search me-1"></i>

                                        Apply Filters

                                    </button>

                                    <a
                                        href="index.php"
                                        class="btn btn-outline-secondary"
                                    >

                                        <i class="bi bi-arrow-counterclockwise me-1"></i>

                                        Reset

                                    </a>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- Purchase Table -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 p-4">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Purchase Records
                            </h5>

                            <small class="text-muted">
                                <?= count($purchases) ?> purchase(s) found
                            </small>

                        </div>

                        <a
                            href="add.php"
                            class="btn btn-primary btn-sm"
                        >

                            <i class="bi bi-plus-circle me-1"></i>

                            New Purchase

                        </a>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if (empty($purchases)): ?>

                        <div class="empty-state text-center py-5">

                            <div class="empty-icon">

                                <i class="bi bi-bag-x"></i>

                            </div>

                            <h5 class="fw-bold mt-3">
                                No Purchases Found
                            </h5>

                            <p class="text-muted mb-3">
                                There are no purchase records matching your filters.
                            </p>

                            <a
                                href="add.php"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-plus-circle me-1"></i>

                                Create Purchase

                            </a>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th class="px-4">
                                            Invoice
                                        </th>

                                        <th>
                                            Supplier
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Created By
                                        </th>

                                        <th class="text-end px-4">
                                            Actions
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($purchases as $purchase): ?>

                                        <?php

                                        $statusClass = "text-bg-secondary";

                                        if (
                                            $purchase["status"] === "completed"
                                        ) {
                                            $statusClass =
                                                "text-bg-success";
                                        } elseif (
                                            $purchase["status"] === "pending"
                                        ) {
                                            $statusClass =
                                                "text-bg-warning";
                                        } elseif (
                                            $purchase["status"] === "cancelled"
                                        ) {
                                            $statusClass =
                                                "text-bg-danger";
                                        }

                                        ?>

                                        <tr>

                                            <td class="px-4">

                                                <span class="invoice-badge">

                                                    <i
                                                        class="bi bi-receipt me-1"
                                                    ></i>

                                                    <?= htmlspecialchars(
                                                        $purchase["invoice_number"]
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <div class="fw-semibold">

                                                    <?= htmlspecialchars(
                                                        $purchase["supplier_name"]
                                                        ?? "Unknown Supplier"
                                                    ) ?>

                                                </div>

                                                <?php if (
                                                    !empty(
                                                        $purchase["company_name"]
                                                    )
                                                ): ?>

                                                    <small class="text-muted">

                                                        <?= htmlspecialchars(
                                                            $purchase["company_name"]
                                                        ) ?>

                                                    </small>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <span class="sale-date">

                                                    <?= htmlspecialchars(
                                                        date(
                                                            "d M Y",
                                                            strtotime(
                                                                $purchase["purchase_date"]
                                                            )
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <span class="sale-total">

                                                    LKR
                                                    <?= number_format(
                                                        (float) $purchase["total"],
                                                        2
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <span
                                                    class="badge <?= $statusClass ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        ucfirst(
                                                            $purchase["status"]
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $purchase["created_by_name"]
                                                    ?? "Unknown"
                                                ) ?>

                                            </td>


                                            <td class="text-end px-4">

                                                <div
                                                    class="d-flex justify-content-end gap-1"
                                                >

                                                    <a
                                                        href="view.php?id=<?= (int) $purchase["id"] ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="View Purchase"
                                                    >

                                                        <i class="bi bi-eye"></i>

                                                    </a>


                                                    <?php if (
                                                        $purchase["status"] === "completed"
                                                    ): ?>

                                                        <a
                                                            href="cancel.php?id=<?= (int) $purchase["id"] ?>"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Cancel Purchase"
                                                        >

                                                            <i class="bi bi-x-circle"></i>

                                                        </a>

                                                    <?php endif; ?>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

<?php

$conn->close();

?>

<?php include "../includes/footer.php"; ?>