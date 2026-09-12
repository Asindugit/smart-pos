<?php

require_once "../config/auth.php";
requireRole(['admin']);

require_once "../config/database.php";
require_once "../config/csrf.php";

/*
|--------------------------------------------------------------------------
| Only POST Requests Allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    exit("Method Not Allowed");
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

requireCsrfToken();

/*
|--------------------------------------------------------------------------
| Get Supplier ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Invalid supplier ID.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Supplier Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM suppliers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to process supplier.")
    );

    exit;
}

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Supplier not found.")
    );

    exit;
}

$supplier = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Check Purchase History
|--------------------------------------------------------------------------
*/

$checkStmt = $conn->prepare("
    SELECT COUNT(*) AS purchase_count
    FROM purchases
    WHERE supplier_id = ?
");

if (!$checkStmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to verify supplier records.")
    );

    exit;
}

$checkStmt->bind_param(
    "i",
    $id
);

$checkStmt->execute();

$checkResult = $checkStmt->get_result();

$purchaseData = $checkResult->fetch_assoc();

$checkStmt->close();

$purchaseCount = (int) (
    $purchaseData["purchase_count"] ?? 0
);

/*
|--------------------------------------------------------------------------
| Prevent Delete If Supplier Has Purchase History
|--------------------------------------------------------------------------
*/

if ($purchaseCount > 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "This supplier cannot be deleted because purchase records are linked to it. You can deactivate the supplier instead."
        )
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Supplier
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM suppliers
    WHERE id = ?
");

if (!$stmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to delete supplier.")
    );

    exit;
}

$stmt->bind_param(
    "i",
    $id
);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        $stmt->close();
        $conn->close();

        header(
            "Location: index.php?success=" .
            urlencode("Supplier deleted successfully.")
        );

        exit;
    }

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Supplier was not deleted.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Database Error
|--------------------------------------------------------------------------
*/

if ($stmt->errno === 1451) {

    $errorMessage =
        "This supplier cannot be deleted because related records exist. You can deactivate the supplier instead.";

} else {

    $errorMessage =
        "Unable to delete supplier. Please try again.";
}

$stmt->close();
$conn->close();

header(
    "Location: index.php?error=" .
    urlencode($errorMessage)
);

exit;