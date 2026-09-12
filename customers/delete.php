<?php

require_once "../config/auth.php";
requireRole(["admin", "manager"]);

require_once "../config/database.php";
require_once "../config/csrf.php";

/*
|--------------------------------------------------------------------------
| Only POST Requests Allowed
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
                Customer deletion must be performed using a POST request.
            </p>

            <a href='index.php'>
                Return to Customers
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
| Validate Customer ID
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
        urlencode("Invalid customer ID.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Customer Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM customers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to process customer.")
    );

    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Customer not found.")
    );

    exit;
}

$customer = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Check Sales History
|--------------------------------------------------------------------------
|
| Customers with sales history should not be deleted.
| This protects important POS transaction history.
|--------------------------------------------------------------------------
*/

$checkStmt = $conn->prepare("
    SELECT COUNT(*) AS sale_count
    FROM sales
    WHERE customer_id = ?
");

if (!$checkStmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to check customer transaction history.")
    );

    exit;
}

$checkStmt->bind_param("i", $id);
$checkStmt->execute();

$checkResult = $checkStmt->get_result();
$usage = $checkResult->fetch_assoc();

$checkStmt->close();

$saleCount = (int) ($usage["sale_count"] ?? 0);

if ($saleCount > 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Customer cannot be deleted because transaction history exists. Deactivate the customer instead."
        )
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Customer
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM customers
    WHERE id = ?
");

if (!$stmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to delete customer.")
    );

    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        $stmt->close();
        $conn->close();

        header(
            "Location: index.php?success=" .
            urlencode("Customer deleted successfully.")
        );

        exit;

    }

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Customer could not be deleted.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Database Error
|--------------------------------------------------------------------------
*/

$errorCode = $conn->errno;

$stmt->close();
$conn->close();

if ($errorCode === 1451) {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Customer cannot be deleted because related transaction records exist. Deactivate the customer instead."
        )
    );

    exit;
}

header(
    "Location: index.php?error=" .
    urlencode("Unable to delete customer. Please try again.")
);

exit;