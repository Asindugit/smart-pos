<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Cancel Purchase";

$purchaseId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

$error = "";
$purchase = null;
$items = [];

/*
|--------------------------------------------------------------------------
| Validate Purchase ID
|--------------------------------------------------------------------------
*/

if (!$purchaseId || $purchaseId <= 0) {

    $error = "Invalid purchase ID.";

} else {

    /*
    |--------------------------------------------------------------------------
    | Load Purchase
    |--------------------------------------------------------------------------
    */

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
            p.notes,

            s.name AS supplier_name,
            s.company_name

        FROM purchases p

        LEFT JOIN suppliers s
            ON s.id = p.supplier_id

        WHERE p.id = ?

        LIMIT 1
    ");

    if (!$purchaseStmt) {

        $error =
            "Unable to load purchase.";

    } else {

        $purchaseStmt->bind_param(
            "i",
            $purchaseId
        );

        if (!$purchaseStmt->execute()) {

            $error =
                "Unable to load purchase.";

        } else {

            $result =
                $purchaseStmt->get_result();

            $purchase =
                $result->fetch_assoc();

            if (!$purchase) {

                $error =
                    "Purchase not found.";
            }

            $result->free();
        }

        $purchaseStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Load Purchase Items
    |--------------------------------------------------------------------------
    */

    if ($error === "" && $purchase) {

        $itemStmt = $conn->prepare("
            SELECT
                pi.product_id,
                pi.quantity,
                pi.unit_cost,
                pi.total,

                p.name AS product_name,
                p.sku,
                p.stock_quantity

            FROM purchase_items pi

            INNER JOIN products p
                ON p.id = pi.product_id

            WHERE pi.purchase_id = ?

            ORDER BY pi.id ASC
        ");

        if (!$itemStmt) {

            $error =
                "Unable to load purchase items.";

        } else {

            $itemStmt->bind_param(
                "i",
                $purchaseId
            );

            if (!$itemStmt->execute()) {

                $error =
                    "Unable to load purchase items.";

            } else {

                $result =
                    $itemStmt->get_result();

                while ($item = $result->fetch_assoc()) {

                    $items[] = $item;
                }

                $result->free();
            }

            $itemStmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Process Cancellation
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $error === ""
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Start Transaction
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();


        /*
        |--------------------------------------------------------------------------
        | Lock Purchase
        |--------------------------------------------------------------------------
        */

        $lockPurchaseStmt = $conn->prepare("
            SELECT
                id,
                invoice_number,
                status
            FROM purchases
            WHERE id = ?
            FOR UPDATE
        ");

        if (!$lockPurchaseStmt) {

            throw new Exception(
                "Unable to prepare purchase lock."
            );
        }

        $lockPurchaseStmt->bind_param(
            "i",
            $purchaseId
        );

        if (!$lockPurchaseStmt->execute()) {

            throw new Exception(
                "Unable to verify purchase."
            );
        }

        $lockedPurchaseResult =
            $lockPurchaseStmt->get_result();

        $lockedPurchase =
            $lockedPurchaseResult->fetch_assoc();

        $lockPurchaseStmt->close();

        if (!$lockedPurchase) {

            throw new Exception(
                "Purchase not found."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Purchase Status
        |--------------------------------------------------------------------------
        */

        if (
            $lockedPurchase["status"] !== "completed"
        ) {

            throw new Exception(
                "Only completed purchases can be cancelled."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Lock Purchase Items + Products
        |--------------------------------------------------------------------------
        */

        $itemsStmt = $conn->prepare("
            SELECT
                pi.product_id,
                pi.quantity,

                p.name AS product_name,
                p.stock_quantity

            FROM purchase_items pi

            INNER JOIN products p
                ON p.id = pi.product_id

            WHERE pi.purchase_id = ?

            FOR UPDATE
        ");

        if (!$itemsStmt) {

            throw new Exception(
                "Unable to prepare stock verification."
            );
        }

        $itemsStmt->bind_param(
            "i",
            $purchaseId
        );

        if (!$itemsStmt->execute()) {

            throw new Exception(
                "Unable to verify product stock."
            );
        }

        $itemsResult =
            $itemsStmt->get_result();

        $lockedItems = [];

        while (
            $item =
            $itemsResult->fetch_assoc()
        ) {

            $lockedItems[] = $item;
        }

        $itemsStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Make Sure Purchase Has Items
        |--------------------------------------------------------------------------
        */

        if (empty($lockedItems)) {

            throw new Exception(
                "This purchase has no items."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Stock Before Reversal
        |--------------------------------------------------------------------------
        */

        foreach ($lockedItems as $item) {

            $currentStock =
                (float) $item["stock_quantity"];

            $purchaseQuantity =
                (float) $item["quantity"];

            if (
                $currentStock <
                $purchaseQuantity
            ) {

                throw new Exception(
                    "Cannot cancel purchase. " .
                    "Insufficient stock for product: " .
                    $item["product_name"] .
                    ". Current stock: " .
                    number_format(
                        $currentStock,
                        2
                    ) .
                    ", required: " .
                    number_format(
                        $purchaseQuantity,
                        2
                    ) .
                    "."
                );
            }
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
                    stock_quantity - ?,
                updated_at =
                    CURRENT_TIMESTAMP
            WHERE id = ?
              AND status = 'active'
        ");

        if (!$stockStmt) {

            throw new Exception(
                "Unable to prepare stock reversal."
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
                'return',
                ?,
                'purchase_cancellation',
                ?,
                ?
            )
        ");

        if (!$movementStmt) {

            throw new Exception(
                "Unable to prepare stock movement."
            );
        }


        $createdBy =
            (int) ($_SESSION["user_id"] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | Reverse Stock
        |--------------------------------------------------------------------------
        */

        foreach ($lockedItems as $item) {

            $productId =
                (int) $item["product_id"];

            $quantity =
                (float) $item["quantity"];

            /*
            |--------------------------------------------------------------------------
            | Decrease Stock
            |--------------------------------------------------------------------------
            */

            $stockStmt->bind_param(
                "di",
                $quantity,
                $productId
            );

            if (!$stockStmt->execute()) {

                throw new Exception(
                    "Unable to reverse stock for product: " .
                    $item["product_name"] .
                    "."
                );
            }

            if ($stockStmt->affected_rows <= 0) {

                throw new Exception(
                    "Stock reversal failed for product: " .
                    $item["product_name"] .
                    "."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Create Return Movement
            |--------------------------------------------------------------------------
            */

            $negativeQuantity =
                -$quantity;

            $movementNotes =
                "Purchase cancellation " .
                $lockedPurchase["invoice_number"] .
                " - " .
                $item["product_name"];

            $movementStmt->bind_param(
                "iidis",
                $productId,
                $createdBy,
                $negativeQuantity,
                $purchaseId,
                $movementNotes
            );

            if (!$movementStmt->execute()) {

                throw new Exception(
                    "Unable to create stock reversal movement."
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Close Statements
        |--------------------------------------------------------------------------
        */

        $stockStmt->close();
        $movementStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Update Purchase Status
        |--------------------------------------------------------------------------
        */

        $updateStmt = $conn->prepare("
            UPDATE purchases
            SET
                status = 'cancelled'
            WHERE id = ?
              AND status = 'completed'
        ");

        if (!$updateStmt) {

            throw new Exception(
                "Unable to prepare purchase cancellation."
            );
        }

        $updateStmt->bind_param(
            "i",
            $purchaseId
        );

        if (!$updateStmt->execute()) {

            throw new Exception(
                "Unable to cancel purchase."
            );
        }

        if ($updateStmt->affected_rows <= 0) {

            throw new Exception(
                "Purchase could not be cancelled."
            );
        }

        $updateStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $conn->commit();

        $conn->close();


        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        header(
            "Location: view.php?id=" .
            $purchaseId .
            "&success=" .
            urlencode(
                "Purchase cancelled successfully and stock was reversed."
            )
        );

        exit;

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */

        $conn->rollback();

        $error =
            $e->getMessage();
    }
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

                            <i class="bi bi-x-circle"></i>

                        </span>

                        <h3 class="mb-0 fw-bold">
                            Cancel Purchase
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Cancel the purchase and reverse its stock.
                    </p>

                </div>

                <a
                    href="<?= $purchase
                        ? 'view.php?id=' . (int) $purchase["id"]
                        : 'index.php' ?>"
                    class="btn btn-outline-secondary"
                >

                    <i class="bi bi-arrow-left me-1"></i>

                    Back

                </a>

            </div>


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


            <?php if ($purchase): ?>

                <?php if (
                    $purchase["status"] !== "completed"
                ): ?>

                    <div class="alert alert-warning">

                        <i class="bi bi-info-circle me-2"></i>

                        This purchase cannot be cancelled because its
                        current status is
                        <strong>
                            <?= htmlspecialchars(
                                ucfirst(
                                    $purchase["status"]
                                )
                            ) ?>
                        </strong>.

                    </div>

                <?php else: ?>

                    <!-- Warning -->

                    <div class="alert alert-danger">

                        <div class="d-flex gap-3">

                            <i
                                class="bi bi-exclamation-triangle fs-4"
                            ></i>

                            <div>

                                <h5 class="alert-heading fw-bold">
                                    Confirm Purchase Cancellation
                                </h5>

                                <p class="mb-0">

                                    Cancelling this purchase will remove
                                    the purchased quantities from current
                                    inventory and mark the purchase as
                                    cancelled.

                                </p>

                            </div>

                        </div>

                    </div>


                    <!-- Purchase Information -->

                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-header bg-white border-0 p-4">

                            <h5 class="mb-0 fw-bold">
                                Purchase Information
                            </h5>

                        </div>

                        <div class="card-body p-4">

                            <div class="row g-4">

                                <div class="col-12 col-md-4">

                                    <div class="text-muted small">
                                        INVOICE NUMBER
                                    </div>

                                    <div class="fw-bold mt-1">

                                        <?= htmlspecialchars(
                                            $purchase["invoice_number"]
                                        ) ?>

                                    </div>

                                </div>


                                <div class="col-12 col-md-4">

                                    <div class="text-muted small">
                                        SUPPLIER
                                    </div>

                                    <div class="fw-bold mt-1">

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

                                </div>


                                <div class="col-12 col-md-4">

                                    <div class="text-muted small">
                                        TOTAL
                                    </div>

                                    <div
                                        class="fw-bold text-success mt-1"
                                    >

                                        LKR
                                        <?= number_format(
                                            (float) $purchase["total"],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Items -->

                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-header bg-white border-0 p-4">

                            <h5 class="mb-1 fw-bold">
                                Stock To Be Reversed
                            </h5>

                            <small class="text-muted">
                                The following quantities will be removed from inventory.
                            </small>

                        </div>

                        <div class="card-body p-0">

                            <div class="table-responsive">

                                <table class="table align-middle mb-0">

                                    <thead class="table-light">

                                        <tr>

                                            <th class="px-4">
                                                Product
                                            </th>

                                            <th>
                                                SKU
                                            </th>

                                            <th class="text-end">
                                                Purchased
                                            </th>

                                            <th class="text-end">
                                                Current Stock
                                            </th>

                                            <th class="text-end px-4">
                                                Stock After Cancellation
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach (
                                            $items as $item
                                        ): ?>

                                            <?php

                                            $currentStock =
                                                (float) $item[
                                                    "stock_quantity"
                                                ];

                                            $quantity =
                                                (float) $item[
                                                    "quantity"
                                                ];

                                            $stockAfter =
                                                $currentStock -
                                                $quantity;

                                            ?>

                                            <tr>

                                                <td class="px-4">

                                                    <div class="fw-semibold">

                                                        <?= htmlspecialchars(
                                                            $item["product_name"]
                                                        ) ?>

                                                    </div>

                                                </td>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $item["sku"]
                                                    ) ?>

                                                </td>

                                                <td class="text-end">

                                                    <?= number_format(
                                                        $quantity,
                                                        2
                                                    ) ?>

                                                </td>

                                                <td class="text-end">

                                                    <?= number_format(
                                                        $currentStock,
                                                        2
                                                    ) ?>

                                                </td>

                                                <td
                                                    class="text-end px-4 fw-bold"
                                                >

                                                    <?= number_format(
                                                        $stockAfter,
                                                        2
                                                    ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>


                    <!-- Confirmation -->

                    <div class="card border-0 shadow-sm">

                        <div class="card-body p-4">

                            <form
                                method="POST"
                                onsubmit="
                                    return confirm(
                                        'Are you sure you want to cancel this purchase? Stock will be reversed.'
                                    );
                                "
                            >

                                <div
                                    class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
                                >

                                    <div>

                                        <h6 class="fw-bold mb-1">
                                            Final Confirmation
                                        </h6>

                                        <p class="text-muted mb-0">
                                            This action cannot be undone automatically.
                                        </p>

                                    </div>

                                    <div class="d-flex gap-2">

                                        <a
                                            href="view.php?id=<?= (int) $purchase["id"] ?>"
                                            class="btn btn-outline-secondary"
                                        >

                                            Keep Purchase

                                        </a>

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >

                                            <i
                                                class="bi bi-x-circle me-1"
                                            ></i>

                                            Cancel Purchase

                                        </button>

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php

$conn->close();

?>

<?php include "../includes/footer.php"; ?>