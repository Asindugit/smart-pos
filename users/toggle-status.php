<?php



/*
|--------------------------------------------------------------------------
| Authentication, Database & CSRF
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| Admin Access Only
|--------------------------------------------------------------------------
*/

requireRole(['admin']);


/*
|--------------------------------------------------------------------------
| Only Allow POST Requests
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
| CSRF Protection
|--------------------------------------------------------------------------
|
| Verify that the request came from a legitimate SmartPOS form.
|
|--------------------------------------------------------------------------
*/

requireCsrfToken();


/*
|--------------------------------------------------------------------------
| Current Logged-in User
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

$currentUserId = (int) (
    $currentUser['id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Get User ID
|--------------------------------------------------------------------------
*/

$userId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Validate User ID
|--------------------------------------------------------------------------
*/

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
| Prevent Changing Own Status
|--------------------------------------------------------------------------
|
| The currently logged-in administrator must not be able
| to deactivate or toggle their own account.
|
|--------------------------------------------------------------------------
*/

if ($userId === $currentUserId) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "You cannot change your own account status."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load User
|--------------------------------------------------------------------------
*/

$loadStmt = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");


if (!$loadStmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to load user information."
        )
    );

    exit;
}


$loadStmt->bind_param(
    "i",
    $userId
);


/*
|--------------------------------------------------------------------------
| Execute User Query
|--------------------------------------------------------------------------
*/

if (!$loadStmt->execute()) {

    $loadStmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to load user information."
        )
    );

    exit;
}


$result =
    $loadStmt->get_result();


$user =
    $result->fetch_assoc();


$loadStmt->close();


/*
|--------------------------------------------------------------------------
| Check User Exists
|--------------------------------------------------------------------------
*/

if (!$user) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("User not found.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Determine New Status
|--------------------------------------------------------------------------
*/

$currentStatus =
    $user['status'];


if ($currentStatus === 'active') {

    $newStatus = 'inactive';

} else {

    $newStatus = 'active';

}


/*
|--------------------------------------------------------------------------
| Protect Last Active Administrator
|--------------------------------------------------------------------------
|
| SmartPOS must always have at least one active administrator.
|
| If the selected user is an active administrator and we are
| attempting to deactivate them, check whether another active
| administrator exists.
|
|--------------------------------------------------------------------------
*/

if (
    $user['role'] === 'admin' &&
    $currentStatus === 'active'
) {


    /*
    |--------------------------------------------------------------------------
    | Count Other Active Administrators
    |--------------------------------------------------------------------------
    */

    $adminStmt = $conn->prepare("
        SELECT COUNT(*) AS active_admins
        FROM users
        WHERE role = 'admin'
          AND status = 'active'
          AND id <> ?
    ");


    if (!$adminStmt) {

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify administrator accounts."
            )
        );

        exit;
    }


    $adminStmt->bind_param(
        "i",
        $userId
    );


    /*
    |--------------------------------------------------------------------------
    | Execute Administrator Check
    |--------------------------------------------------------------------------
    */

    if (!$adminStmt->execute()) {

        $adminStmt->close();
        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "Unable to verify administrator accounts."
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
            $adminData['active_admins'] ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Prevent Deactivating Last Active Admin
    |--------------------------------------------------------------------------
    */

    if ($otherActiveAdmins === 0) {

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode(
                "This user is the only active administrator. " .
                "Another active administrator must exist before " .
                "this account can be deactivated."
            )
        );

        exit;
    }

}


/*
|--------------------------------------------------------------------------
| Update User Status
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare("
    UPDATE users
    SET
        status = ?,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
");


if (!$updateStmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to prepare status update."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

$updateStmt->bind_param(
    "si",
    $newStatus,
    $userId
);


/*
|--------------------------------------------------------------------------
| Execute Update
|--------------------------------------------------------------------------
*/

if ($updateStmt->execute()) {


    /*
    |--------------------------------------------------------------------------
    | Close Statement & Database
    |--------------------------------------------------------------------------
    */

    $updateStmt->close();

    $conn->close();


    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    if ($newStatus === 'active') {

        $message =
            "User '" .
            $user['username'] .
            "' has been activated successfully.";

    } else {

        $message =
            "User '" .
            $user['username'] .
            "' has been deactivated successfully.";

    }


    /*
    |--------------------------------------------------------------------------
    | Redirect With Success
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?success=" .
        urlencode($message)
    );

    exit;


} else {


    /*
    |--------------------------------------------------------------------------
    | Update Failed
    |--------------------------------------------------------------------------
    */

    $updateStmt->close();

    $conn->close();


    header(
        "Location: index.php?error=" .
        urlencode(
            "Failed to update user status."
        )
    );

    exit;

}