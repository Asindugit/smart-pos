<?php


require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";

$pageTitle = "Purchase Details";

$purchaseId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

$success = trim($_GET["success"] ?? "");
$error = "";

$purchase = null;
$items = [];

/*
|--------------------------------------------------------------------------
| Validate ID
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
            p.supplier_id,
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
            s.phone AS supplier_phone,
            s.email AS supplier_email,
            s.address AS supplier_address,

            u.full_name AS created_by_name

        FROM purchases p

        LEFT JOIN suppliers s
            ON s.id = p.supplier_id

        LEFT JOIN users u
            ON u.id = p.created_by

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
                pi.id,
                pi.product_id,
                pi.quantity,
                pi.unit_cost,
                pi.total,

                p.name AS product_name,
                p.sku,
                p.unit

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

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<style>

@media print {

    .navbar,
    .sidebar,
    .no-print {
        display: none !important;
    }

    .main-wrapper {
        margin-left: 0 !important;
    }

    .main-content {
        width: 100% !important;
    }

    .purchase-print-area {
        box-shadow: none !important;
        border: 0 !important;
    }

    body {
        background: #ffffff !important;
    }

}

</style>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- Page Header -->

            <div class="dashboard-header mb-4 no-print">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">

                            <i class="bi bi-receipt"></i>

                        </span>

                        <h3 class="mb-0 fw-bold">
                            Purchase Details
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        View complete purchase information.
                    </p>

                </div>

                <div class="d-flex gap-2">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back

                    </a>

                    <?php if (
                        $purchase &&
                        $purchase["status"] === "completed"
                    ): ?>

                        <a
                            href="cancel.php?id=<?= (int) $purchase["id"] ?>"
                            class="btn btn-outline-danger"
                        >

                            <i class="bi bi-x-circle me-1"></i>

                            Cancel Purchase

                        </a>

                    <?php endif; ?>

                    <?php if ($purchase): ?>

                        <button
                            type="button"
                            class="btn btn-primary"
                            onclick="window.print()"
                        >

                            <i class="bi bi-printer me-1"></i>

                            Print

                        </button>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Success -->

            <?php if ($success !== ""): ?>

                <div
                    class="alert alert-success alert-dismissible fade show no-print"
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


            <?php if ($purchase): ?>

                <!-- Purchase Information -->

                <div class="card border-0 shadow-sm mb-4 purchase-print-area">

                    <div class="card-header bg-white border-0 p-4">

                        <div
                            class="d-flex justify-content-between align-items-start gap-3"
                        >

                            <div>

                                <h4 class="fw-bold mb-2">

                                    <?= htmlspecialchars(
                                        $purchase["invoice_number"]
                                    ) ?>

                                </h4>

                                <div class="text-muted">

                                    Purchase Date:

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $purchase["purchase_date"]
                                            )
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <?php

                            $statusClass =
                                "text-bg-secondary";

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

                            <span
                                class="badge <?= $statusClass ?> fs-6"
                            >

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $purchase["status"]
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <div class="card-body p-4">

                        <div class="row g-4">

                            <!-- Supplier -->

                            <div class="col-12 col-md-6">

                                <div class="border rounded p-3 h-100">

                                    <div class="text-muted small mb-2">
                                        SUPPLIER
                                    </div>

                                    <h6 class="fw-bold mb-1">

                                        <?= htmlspecialchars(
                                            $purchase["supplier_name"]
                                            ?? "Unknown Supplier"
                                        ) ?>

                                    </h6>

                                    <?php if (
                                        !empty(
                                            $purchase["company_name"]
                                        )
                                    ): ?>

                                        <div class="text-muted small">

                                            <?= htmlspecialchars(
                                                $purchase["company_name"]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $purchase["supplier_phone"]
                                        )
                                    ): ?>

                                        <div class="small mt-2">

                                            <i
                                                class="bi bi-telephone me-1"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $purchase["supplier_phone"]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $purchase["supplier_email"]
                                        )
                                    ): ?>

                                        <div class="small">

                                            <i
                                                class="bi bi-envelope me-1"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $purchase["supplier_email"]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <!-- Purchase Details -->

                            <div class="col-12 col-md-6">

                                <div class="border rounded p-3 h-100">

                                    <div class="text-muted small mb-2">
                                        PURCHASE DETAILS
                                    </div>

                                    <div class="d-flex justify-content-between mb-2">

                                        <span class="text-muted">
                                            Invoice
                                        </span>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $purchase["invoice_number"]
                                            ) ?>
                                        </strong>

                                    </div>

                                    <div class="d-flex justify-content-between mb-2">

                                        <span class="text-muted">
                                            Date
                                        </span>

                                        <strong>
                                            <?= htmlspecialchars(
                                                date(
                                                    "d M Y",
                                                    strtotime(
                                                        $purchase["purchase_date"]
                                                    )
                                                )
                                            ) ?>
                                        </strong>

                                    </div>

                                    <div class="d-flex justify-content-between">

                                        <span class="text-muted">
                                            Created By
                                        </span>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $purchase["created_by_name"]
                                                ?? "Unknown"
                                            ) ?>
                                        </strong>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Items -->

                <div class="card border-0 shadow-sm mb-4 purchase-print-area">

                    <div class="card-header bg-white border-0 p-4">

                        <h5 class="mb-0 fw-bold">
                            Purchase Items
                        </h5>

                    </div>

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th class="px-4">
                                            #
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            SKU
                                        </th>

                                        <th class="text-end">
                                            Quantity
                                        </th>

                                        <th class="text-end">
                                            Unit Cost
                                        </th>

                                        <th class="text-end px-4">
                                            Total
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (empty($items)): ?>

                                        <tr>

                                            <td
                                                colspan="6"
                                                class="text-center py-5 text-muted"
                                            >

                                                No purchase items found.

                                            </td>

                                        </tr>

                                    <?php else: ?>

                                        <?php foreach (
                                            $items as $index => $item
                                        ): ?>

                                            <tr>

                                                <td class="px-4">

                                                    <?= $index + 1 ?>

                                                </td>

                                                <td>

                                                    <div class="fw-semibold">

                                                        <?= htmlspecialchars(
                                                            $item["product_name"]
                                                        ) ?>

                                                    </div>

                                                </td>

                                                <td>

                                                    <span
                                                        class="badge bg-light text-dark"
                                                    >

                                                        <?= htmlspecialchars(
                                                            $item["sku"]
                                                        ) ?>

                                                    </span>

                                                </td>

                                                <td class="text-end">

                                                    <?= number_format(
                                                        (float) $item["quantity"],
                                                        2
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $item["unit"]
                                                    ) ?>

                                                </td>

                                                <td class="text-end">

                                                    LKR
                                                    <?= number_format(
                                                        (float) $item["unit_cost"],
                                                        2
                                                    ) ?>

                                                </td>

                                                <td class="text-end px-4">

                                                    <strong>

                                                        LKR
                                                        <?= number_format(
                                                            (float) $item["total"],
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                <!-- Summary -->

                <div class="row justify-content-end">

                    <div class="col-12 col-md-6 col-lg-5">

                        <div class="card border-0 shadow-sm purchase-print-area">

                            <div class="card-header bg-white border-0 p-4">

                                <h5 class="mb-0 fw-bold">
                                    Purchase Summary
                                </h5>

                            </div>

                            <div class="card-body p-4">

                                <div
                                    class="d-flex justify-content-between mb-3"
                                >

                                    <span class="text-muted">
                                        Subtotal
                                    </span>

                                    <strong>

                                        LKR
                                        <?= number_format(
                                            (float) $purchase["subtotal"],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div
                                    class="d-flex justify-content-between mb-3"
                                >

                                    <span class="text-muted">
                                        Discount
                                    </span>

                                    <strong>

                                        LKR
                                        <?= number_format(
                                            (float) $purchase["discount"],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div
                                    class="d-flex justify-content-between mb-3"
                                >

                                    <span class="text-muted">
                                        Tax
                                    </span>

                                    <strong>

                                        LKR
                                        <?= number_format(
                                            (float) $purchase["tax"],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <hr>


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
                                        <?= number_format(
                                            (float) $purchase["total"],
                                            2
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Notes -->

                <?php if (
                    !empty(
                        $purchase["notes"]
                    )
                ): ?>

                    <div class="card border-0 shadow-sm mt-4 purchase-print-area">

                        <div class="card-header bg-white border-0 p-4">

                            <h5 class="mb-0 fw-bold">
                                Notes
                            </h5>

                        </div>

                        <div class="card-body p-4">

                            <?= nl2br(
                                htmlspecialchars(
                                    $purchase["notes"]
                                )
                            ) ?>

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