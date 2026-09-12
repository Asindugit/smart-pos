<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "New Purchase";

$error = "";

/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/

$purchaseDate = date("Y-m-d");
$invoiceNumber = "PUR-" . date("YmdHis");
$discount = 0;
$tax = 0;
$notes = "";
$supplierId = "";

/*
|--------------------------------------------------------------------------
| Load Active Suppliers
|--------------------------------------------------------------------------
*/

$suppliers = [];

$supplierResult = $conn->query("
    SELECT
        id,
        supplier_code,
        name,
        company_name
    FROM suppliers
    WHERE status = 'active'
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
| Load Active Products
|--------------------------------------------------------------------------
*/

$products = [];

$productResult = $conn->query("
    SELECT
        id,
        sku,
        name,
        purchase_price,
        stock_quantity,
        unit
    FROM products
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($productResult) {

    while ($product = $productResult->fetch_assoc()) {

        $products[] = $product;
    }

    $productResult->free();
}

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | Get Form Values
    |--------------------------------------------------------------------------
    */

    $supplierId = filter_input(
        INPUT_POST,
        "supplier_id",
        FILTER_VALIDATE_INT
    );

    $purchaseDate = trim(
        $_POST["purchase_date"] ?? ""
    );

    $invoiceNumber = trim(
        $_POST["invoice_number"] ?? ""
    );

    $discount = is_numeric($_POST["discount"] ?? null)
        ? (float) $_POST["discount"]
        : 0;

    $tax = is_numeric($_POST["tax"] ?? null)
        ? (float) $_POST["tax"]
        : 0;

    $notes = trim(
        $_POST["notes"] ?? ""
    );

    $productIds = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $unitCosts = $_POST["unit_cost"] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (!$supplierId || $supplierId <= 0) {

        $error = "Please select a supplier.";

    } elseif ($purchaseDate === "") {

        $error = "Purchase date is required.";

    } elseif ($invoiceNumber === "") {

        $error = "Invoice number is required.";

    } elseif (!preg_match(
        '/^[A-Za-z0-9\-_\/]+$/',
        $invoiceNumber
    )) {

        $error = "Invoice number contains invalid characters.";

    } elseif (strlen($invoiceNumber) > 100) {

        $error = "Invoice number cannot exceed 100 characters.";

    } elseif ($discount < 0) {

        $error = "Discount cannot be negative.";

    } elseif ($tax < 0) {

        $error = "Tax cannot be negative.";

    } elseif (!is_array($productIds) || empty($productIds)) {

        $error = "Please add at least one product.";

    }

    /*
    |--------------------------------------------------------------------------
    | Validate Purchase Date
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $dateObject = DateTime::createFromFormat(
            "Y-m-d",
            $purchaseDate
        );

        if (
            !$dateObject ||
            $dateObject->format("Y-m-d") !== $purchaseDate
        ) {

            $error = "Invalid purchase date.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Supplier
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $supplierStmt = $conn->prepare("
            SELECT
                id
            FROM suppliers
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
        ");

        if (!$supplierStmt) {

            $error = "Unable to verify supplier.";

        } else {

            $supplierStmt->bind_param(
                "i",
                $supplierId
            );

            if (!$supplierStmt->execute()) {

                $error = "Unable to verify supplier.";

            } else {

                $supplierCheck =
                    $supplierStmt->get_result();

                if ($supplierCheck->num_rows === 0) {

                    $error =
                        "Selected supplier is not available.";
                }
            }

            $supplierStmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Invoice Number
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $invoiceStmt = $conn->prepare("
            SELECT
                id
            FROM purchases
            WHERE invoice_number = ?
            LIMIT 1
        ");

        if (!$invoiceStmt) {

            $error =
                "Unable to verify invoice number.";

        } else {

            $invoiceStmt->bind_param(
                "s",
                $invoiceNumber
            );

            if (!$invoiceStmt->execute()) {

                $error =
                    "Unable to verify invoice number.";

            } else {

                $invoiceCheck =
                    $invoiceStmt->get_result();

                if ($invoiceCheck->num_rows > 0) {

                    $error =
                        "Invoice number already exists.";
                }
            }

            $invoiceStmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Purchase Items
    |--------------------------------------------------------------------------
    */

    $purchaseItems = [];
    $subtotal = 0;

    if ($error === "") {

        /*
        |--------------------------------------------------------------------------
        | Make Sure Array Lengths Match
        |--------------------------------------------------------------------------
        */

        if (
            !is_array($quantities) ||
            !is_array($unitCosts) ||
            count($productIds) !== count($quantities) ||
            count($productIds) !== count($unitCosts)
        ) {

            $error =
                "Invalid purchase item information.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Products
            |--------------------------------------------------------------------------
            */

            $usedProducts = [];

            /*
            |--------------------------------------------------------------------------
            | Product Verification Statement
            |--------------------------------------------------------------------------
            */

            $productStmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    purchase_price,
                    unit
                FROM products
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            if (!$productStmt) {

                $error =
                    "Unable to verify products.";

            } else {

                foreach ($productIds as $index => $productIdValue) {

                    $productId =
                        (int) $productIdValue;

                    $quantity =
                        is_numeric($quantities[$index] ?? null)
                            ? (float) $quantities[$index]
                            : 0;

                    $unitCost =
                        is_numeric($unitCosts[$index] ?? null)
                            ? (float) $unitCosts[$index]
                            : 0;

                    /*
                    |--------------------------------------------------------------------------
                    | Product ID Validation
                    |--------------------------------------------------------------------------
                    */

                    if ($productId <= 0) {

                        $error =
                            "Invalid product selected.";

                        break;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Product Validation
                    |--------------------------------------------------------------------------
                    */

                    if (isset($usedProducts[$productId])) {

                        $error =
                            "A product cannot be added more than once. " .
                            "Increase its quantity instead.";

                        break;
                    }

                    $usedProducts[$productId] = true;

                    /*
                    |--------------------------------------------------------------------------
                    | Quantity Validation
                    |--------------------------------------------------------------------------
                    */

                    if ($quantity <= 0) {

                        $error =
                            "Product quantity must be greater than zero.";

                        break;
                    }

                    if ($quantity > 999999999) {

                        $error =
                            "Product quantity is too large.";

                        break;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Unit Cost Validation
                    |--------------------------------------------------------------------------
                    */

                    if ($unitCost < 0) {

                        $error =
                            "Unit cost cannot be negative.";

                        break;
                    }

                    if ($unitCost > 999999999) {

                        $error =
                            "Unit cost is too large.";

                        break;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Verify Product Exists
                    |--------------------------------------------------------------------------
                    */

                    $productStmt->bind_param(
                        "i",
                        $productId
                    );

                    if (!$productStmt->execute()) {

                        $error =
                            "Unable to verify selected product.";

                        break;
                    }

                    $productCheck =
                        $productStmt->get_result();

                    if ($productCheck->num_rows === 0) {

                        $error =
                            "One of the selected products " .
                            "is no longer available.";

                        break;
                    }

                    $productData =
                        $productCheck->fetch_assoc();

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate Item Total
                    |--------------------------------------------------------------------------
                    */

                    $itemTotal =
                        $quantity * $unitCost;

                    $subtotal += $itemTotal;

                    /*
                    |--------------------------------------------------------------------------
                    | Store Item
                    |--------------------------------------------------------------------------
                    */

                    $purchaseItems[] = [
                        "product_id" =>
                            $productId,

                        "product_name" =>
                            $productData["name"],

                        "quantity" =>
                            $quantity,

                        "unit_cost" =>
                            $unitCost,

                        "total" =>
                            $itemTotal
                    ];
                }

                $productStmt->close();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Discount
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        if ($discount > $subtotal) {

            $error =
                "Discount cannot be greater than the subtotal.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Grand Total
    |--------------------------------------------------------------------------
    */

    $total = 0;

    if ($error === "") {

        $total =
            $subtotal -
            $discount +
            $tax;

        if ($total < 0) {

            $error =
                "Purchase total cannot be negative.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Purchase
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            /*
            |--------------------------------------------------------------------------
            | Start Database Transaction
            |--------------------------------------------------------------------------
            */

            $conn->begin_transaction();

            /*
            |--------------------------------------------------------------------------
            | Current Logged-In User
            |--------------------------------------------------------------------------
            */

            $createdBy =
                (int) ($_SESSION["user_id"] ?? 0);

            if ($createdBy <= 0) {

                throw new Exception(
                    "Invalid logged-in user."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Insert Purchase
            |--------------------------------------------------------------------------
            */

            $purchaseStmt = $conn->prepare("
                INSERT INTO purchases
                (
                    supplier_id,
                    invoice_number,
                    purchase_date,
                    subtotal,
                    discount,
                    tax,
                    total,
                    status,
                    notes,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'completed',
                    ?,
                    ?
                )
            ");

            if (!$purchaseStmt) {

                throw new Exception(
                    "Unable to prepare purchase statement: " .
                    $conn->error
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Correct Parameter Types
            |--------------------------------------------------------------------------
            |
            | i = integer
            | s = string
            | d = decimal/double
            |
            | supplierId    = i
            | invoiceNumber = s
            | purchaseDate  = s
            | subtotal      = d
            | discount      = d
            | tax           = d
            | total         = d
            | notes         = s
            | createdBy     = i
            |
            |--------------------------------------------------------------------------
            */

            $purchaseStmt->bind_param(
                "issddddsi",
                $supplierId,
                $invoiceNumber,
                $purchaseDate,
                $subtotal,
                $discount,
                $tax,
                $total,
                $notes,
                $createdBy
            );

            if (!$purchaseStmt->execute()) {

                throw new Exception(
                    "Unable to save purchase: " .
                    $purchaseStmt->error
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Get Purchase ID
            |--------------------------------------------------------------------------
            */

            $purchaseId =
                $conn->insert_id;

            $purchaseStmt->close();

            if ($purchaseId <= 0) {

                throw new Exception(
                    "Purchase ID was not generated."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Purchase Item Insert
            |--------------------------------------------------------------------------
            */

            $itemStmt = $conn->prepare("
                INSERT INTO purchase_items
                (
                    purchase_id,
                    product_id,
                    quantity,
                    unit_cost,
                    total
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$itemStmt) {

                throw new Exception(
                    "Unable to prepare purchase item statement: " .
                    $conn->error
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Stock Update
            |--------------------------------------------------------------------------
            */

            $stockStmt = $conn->prepare("
                UPDATE products
                SET
                    stock_quantity =
                        stock_quantity + ?,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE id = ?
                  AND status = 'active'
            ");

            if (!$stockStmt) {

                throw new Exception(
                    "Unable to prepare stock update statement: " .
                    $conn->error
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Stock Movement
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
                VALUES
                (
                    ?,
                    ?,
                    'purchase',
                    ?,
                    'purchase',
                    ?,
                    ?
                )
            ");

            if (!$movementStmt) {

                throw new Exception(
                    "Unable to prepare stock movement statement: " .
                    $conn->error
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Process Every Purchase Item
            |--------------------------------------------------------------------------
            */

            foreach ($purchaseItems as $item) {

                $productId =
                    (int) $item["product_id"];

                $quantity =
                    (float) $item["quantity"];

                $unitCost =
                    (float) $item["unit_cost"];

                $itemTotal =
                    (float) $item["total"];

                /*
                |--------------------------------------------------------------------------
                | Insert Purchase Item
                |--------------------------------------------------------------------------
                */

                $itemStmt->bind_param(
                    "iiddd",
                    $purchaseId,
                    $productId,
                    $quantity,
                    $unitCost,
                    $itemTotal
                );

                if (!$itemStmt->execute()) {

                    throw new Exception(
                        "Unable to save purchase item: " .
                        $itemStmt->error
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Increase Product Stock
                |--------------------------------------------------------------------------
                */

                $stockStmt->bind_param(
                    "di",
                    $quantity,
                    $productId
                );

                if (!$stockStmt->execute()) {

                    throw new Exception(
                        "Unable to update stock: " .
                        $stockStmt->error
                    );
                }

                if ($stockStmt->affected_rows <= 0) {

                    throw new Exception(
                        "Unable to update stock for product: " .
                        $item["product_name"]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Stock Movement
                |--------------------------------------------------------------------------
                */

                $movementNotes =
                    "Purchase " .
                    $invoiceNumber .
                    " - " .
                    $item["product_name"];

                $movementStmt->bind_param(
                    "iidis",
                    $productId,
                    $createdBy,
                    $quantity,
                    $purchaseId,
                    $movementNotes
                );

                if (!$movementStmt->execute()) {

                    throw new Exception(
                        "Unable to create stock movement: " .
                        $movementStmt->error
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Close Statements
            |--------------------------------------------------------------------------
            */

            $itemStmt->close();
            $stockStmt->close();
            $movementStmt->close();

            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $conn->commit();

            /*
            |--------------------------------------------------------------------------
            | Close Database Connection
            |--------------------------------------------------------------------------
            */

            $conn->close();

            /*
            |--------------------------------------------------------------------------
            | Redirect To Purchase View
            |--------------------------------------------------------------------------
            */

            header(
                "Location: view.php?id=" .
                $purchaseId .
                "&success=" .
                urlencode(
                    "Purchase completed successfully."
                )
            );

            exit;

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback All Changes
            |--------------------------------------------------------------------------
            */

            $conn->rollback();

            /*
            |--------------------------------------------------------------------------
            | User-Friendly Error
            |--------------------------------------------------------------------------
            |
            | During development we display the actual error.
            | Change this to a generic message before production.
            |
            |--------------------------------------------------------------------------
            */

            $error =
                "Unable to save purchase: " .
                $e->getMessage();
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

            <!--
            |--------------------------------------------------------------------------
            | Page Header
            |--------------------------------------------------------------------------
            -->

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">

                            <i class="bi bi-bag-plus"></i>

                        </span>

                        <h3 class="mb-0 fw-bold">
                            New Purchase
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Record products received from a supplier.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >

                    <i class="bi bi-arrow-left me-1"></i>

                    Back to Purchases

                </a>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Error Alert
            |--------------------------------------------------------------------------
            -->

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


            <!--
            |--------------------------------------------------------------------------
            | Supplier Availability Warning
            |--------------------------------------------------------------------------
            -->

            <?php if (empty($suppliers)): ?>

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    No active suppliers are available.

                    <a
                        href="../suppliers/add.php"
                        class="alert-link"
                    >
                        Add a supplier first.
                    </a>

                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Product Availability Warning
            |--------------------------------------------------------------------------
            -->

            <?php if (empty($products)): ?>

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    No active products are available.

                    <a
                        href="../products/add.php"
                        class="alert-link"
                    >
                        Add a product first.
                    </a>

                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Purchase Form
            |--------------------------------------------------------------------------
            -->

            <form
                method="POST"
                action=""
                id="purchaseForm"
            >

                <!--
                |--------------------------------------------------------------------------
                | Purchase Information
                |--------------------------------------------------------------------------
                -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white border-0 p-4">

                        <div class="d-flex align-items-center gap-3">

                            <div class="section-icon section-icon-blue">

                                <i class="bi bi-receipt"></i>

                            </div>

                            <div>

                                <h5 class="mb-1 fw-bold">
                                    Purchase Information
                                </h5>

                                <small class="text-muted">
                                    Enter supplier and invoice information.
                                </small>

                            </div>

                        </div>

                    </div>

                    <div class="card-body p-4">

                        <div class="row g-4">

                            <!-- Supplier -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="supplier_id"
                                    class="form-label fw-semibold"
                                >

                                    Supplier

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>

                                <select
                                    id="supplier_id"
                                    name="supplier_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select Supplier
                                    </option>

                                    <?php foreach ($suppliers as $supplier): ?>

                                        <option
                                            value="<?= (int) $supplier["id"] ?>"
                                            <?= (string) $supplierId === (string) $supplier["id"] ? "selected" : "" ?>
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


                            <!-- Invoice Number -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="invoice_number"
                                    class="form-label fw-semibold"
                                >

                                    Invoice Number

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>

                                <input
                                    type="text"
                                    id="invoice_number"
                                    name="invoice_number"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $invoiceNumber
                                    ) ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>


                            <!-- Purchase Date -->

                            <div class="col-12 col-md-4">

                                <label
                                    for="purchase_date"
                                    class="form-label fw-semibold"
                                >

                                    Purchase Date

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>

                                <input
                                    type="date"
                                    id="purchase_date"
                                    name="purchase_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $purchaseDate
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | Purchase Items
                |--------------------------------------------------------------------------
                -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white border-0 p-4">

                        <div
                            class="d-flex justify-content-between align-items-center gap-3"
                        >

                            <div class="d-flex align-items-center gap-3">

                                <div class="section-icon section-icon-blue">

                                    <i class="bi bi-box-seam"></i>

                                </div>

                                <div>

                                    <h5 class="mb-1 fw-bold">
                                        Purchase Items
                                    </h5>

                                    <small class="text-muted">
                                        Add products received from the supplier.
                                    </small>

                                </div>

                            </div>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                id="addItemBtn"
                            >

                                <i class="bi bi-plus-circle me-1"></i>

                                Add Product

                            </button>

                        </div>

                    </div>


                    <div class="card-body p-4">

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th style="min-width: 250px;">
                                            Product
                                        </th>

                                        <th style="min-width: 130px;">
                                            Quantity
                                        </th>

                                        <th style="min-width: 160px;">
                                            Unit Cost
                                        </th>

                                        <th style="min-width: 160px;">
                                            Total
                                        </th>

                                        <th style="width: 60px;">
                                        </th>

                                    </tr>

                                </thead>

                                <tbody id="purchaseItems"></tbody>

                            </table>

                        </div>


                        <div
                            id="emptyItems"
                            class="text-center py-4 text-muted"
                        >

                            <i
                                class="bi bi-box-seam fs-2 d-block mb-2"
                            ></i>

                            Add at least one product to this purchase.

                        </div>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | Notes + Summary
                |--------------------------------------------------------------------------
                -->

                <div class="row g-4">

                    <!-- Notes -->

                    <div class="col-12 col-lg-7">

                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white border-0 p-4">

                                <h5 class="mb-0 fw-bold">
                                    Notes
                                </h5>

                            </div>

                            <div class="card-body p-4">

                                <label
                                    for="notes"
                                    class="form-label fw-semibold"
                                >

                                    Purchase Notes

                                </label>

                                <textarea
                                    id="notes"
                                    name="notes"
                                    class="form-control"
                                    rows="6"
                                    placeholder="Optional notes about this purchase"
                                ><?= htmlspecialchars(
                                    $notes
                                ) ?></textarea>

                            </div>

                        </div>

                    </div>


                    <!-- Summary -->

                    <div class="col-12 col-lg-5">

                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white border-0 p-4">

                                <h5 class="mb-0 fw-bold">
                                    Purchase Summary
                                </h5>

                            </div>

                            <div class="card-body p-4">

                                <!-- Subtotal -->

                                <div
                                    class="d-flex justify-content-between mb-3"
                                >

                                    <span class="text-muted">
                                        Subtotal
                                    </span>

                                    <strong>

                                        LKR

                                        <span id="subtotalDisplay">
                                            0.00
                                        </span>

                                    </strong>

                                </div>


                                <!-- Discount -->

                                <div class="mb-3">

                                    <label
                                        for="discount"
                                        class="form-label fw-semibold"
                                    >

                                        Discount

                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            id="discount"
                                            name="discount"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                $discount
                                            ) ?>"
                                            min="0"
                                            step="0.01"
                                        >

                                    </div>

                                </div>


                                <!-- Tax -->

                                <div class="mb-3">

                                    <label
                                        for="tax"
                                        class="form-label fw-semibold"
                                    >

                                        Tax

                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            LKR
                                        </span>

                                        <input
                                            type="number"
                                            id="tax"
                                            name="tax"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                $tax
                                            ) ?>"
                                            min="0"
                                            step="0.01"
                                        >

                                    </div>

                                </div>


                                <hr>


                                <!-- Grand Total -->

                                <div
                                    class="d-flex justify-content-between align-items-center"
                                >

                                    <span class="fw-bold">
                                        Grand Total
                                    </span>

                                    <span
                                        class="fs-4 fw-bold text-success"
                                    >

                                        LKR

                                        <span id="totalDisplay">
                                            0.00
                                        </span>

                                    </span>

                                </div>


                                <!-- Submit -->

                                <div class="d-grid mt-4">

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-lg"
                                        id="submitPurchaseBtn"
                                    >

                                        <i
                                            class="bi bi-check-circle me-1"
                                        ></i>

                                        Complete Purchase

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </main>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Product Data
|--------------------------------------------------------------------------
*/

const products =
    <?= json_encode(
        $products,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;


/*
|--------------------------------------------------------------------------
| DOM Elements
|--------------------------------------------------------------------------
*/

const purchaseItems =
    document.getElementById("purchaseItems");

const emptyItems =
    document.getElementById("emptyItems");

const addItemBtn =
    document.getElementById("addItemBtn");

const discountInput =
    document.getElementById("discount");

const taxInput =
    document.getElementById("tax");

const purchaseForm =
    document.getElementById("purchaseForm");


/*
|--------------------------------------------------------------------------
| Format Money
|--------------------------------------------------------------------------
*/

function formatMoney(value) {

    return Number(value).toLocaleString(
        "en-LK",
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}


/*
|--------------------------------------------------------------------------
| Create Product Options
|--------------------------------------------------------------------------
*/

function createProductOptions() {

    let options = `
        <option value="">
            Select Product
        </option>
    `;

    products.forEach(function(product) {

        options += `
            <option
                value="${escapeHtml(product.id)}"
                data-cost="${escapeHtml(product.purchase_price)}"
            >
                ${escapeHtml(product.name)}
                (${escapeHtml(product.sku)})
            </option>
        `;

    });

    return options;
}


/*
|--------------------------------------------------------------------------
| Update Summary
|--------------------------------------------------------------------------
*/

function updateSummary() {

    let subtotal = 0;

    document
        .querySelectorAll(".purchase-row")
        .forEach(function(row) {

            const quantityInput =
                row.querySelector(".quantity-input");

            const costInput =
                row.querySelector(".cost-input");

            const rowTotal =
                row.querySelector(".row-total");

            const quantity =
                parseFloat(quantityInput.value) || 0;

            const cost =
                parseFloat(costInput.value) || 0;

            const total =
                quantity * cost;

            rowTotal.textContent =
                formatMoney(total);

            subtotal += total;

        });


    let discount =
        parseFloat(discountInput.value) || 0;

    let tax =
        parseFloat(taxInput.value) || 0;


    if (discount < 0) {
        discount = 0;
    }

    if (tax < 0) {
        tax = 0;
    }


    const grandTotal =
        Math.max(
            0,
            subtotal -
            discount +
            tax
        );


    document.getElementById(
        "subtotalDisplay"
    ).textContent =
        formatMoney(subtotal);


    document.getElementById(
        "totalDisplay"
    ).textContent =
        formatMoney(grandTotal);


    emptyItems.style.display =
        purchaseItems.children.length === 0
            ? "block"
            : "none";
}


/*
|--------------------------------------------------------------------------
| Add Purchase Item
|--------------------------------------------------------------------------
*/

function addItem() {

    if (products.length === 0) {

        alert(
            "Please add an active product first."
        );

        return;
    }


    const row =
        document.createElement("tr");

    row.className =
        "purchase-row";


    row.innerHTML = `

        <td>

            <select
                name="product_id[]"
                class="form-select product-select"
                required
            >

                ${createProductOptions()}

            </select>

        </td>


        <td>

            <input
                type="number"
                name="quantity[]"
                class="form-control quantity-input"
                min="0.01"
                max="999999999"
                step="0.01"
                value="1"
                required
            >

        </td>


        <td>

            <div class="input-group">

                <span class="input-group-text">
                    LKR
                </span>

                <input
                    type="number"
                    name="unit_cost[]"
                    class="form-control cost-input"
                    min="0"
                    max="999999999"
                    step="0.01"
                    value="0"
                    required
                >

            </div>

        </td>


        <td>

            <strong>

                LKR

                <span class="row-total">
                    0.00
                </span>

            </strong>

        </td>


        <td>

            <button
                type="button"
                class="btn btn-sm btn-outline-danger remove-item"
                title="Remove"
            >

                <i class="bi bi-trash"></i>

            </button>

        </td>

    `;


    purchaseItems.appendChild(row);


    /*
    |--------------------------------------------------------------------------
    | Product Selection
    |--------------------------------------------------------------------------
    */

    const productSelect =
        row.querySelector(".product-select");

    const costInput =
        row.querySelector(".cost-input");


    productSelect.addEventListener(
        "change",
        function() {

            const selectedOption =
                this.options[
                    this.selectedIndex
                ];


            const cost =
                selectedOption.dataset.cost || 0;


            costInput.value =
                Number(cost).toFixed(2);


            updateSummary();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Quantity / Cost Changes
    |--------------------------------------------------------------------------
    */

    row
        .querySelectorAll("input")
        .forEach(function(input) {

            input.addEventListener(
                "input",
                updateSummary
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    row
        .querySelector(".remove-item")
        .addEventListener(
            "click",
            function() {

                row.remove();

                updateSummary();

            }
        );


    updateSummary();
}


/*
|--------------------------------------------------------------------------
| Add Product Button
|--------------------------------------------------------------------------
*/

addItemBtn.addEventListener(
    "click",
    addItem
);


/*
|--------------------------------------------------------------------------
| Discount / Tax
|--------------------------------------------------------------------------
*/

discountInput.addEventListener(
    "input",
    updateSummary
);

taxInput.addEventListener(
    "input",
    updateSummary
);


/*
|--------------------------------------------------------------------------
| Form Validation
|--------------------------------------------------------------------------
*/

purchaseForm.addEventListener(
    "submit",
    function(event) {

        /*
        |--------------------------------------------------------------------------
        | Check Items
        |--------------------------------------------------------------------------
        */

        if (
            purchaseItems.children.length === 0
        ) {

            event.preventDefault();

            alert(
                "Please add at least one product."
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Check Products
        |--------------------------------------------------------------------------
        */

        const selectedProducts = [];

        let duplicate = false;

        let invalidProduct = false;


        document
            .querySelectorAll(".product-select")
            .forEach(function(select) {

                if (select.value === "") {

                    invalidProduct = true;

                    return;
                }


                if (
                    selectedProducts.includes(
                        select.value
                    )
                ) {

                    duplicate = true;
                }


                selectedProducts.push(
                    select.value
                );

            });


        /*
        |--------------------------------------------------------------------------
        | Invalid Product
        |--------------------------------------------------------------------------
        */

        if (invalidProduct) {

            event.preventDefault();

            alert(
                "Please select a product for every row."
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Product
        |--------------------------------------------------------------------------
        */

        if (duplicate) {

            event.preventDefault();

            alert(
                "A product cannot be added more than once. " +
                "Increase its quantity instead."
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Discount Validation
        |--------------------------------------------------------------------------
        */

        const discount =
            parseFloat(
                discountInput.value
            ) || 0;

        const subtotal =
            Array.from(
                document.querySelectorAll(
                    ".purchase-row"
                )
            ).reduce(
                function(total, row) {

                    const quantity =
                        parseFloat(
                            row.querySelector(
                                ".quantity-input"
                            ).value
                        ) || 0;

                    const cost =
                        parseFloat(
                            row.querySelector(
                                ".cost-input"
                            ).value
                        ) || 0;

                    return total +
                        (quantity * cost);

                },
                0
            );


        if (discount > subtotal) {

            event.preventDefault();

            alert(
                "Discount cannot be greater than the subtotal."
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Disable Submit Button
        |--------------------------------------------------------------------------
        */

        const submitButton =
            document.getElementById(
                "submitPurchaseBtn"
            );

        if (submitButton) {

            submitButton.disabled = true;

            submitButton.innerHTML = `
                <span
                    class="spinner-border spinner-border-sm me-2"
                ></span>
                Saving Purchase...
            `;
        }

    }
);


/*
|--------------------------------------------------------------------------
| Add Initial Row
|--------------------------------------------------------------------------
*/

addItem();


/*
|--------------------------------------------------------------------------
| Initial Summary
|--------------------------------------------------------------------------
*/

updateSummary();

</script>


<?php include "../includes/footer.php"; ?>