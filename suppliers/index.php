<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Suppliers";

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| Limit Search Length
|--------------------------------------------------------------------------
*/

if (mb_strlen($search) > 150) {
    $search = mb_substr($search, 0, 150);
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

$status = $_GET["status"] ?? "";

$allowedStatuses = ["active", "inactive"];

if (!in_array($status, $allowedStatuses, true)) {
    $status = "";
}

/*
|--------------------------------------------------------------------------
| Build Supplier Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        supplier_code,
        name,
        company_name,
        phone,
        email,
        address,
        status,
        created_at
    FROM suppliers
    WHERE 1 = 1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            supplier_code LIKE ?
            OR name LIKE ?
            OR company_name LIKE ?
            OR phone LIKE ?
            OR email LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sssss";
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== "") {

    $sql .= " AND status = ?";

    $params[] = $status;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= " ORDER BY id DESC";

/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {

    $stmt = null;
    $result = false;
    $queryError = "Unable to load suppliers.";

} else {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {

        $result = false;
        $queryError = "Unable to load suppliers.";

    } else {

        $result = $stmt->get_result();
        $queryError = "";
    }
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$success = trim($_GET["success"] ?? "");
$error = trim($_GET["error"] ?? "");

if (mb_strlen($success) > 500) {
    $success = mb_substr($success, 0, 500);
}

if (mb_strlen($error) > 500) {
    $error = mb_substr($error, 0, 500);
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statsSql = "
    SELECT
        COUNT(*) AS total_suppliers,
        COALESCE(SUM(status = 'active'), 0) AS active_suppliers,
        COALESCE(SUM(status = 'inactive'), 0) AS inactive_suppliers
    FROM suppliers
";

$statsResult = $conn->query($statsSql);

if ($statsResult) {

    $stats = $statsResult->fetch_assoc();

} else {

    $stats = [
        "total_suppliers" => 0,
        "active_suppliers" => 0,
        "inactive_suppliers" => 0
    ];
}

$totalSuppliers = (int) ($stats["total_suppliers"] ?? 0);
$activeSuppliers = (int) ($stats["active_suppliers"] ?? 0);
$inactiveSuppliers = (int) ($stats["inactive_suppliers"] ?? 0);

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
                            <i class="bi bi-truck"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            Suppliers
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Manage supplier information and supplier records.
                    </p>

                </div>

                <div>

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus-circle me-1"></i>
                        Add Supplier
                    </a>

                </div>

            </div>

            <!-- Alerts -->

            <?php if ($success !== ""): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <?php if (!empty($queryError)): ?>

                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars(
                        $queryError,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

            <?php endif; ?>

            <!-- Statistics -->

            <div class="row g-4 mb-4">

                <div class="col-12 col-md-4">

                    <div class="stat-card stat-card-blue h-100">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="stat-label">
                                        Total Suppliers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($totalSuppliers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-blue mt-2">
                                        <i class="bi bi-truck"></i>
                                        All supplier records
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-blue">
                                    <i class="bi bi-truck"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-md-4">

                    <div class="stat-card stat-card-green h-100">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="stat-label">
                                        Active Suppliers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($activeSuppliers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-green mt-2">
                                        <i class="bi bi-check-circle"></i>
                                        Available for purchases
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-green">
                                    <i class="bi bi-building-check"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-md-4">

                    <div class="stat-card stat-card-red h-100">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="stat-label">
                                        Inactive Suppliers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($inactiveSuppliers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-red mt-2">
                                        <i class="bi bi-building-x"></i>
                                        Currently inactive
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-red">
                                    <i class="bi bi-building-x"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Supplier List -->

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="dashboard-section-header">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">
                            <i class="bi bi-truck"></i>
                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Supplier List
                            </h5>

                            <small class="text-muted">
                                View and manage registered suppliers.
                            </small>

                        </div>

                    </div>

                </div>

                <!-- Filters -->

                <div class="card-body border-top">

                    <form method="GET" action="">

                        <div class="row g-3 align-items-end">

                            <div class="col-12 col-lg-6">

                                <label class="form-label fw-semibold">
                                    Search Supplier
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>

                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="Name, code, company, phone or email"
                                        value="<?= htmlspecialchars(
                                            $search,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        maxlength="150"
                                    >

                                </div>

                            </div>

                            <div class="col-12 col-md-6 col-lg-3">

                                <label class="form-label fw-semibold">
                                    Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Statuses
                                    </option>

                                    <option
                                        value="active"
                                        <?= $status === "active" ? "selected" : "" ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $status === "inactive" ? "selected" : "" ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-search me-1"></i>
                                    Search
                                </button>

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>

                            </div>

                        </div>

                    </form>

                </div>

                <!-- Table -->

                <div class="table-responsive">

                    <table class="table align-middle dashboard-table">

                        <thead>

                            <tr>

                                <th>Supplier</th>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if ($result && $result->num_rows > 0): ?>

                                <?php while ($supplier = $result->fetch_assoc()): ?>

                                    <tr>

                                        <td>

                                            <div class="d-flex align-items-center gap-3">

                                                <div class="stock-product-icon bg-primary-subtle text-primary">
                                                    <i class="bi bi-truck"></i>
                                                </div>

                                                <div>

                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars(
                                                            $supplier["name"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>
                                                    </div>

                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(
                                                            $supplier["supplier_code"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>
                                                    </small>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <?php if (!empty($supplier["company_name"])): ?>

                                                <?= htmlspecialchars(
                                                    $supplier["company_name"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">—</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if (!empty($supplier["phone"])): ?>

                                                <i class="bi bi-telephone me-1 text-muted"></i>

                                                <?= htmlspecialchars(
                                                    $supplier["phone"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">—</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if (!empty($supplier["email"])): ?>

                                                <?= htmlspecialchars(
                                                    $supplier["email"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">—</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if (!empty($supplier["address"])): ?>

                                                <span
                                                    title="<?= htmlspecialchars(
                                                        $supplier["address"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        mb_strlen($supplier["address"]) > 35
                                                            ? mb_substr($supplier["address"], 0, 35) . "..."
                                                            : $supplier["address"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted">—</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ($supplier["status"] === "active"): ?>

                                                <span class="badge text-bg-success">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge text-bg-secondary">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <span class="sale-date">
                                                <?= htmlspecialchars(
                                                    date(
                                                        "d M Y",
                                                        strtotime($supplier["created_at"])
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>

                                        </td>

                                        <td class="text-end">

                                            <div class="btn-group">

                                                <a
                                                    href="edit.php?id=<?= (int) $supplier["id"] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit Supplier"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <?php if (
                                                    isset($_SESSION["user_role"]) &&
                                                    $_SESSION["user_role"] === "admin"
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="delete.php"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this supplier?');"
                                                    >

                                                        <?= csrfField() ?>

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $supplier["id"] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete Supplier"
                                                        >
                                                            <i class="bi bi-trash"></i>
                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="8"
                                        class="text-center"
                                    >

                                        <div class="empty-state py-5">

                                            <div class="empty-icon mb-3">
                                                <i class="bi bi-truck"></i>
                                            </div>

                                            <h6 class="fw-bold">
                                                No suppliers found
                                            </h6>

                                            <p class="text-muted mb-3">
                                                No supplier records match your search.
                                            </p>

                                            <a
                                                href="add.php"
                                                class="btn btn-primary btn-sm"
                                            >
                                                <i class="bi bi-plus-circle me-1"></i>
                                                Add Supplier
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php

if ($stmt instanceof mysqli_stmt) {
    $stmt->close();
}

$conn->close();

?>

<?php include "../includes/footer.php"; ?>