<?php

/*
|--------------------------------------------------------------------------
| SmartPOS - Products
|--------------------------------------------------------------------------
| Product listing, searching and filtering.
|
| Permissions:
| - Admin
| - Manager
|
| Security:
| - Authentication
| - Role-based access
| - CSRF protection for destructive actions
| - Prepared statements
| - Input validation
| - Output escaping
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";

requireRole([
    'admin',
    'manager'
]);


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle = "Products";


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";


$categoryId = isset($_GET["category_id"])
    ? filter_var(
        $_GET["category_id"],
        FILTER_VALIDATE_INT
    )
    : 0;


$status = isset($_GET["status"])
    ? trim($_GET["status"])
    : "";


/*
|--------------------------------------------------------------------------
| VALIDATE CATEGORY ID
|--------------------------------------------------------------------------
*/

if (
    $categoryId === false ||
    $categoryId < 0
) {

    $categoryId = 0;
}


/*
|--------------------------------------------------------------------------
| LIMIT SEARCH LENGTH
|--------------------------------------------------------------------------
*/

if (strlen($search) > 150) {

    $search = substr(
        $search,
        0,
        150
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "active",
    "inactive"
];


if (
    $status !== "" &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $status = "";
}


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
|
| Load categories for the category filter.
|
|--------------------------------------------------------------------------
*/

$categories = [];


$categoryStmt = $conn->prepare("
    SELECT
        id,
        name
    FROM categories
    ORDER BY name ASC
");


if ($categoryStmt) {

    if ($categoryStmt->execute()) {

        $categoryResult =
            $categoryStmt->get_result();


        while (
            $row =
            $categoryResult->fetch_assoc()
        ) {

            $categories[] = $row;
        }

    } else {

        error_log(
            "SmartPOS products category query failed: " .
            $categoryStmt->error
        );
    }


    $categoryStmt->close();

} else {

    error_log(
        "SmartPOS products category prepare failed: " .
        $conn->error
    );
}


/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.description,
        p.category_id,
        p.purchase_price,
        p.selling_price,
        p.stock_quantity,
        p.reorder_level,
        p.unit,
        p.image,
        p.status,
        p.created_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.id
    WHERE 1 = 1
";


$params = [];

$types = "";


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
|
| Search by:
| - Product name
| - SKU
| - Barcode
|
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            p.name LIKE ?
            OR p.sku LIKE ?
            OR p.barcode LIKE ?
        )
    ";


    $searchValue =
        "%" . $search . "%";


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;


    $types .= "sss";
}


/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

if ($categoryId > 0) {

    $sql .= "
        AND p.category_id = ?
    ";


    $params[] =
        $categoryId;


    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== "") {

    $sql .= "
        AND p.status = ?
    ";


    $params[] =
        $status;


    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY p.created_at DESC
";


/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    error_log(
        "SmartPOS products query prepare failed: " .
        $conn->error
    );

    $conn->close();

    die("Unable to load products.");
}


/*
|--------------------------------------------------------------------------
| BIND DYNAMIC PARAMETERS
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


/*
|--------------------------------------------------------------------------
| EXECUTE PRODUCTS QUERY
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    error_log(
        "SmartPOS products query failed: " .
        $stmt->error
    );

    $stmt->close();
    $conn->close();

    die("Unable to load products.");
}


$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

while (
    $row =
    $result->fetch_assoc()
) {

    $products[] = $row;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| CLOSE DATABASE
|--------------------------------------------------------------------------
|
| The database connection is no longer needed before rendering.
|
|--------------------------------------------------------------------------
*/

$conn->close();

?>


<?php include "../includes/header.php"; ?>

<?php include "../includes/navbar.php"; ?>


<div class="main-wrapper">


    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">


        <div class="container-fluid py-4">


            <!-- =========================================================
                 PAGE HEADER
                 ========================================================= -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >


                <div>


                    <div
                        class="d-flex align-items-center gap-2 mb-1"
                    >


                        <div
                            class="dashboard-title-icon"
                        >

                            <i
                                class="bi bi-box-seam"
                            ></i>

                        </div>


                        <h2 class="mb-0 fw-bold">

                            Products

                        </h2>


                    </div>


                    <p class="text-muted mb-0">

                        Manage products, pricing and stock information.

                    </p>


                </div>


                <div>


                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >

                        <i
                            class="bi bi-plus-lg me-1"
                        ></i>

                        Add Product

                    </a>


                </div>


            </div>


            <!-- =========================================================
                 SUCCESS MESSAGE
                 ========================================================= -->

            <?php if (isset($_GET["success"])): ?>


                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >


                    <i
                        class="bi bi-check-circle me-2"
                    ></i>


                    <?= htmlspecialchars(
                        $_GET["success"],
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


            <!-- =========================================================
                 ERROR MESSAGE
                 ========================================================= -->

            <?php if (isset($_GET["error"])): ?>


                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >


                    <i
                        class="bi bi-exclamation-triangle me-2"
                    ></i>


                    <?= htmlspecialchars(
                        $_GET["error"],
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


            <!-- =========================================================
                 FILTERS
                 ========================================================= -->

            <div
                class="card dashboard-card mb-4"
            >


                <div class="card-body">


                    <form
                        method="GET"
                        action="index.php"
                    >


                        <div
                            class="row g-3 align-items-end"
                        >


                            <!-- SEARCH -->

                            <div
                                class="col-12 col-md-5"
                            >


                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >

                                    Search

                                </label>


                                <div
                                    class="input-group"
                                >


                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="bi bi-search"
                                        ></i>

                                    </span>


                                    <input
                                        type="text"
                                        class="form-control"
                                        id="search"
                                        name="search"
                                        value="<?= htmlspecialchars(
                                            $search,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        placeholder="Search name, SKU or barcode"
                                        maxlength="150"
                                    >


                                </div>


                            </div>


                            <!-- CATEGORY -->

                            <div
                                class="col-12 col-md-3"
                            >


                                <label
                                    for="category_id"
                                    class="form-label fw-semibold"
                                >

                                    Category

                                </label>


                                <select
                                    class="form-select"
                                    id="category_id"
                                    name="category_id"
                                >


                                    <option value="0">

                                        All Categories

                                    </option>


                                    <?php foreach (
                                        $categories
                                        as $category
                                    ): ?>


                                        <option
                                            value="<?= (int) $category["id"] ?>"
                                            <?= $categoryId === (int) $category["id"]
                                                ? "selected"
                                                : "" ?>
                                        >


                                            <?= htmlspecialchars(
                                                $category["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>


                                        </option>


                                    <?php endforeach; ?>


                                </select>


                            </div>


                            <!-- STATUS -->

                            <div
                                class="col-12 col-md-2"
                            >


                                <label
                                    for="status"
                                    class="form-label fw-semibold"
                                >

                                    Status

                                </label>


                                <select
                                    class="form-select"
                                    id="status"
                                    name="status"
                                >


                                    <option value="">

                                        All Status

                                    </option>


                                    <option
                                        value="active"
                                        <?= $status === "active"
                                            ? "selected"
                                            : "" ?>
                                    >

                                        Active

                                    </option>


                                    <option
                                        value="inactive"
                                        <?= $status === "inactive"
                                            ? "selected"
                                            : "" ?>
                                    >

                                        Inactive

                                    </option>


                                </select>


                            </div>


                            <!-- FILTER BUTTONS -->

                            <div
                                class="col-12 col-md-2 d-flex gap-2"
                            >


                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >

                                    <i
                                        class="bi bi-search me-1"
                                    ></i>

                                    Filter

                                </button>


                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                    title="Clear filters"
                                >

                                    <i
                                        class="bi bi-x-lg"
                                    ></i>

                                </a>


                            </div>


                        </div>


                    </form>


                </div>


            </div>


            <!-- =========================================================
                 PRODUCT TABLE
                 ========================================================= -->

            <div
                class="card dashboard-card"
            >


                <!-- SECTION HEADER -->

                <div
                    class="dashboard-section-header"
                >


                    <div
                        class="d-flex align-items-center gap-3"
                    >


                        <div
                            class="section-icon section-icon-blue"
                        >

                            <i
                                class="bi bi-box-seam"
                            ></i>

                        </div>


                        <div>


                            <h5
                                class="mb-1 fw-bold"
                            >

                                Product List

                            </h5>


                            <small class="text-muted">

                                <?= count($products) ?>

                                product(s) found

                            </small>


                        </div>


                    </div>


                </div>


                <!-- EMPTY STATE -->

                <?php if (empty($products)): ?>


                    <div class="card-body">


                        <div
                            class="empty-state text-center py-5"
                        >


                            <div
                                class="empty-icon mb-3"
                            >

                                <i
                                    class="bi bi-box-seam"
                                ></i>

                            </div>


                            <h5 class="fw-bold">

                                No products found

                            </h5>


                            <p
                                class="text-muted mb-3"
                            >

                                There are no products matching your search.

                            </p>


                            <a
                                href="add.php"
                                class="btn btn-primary"
                            >

                                <i
                                    class="bi bi-plus-lg me-1"
                                ></i>

                                Add Product

                            </a>


                        </div>


                    </div>


                <?php else: ?>


                    <!-- RESPONSIVE TABLE -->

                    <div
                        class="table-responsive"
                    >


                        <table
                            class="table align-middle dashboard-table"
                        >


                            <thead>


                                <tr>


                                    <th>
                                        Product
                                    </th>


                                    <th>
                                        SKU
                                    </th>


                                    <th>
                                        Category
                                    </th>


                                    <th>
                                        Purchase
                                    </th>


                                    <th>
                                        Selling
                                    </th>


                                    <th>
                                        Stock
                                    </th>


                                    <th>
                                        Status
                                    </th>


                                    <th
                                        class="text-end"
                                    >

                                        Actions

                                    </th>


                                </tr>


                            </thead>


                            <tbody>


                                <?php foreach (
                                    $products
                                    as $product
                                ): ?>


                                    <?php

                                    $stock =
                                        (float)
                                        $product["stock_quantity"];


                                    $reorderLevel =
                                        (float)
                                        $product["reorder_level"];


                                    $isLowStock =
                                        $stock <= $reorderLevel;

                                    ?>


                                    <tr>


                                        <!-- PRODUCT -->

                                        <td>


                                            <div
                                                class="d-flex align-items-center gap-2"
                                            >


                                                <div
                                                    class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:42px;height:42px;"
                                                >


                                                    <i
                                                        class="bi bi-box text-primary"
                                                    ></i>


                                                </div>


                                                <div>


                                                    <div
                                                        class="fw-semibold"
                                                    >

                                                        <?= htmlspecialchars(
                                                            $product["name"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>


                                                    </div>


                                                    <?php if (
                                                        !empty(
                                                            $product["barcode"]
                                                        )
                                                    ): ?>


                                                        <small
                                                            class="text-muted"
                                                        >

                                                            Barcode:

                                                            <?= htmlspecialchars(
                                                                $product["barcode"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>


                                                        </small>


                                                    <?php endif; ?>


                                                </div>


                                            </div>


                                        </td>


                                        <!-- SKU -->

                                        <td>


                                            <span
                                                class="badge bg-light text-dark border"
                                            >

                                                <?= htmlspecialchars(
                                                    $product["sku"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>


                                            </span>


                                        </td>


                                        <!-- CATEGORY -->

                                        <td>


                                            <?php if (
                                                !empty(
                                                    $product["category_name"]
                                                )
                                            ): ?>


                                                <?= htmlspecialchars(
                                                    $product["category_name"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>


                                            <?php else: ?>


                                                <span
                                                    class="text-muted"
                                                >

                                                    Uncategorised

                                                </span>


                                            <?php endif; ?>


                                        </td>


                                        <!-- PURCHASE PRICE -->

                                        <td>


                                            LKR

                                            <?= number_format(
                                                (float)
                                                $product["purchase_price"],
                                                2
                                            ) ?>


                                        </td>


                                        <!-- SELLING PRICE -->

                                        <td>


                                            <span
                                                class="fw-semibold text-success"
                                            >

                                                LKR

                                                <?= number_format(
                                                    (float)
                                                    $product["selling_price"],
                                                    2
                                                ) ?>


                                            </span>


                                        </td>


                                        <!-- STOCK -->

                                        <td>


                                            <?php if (
                                                $isLowStock
                                            ): ?>


                                                <span
                                                    class="text-danger fw-bold"
                                                >

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>


                                                    <?= htmlspecialchars(
                                                        $product["unit"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>


                                                </span>


                                                <div>


                                                    <small
                                                        class="text-danger"
                                                    >

                                                        <i
                                                            class="bi bi-exclamation-triangle"
                                                        ></i>

                                                        Low stock

                                                    </small>


                                                </div>


                                            <?php else: ?>


                                                <span
                                                    class="fw-semibold"
                                                >

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>


                                                    <?= htmlspecialchars(
                                                        $product["unit"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>


                                                </span>


                                            <?php endif; ?>


                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <?php if (
                                                $product["status"] === "active"
                                            ): ?>


                                                <span
                                                    class="badge text-bg-success"
                                                >

                                                    Active

                                                </span>


                                            <?php else: ?>


                                                <span
                                                    class="badge text-bg-secondary"
                                                >

                                                    Inactive

                                                </span>


                                            <?php endif; ?>


                                        </td>


                                        <!-- ACTIONS -->

                                        <td
                                            class="text-end"
                                        >


                                            <div
                                                class="btn-group"
                                            >


                                                <!-- EDIT -->

                                                <a
                                                    href="edit.php?id=<?= (int) $product["id"] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit"
                                                >

                                                    <i
                                                        class="bi bi-pencil"
                                                    ></i>

                                                </a>


                                                <!-- =================================================
                                                     DELETE
                                                     ================================================= -->

                                                <form
                                                    method="POST"
                                                    action="delete.php"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');"
                                                >


                                                    <!-- CSRF TOKEN -->

                                                    <?= csrfField() ?>


                                                    <!-- PRODUCT ID -->

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $product["id"] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete"
                                                    >

                                                        <i
                                                            class="bi bi-trash"
                                                        ></i>

                                                    </button>


                                                </form>


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


    </main>


</div>


<?php include "../includes/footer.php"; ?>