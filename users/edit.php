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
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = "Edit User";


/*
|--------------------------------------------------------------------------
| Current Logged-in User
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

$currentUserId = (int) ($currentUser['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get User ID
|--------------------------------------------------------------------------
*/

$userId = filter_input(
    INPUT_GET,
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
| Load User
|--------------------------------------------------------------------------
|
| The existing password hash is NOT selected because it is not required
| for editing. If a new password is entered, a new hash is generated.
|
|--------------------------------------------------------------------------
*/

$loadStmt = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        role,
        status,
        created_at,
        updated_at
    FROM users
    WHERE id = ?
    LIMIT 1
");


if (!$loadStmt) {

    $conn->close();

    die("Unable to prepare user query.");

}


$loadStmt->bind_param(
    "i",
    $userId
);


if (!$loadStmt->execute()) {

    $loadStmt->close();
    $conn->close();

    die("Unable to load user.");

}


$result = $loadStmt->get_result();

$user = $result->fetch_assoc();

$loadStmt->close();


/*
|--------------------------------------------------------------------------
| User Not Found
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
| Form Variables
|--------------------------------------------------------------------------
*/

$fullName = $user['full_name'];

$username = $user['username'];

$email = $user['email'] ?? '';

$role = $user['role'];

$status = $user['status'];

$errors = [];


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'admin',
    'manager',
    'cashier'
];

$allowedStatuses = [
    'active',
    'inactive'
];


/*
|--------------------------------------------------------------------------
| Is Current User?
|--------------------------------------------------------------------------
*/

$isCurrentUser = (
    (int) $user['id'] === $currentUserId
);


/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | This prevents unauthorized websites from submitting forms on behalf
    | of an authenticated administrator.
    |
    |--------------------------------------------------------------------------
    */

    requireCsrfToken();


    /*
    |--------------------------------------------------------------------------
    | Get Form Data
    |--------------------------------------------------------------------------
    */

    $fullName = trim(
        $_POST['full_name'] ?? ''
    );

    $username = trim(
        $_POST['username'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password = $_POST['password'] ?? '';

    $confirmPassword = $_POST['confirm_password'] ?? '';

    $role = trim(
        $_POST['role'] ?? ''
    );

    $status = trim(
        $_POST['status'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Full Name
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $errors[] =
            "Full name is required.";

    } elseif (mb_strlen($fullName) > 100) {

        $errors[] =
            "Full name cannot exceed 100 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Username
    |--------------------------------------------------------------------------
    */

    if ($username === '') {

        $errors[] =
            "Username is required.";

    } elseif (mb_strlen($username) > 50) {

        $errors[] =
            "Username cannot exceed 50 characters.";

    } elseif (!preg_match(
        '/^[A-Za-z0-9._-]+$/',
        $username
    )) {

        $errors[] =
            "Username can contain only letters, numbers, dots, underscores and hyphens.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if ($email !== '') {

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {

            $errors[] =
                "Please enter a valid email address.";

        } elseif (mb_strlen($email) > 150) {

            $errors[] =
                "Email cannot exceed 150 characters.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Role
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $role,
        $allowedRoles,
        true
    )) {

        $errors[] =
            "Invalid user role selected.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {

        $errors[] =
            "Invalid user status selected.";

    }


    /*
    |--------------------------------------------------------------------------
    | Protect Current Administrator Account
    |--------------------------------------------------------------------------
    |
    | The currently logged-in administrator cannot:
    |
    | 1. Deactivate their own account.
    | 2. Change their own role from admin.
    |
    |--------------------------------------------------------------------------
    */

    if ($isCurrentUser) {


        if ($status !== 'active') {

            $errors[] =
                "You cannot deactivate your own account.";

        }


        if ($role !== 'admin') {

            $errors[] =
                "You cannot change your own role from admin.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Validate New Password
    |--------------------------------------------------------------------------
    |
    | Password is optional during editing.
    |
    | Empty password:
    |     Existing password remains unchanged.
    |
    | New password:
    |     Must satisfy the SmartPOS password policy.
    |
    |--------------------------------------------------------------------------
    */

    if ($password !== '') {


        /*
        |--------------------------------------------------------------------------
        | Minimum Length
        |--------------------------------------------------------------------------
        */

        if (strlen($password) < 8) {

            $errors[] =
                "New password must contain at least 8 characters.";

        }


        /*
        |--------------------------------------------------------------------------
        | Uppercase Character
        |--------------------------------------------------------------------------
        */

        if (!preg_match(
            '/[A-Z]/',
            $password
        )) {

            $errors[] =
                "New password must contain at least one uppercase letter.";

        }


        /*
        |--------------------------------------------------------------------------
        | Lowercase Character
        |--------------------------------------------------------------------------
        */

        if (!preg_match(
            '/[a-z]/',
            $password
        )) {

            $errors[] =
                "New password must contain at least one lowercase letter.";

        }


        /*
        |--------------------------------------------------------------------------
        | Number
        |--------------------------------------------------------------------------
        */

        if (!preg_match(
            '/[0-9]/',
            $password
        )) {

            $errors[] =
                "New password must contain at least one number.";

        }


        /*
        |--------------------------------------------------------------------------
        | Special Character
        |--------------------------------------------------------------------------
        */

        if (!preg_match(
            '/[^A-Za-z0-9]/',
            $password
        )) {

            $errors[] =
                "New password must contain at least one special character.";

        }


        /*
        |--------------------------------------------------------------------------
        | Confirm Password
        |--------------------------------------------------------------------------
        */

        if ($password !== $confirmPassword) {

            $errors[] =
                "New password and confirm password do not match.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Check Username / Email Uniqueness
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {


        $checkStmt = $conn->prepare("
            SELECT
                id,
                username,
                email
            FROM users
            WHERE id <> ?
              AND (
                    username = ?
                    OR (
                        email IS NOT NULL
                        AND email <> ''
                        AND email = ?
                    )
              )
            LIMIT 1
        ");


        if (!$checkStmt) {

            $errors[] =
                "Unable to validate user information.";

        } else {


            $checkStmt->bind_param(
                "iss",
                $userId,
                $username,
                $email
            );


            if (!$checkStmt->execute()) {

                $errors[] =
                    "Unable to check existing user information.";

            } else {


                $checkResult =
                    $checkStmt->get_result();


                $existingUser =
                    $checkResult->fetch_assoc();


                if ($existingUser) {


                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Username
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset($existingUser['username']) &&
                        $existingUser['username'] === $username
                    ) {

                        $errors[] =
                            "This username is already in use.";

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Email
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $email !== '' &&
                        isset($existingUser['email']) &&
                        $existingUser['email'] === $email
                    ) {

                        $errors[] =
                            "This email address is already in use.";

                    }

                }

            }


            $checkStmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Update User
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {


        /*
        |--------------------------------------------------------------------------
        | Empty Email
        |--------------------------------------------------------------------------
        |
        | Store empty email values as NULL instead of an empty string.
        |
        |--------------------------------------------------------------------------
        */

        $emailValue = (
            $email !== ''
                ? $email
                : null
        );


        /*
        |--------------------------------------------------------------------------
        | Update With New Password
        |--------------------------------------------------------------------------
        */

        if ($password !== '') {


            /*
            |--------------------------------------------------------------------------
            | Generate Secure Password Hash
            |--------------------------------------------------------------------------
            */

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            if ($hashedPassword === false) {

                $errors[] =
                    "Unable to securely create the new password.";

            } else {


                $updateStmt = $conn->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        email = ?,
                        password = ?,
                        role = ?,
                        status = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");


                if (!$updateStmt) {

                    $errors[] =
                        "Unable to prepare user update.";

                } else {


                    $updateStmt->bind_param(
                        "ssssssi",
                        $fullName,
                        $username,
                        $emailValue,
                        $hashedPassword,
                        $role,
                        $status,
                        $userId
                    );


                    if (!$updateStmt->execute()) {


                        if ($updateStmt->errno === 1062) {

                            $errors[] =
                                "Username or email already exists.";

                        } else {

                            $errors[] =
                                "Failed to update user.";

                        }

                    }


                    $updateStmt->close();

                }

            }


        } else {


            /*
            |--------------------------------------------------------------------------
            | Update Without Password
            |--------------------------------------------------------------------------
            |
            | The existing password remains unchanged.
            |
            |--------------------------------------------------------------------------
            */

            $updateStmt = $conn->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    username = ?,
                    email = ?,
                    role = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");


            if (!$updateStmt) {

                $errors[] =
                    "Unable to prepare user update.";

            } else {


                $updateStmt->bind_param(
                    "sssssi",
                    $fullName,
                    $username,
                    $emailValue,
                    $role,
                    $status,
                    $userId
                );


                if (!$updateStmt->execute()) {


                    if ($updateStmt->errno === 1062) {

                        $errors[] =
                            "Username or email already exists.";

                    } else {

                        $errors[] =
                            "Failed to update user.";

                    }

                }


                $updateStmt->close();

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Successful Update
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {


            /*
            |--------------------------------------------------------------------------
            | Update Current Session
            |--------------------------------------------------------------------------
            |
            | If the administrator edited their own account, immediately
            | update the session values used by the navbar and authorization.
            |
            |--------------------------------------------------------------------------
            */

            if ($isCurrentUser) {

                $_SESSION['user_name'] =
                    $fullName;

                $_SESSION['username'] =
                    $username;

                $_SESSION['user_role'] =
                    $role;

            }


            /*
            |--------------------------------------------------------------------------
            | Close Database
            |--------------------------------------------------------------------------
            */

            $conn->close();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                "Location: index.php?success=" .
                urlencode("User updated successfully.")
            );

            exit;

        }

    }

}


/*
|--------------------------------------------------------------------------
| Include Header
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>


<?php include "../includes/navbar.php"; ?>


<div class="main-wrapper">


    <?php include "../includes/sidebar.php"; ?>


    <main class="main-content">


        <div class="container-fluid py-4">


            <!-- =========================================================
                 PAGE HEADER
                 ========================================================= -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >


                <div>

                    <h1 class="h3 fw-bold mb-1">

                        <i class="bi bi-person-gear me-2"></i>

                        Edit User

                    </h1>


                    <p class="text-muted mb-0">

                        Update the SmartPOS user account information.

                    </p>

                </div>


                <div>

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back to Users

                    </a>

                </div>


            </div>


            <!-- =========================================================
                 ERROR ALERT
                 ========================================================= -->

            <?php if (!empty($errors)): ?>


                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >


                    <div class="d-flex gap-3">


                        <div class="fs-4">

                            <i class="bi bi-exclamation-triangle"></i>

                        </div>


                        <div>

                            <h6 class="fw-bold mb-2">

                                Please correct the following:

                            </h6>


                            <ul class="mb-0 ps-3">


                                <?php foreach ($errors as $error): ?>

                                    <li>

                                        <?= htmlspecialchars(
                                            $error,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </li>

                                <?php endforeach; ?>


                            </ul>

                        </div>


                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>


                </div>


            <?php endif; ?>


            <!-- =========================================================
                 CURRENT USER NOTICE
                 ========================================================= -->

            <?php if ($isCurrentUser): ?>


                <div class="alert alert-warning">


                    <div class="d-flex gap-3">


                        <div class="fs-4">

                            <i class="bi bi-shield-exclamation"></i>

                        </div>


                        <div>

                            <h6 class="fw-bold mb-1">

                                You are editing your own account

                            </h6>


                            <p class="mb-0">

                                For security, your account must remain
                                <strong>Active</strong> and your role must
                                remain <strong>Admin</strong>.

                            </p>

                        </div>


                    </div>


                </div>


            <?php endif; ?>


            <!-- =========================================================
                 EDIT USER FORM
                 ========================================================= -->

            <div class="row justify-content-center">


                <div class="col-12 col-xl-9">


                    <div class="card border-0 shadow-sm">


                        <!-- =================================================
                             CARD HEADER
                             ================================================= -->

                        <div class="card-header bg-white border-0 py-3">


                            <div class="d-flex align-items-center">


                                <div
                                    class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-3"
                                    style="width: 45px; height: 45px;"
                                >

                                    <i class="bi bi-person-gear fs-5"></i>

                                </div>


                                <div>

                                    <h5 class="fw-bold mb-1">

                                        User Account Details

                                    </h5>


                                    <small class="text-muted">

                                        User ID:

                                        <strong>
                                            #<?= (int) $user['id'] ?>
                                        </strong>

                                    </small>

                                </div>


                            </div>


                        </div>


                        <!-- =================================================
                             CARD BODY
                             ================================================= -->

                        <div class="card-body p-4">


                            <form
                                method="POST"
                                action="edit.php?id=<?= (int) $userId ?>"
                                autocomplete="off"
                            >


                                <!-- =================================================
                                     CSRF TOKEN
                                     ================================================= -->

                                <?= csrfField() ?>


                                <!-- =================================================
                                     BASIC INFORMATION
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-person me-2 text-primary"></i>

                                    Basic Information

                                </h6>


                                <div class="row g-4 mb-4">


                                    <!-- =================================================
                                         FULL NAME
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="full_name"
                                            class="form-label fw-semibold"
                                        >

                                            Full Name

                                            <span class="text-danger">*</span>

                                        </label>


                                        <input
                                            type="text"
                                            class="form-control"
                                            id="full_name"
                                            name="full_name"
                                            maxlength="100"
                                            value="<?= htmlspecialchars(
                                                $fullName,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            placeholder="Enter full name"
                                            required
                                        >


                                    </div>


                                    <!-- =================================================
                                         USERNAME
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="username"
                                            class="form-label fw-semibold"
                                        >

                                            Username

                                            <span class="text-danger">*</span>

                                        </label>


                                        <div class="input-group">


                                            <span class="input-group-text">

                                                <i class="bi bi-at"></i>

                                            </span>


                                            <input
                                                type="text"
                                                class="form-control"
                                                id="username"
                                                name="username"
                                                maxlength="50"
                                                value="<?= htmlspecialchars(
                                                    $username,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                placeholder="Enter username"
                                                required
                                            >


                                        </div>


                                        <div class="form-text">

                                            Letters, numbers, dots,
                                            underscores and hyphens only.

                                        </div>


                                    </div>


                                    <!-- =================================================
                                         EMAIL
                                         ================================================= -->

                                    <div class="col-12">


                                        <label
                                            for="email"
                                            class="form-label fw-semibold"
                                        >

                                            Email Address

                                            <span class="text-muted fw-normal">
                                                (Optional)
                                            </span>

                                        </label>


                                        <div class="input-group">


                                            <span class="input-group-text">

                                                <i class="bi bi-envelope"></i>

                                            </span>


                                            <input
                                                type="email"
                                                class="form-control"
                                                id="email"
                                                name="email"
                                                maxlength="150"
                                                value="<?= htmlspecialchars(
                                                    $email,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                placeholder="user@example.com"
                                            >


                                        </div>


                                    </div>


                                </div>


                                <hr class="my-4">


                                <!-- =================================================
                                     PASSWORD
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-shield-lock me-2 text-primary"></i>

                                    Change Password

                                </h6>


                                <div class="alert alert-light border mb-4">


                                    <i class="bi bi-info-circle me-2"></i>

                                    Leave the password fields empty if you
                                    do not want to change the current password.

                                </div>


                                <div class="row g-4 mb-4">


                                    <!-- =================================================
                                         NEW PASSWORD
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="password"
                                            class="form-label fw-semibold"
                                        >

                                            New Password

                                        </label>


                                        <div class="input-group">


                                            <span class="input-group-text">

                                                <i class="bi bi-lock"></i>

                                            </span>


                                            <input
                                                type="password"
                                                class="form-control"
                                                id="password"
                                                name="password"
                                                minlength="8"
                                                placeholder="Leave blank to keep current"
                                                autocomplete="new-password"
                                            >


                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                id="togglePassword"
                                                title="Show password"
                                            >

                                                <i class="bi bi-eye"></i>

                                            </button>


                                        </div>


                                        <div class="form-text">

                                            Minimum 8 characters with uppercase,
                                            lowercase, number and special character.

                                        </div>


                                    </div>


                                    <!-- =================================================
                                         CONFIRM PASSWORD
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="confirm_password"
                                            class="form-label fw-semibold"
                                        >

                                            Confirm New Password

                                        </label>


                                        <div class="input-group">


                                            <span class="input-group-text">

                                                <i class="bi bi-lock-fill"></i>

                                            </span>


                                            <input
                                                type="password"
                                                class="form-control"
                                                id="confirm_password"
                                                name="confirm_password"
                                                minlength="8"
                                                placeholder="Confirm new password"
                                                autocomplete="new-password"
                                            >


                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                id="toggleConfirmPassword"
                                                title="Show password"
                                            >

                                                <i class="bi bi-eye"></i>

                                            </button>


                                        </div>


                                    </div>


                                </div>


                                <hr class="my-4">


                                <!-- =================================================
                                     ACCESS & STATUS
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-person-badge me-2 text-primary"></i>

                                    Access & Status

                                </h6>


                                <div class="row g-4 mb-4">


                                    <!-- =================================================
                                         ROLE
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="role"
                                            class="form-label fw-semibold"
                                        >

                                            User Role

                                            <span class="text-danger">*</span>

                                        </label>


                                        <select
                                            class="form-select"
                                            id="role"
                                            name="role"
                                            <?= $isCurrentUser ? 'disabled' : '' ?>
                                            required
                                        >


                                            <option
                                                value="admin"
                                                <?= $role === 'admin' ? 'selected' : '' ?>
                                            >

                                                Admin

                                            </option>


                                            <option
                                                value="manager"
                                                <?= $role === 'manager' ? 'selected' : '' ?>
                                            >

                                                Manager

                                            </option>


                                            <option
                                                value="cashier"
                                                <?= $role === 'cashier' ? 'selected' : '' ?>
                                            >

                                                Cashier

                                            </option>


                                        </select>


                                        <?php if ($isCurrentUser): ?>


                                            <!--
                                            Disabled fields are not submitted.
                                            Preserve the admin role with a hidden
                                            field.
                                            -->

                                            <input
                                                type="hidden"
                                                name="role"
                                                value="admin"
                                            >


                                        <?php endif; ?>


                                        <div class="form-text">

                                            Determines the user's access level.

                                        </div>


                                    </div>


                                    <!-- =================================================
                                         STATUS
                                         ================================================= -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="status"
                                            class="form-label fw-semibold"
                                        >

                                            Account Status

                                            <span class="text-danger">*</span>

                                        </label>


                                        <select
                                            class="form-select"
                                            id="status"
                                            name="status"
                                            <?= $isCurrentUser ? 'disabled' : '' ?>
                                            required
                                        >


                                            <option
                                                value="active"
                                                <?= $status === 'active' ? 'selected' : '' ?>
                                            >

                                                Active

                                            </option>


                                            <option
                                                value="inactive"
                                                <?= $status === 'inactive' ? 'selected' : '' ?>
                                            >

                                                Inactive

                                            </option>


                                        </select>


                                        <?php if ($isCurrentUser): ?>


                                            <!--
                                            Disabled fields are not submitted.
                                            Preserve the active status with a
                                            hidden field.
                                            -->

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="active"
                                            >


                                        <?php endif; ?>


                                        <div class="form-text">

                                            Inactive users cannot log in.

                                        </div>


                                    </div>


                                </div>


                                <!-- =================================================
                                     SECURITY NOTICE
                                     ================================================= -->

                                <div class="alert alert-info">


                                    <div class="d-flex gap-3">


                                        <div class="fs-4">

                                            <i class="bi bi-shield-check"></i>

                                        </div>


                                        <div>

                                            <h6 class="fw-bold mb-1">

                                                Secure Account Management

                                            </h6>


                                            <p class="mb-0">

                                                Passwords are stored using
                                                secure password hashing.
                                                Existing passwords remain
                                                unchanged unless you enter a
                                                new password.

                                            </p>

                                        </div>


                                    </div>


                                </div>


                                <!-- =================================================
                                     FORM BUTTONS
                                     ================================================= -->

                                <div
                                    class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4"
                                >


                                    <a
                                        href="index.php"
                                        class="btn btn-outline-secondary order-2 order-sm-1"
                                    >

                                        <i class="bi bi-x-circle me-1"></i>

                                        Cancel

                                    </a>


                                    <button
                                        type="submit"
                                        class="btn btn-primary order-1 order-sm-2"
                                    >

                                        <i class="bi bi-save me-1"></i>

                                        Save Changes

                                    </button>


                                </div>


                            </form>


                        </div>


                    </div>


                </div>


            </div>


        </div>


    </main>


</div>


<?php

/*
|--------------------------------------------------------------------------
| Close Database Connection
|--------------------------------------------------------------------------
*/

$conn->close();

?>


<?php include "../includes/footer.php"; ?>


<!-- ================================================================
     PASSWORD VISIBILITY & CLIENT VALIDATION
     ================================================================ -->

<script>

document.addEventListener("DOMContentLoaded", function () {


    /*
    |--------------------------------------------------------------------------
    | Password Elements
    |--------------------------------------------------------------------------
    */

    const passwordInput =
        document.getElementById("password");

    const confirmPasswordInput =
        document.getElementById("confirm_password");

    const togglePassword =
        document.getElementById("togglePassword");

    const toggleConfirmPassword =
        document.getElementById("toggleConfirmPassword");


    /*
    |--------------------------------------------------------------------------
    | Toggle New Password Visibility
    |--------------------------------------------------------------------------
    */

    if (passwordInput && togglePassword) {

        togglePassword.addEventListener(
            "click",
            function () {

                if (passwordInput.type === "password") {

                    passwordInput.type = "text";

                    this.innerHTML =
                        '<i class="bi bi-eye-slash"></i>';

                    this.title = "Hide password";

                } else {

                    passwordInput.type = "password";

                    this.innerHTML =
                        '<i class="bi bi-eye"></i>';

                    this.title = "Show password";

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Toggle Confirm Password Visibility
    |--------------------------------------------------------------------------
    */

    if (
        confirmPasswordInput &&
        toggleConfirmPassword
    ) {

        toggleConfirmPassword.addEventListener(
            "click",
            function () {

                if (
                    confirmPasswordInput.type === "password"
                ) {

                    confirmPasswordInput.type = "text";

                    this.innerHTML =
                        '<i class="bi bi-eye-slash"></i>';

                    this.title = "Hide password";

                } else {

                    confirmPasswordInput.type = "password";

                    this.innerHTML =
                        '<i class="bi bi-eye"></i>';

                    this.title = "Show password";

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Form Validation
    |--------------------------------------------------------------------------
    */

    const form =
        document.querySelector("form");


    if (form) {

        form.addEventListener(
            "submit",
            function (event) {


                /*
                |--------------------------------------------------------------------------
                | Password Fields
                |--------------------------------------------------------------------------
                */

                const password =
                    passwordInput
                        ? passwordInput.value
                        : "";

                const confirmPassword =
                    confirmPasswordInput
                        ? confirmPasswordInput.value
                        : "";


                /*
                |--------------------------------------------------------------------------
                | Only Validate Confirmation When
                | A New Password Has Been Entered
                |--------------------------------------------------------------------------
                */

                if (
                    password !== "" &&
                    password !== confirmPassword
                ) {

                    event.preventDefault();


                    alert(
                        "New password and confirm password do not match."
                    );


                    if (confirmPasswordInput) {

                        confirmPasswordInput.focus();

                    }

                }

            }
        );

    }

});

</script>