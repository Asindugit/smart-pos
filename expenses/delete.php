<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

/*
|--------------------------------------------------------------------------
| Only POST Requests Allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    header('Allow: POST');

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>

            <h1>405 - Method Not Allowed</h1>

            <p>
                This action only accepts POST requests.
            </p>

            <a href='index.php'>
                Return to Expenses
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
| Validate Expense ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Invalid expense ID.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Expense
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Check Expense Exists
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id, title
        FROM expenses
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare expense lookup."
        );
    }

    $stmt->bind_param(
        "i",
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Failed to check expense."
        );
    }

    $result = $stmt->get_result();

    $expense = $result->fetch_assoc();

    $stmt->close();

    if (!$expense) {

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode("Expense not found.")
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM expenses
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare expense deletion."
        );
    }

    $stmt->bind_param(
        "i",
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Failed to delete expense."
        );
    }

    if ($stmt->affected_rows !== 1) {

        $stmt->close();

        throw new Exception(
            "Expense was not deleted."
        );
    }

    $stmt->close();

    $conn->close();

    header(
        "Location: index.php?success=" .
        urlencode(
            "Expense deleted successfully."
        )
    );

    exit;

} catch (Throwable $e) {

    error_log(
        "SmartPOS Expense Delete Error: " .
        $e->getMessage()
    );

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to delete the expense. Please try again."
        )
    );

    exit;
}