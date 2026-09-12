<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Customers";

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

if (strlen($search) > 150) {
    $search = substr($search, 0, 150);
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
| Build Customer Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        customer_code,
        name,
        phone,
        email,
        address,
        status,
        created_at
    FROM customers
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
            customer_code LIKE ?
            OR name LIKE ?
            OR phone LIKE ?
            OR email LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
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
    die("Unable to load customers.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    $stmt->close();
    die("Unable to load customers.");
}

$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Success / Error Messages
|--------------------------------------------------------------------------
*/

$success = trim($_GET["success"] ?? "");
$error = trim($_GET["error"] ?? "");

/*
|--------------------------------------------------------------------------
| Limit URL Messages
|--------------------------------------------------------------------------
*/

if (strlen($success) > 300) {
    $success = substr($success, 0, 300);
}

if (strlen($error) > 300) {
    $error = substr($error, 0, 300);
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statsSql = "
    SELECT
        COUNT(*) AS total_customers,
        SUM(status = 'active') AS active_customers,
        SUM(status = 'inactive') AS inactive_customers
    FROM customers
";

$statsResult = $conn->query($statsSql);

if (!$statsResult) {
    $stats = [
        "total_customers" => 0,
        "active_customers" => 0,
        "inactive_customers" => 0
    ];
} else {
    $stats = $statsResult->fetch_assoc();
}

$totalCustomers = (int) ($stats["total_customers"] ?? 0);
$activeCustomers = (int) ($stats["active_customers"] ?? 0);
$inactiveCustomers = (int) ($stats["inactive_customers"] ?? 0);

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">
                            <i class="bi bi-people"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            Customers
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Manage customer information and customer records.
                    </p>

                </div>

                <div>

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-person-plus me-1"></i>
                        Add Customer
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


            <!-- Statistics -->

            <div class="row g-4 mb-4">

                <div class="col-12 col-md-4">

                    <div class="stat-card stat-card-blue h-100">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="stat-label">
                                        Total Customers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($totalCustomers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-blue mt-2">
                                        <i class="bi bi-people"></i>
                                        All customer records
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-blue">
                                    <i class="bi bi-people"></i>
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
                                        Active Customers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($activeCustomers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-green mt-2">
                                        <i class="bi bi-check-circle"></i>
                                        Available for sales
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-green">
                                    <i class="bi bi-person-check"></i>
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
                                        Inactive Customers
                                    </div>

                                    <div class="stat-value fw-bold mt-1">
                                        <?= number_format($inactiveCustomers) ?>
                                    </div>

                                    <div class="stat-footer stat-footer-red mt-2">
                                        <i class="bi bi-person-x"></i>
                                        Currently inactive
                                    </div>

                                </div>

                                <div class="stat-icon stat-icon-red">
                                    <i class="bi bi-person-x"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Customer List -->

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="dashboard-section-header">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Customer List
                            </h5>

                            <small class="text-muted">
                                View and manage registered customers.
                            </small>

                        </div>

                    </div>

                </div>


                <!-- Filters -->

                <div class="card-body border-top">

                    <form
                        method="GET"
                        action=""
                    >

                        <div class="row g-3 align-items-end">

                            <div class="col-12 col-lg-6">

                                <label class="form-label fw-semibold">
                                    Search Customer
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>

                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="Name, code, phone or email"
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

                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if ($result->num_rows > 0): ?>

                                <?php while ($customer = $result->fetch_assoc()): ?>

                                    <tr>

                                        <td>

                                            <div class="d-flex align-items-center gap-3">

                                                <div class="stock-product-icon bg-primary-subtle text-primary">
                                                    <i class="bi bi-person"></i>
                                                </div>

                                                <div>

                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars(
                                                            $customer["name"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>
                                                    </div>

                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(
                                                            $customer["customer_code"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>
                                                    </small>

                                                </div>

                                            </div>

                                        </td>


                                        <td>

                                            <?php if (!empty($customer["phone"])): ?>

                                                <div>

                                                    <i class="bi bi-telephone me-1 text-muted"></i>

                                                    <?= htmlspecialchars(
                                                        $customer["phone"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </div>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if (!empty($customer["email"])): ?>

                                                <?= htmlspecialchars(
                                                    $customer["email"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if (!empty($customer["address"])): ?>

                                                <span
                                                    title="<?= htmlspecialchars(
                                                        $customer["address"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        strlen($customer["address"]) > 35
                                                            ? substr($customer["address"], 0, 35) . "..."
                                                            : $customer["address"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if ($customer["status"] === "active"): ?>

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

                                                <?= date(
                                                    "d M Y",
                                                    strtotime($customer["created_at"])
                                                ) ?>

                                            </span>

                                        </td>


                                        <td class="text-end">

                                            <div class="btn-group">

                                                <a
                                                    href="edit.php?id=<?= (int) $customer["id"] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit Customer"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>


                                                <?php
                                                /*
                                                 * Only Admin and Manager
                                                 * can delete customers.
                                                 */
                                                ?>

                                                <?php if (
                                                    isset($_SESSION["user_role"]) &&
                                                    in_array(
                                                        $_SESSION["user_role"],
                                                        ["admin", "manager"],
                                                        true
                                                    )
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="delete.php"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this customer?');"
                                                    >

                                                        <?= csrfField() ?>

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $customer["id"] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete Customer"
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
                                        colspan="7"
                                        class="text-center"
                                    >

                                        <div class="empty-state py-5">

                                            <div class="empty-icon mb-3">
                                                <i class="bi bi-people"></i>
                                            </div>

                                            <h6 class="fw-bold">
                                                No customers found
                                            </h6>

                                            <p class="text-muted mb-3">
                                                No customer records match your search.
                                            </p>

                                            <a
                                                href="add.php"
                                                class="btn btn-primary btn-sm"
                                            >
                                                <i class="bi bi-person-plus me-1"></i>
                                                Add Customer
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

$stmt->close();
$conn->close();

?>

<?php include "../includes/footer.php"; ?>