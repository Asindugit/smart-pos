<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Edit Product";

$productId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($productId <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid product ID.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Allowed Units
|--------------------------------------------------------------------------
*/

$units = [
    "pcs",
    "kg",
    "g",
    "l",
    "ml",
    "box",
    "pack",
    "dozen"
];


/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "active",
    "inactive"
];


/*
|--------------------------------------------------------------------------
| Load Product
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        sku,
        barcode,
        name,
        description,
        category_id,
        purchase_price,
        selling_price,
        stock_quantity,
        reorder_level,
        unit,
        status
    FROM products
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to load product.")
    );

    exit;
}

$stmt->bind_param(
    "i",
    $productId
);

if (!$stmt->execute()) {

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to load product.")
    );

    exit;
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode("Product not found.")
    );

    exit;
}

$product = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$sku = $product["sku"] ?? "";
$barcode = $product["barcode"] ?? "";
$name = $product["name"] ?? "";
$description = $product["description"] ?? "";

$categoryId = !empty($product["category_id"])
    ? (int) $product["category_id"]
    : 0;

$purchasePrice = $product["purchase_price"] ?? "";
$sellingPrice = $product["selling_price"] ?? "";
$stockQuantity = $product["stock_quantity"] ?? "";
$reorderLevel = $product["reorder_level"] ?? "5";
$unit = $product["unit"] ?? "pcs";
$status = $product["status"] ?? "active";

$errors = [];


/*
|--------------------------------------------------------------------------
| Load Categories
|--------------------------------------------------------------------------
*/

$categories = [];

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM categories
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($stmt) {

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $categories[] = $row;
        }
    }

    $stmt->close();

} else {

    $errors[] =
        "Unable to load categories.";
}


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */

    requireCsrfToken();


    /*
    |--------------------------------------------------------------------------
    | Get Submitted Values
    |--------------------------------------------------------------------------
    */

    $sku = trim(
        $_POST["sku"] ?? ""
    );

    $barcode = trim(
        $_POST["barcode"] ?? ""
    );

    $name = trim(
        $_POST["name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $categoryId = isset($_POST["category_id"])
        ? (int) $_POST["category_id"]
        : 0;

    $purchasePrice = trim(
        $_POST["purchase_price"] ?? ""
    );

    $sellingPrice = trim(
        $_POST["selling_price"] ?? ""
    );

    $stockQuantity = trim(
        $_POST["stock_quantity"] ?? ""
    );

    $reorderLevel = trim(
        $_POST["reorder_level"] ?? ""
    );

    $unit = trim(
        $_POST["unit"] ?? ""
    );

    $status = trim(
        $_POST["status"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | Validate SKU
    |--------------------------------------------------------------------------
    */

    if ($sku === "") {

        $errors[] =
            "SKU is required.";

    } elseif (strlen($sku) > 100) {

        $errors[] =
            "SKU must not exceed 100 characters.";

    } elseif (!preg_match(
        '/^[A-Za-z0-9._\-\/]+$/',
        $sku
    )) {

        $errors[] =
            "SKU contains invalid characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Product Name
    |--------------------------------------------------------------------------
    */

    if ($name === "") {

        $errors[] =
            "Product name is required.";

    } elseif (strlen($name) < 2) {

        $errors[] =
            "Product name must contain at least 2 characters.";

    } elseif (strlen($name) > 150) {

        $errors[] =
            "Product name must not exceed 150 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Barcode
    |--------------------------------------------------------------------------
    */

    if ($barcode !== "") {

        if (strlen($barcode) > 100) {

            $errors[] =
                "Barcode must not exceed 100 characters.";

        } elseif (!preg_match(
            '/^[A-Za-z0-9\-]+$/',
            $barcode
        )) {

            $errors[] =
                "Barcode contains invalid characters.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Description
    |--------------------------------------------------------------------------
    */

    if (strlen($description) > 1000) {

        $errors[] =
            "Description must not exceed 1000 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Category
    |--------------------------------------------------------------------------
    */

    if ($categoryId <= 0) {

        $errors[] =
            "Please select a category.";

    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            AND status = 'active'
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $categoryId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result->num_rows === 0) {

                    $errors[] =
                        "Selected category is invalid.";
                }

            } else {

                $errors[] =
                    "Unable to validate category.";
            }

            $stmt->close();

        } else {

            $errors[] =
                "Unable to validate category.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Purchase Price
    |--------------------------------------------------------------------------
    */

    if (
        $purchasePrice === "" ||
        !is_numeric($purchasePrice)
    ) {

        $errors[] =
            "Purchase price must be a valid number.";

    } elseif (
        (float) $purchasePrice < 0
    ) {

        $errors[] =
            "Purchase price cannot be negative.";

    } elseif (
        (float) $purchasePrice > 9999999999.99
    ) {

        $errors[] =
            "Purchase price is too large.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Selling Price
    |--------------------------------------------------------------------------
    */

    if (
        $sellingPrice === "" ||
        !is_numeric($sellingPrice)
    ) {

        $errors[] =
            "Selling price must be a valid number.";

    } elseif (
        (float) $sellingPrice < 0
    ) {

        $errors[] =
            "Selling price cannot be negative.";

    } elseif (
        (float) $sellingPrice > 9999999999.99
    ) {

        $errors[] =
            "Selling price is too large.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Stock Quantity
    |--------------------------------------------------------------------------
    */

    if (
        $stockQuantity === "" ||
        !is_numeric($stockQuantity)
    ) {

        $errors[] =
            "Stock quantity must be a valid number.";

    } elseif (
        (float) $stockQuantity < 0
    ) {

        $errors[] =
            "Stock quantity cannot be negative.";

    } elseif (
        (float) $stockQuantity > 9999999999.99
    ) {

        $errors[] =
            "Stock quantity is too large.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Reorder Level
    |--------------------------------------------------------------------------
    */

    if (
        $reorderLevel === "" ||
        !is_numeric($reorderLevel)
    ) {

        $errors[] =
            "Reorder level must be a valid number.";

    } elseif (
        (float) $reorderLevel < 0
    ) {

        $errors[] =
            "Reorder level cannot be negative.";

    } elseif (
        (float) $reorderLevel > 9999999999.99
    ) {

        $errors[] =
            "Reorder level is too large.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Unit
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $unit,
        $units,
        true
    )) {

        $errors[] =
            "Invalid unit selected.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {

        $errors[] =
            "Invalid product status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate SKU
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM products
            WHERE sku = ?
            AND id != ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $sku,
                $productId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result->num_rows > 0) {

                    $errors[] =
                        "SKU already exists.";
                }

            } else {

                $errors[] =
                    "Unable to validate SKU.";
            }

            $stmt->close();

        } else {

            $errors[] =
                "Unable to validate SKU.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Barcode
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $barcode !== ""
    ) {

        $stmt = $conn->prepare("
            SELECT id
            FROM products
            WHERE barcode = ?
            AND id != ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $barcode,
                $productId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result->num_rows > 0) {

                    $errors[] =
                        "Barcode already exists.";
                }

            } else {

                $errors[] =
                    "Unable to validate barcode.";
            }

            $stmt->close();

        } else {

            $errors[] =
                "Unable to validate barcode.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Product
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $purchasePriceValue =
            (float) $purchasePrice;

        $sellingPriceValue =
            (float) $sellingPrice;

        $stockQuantityValue =
            (float) $stockQuantity;

        $reorderLevelValue =
            (float) $reorderLevel;


        $stmt = $conn->prepare("
            UPDATE products
            SET
                category_id = ?,
                sku = ?,
                barcode = NULLIF(?, ''),
                name = ?,
                description = ?,
                purchase_price = ?,
                selling_price = ?,
                stock_quantity = ?,
                reorder_level = ?,
                unit = ?,
                status = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "issssddddssi",
                $categoryId,
                $sku,
                $barcode,
                $name,
                $description,
                $purchasePriceValue,
                $sellingPriceValue,
                $stockQuantityValue,
                $reorderLevelValue,
                $unit,
                $status,
                $productId
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: index.php?success=" .
                    urlencode(
                        "Product updated successfully."
                    )
                );

                exit;

            } else {

                if ($conn->errno === 1062) {

                    $errors[] =
                        "SKU or barcode already exists.";

                } else {

                    $errors[] =
                        "Failed to update product. Please try again.";
                }

                $stmt->close();
            }

        } else {

            $errors[] =
                "Unable to update product.";
        }
    }
}

?>

<?php include "../includes/header.php"; ?>

<?php include "../includes/navbar.php"; ?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- Header -->

            <div class="mb-4">

                <div class="d-flex align-items-center gap-2 mb-1">

                    <div class="dashboard-title-icon">

                        <i class="bi bi-pencil-square"></i>

                    </div>

                    <h2 class="mb-0 fw-bold">
                        Edit Product
                    </h2>

                </div>

                <p class="text-muted mb-0">
                    Update product information, pricing and stock.
                </p>

            </div>


            <!-- Errors -->

            <?php if (!empty($errors)): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >

                    <div class="fw-semibold mb-2">

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        Please fix the following:

                    </div>

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <!-- Form -->

            <form
                method="POST"
                action="edit.php?id=<?= (int) $productId ?>"
                autocomplete="off"
            >

                <?= csrfField() ?>

                <div class="row g-4">

                    <!-- Product Information -->

                    <div class="col-12 col-xl-8">

                        <div class="card dashboard-card">

                            <div class="card-header bg-white py-3">

                                <h5 class="mb-0 fw-bold">

                                    <i class="bi bi-box-seam text-primary me-2"></i>

                                    Product Information

                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="row g-3">

                                    <!-- SKU -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="sku"
                                            class="form-label fw-semibold"
                                        >
                                            SKU
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="sku"
                                            name="sku"
                                            value="<?= htmlspecialchars(
                                                $sku,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            maxlength="100"
                                            placeholder="e.g. PROD-001"
                                            required
                                        >

                                        <div class="form-text">
                                            Unique product stock keeping unit.
                                        </div>

                                    </div>


                                    <!-- Barcode -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="barcode"
                                            class="form-label fw-semibold"
                                        >
                                            Barcode
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="barcode"
                                            name="barcode"
                                            value="<?= htmlspecialchars(
                                                $barcode,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            maxlength="100"
                                            placeholder="Enter barcode"
                                        >

                                    </div>


                                    <!-- Product Name -->

                                    <div class="col-12">

                                        <label
                                            for="name"
                                            class="form-label fw-semibold"
                                        >
                                            Product Name
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="name"
                                            name="name"
                                            value="<?= htmlspecialchars(
                                                $name,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            maxlength="150"
                                            placeholder="Enter product name"
                                            required
                                        >

                                    </div>


                                    <!-- Category -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="category_id"
                                            class="form-label fw-semibold"
                                        >
                                            Category
                                            <span class="text-danger">*</span>
                                        </label>

                                        <select
                                            class="form-select"
                                            id="category_id"
                                            name="category_id"
                                            required
                                        >

                                            <option value="0">
                                                Select Category
                                            </option>

                                            <?php foreach ($categories as $category): ?>

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


                                    <!-- Unit -->

                                    <div class="col-12 col-md-6">

                                        <label
                                            for="unit"
                                            class="form-label fw-semibold"
                                        >
                                            Unit
                                            <span class="text-danger">*</span>
                                        </label>

                                        <select
                                            class="form-select"
                                            id="unit"
                                            name="unit"
                                            required
                                        >

                                            <?php foreach ($units as $unitOption): ?>

                                                <option
                                                    value="<?= htmlspecialchars(
                                                        $unitOption,
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                    <?= $unit === $unitOption
                                                        ? "selected"
                                                        : "" ?>
                                                >
                                                    <?= htmlspecialchars(
                                                        $unitOption,
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                    <!-- Description -->

                                    <div class="col-12">

                                        <label
                                            for="description"
                                            class="form-label fw-semibold"
                                        >
                                            Description
                                        </label>

                                        <textarea
                                            class="form-control"
                                            id="description"
                                            name="description"
                                            rows="4"
                                            maxlength="1000"
                                            placeholder="Enter product description"
                                        ><?= htmlspecialchars(
                                            $description,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?></textarea>

                                        <div class="form-text">
                                            Maximum 1000 characters.
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Pricing and Stock -->

                    <div class="col-12 col-xl-4">

                        <!-- Pricing -->

                        <div class="card dashboard-card mb-4">

                            <div class="card-header bg-white py-3">

                                <h5 class="mb-0 fw-bold">

                                    <i class="bi bi-currency-exchange text-success me-2"></i>

                                    Pricing

                                </h5>

                            </div>

                            <div class="card-body">

                                <!-- Purchase Price -->

                                <div class="mb-3">

                                    <label
                                        for="purchase_price"
                                        class="form-label fw-semibold"
                                    >
                                        Purchase Price
                                        <span class="text-danger">*</span>
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            class="form-control"
                                            id="purchase_price"
                                            name="purchase_price"
                                            value="<?= htmlspecialchars(
                                                $purchasePrice,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            min="0"
                                            max="9999999999.99"
                                            step="0.01"
                                            placeholder="0.00"
                                            required
                                        >

                                    </div>

                                </div>


                                <!-- Selling Price -->

                                <div>

                                    <label
                                        for="selling_price"
                                        class="form-label fw-semibold"
                                    >
                                        Selling Price
                                        <span class="text-danger">*</span>
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            class="form-control"
                                            id="selling_price"
                                            name="selling_price"
                                            value="<?= htmlspecialchars(
                                                $sellingPrice,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            min="0"
                                            max="9999999999.99"
                                            step="0.01"
                                            placeholder="0.00"
                                            required
                                        >

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- Stock -->

                        <div class="card dashboard-card">

                            <div class="card-header bg-white py-3">

                                <h5 class="mb-0 fw-bold">

                                    <i class="bi bi-boxes text-danger me-2"></i>

                                    Stock

                                </h5>

                            </div>

                            <div class="card-body">

                                <!-- Stock Quantity -->

                                <div class="mb-3">

                                    <label
                                        for="stock_quantity"
                                        class="form-label fw-semibold"
                                    >
                                        Stock Quantity
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control"
                                        id="stock_quantity"
                                        name="stock_quantity"
                                        value="<?= htmlspecialchars(
                                            $stockQuantity,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        min="0"
                                        max="9999999999.99"
                                        step="0.01"
                                        placeholder="0"
                                        required
                                    >

                                </div>


                                <!-- Reorder Level -->

                                <div class="mb-3">

                                    <label
                                        for="reorder_level"
                                        class="form-label fw-semibold"
                                    >
                                        Reorder Level
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control"
                                        id="reorder_level"
                                        name="reorder_level"
                                        value="<?= htmlspecialchars(
                                            $reorderLevel,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        min="0"
                                        max="9999999999.99"
                                        step="0.01"
                                        required
                                    >

                                    <div class="form-text">
                                        Low-stock warning threshold.
                                    </div>

                                </div>


                                <!-- Status -->

                                <div>

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

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Buttons -->

                <div class="d-flex justify-content-end gap-2 mt-4">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-check-lg me-1"></i>
                        Update Product
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>

<?php include "../includes/footer.php"; ?>