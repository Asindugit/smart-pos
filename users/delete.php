<?php

/*
|--------------------------------------------------------------------------
| SmartPOS - Delete User
|--------------------------------------------------------------------------
| Admin only
| POST only
| CSRF protected
| Prevent self deletion
| Prevent deletion of the only active admin
| Preserve historical transaction accountability
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

requireRole(['admin']);


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Invalid request method.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

requireCsrfToken();


/*
|--------------------------------------------------------------------------
| GET USER ID
|--------------------------------------------------------------------------
*/

$userId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$userId || $userId <= 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Invalid user ID.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

$currentUserId = (int) (
    $currentUser['id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| PREVENT SELF DELETE
|--------------------------------------------------------------------------
*/

if ($userId === $currentUserId) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("You cannot delete your own account.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD USER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    error_log(
        "SmartPOS delete user prepare failed: " .
        $conn->error
    );

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to prepare the delete operation.")
    );

    exit;
}


$stmt->bind_param(
    "i",
    $userId
);


if (!$stmt->execute()) {

    error_log(
        "SmartPOS delete user lookup failed: " .
        $stmt->error
    );

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to load the user information.")
    );

    exit;
}


$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$user) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("User account not found.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PROTECT ONLY ACTIVE ADMIN
|--------------------------------------------------------------------------
*/

if (
    $user['role'] === 'admin' &&
    $user['status'] === 'active'
) {

    $adminStmt = $conn->prepare("
        SELECT COUNT(*) AS admin_count
        FROM users
        WHERE role = 'admin'
          AND status = 'active'
          AND id <> ?
    ");

    if (!$adminStmt) {

        error_log(
            "SmartPOS admin protection prepare failed: " .
            $conn->error
        );

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify administrator protection."
            )
        );

        exit;
    }


    $adminStmt->bind_param(
        "i",
        $userId
    );


    if (!$adminStmt->execute()) {

        error_log(
            "SmartPOS admin protection query failed: " .
            $adminStmt->error
        );

        $adminStmt->close();
        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify administrator protection."
            )
        );

        exit;
    }


    $adminResult =
        $adminStmt->get_result();

    $adminData =
        $adminResult->fetch_assoc();

    $adminStmt->close();


    $otherActiveAdmins =
        (int) (
            $adminData['admin_count'] ?? 0
        );


    if ($otherActiveAdmins === 0) {

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "This administrator cannot be deleted because " .
                "they are the only active administrator. " .
                "Add another active administrator first, or " .
                "deactivate this account instead."
            )
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CHECK HISTORICAL RECORDS
|--------------------------------------------------------------------------
|
| Instead of waiting for MySQL foreign-key error 1451,
| check whether the user is referenced by existing records.
|
|--------------------------------------------------------------------------
*/

$historyChecks = [

    'sales' => "
        SELECT COUNT(*) AS total
        FROM sales
        WHERE cashier_id = ?
    ",

    'purchases' => "
        SELECT COUNT(*) AS total
        FROM purchases
        WHERE created_by = ?
    ",

    'stock_movements' => "
        SELECT COUNT(*) AS total
        FROM stock_movements
        WHERE user_id = ?
    ",

    'expenses' => "
        SELECT COUNT(*) AS total
        FROM expenses
        WHERE created_by = ?
    "
];


$hasHistory = false;


foreach ($historyChecks as $table => $sql) {

    $historyStmt = $conn->prepare($sql);

    if (!$historyStmt) {

        error_log(
            "SmartPOS user history check failed for " .
            $table .
            ": " .
            $conn->error
        );

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify user history."
            )
        );

        exit;
    }


    $historyStmt->bind_param(
        "i",
        $userId
    );


    if (!$historyStmt->execute()) {

        error_log(
            "SmartPOS user history query failed for " .
            $table .
            ": " .
            $historyStmt->error
        );

        $historyStmt->close();
        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify user history."
            )
        );

        exit;
    }


    $historyResult =
        $historyStmt->get_result();

    $historyData =
        $historyResult->fetch_assoc();

    $historyStmt->close();


    $count =
        (int) (
            $historyData['total'] ?? 0
        );


    if ($count > 0) {

        $hasHistory = true;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| DO NOT DELETE USERS WITH HISTORY
|--------------------------------------------------------------------------
|
| Historical records should remain linked to the original user.
|
| Deactivate instead.
|--------------------------------------------------------------------------
*/

if ($hasHistory) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "This user cannot be permanently deleted because " .
            "they are linked to existing transaction or history records. " .
            "Deactivate the user account instead."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/

$deleteStmt = $conn->prepare("
    DELETE FROM users
    WHERE id = ?
");

if (!$deleteStmt) {

    error_log(
        "SmartPOS delete user prepare failed: " .
        $conn->error
    );

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to prepare the delete operation."
        )
    );

    exit;
}


$deleteStmt->bind_param(
    "i",
    $userId
);


/*
|--------------------------------------------------------------------------
| EXECUTE DELETE
|--------------------------------------------------------------------------
*/

if (!$deleteStmt->execute()) {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | Get the error from the prepared statement.
    |--------------------------------------------------------------------------
    */

    $errorNumber =
        $deleteStmt->errno;

    $errorMessage =
        $deleteStmt->error;


    error_log(
        "SmartPOS user deletion failed. " .
        "User ID: " .
        $userId .
        " | Error: " .
        $errorNumber .
        " | Message: " .
        $errorMessage
    );


    $deleteStmt->close();
    $conn->close();


    /*
    |--------------------------------------------------------------------------
    | FOREIGN KEY ERROR
    |--------------------------------------------------------------------------
    */

    if ($errorNumber === 1451) {

        header(
            "Location: index.php?error=" .
            urlencode(
                "This user cannot be deleted because they are linked " .
                "to existing transaction or history records. " .
                "Deactivate the user account instead."
            )
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | GENERAL ERROR
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?error=" .
        urlencode(
            "The user could not be deleted because a database error occurred."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK AFFECTED ROWS
|--------------------------------------------------------------------------
*/

if ($deleteStmt->affected_rows !== 1) {

    $deleteStmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("User could not be deleted.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

$username =
    $user['username'];


/*
|--------------------------------------------------------------------------
| CLOSE
|--------------------------------------------------------------------------
*/

$deleteStmt->close();
$conn->close();


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

$message =
    "User '" .
    $username .
    "' was deleted successfully.";


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    "Location: index.php?success=" .
    urlencode($message)
);

exit;

?>