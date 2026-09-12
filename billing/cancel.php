<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";


// ------------------------------------------------------------
// Redirect With Message
// ------------------------------------------------------------

function redirectWithMessage($message, $type = "error")
{
    $url =
        "index.php?" .
        $type . "=" .
        urlencode($message);

    header("Location: " . $url);
    exit;
}


// ------------------------------------------------------------
// Only POST Requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    header("Allow: POST");

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>

            <h1>405 - Method Not Allowed</h1>

            <p>
                Bill cancellation must be submitted using POST.
            </p>

            <a href='index.php'>
                Return to Billing
            </a>

        </div>
    ");
}


// ------------------------------------------------------------
// Verify CSRF Token
// ------------------------------------------------------------

if (!verifyCsrfToken()) {

    http_response_code(403);

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>

            <h1>403 - Invalid Request</h1>

            <p>
                Your security token is invalid or expired.
            </p>

            <p>
                Please return to billing and try again.
            </p>

            <a href='index.php'>
                Return to Billing
            </a>

        </div>
    ");
}


// ------------------------------------------------------------
// Get Sale ID
// ------------------------------------------------------------

$saleId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $saleId === false ||
    $saleId === null ||
    $saleId <= 0
) {

    redirectWithMessage(
        "Invalid bill ID."
    );
}


// ------------------------------------------------------------
// Get Current User
// ------------------------------------------------------------

$currentUser = currentUser();

$userId = filter_var(
    $currentUser['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $userId === false ||
    $userId === null ||
    $userId <= 0
) {

    redirectWithMessage(
        "Unable to identify the current user."
    );
}


// ------------------------------------------------------------
// Start Database Transaction
// ------------------------------------------------------------

$conn->begin_transaction();

try {


    // --------------------------------------------------------
    // Get Sale
    // Lock Sale Row
    // --------------------------------------------------------

    $saleStmt = $conn->prepare("
        SELECT
            id,
            invoice_number,
            status,
            total
        FROM sales
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$saleStmt) {

        throw new Exception(
            "Failed to prepare sale query."
        );
    }


    $saleStmt->bind_param(
        "i",
        $saleId
    );


    if (!$saleStmt->execute()) {

        $saleStmt->close();

        throw new Exception(
            "Failed to load bill."
        );
    }


    $saleResult =
        $saleStmt->get_result();


    if ($saleResult->num_rows !== 1) {

        $saleStmt->close();

        throw new Exception(
            "Bill not found."
        );
    }


    $sale =
        $saleResult->fetch_assoc();


    $saleStmt->close();


    // --------------------------------------------------------
    // Check Bill Status
    // --------------------------------------------------------

    if (
        strtolower(
            (string)$sale['status']
        ) !== 'completed'
    ) {

        throw new Exception(
            "This bill cannot be cancelled because it is already " .
            $sale['status'] .
            "."
        );
    }


    // --------------------------------------------------------
    // Get Sale Items
    // Lock Product Rows
    // --------------------------------------------------------

    $itemsStmt = $conn->prepare("
        SELECT
            si.id,
            si.product_id,
            si.quantity,
            si.unit_price,
            si.total,
            p.name AS product_name,
            p.sku,
            p.stock_quantity
        FROM sale_items si

        INNER JOIN products p
            ON p.id = si.product_id

        WHERE si.sale_id = ?

        ORDER BY si.id ASC

        FOR UPDATE
    ");


    if (!$itemsStmt) {

        throw new Exception(
            "Failed to prepare sale items query."
        );
    }


    $itemsStmt->bind_param(
        "i",
        $saleId
    );


    if (!$itemsStmt->execute()) {

        $itemsStmt->close();

        throw new Exception(
            "Failed to load bill items."
        );
    }


    $itemsResult =
        $itemsStmt->get_result();


    if ($itemsResult->num_rows === 0) {

        $itemsStmt->close();

        throw new Exception(
            "This bill has no products and cannot be cancelled."
        );
    }


    $saleItems = [];


    while (
        $item =
        $itemsResult->fetch_assoc()
    ) {


        // ----------------------------------------------------
        // Validate Product ID
        // ----------------------------------------------------

        $productId = filter_var(
            $item['product_id'],
            FILTER_VALIDATE_INT
        );


        if (
            $productId === false ||
            $productId <= 0
        ) {

            $itemsStmt->close();

            throw new Exception(
                "Invalid product information found in the bill."
            );
        }


        // ----------------------------------------------------
        // Validate Quantity
        // ----------------------------------------------------

        if (
            !is_numeric($item['quantity']) ||
            !is_finite(
                (float)$item['quantity']
            )
        ) {

            $itemsStmt->close();

            throw new Exception(
                "Invalid quantity found for product '" .
                $item['product_name'] .
                "'."
            );
        }


        $quantity =
            round(
                (float)$item['quantity'],
                2
            );


        if (
            $quantity <= 0 ||
            $quantity > 999999999
        ) {

            $itemsStmt->close();

            throw new Exception(
                "Invalid quantity found for product '" .
                $item['product_name'] .
                "'."
            );
        }


        // ----------------------------------------------------
        // Validate Current Stock
        // ----------------------------------------------------

        if (
            !is_numeric(
                $item['stock_quantity']
            ) ||
            !is_finite(
                (float)$item['stock_quantity']
            )
        ) {

            $itemsStmt->close();

            throw new Exception(
                "Invalid stock quantity found for product '" .
                $item['product_name'] .
                "'."
            );
        }


        $currentStock =
            round(
                (float)$item['stock_quantity'],
                2
            );


        // ----------------------------------------------------
        // Check DECIMAL(12,2) Limit
        // ----------------------------------------------------

        $newStock =
            $currentStock +
            $quantity;


        if (
            !is_finite($newStock) ||
            $newStock > 9999999999.99
        ) {

            $itemsStmt->close();

            throw new Exception(
                "Stock limit would be exceeded for product '" .
                $item['product_name'] .
                "'."
            );
        }


        // ----------------------------------------------------
        // Store Validated Item
        // ----------------------------------------------------

        $item['product_id'] =
            $productId;

        $item['quantity'] =
            $quantity;

        $item['stock_quantity'] =
            $currentStock;


        $saleItems[] =
            $item;
    }


    $itemsStmt->close();


    // --------------------------------------------------------
    // Prepare Stock Update
    // --------------------------------------------------------

    $stockStmt = $conn->prepare("
        UPDATE products

        SET
            stock_quantity =
                stock_quantity + ?,

            updated_at =
                CURRENT_TIMESTAMP

        WHERE id = ?
    ");


    if (!$stockStmt) {

        throw new Exception(
            "Failed to prepare stock update query."
        );
    }


    // --------------------------------------------------------
    // Prepare Stock Movement
    // --------------------------------------------------------

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
            'sale_cancellation',
            ?,
            ?
        )
    ");


    if (!$movementStmt) {

        $stockStmt->close();

        throw new Exception(
            "Failed to prepare stock movement query."
        );
    }


    // --------------------------------------------------------
    // Restore Stock
    // --------------------------------------------------------

    foreach ($saleItems as $item) {


        $productId =
            (int)$item['product_id'];


        $quantity =
            (float)$item['quantity'];


        $productName =
            $item['product_name'];


        // ----------------------------------------------------
        // Restore Product Stock
        // ----------------------------------------------------

        $stockStmt->bind_param(
            "di",
            $quantity,
            $productId
        );


        if (!$stockStmt->execute()) {

            throw new Exception(
                "Failed to restore stock for product '" .
                $productName .
                "'."
            );
        }


        // ----------------------------------------------------
        // Verify Stock Update
        // ----------------------------------------------------

        if (
            $stockStmt->affected_rows !== 1
        ) {

            throw new Exception(
                "Stock could not be restored for product '" .
                $productName .
                "'."
            );
        }


        // ----------------------------------------------------
        // Stock Movement
        // ----------------------------------------------------

        $movementNotes =
            "Stock restored after cancelling sale " .
            $sale['invoice_number'] .
            " - " .
            $productName;


        $movementQuantity =
            $quantity;


        $movementStmt->bind_param(
            "iidis",
            $productId,
            $userId,
            $movementQuantity,
            $saleId,
            $movementNotes
        );


        if (!$movementStmt->execute()) {

            throw new Exception(
                "Failed to create stock movement."
            );
        }


        if (
            $movementStmt->affected_rows !== 1
        ) {

            throw new Exception(
                "Stock movement could not be recorded."
            );
        }
    }


    $stockStmt->close();

    $movementStmt->close();


    // --------------------------------------------------------
    // Change Sale Status
    // --------------------------------------------------------

    $updateSaleStmt = $conn->prepare("
        UPDATE sales

        SET
            status = 'cancelled'

        WHERE id = ?

        AND status = 'completed'
    ");


    if (!$updateSaleStmt) {

        throw new Exception(
            "Failed to prepare bill cancellation query."
        );
    }


    $updateSaleStmt->bind_param(
        "i",
        $saleId
    );


    if (!$updateSaleStmt->execute()) {

        $updateSaleStmt->close();

        throw new Exception(
            "Failed to cancel the bill."
        );
    }


    // --------------------------------------------------------
    // Verify Sale Was Updated
    // --------------------------------------------------------

    if (
        $updateSaleStmt->affected_rows !== 1
    ) {

        $updateSaleStmt->close();

        throw new Exception(
            "Bill could not be cancelled. " .
            "It may have already been cancelled."
        );
    }


    $updateSaleStmt->close();


    // --------------------------------------------------------
    // Commit
    // --------------------------------------------------------

    $conn->commit();


    // --------------------------------------------------------
    // Close Connection
    // --------------------------------------------------------

    $conn->close();


    // --------------------------------------------------------
    // Return To Billing
    // --------------------------------------------------------

    header(
        "Location: index.php?success=" .
        urlencode(
            "Bill " .
            $sale['invoice_number'] .
            " cancelled successfully and stock restored."
        )
    );

    exit;


} catch (Throwable $e) {


    // --------------------------------------------------------
    // Rollback
    // --------------------------------------------------------

    try {

        $conn->rollback();

    } catch (Throwable $rollbackError) {

        // Ignore rollback errors.
    }


    // --------------------------------------------------------
    // Log Technical Error
    // --------------------------------------------------------

    error_log(
        "SmartPOS Bill Cancellation Error - " .
        "Sale ID: " .
        $saleId .
        " - User ID: " .
        $userId .
        " - Error: " .
        $e->getMessage()
    );


    // --------------------------------------------------------
    // Close Connection
    // --------------------------------------------------------

    $conn->close();


    // --------------------------------------------------------
    // User-Friendly Error
    // --------------------------------------------------------

    redirectWithMessage(
        "The bill could not be cancelled. " .
        "No changes were saved. Please try again."
    );
}

?>