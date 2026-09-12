<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Stock Movement History";

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$movementType = isset($_GET["movement_type"])
    ? trim($_GET["movement_type"])
    : "";

$productId = isset($_GET["product_id"])
    ? (int) $_GET["product_id"]
    : 0;


/*
|--------------------------------------------------------------------------
| Allowed Movement Types
|--------------------------------------------------------------------------
*/

$allowedMovementTypes = [
    "purchase",
    "sale",
    "return",
    "adjustment",
    "damage"
];

if (!in_array($movementType, $allowedMovementTypes, true)) {
    $movementType = "";
}


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

$products = [];

$productStmt = $conn->prepare("
    SELECT
        id,
        name,
        sku
    FROM products
    ORDER BY name ASC
");

if ($productStmt) {

    $productStmt->execute();

    $productResult = $productStmt->get_result();

    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }

    $productStmt->close();
}


/*
|--------------------------------------------------------------------------
| Stock Movements
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        sm.id,
        sm.product_id,
        sm.user_id,
        sm.movement_type,
        sm.quantity,
        sm.reference_type,
        sm.reference_id,
        sm.notes,
        sm.created_at,

        p.name AS product_name,
        p.sku,
        p.unit,

        u.full_name AS user_name

    FROM stock_movements sm

    INNER JOIN products p
        ON sm.product_id = p.id

    LEFT JOIN users u
        ON sm.user_id = u.id

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
            p.name LIKE ?
            OR p.sku LIKE ?
            OR sm.notes LIKE ?
            OR sm.reference_type LIKE ?
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
| Movement Type Filter
|--------------------------------------------------------------------------
*/

if ($movementType !== "") {

    $sql .= "
        AND sm.movement_type = ?
    ";

    $params[] = $movementType;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Product Filter
|--------------------------------------------------------------------------
*/

if ($productId > 0) {

    $sql .= "
        AND sm.product_id = ?
    ";

    $params[] = $productId;

    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY sm.created_at DESC, sm.id DESC
";


$stmt = $conn->prepare($sql);

$movements = [];

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );

    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $movements[] = $row;

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

            <!-- PAGE HEADER -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="dashboard-title-icon">

                            <i class="bi bi-clock-history"></i>

                        </div>

                        <h2 class="mb-0 fw-bold">
                            Stock Movement History
                        </h2>

                    </div>

                    <p class="text-muted mb-0">
                        View all inventory stock movements and adjustments.
                    </p>

                </div>

                <div>

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back to Inventory

                    </a>

                </div>

            </div>


            <!-- SUCCESS MESSAGE -->

            <?php if (isset($_GET["success"])): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($_GET["success"]) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- FILTERS -->

            <div class="card dashboard-card mb-4">

                <div class="card-body">

                    <form
                        method="GET"
                        action="movements.php"
                    >

                        <div class="row g-3 align-items-end">

                            <!-- SEARCH -->

                            <div class="col-12 col-lg-5">

                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >
                                    Search
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="bi bi-search"></i>

                                    </span>

                                    <input
                                        type="text"
                                        id="search"
                                        name="search"
                                        class="form-control"
                                        value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Product, SKU, notes or reference"
                                    >

                                </div>

                            </div>


                            <!-- MOVEMENT TYPE -->

                            <div class="col-12 col-md-5 col-lg-3">

                                <label
                                    for="movement_type"
                                    class="form-label fw-semibold"
                                >
                                    Movement Type
                                </label>

                                <select
                                    id="movement_type"
                                    name="movement_type"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Movements
                                    </option>

                                    <option
                                        value="purchase"
                                        <?= $movementType === "purchase"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Purchase
                                    </option>

                                    <option
                                        value="sale"
                                        <?= $movementType === "sale"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Sale
                                    </option>

                                    <option
                                        value="return"
                                        <?= $movementType === "return"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Return
                                    </option>

                                    <option
                                        value="adjustment"
                                        <?= $movementType === "adjustment"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Adjustment
                                    </option>

                                    <option
                                        value="damage"
                                        <?= $movementType === "damage"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Damage
                                    </option>

                                </select>

                            </div>


                            <!-- PRODUCT -->

                            <div class="col-12 col-md-5 col-lg-2">

                                <label
                                    for="product_id"
                                    class="form-label fw-semibold"
                                >
                                    Product
                                </label>

                                <select
                                    id="product_id"
                                    name="product_id"
                                    class="form-select"
                                >

                                    <option value="0">
                                        All Products
                                    </option>

                                    <?php foreach ($products as $item): ?>

                                        <option
                                            value="<?= (int) $item["id"] ?>"
                                            <?= $productId === (int) $item["id"]
                                                ? "selected"
                                                : "" ?>
                                        >

                                            <?= htmlspecialchars(
                                                $item["name"]
                                            ) ?>

                                            -
                                            <?= htmlspecialchars(
                                                $item["sku"]
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- BUTTONS -->

                            <div class="col-12 col-md-7 col-lg-2 d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >

                                    <i class="bi bi-search me-1"></i>

                                    Filter

                                </button>

                                <a
                                    href="movements.php"
                                    class="btn btn-outline-secondary"
                                    title="Clear filters"
                                >

                                    <i class="bi bi-x-lg"></i>

                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- MOVEMENT TABLE -->

            <div class="card dashboard-card">

                <div class="dashboard-section-header">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">

                            <i class="bi bi-clock-history"></i>

                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Movement History
                            </h5>

                            <small class="text-muted">

                                <?= count($movements) ?>
                                movement(s) found

                            </small>

                        </div>

                    </div>

                </div>


                <?php if (empty($movements)): ?>

                    <div class="card-body">

                        <div class="empty-state text-center py-5">

                            <div class="empty-icon mb-3">

                                <i class="bi bi-clock-history"></i>

                            </div>

                            <h5 class="fw-bold">
                                No stock movements found
                            </h5>

                            <p class="text-muted mb-3">
                                There are no stock movements matching your filters.
                            </p>

                            <a
                                href="adjust.php"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-sliders me-1"></i>

                                Adjust Stock

                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table align-middle dashboard-table">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Movement
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        User
                                    </th>

                                    <th>
                                        Notes
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($movements as $movement): ?>

                                    <?php

                                    $movementQuantity =
                                        (float) $movement["quantity"];

                                    $movementTypeValue =
                                        $movement["movement_type"];

                                    ?>


                                    <tr>

                                        <!-- DATE -->

                                        <td>

                                            <div class="fw-semibold">

                                                <?= htmlspecialchars(
                                                    date(
                                                        "d M Y",
                                                        strtotime(
                                                            $movement["created_at"]
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    date(
                                                        "h:i A",
                                                        strtotime(
                                                            $movement["created_at"]
                                                        )
                                                    )
                                                ) ?>

                                            </small>

                                        </td>


                                        <!-- PRODUCT -->

                                        <td>

                                            <div class="d-flex align-items-center gap-2">

                                                <div
                                                    class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:42px;height:42px;"
                                                >

                                                    <i
                                                        class="bi bi-box-seam text-primary"
                                                    ></i>

                                                </div>

                                                <div>

                                                    <div class="fw-semibold">

                                                        <?= htmlspecialchars(
                                                            $movement["product_name"]
                                                        ) ?>

                                                    </div>

                                                    <small class="text-muted">

                                                        SKU:
                                                        <?= htmlspecialchars(
                                                            $movement["sku"]
                                                        ) ?>

                                                    </small>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- MOVEMENT -->

                                        <td>

                                            <?php if (
                                                $movementTypeValue === "purchase"
                                            ): ?>

                                                <span class="badge text-bg-success">

                                                    <i
                                                        class="bi bi-bag-plus me-1"
                                                    ></i>

                                                    Purchase

                                                </span>

                                            <?php elseif (
                                                $movementTypeValue === "sale"
                                            ): ?>

                                                <span class="badge text-bg-primary">

                                                    <i
                                                        class="bi bi-cart3 me-1"
                                                    ></i>

                                                    Sale

                                                </span>

                                            <?php elseif (
                                                $movementTypeValue === "return"
                                            ): ?>

                                                <span class="badge text-bg-info">

                                                    <i
                                                        class="bi bi-arrow-return-left me-1"
                                                    ></i>

                                                    Return

                                                </span>

                                            <?php elseif (
                                                $movementTypeValue === "damage"
                                            ): ?>

                                                <span class="badge text-bg-danger">

                                                    <i
                                                        class="bi bi-exclamation-triangle me-1"
                                                    ></i>

                                                    Damage

                                                </span>

                                            <?php else: ?>

                                                <span class="badge text-bg-warning">

                                                    <i
                                                        class="bi bi-sliders me-1"
                                                    ></i>

                                                    Adjustment

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- QUANTITY -->

                                        <td>

                                            <?php if ($movementQuantity > 0): ?>

                                                <span
                                                    class="text-success fw-bold"
                                                >

                                                    <i class="bi bi-plus-lg"></i>

                                                    <?= number_format(
                                                        $movementQuantity,
                                                        2
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $movement["unit"]
                                                    ) ?>

                                                </span>

                                            <?php elseif ($movementQuantity < 0): ?>

                                                <span
                                                    class="text-danger fw-bold"
                                                >

                                                    <i class="bi bi-dash-lg"></i>

                                                    <?= number_format(
                                                        abs($movementQuantity),
                                                        2
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $movement["unit"]
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted fw-semibold">

                                                    0.00
                                                    <?= htmlspecialchars(
                                                        $movement["unit"]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- REFERENCE -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $movement["reference_type"]
                                                )
                                            ): ?>

                                                <span
                                                    class="badge bg-light text-dark border"
                                                >

                                                    <?= htmlspecialchars(
                                                        $movement["reference_type"]
                                                    ) ?>

                                                </span>

                                                <?php if (
                                                    $movement["reference_id"]
                                                    !== null
                                                ): ?>

                                                    <div>

                                                        <small class="text-muted">

                                                            #
                                                            <?= (int) $movement[
                                                                "reference_id"
                                                            ] ?>

                                                        </small>

                                                    </div>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- USER -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $movement["user_name"]
                                                )
                                            ): ?>

                                                <div
                                                    class="d-flex align-items-center gap-2"
                                                >

                                                    <div
                                                        class="cashier-avatar"
                                                    >

                                                        <i
                                                            class="bi bi-person"
                                                        ></i>

                                                    </div>

                                                    <span>

                                                        <?= htmlspecialchars(
                                                            $movement["user_name"]
                                                        ) ?>

                                                    </span>

                                                </div>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    System
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- NOTES -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $movement["notes"]
                                                )
                                            ): ?>

                                                <span
                                                    class="d-inline-block"
                                                    style="max-width:220px;"
                                                    title="<?= htmlspecialchars(
                                                        $movement["notes"]
                                                    ) ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        $movement["notes"]
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php

$conn->close();

include "../includes/footer.php";

?>