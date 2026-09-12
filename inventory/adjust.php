<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Adjust Stock";

$productId = isset($_GET["product_id"])
    ? (int) $_GET["product_id"]
    : (int) ($_POST["product_id"] ?? 0);

$adjustmentType = $_POST["adjustment_type"] ?? "add";
$quantity = $_POST["quantity"] ?? "";
$notes = trim($_POST["notes"] ?? "");

$errors = [];

$product = null;


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

$userId = (int) ($currentUser["id"] ?? 0);


/*
|--------------------------------------------------------------------------
| Load Product
|--------------------------------------------------------------------------
*/

if ($productId > 0) {

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.sku,
            p.barcode,
            p.name,
            p.stock_quantity,
            p.reorder_level,
            p.unit,
            p.status,
            c.name AS category_name
        FROM products p
        LEFT JOIN categories c
            ON p.category_id = c.id
        WHERE p.id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $productId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $product = $result->fetch_assoc();

        }

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| Validate Product
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($product === null) {

        $errors[] = "The selected product could not be found.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Adjustment Type
    |--------------------------------------------------------------------------
    */

    $allowedAdjustmentTypes = [
        "add",
        "remove",
        "set"
    ];

    if (!in_array($adjustmentType, $allowedAdjustmentTypes, true)) {

        $errors[] = "Invalid stock adjustment type.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Quantity
    |--------------------------------------------------------------------------
    */

    if ($quantity === "") {

        $errors[] = "Quantity is required.";

    } elseif (!is_numeric($quantity)) {

        $errors[] = "Quantity must be a valid number.";

    } elseif ((float) $quantity <= 0) {

        $errors[] = "Quantity must be greater than zero.";

    } elseif ((float) $quantity > 999999999) {

        $errors[] = "Quantity is too large.";

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate New Stock
    |--------------------------------------------------------------------------
    */

    if (empty($errors) && $product !== null) {

        $currentStock =
            (float) $product["stock_quantity"];

        $adjustmentQuantity =
            (float) $quantity;


        if ($adjustmentType === "add") {

            $newStock =
                $currentStock + $adjustmentQuantity;

        } elseif ($adjustmentType === "remove") {

            $newStock =
                $currentStock - $adjustmentQuantity;

        } else {

            $newStock =
                $adjustmentQuantity;

        }


        /*
        |--------------------------------------------------------------------------
        | Prevent Negative Stock
        |--------------------------------------------------------------------------
        */

        if ($newStock < 0) {

            $errors[] =
                "Stock cannot become negative. Current stock is " .
                number_format($currentStock, 2) .
                " " .
                $product["unit"] .
                ".";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Save Stock Adjustment
    |--------------------------------------------------------------------------
    */

    if (empty($errors) && $product !== null) {

        /*
        |--------------------------------------------------------------------------
        | Start Transaction
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Update Product Stock
            |--------------------------------------------------------------------------
            */

            $updateStmt = $conn->prepare("
                UPDATE products
                SET
                    stock_quantity = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            if (!$updateStmt) {

                throw new Exception(
                    "Unable to prepare stock update."
                );

            }


            $updateStmt->bind_param(
                "di",
                $newStock,
                $productId
            );


            if (!$updateStmt->execute()) {

                $updateStmt->close();

                throw new Exception(
                    "Failed to update product stock."
                );

            }

            $updateStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Movement Type
            |--------------------------------------------------------------------------
            */

            $movementType = "adjustment";


            /*
            |--------------------------------------------------------------------------
            | Movement Quantity
            |--------------------------------------------------------------------------
            |
            | Add:
            |   +quantity
            |
            | Remove:
            |   -quantity
            |
            | Set:
            |   Difference between old and new stock
            |
            */

            if ($adjustmentType === "add") {

                $movementQuantity =
                    $adjustmentQuantity;

            } elseif ($adjustmentType === "remove") {

                $movementQuantity =
                    -$adjustmentQuantity;

            } else {

                $movementQuantity =
                    $newStock - $currentStock;

            }


            /*
            |--------------------------------------------------------------------------
            | Movement Notes
            |--------------------------------------------------------------------------
            */

            if ($notes === "") {

                if ($adjustmentType === "add") {

                    $movementNotes =
                        "Manual stock addition.";

                } elseif ($adjustmentType === "remove") {

                    $movementNotes =
                        "Manual stock removal.";

                } else {

                    $movementNotes =
                        "Manual stock count adjustment.";

                }

            } else {

                $movementNotes = $notes;

            }


            /*
            |--------------------------------------------------------------------------
            | Insert Stock Movement
            |--------------------------------------------------------------------------
            */

            $movementStmt = $conn->prepare("
                INSERT INTO stock_movements
                (
                    product_id,
                    user_id,
                    movement_type,
                    quantity,
                    reference_type,
                    reference_id,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");


            if (!$movementStmt) {

                throw new Exception(
                    "Unable to prepare stock movement."
                );

            }


            $referenceType = "manual_adjustment";
            $referenceId = null;


            $movementStmt->bind_param(
                "iisdsis",
                $productId,
                $userId,
                $movementType,
                $movementQuantity,
                $referenceType,
                $referenceId,
                $movementNotes
            );


            if (!$movementStmt->execute()) {

                $movementStmt->close();

                throw new Exception(
                    "Failed to record stock movement."
                );

            }

            $movementStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                "Location: index.php?success=" .
                urlencode("Stock adjusted successfully.")
            );

            exit;

        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback Transaction
            |--------------------------------------------------------------------------
            */

            $conn->rollback();

            $errors[] =
                "Failed to adjust stock. Please try again.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

include "../includes/header.php";
include "../includes/navbar.php";

?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- PAGE HEADER -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >

                <div>

                    <h2 class="fw-bold mb-1">
                        Adjust Stock
                    </h2>

                    <p class="text-muted mb-0">
                        Manually update product stock and record the movement.
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


            <!-- ERROR MESSAGES -->

            <?php if (!empty($errors)): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <div class="fw-semibold mb-2">

                        <i
                            class="bi bi-exclamation-triangle me-2"
                        ></i>

                        Please fix the following:

                    </div>

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <?php if ($product === null): ?>

                <!-- PRODUCT NOT FOUND -->

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="empty-state text-center py-5">

                            <div class="empty-icon mb-3">

                                <i class="bi bi-box-seam"></i>

                            </div>

                            <h5 class="fw-bold">
                                Product not found
                            </h5>

                            <p class="text-muted mb-3">
                                The selected product could not be found.
                            </p>

                            <a
                                href="index.php"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-arrow-left me-1"></i>

                                Back to Inventory

                            </a>

                        </div>

                    </div>

                </div>

            <?php else: ?>


                <!-- PRODUCT INFORMATION -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white py-3">

                        <div
                            class="d-flex align-items-center gap-2"
                        >

                            <div
                                class="section-icon section-icon-blue"
                            >

                                <i class="bi bi-box-seam"></i>

                            </div>

                            <div>

                                <h5 class="mb-0 fw-semibold">
                                    Product Information
                                </h5>

                                <small class="text-muted">
                                    Review the current product stock.
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body p-4">

                        <div class="row g-4">

                            <!-- PRODUCT -->

                            <div class="col-12 col-md-6">

                                <div class="text-muted small mb-1">
                                    Product
                                </div>

                                <div class="fw-semibold fs-5">

                                    <?= htmlspecialchars(
                                        $product["name"]
                                    ) ?>

                                </div>

                            </div>


                            <!-- SKU -->

                            <div class="col-12 col-md-3">

                                <div class="text-muted small mb-1">
                                    SKU
                                </div>

                                <span
                                    class="badge bg-light text-dark border"
                                >

                                    <?= htmlspecialchars(
                                        $product["sku"]
                                    ) ?>

                                </span>

                            </div>


                            <!-- CATEGORY -->

                            <div class="col-12 col-md-3">

                                <div class="text-muted small mb-1">
                                    Category
                                </div>

                                <div class="fw-semibold">

                                    <?= !empty($product["category_name"])
                                        ? htmlspecialchars(
                                            $product["category_name"]
                                        )
                                        : "Uncategorised"
                                    ?>

                                </div>

                            </div>


                            <!-- CURRENT STOCK -->

                            <div class="col-12 col-md-4">

                                <div class="text-muted small mb-1">
                                    Current Stock
                                </div>

                                <div
                                    class="fs-4 fw-bold text-primary"
                                >

                                    <?= number_format(
                                        (float) $product["stock_quantity"],
                                        2
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product["unit"]
                                    ) ?>

                                </div>

                            </div>


                            <!-- REORDER LEVEL -->

                            <div class="col-12 col-md-4">

                                <div class="text-muted small mb-1">
                                    Reorder Level
                                </div>

                                <div class="fs-5 fw-semibold">

                                    <?= number_format(
                                        (float) $product["reorder_level"],
                                        2
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product["unit"]
                                    ) ?>

                                </div>

                            </div>


                            <!-- PRODUCT STATUS -->

                            <div class="col-12 col-md-4">

                                <div class="text-muted small mb-1">
                                    Product Status
                                </div>

                                <?php if ($product["status"] === "active"): ?>

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

                            </div>

                        </div>

                    </div>

                </div>


                <!-- STOCK ADJUSTMENT FORM -->

                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white py-3">

                        <div
                            class="d-flex align-items-center gap-2"
                        >

                            <div
                                class="section-icon section-icon-blue"
                            >

                                <i class="bi bi-sliders"></i>

                            </div>

                            <div>

                                <h5 class="mb-0 fw-semibold">
                                    Stock Adjustment
                                </h5>

                                <small class="text-muted">
                                    Enter how you want to update the stock.
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body p-4">

                        <form
                            method="POST"
                            action=""
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product["id"] ?>"
                            >


                            <div class="row g-4">

                                <!-- ADJUSTMENT TYPE -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="adjustment_type"
                                        class="form-label fw-semibold"
                                    >

                                        Adjustment Type

                                        <span class="text-danger">
                                            *
                                        </span>

                                    </label>

                                    <select
                                        id="adjustment_type"
                                        name="adjustment_type"
                                        class="form-select"
                                        required
                                    >

                                        <option
                                            value="add"
                                            <?= $adjustmentType === "add"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Add Stock
                                        </option>

                                        <option
                                            value="remove"
                                            <?= $adjustmentType === "remove"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Remove Stock
                                        </option>

                                        <option
                                            value="set"
                                            <?= $adjustmentType === "set"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Set Stock
                                        </option>

                                    </select>

                                    <div class="form-text">
                                        Choose how the stock quantity should be changed.
                                    </div>

                                </div>


                                <!-- QUANTITY -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="quantity"
                                        class="form-label fw-semibold"
                                    >

                                        Quantity

                                        <span class="text-danger">
                                            *
                                        </span>

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="number"
                                            id="quantity"
                                            name="quantity"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                (string) $quantity
                                            ) ?>"
                                            min="0.01"
                                            max="999999999"
                                            step="0.01"
                                            placeholder="Enter quantity"
                                            required
                                        >

                                        <span class="input-group-text">

                                            <?= htmlspecialchars(
                                                $product["unit"]
                                            ) ?>

                                        </span>

                                    </div>

                                    <div class="form-text">
                                        Enter a quantity greater than zero.
                                    </div>

                                </div>


                                <!-- NOTES -->

                                <div class="col-12">

                                    <label
                                        for="notes"
                                        class="form-label fw-semibold"
                                    >

                                        Reason / Notes

                                    </label>

                                    <textarea
                                        id="notes"
                                        name="notes"
                                        class="form-control"
                                        rows="4"
                                        maxlength="500"
                                        placeholder="Example: Physical stock count correction..."
                                    ><?= htmlspecialchars($notes) ?></textarea>

                                    <div class="form-text">
                                        Optional. Add a reason for this stock adjustment.
                                    </div>

                                </div>

                            </div>


                            <!-- FORM BUTTONS -->

                            <div
                                class="d-flex flex-column flex-sm-row gap-2 mt-4 pt-4 border-top"
                            >

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check-lg me-1"></i>

                                    Save Adjustment

                                </button>

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                >

                                    <i class="bi bi-x-lg me-1"></i>

                                    Cancel

                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php

$conn->close();

include "../includes/footer.php";

?>