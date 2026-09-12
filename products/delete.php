<?php

require_once "../config/auth.php";
requireRole(['admin']);

require_once "../config/database.php";
require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| Only POST Requests Are Allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

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
                This action must be submitted using POST.
            </p>

            <a href='/smart-pos/products/index.php'>
                Return to Products
            </a>
        </div>
    ");
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

requireCsrfToken();


/*
|--------------------------------------------------------------------------
| Get Product ID
|--------------------------------------------------------------------------
*/

$productId = isset($_POST["id"])
    ? (int) $_POST["id"]
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
| Load Product
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        sku
    FROM products
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to process the request.")
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
        urlencode("Unable to process the request.")
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
| Check Sales Usage
|--------------------------------------------------------------------------
|
| Products used in sales must never be deleted because
| historical sales records need to remain intact.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS sale_count
    FROM sale_items
    WHERE product_id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to check product usage.")
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
        urlencode("Unable to check product usage.")
    );

    exit;
}


$result = $stmt->get_result();

$row = $result->fetch_assoc();

$saleCount = (int) (
    $row["sale_count"] ?? 0
);

$stmt->close();


if ($saleCount > 0) {

    $message =
        "Cannot delete product \"" .
        $product["name"] .
        "\" because it is already used in " .
        $saleCount .
        " sale record" .
        ($saleCount === 1 ? "" : "s") .
        ". Please set the product to inactive instead.";

    header(
        "Location: index.php?error=" .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Purchase Usage
|--------------------------------------------------------------------------
|
| Products used in purchases must not be deleted because
| historical purchase records need to remain intact.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS purchase_count
    FROM purchase_items
    WHERE product_id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to check purchase usage.")
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
        urlencode("Unable to check purchase usage.")
    );

    exit;
}


$result = $stmt->get_result();

$row = $result->fetch_assoc();

$purchaseCount = (int) (
    $row["purchase_count"] ?? 0
);

$stmt->close();


if ($purchaseCount > 0) {

    $message =
        "Cannot delete product \"" .
        $product["name"] .
        "\" because it is already used in " .
        $purchaseCount .
        " purchase record" .
        ($purchaseCount === 1 ? "" : "s") .
        ". Please set the product to inactive instead.";

    header(
        "Location: index.php?error=" .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Stock Movement Usage
|--------------------------------------------------------------------------
|
| Stock movements are part of the inventory audit trail.
| They must not be deleted with the product.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS movement_count
    FROM stock_movements
    WHERE product_id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to check inventory history.")
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
        urlencode("Unable to check inventory history.")
    );

    exit;
}


$result = $stmt->get_result();

$row = $result->fetch_assoc();

$movementCount = (int) (
    $row["movement_count"] ?? 0
);

$stmt->close();


if ($movementCount > 0) {

    $message =
        "Cannot delete product \"" .
        $product["name"] .
        "\" because it has " .
        $movementCount .
        " inventory movement record" .
        ($movementCount === 1 ? "" : "s") .
        ". Please set the product to inactive instead.";

    header(
        "Location: index.php?error=" .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Product
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM products
    WHERE id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to delete product.")
    );

    exit;
}


$stmt->bind_param(
    "i",
    $productId
);


if ($stmt->execute()) {

    /*
    |--------------------------------------------------------------------------
    | Confirm Actual Deletion
    |--------------------------------------------------------------------------
    */

    if ($stmt->affected_rows === 1) {

        $stmt->close();

        header(
            "Location: index.php?success=" .
            urlencode(
                "Product \"" .
                $product["name"] .
                "\" deleted successfully."
            )
        );

        exit;
    }

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode("Product could not be deleted.")
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Database Error Handling
|--------------------------------------------------------------------------
*/

$errorCode = $stmt->errno;

$stmt->close();


/*
|--------------------------------------------------------------------------
| Foreign Key Protection
|--------------------------------------------------------------------------
*/

if ($errorCode === 1451) {

    header(
        "Location: index.php?error=" .
        urlencode(
            "This product is linked to existing records and cannot be deleted. Please set it to inactive instead."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Generic Error
|--------------------------------------------------------------------------
*/

header(
    "Location: index.php?error=" .
    urlencode(
        "Failed to delete product. Please try again."
    )
);

exit;