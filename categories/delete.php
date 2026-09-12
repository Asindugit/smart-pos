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

    header("Allow: POST");

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>

            <h1>405 - Method Not Allowed</h1>

            <p>
                Category deletion must be performed using a valid form request.
            </p>

            <a href='index.php'>
                Return to Categories
            </a>

        </div>
    ");

}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

requireCsrfToken();


/*
|--------------------------------------------------------------------------
| Get Category ID
|--------------------------------------------------------------------------
*/

$categoryId = isset($_POST["id"])
    ? (int) $_POST["id"]
    : 0;


/*
|--------------------------------------------------------------------------
| Validate Category ID
|--------------------------------------------------------------------------
*/

if ($categoryId <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid category ID.")
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Check Category Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM categories
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
    $categoryId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode("Category not found.")
    );

    exit;

}

$category = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Product Usage
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS product_count
    FROM products
    WHERE category_id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to check category usage.")
    );

    exit;

}

$stmt->bind_param(
    "i",
    $categoryId
);

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$productCount = (int) ($row["product_count"] ?? 0);

$stmt->close();


/*
|--------------------------------------------------------------------------
| Prevent Deletion If Products Exist
|--------------------------------------------------------------------------
*/

if ($productCount > 0) {

    $message =
        "Cannot delete category \"" .
        $category["name"] .
        "\" because it is assigned to " .
        $productCount .
        " product" .
        ($productCount === 1 ? "" : "s") .
        ". Please change the product categor" .
        ($productCount === 1 ? "y" : "ies") .
        " first.";

    header(
        "Location: index.php?error=" .
        urlencode($message)
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM categories
    WHERE id = ?
");

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to delete category.")
    );

    exit;

}

$stmt->bind_param(
    "i",
    $categoryId
);


/*
|--------------------------------------------------------------------------
| Execute Delete
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    if ($stmt->affected_rows === 0) {

        $stmt->close();

        header(
            "Location: index.php?error=" .
            urlencode("Category could not be deleted.")
        );

        exit;

    }

    $stmt->close();

    header(
        "Location: index.php?success=" .
        urlencode(
            "Category \"" .
            $category["name"] .
            "\" deleted successfully."
        )
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Handle Foreign Key Constraint
|--------------------------------------------------------------------------
*/

if ($stmt->errno === 1451) {

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "This category cannot be deleted because it is still being used."
        )
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Generic Database Error
|--------------------------------------------------------------------------
*/

$stmt->close();

header(
    "Location: index.php?error=" .
    urlencode(
        "Failed to delete category. Please try again."
    )
);

exit;

?>