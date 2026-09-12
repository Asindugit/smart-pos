<?php



/*
|--------------------------------------------------------------------------
| Authentication & Database
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
require_once "../config/database.php";


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

$pageTitle = "Add User";


/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/

$fullName = "";
$username = "";
$email = "";
$role = "cashier";
$status = "active";

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
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | Get Form Data
    |--------------------------------------------------------------------------
    */

    $fullName = trim($_POST['full_name'] ?? '');

    $username = trim($_POST['username'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';

    $confirmPassword = $_POST['confirm_password'] ?? '';

    $role = trim($_POST['role'] ?? 'cashier');

    $status = trim($_POST['status'] ?? 'active');


    /*
    |--------------------------------------------------------------------------
    | Validate Full Name
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $errors[] = "Full name is required.";

    } elseif (mb_strlen($fullName) > 100) {

        $errors[] = "Full name cannot exceed 100 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Username
    |--------------------------------------------------------------------------
    */

    if ($username === '') {

        $errors[] = "Username is required.";

    } elseif (mb_strlen($username) > 50) {

        $errors[] = "Username cannot exceed 50 characters.";

    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {

        $errors[] = "Username can contain only letters, numbers, dots, underscores and hyphens.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] = "Please enter a valid email address.";

    } elseif ($email !== '' && mb_strlen($email) > 150) {

        $errors[] = "Email cannot exceed 150 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if ($password === '') {

        $errors[] = "Password is required.";

    } elseif (strlen($password) < 8) {

        $errors[] = "Password must contain at least 8 characters.";

    } elseif (!preg_match('/[A-Z]/', $password)) {

        $errors[] = "Password must contain at least one uppercase letter.";

    } elseif (!preg_match('/[a-z]/', $password)) {

        $errors[] = "Password must contain at least one lowercase letter.";

    } elseif (!preg_match('/[0-9]/', $password)) {

        $errors[] = "Password must contain at least one number.";

    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {

        $errors[] = "Password must contain at least one special character.";

    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */

    if ($password !== $confirmPassword) {

        $errors[] = "Password and confirm password do not match.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Role
    |--------------------------------------------------------------------------
    */

    if (!in_array($role, $allowedRoles, true)) {

        $errors[] = "Invalid user role selected.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (!in_array($status, $allowedStatuses, true)) {

        $errors[] = "Invalid user status selected.";

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
            WHERE username = ?
               OR (
                    email IS NOT NULL
                    AND email <> ''
                    AND email = ?
               )
            LIMIT 1
        ");


        if (!$checkStmt) {

            $errors[] = "Unable to validate user information.";

        } else {


            $checkStmt->bind_param(
                "ss",
                $username,
                $email
            );


            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            $existingUser = $checkResult->fetch_assoc();

            $checkStmt->close();


            if ($existingUser) {


                if (
                    isset($existingUser['username']) &&
                    $existingUser['username'] === $username
                ) {

                    $errors[] = "This username is already in use.";

                }


                if (
                    $email !== '' &&
                    isset($existingUser['email']) &&
                    $existingUser['email'] === $email
                ) {

                    $errors[] = "This email address is already in use.";

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Create User
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {


        /*
        |--------------------------------------------------------------------------
        | Secure Password Hash
        |--------------------------------------------------------------------------
        */

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        if ($hashedPassword === false) {

            $errors[] = "Unable to securely create the password.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Insert User
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name,
                    username,
                    email,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");


            if (!$stmt) {

                $errors[] = "Unable to prepare user creation.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | Store NULL for Empty Email
                |--------------------------------------------------------------------------
                */

                $emailValue = $email !== ''
                    ? $email
                    : null;


                $stmt->bind_param(
                    "ssssss",
                    $fullName,
                    $username,
                    $emailValue,
                    $hashedPassword,
                    $role,
                    $status
                );


                if ($stmt->execute()) {


                    /*
                    |--------------------------------------------------------------------------
                    | Success
                    |--------------------------------------------------------------------------
                    */

                    $stmt->close();

                    $conn->close();


                    header(
                        "Location: index.php?success=" .
                        urlencode("User created successfully.")
                    );

                    exit;


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | Handle Database Error
                    |--------------------------------------------------------------------------
                    */

                    if ($stmt->errno === 1062) {

                        $errors[] =
                            "Username or email already exists.";

                    } else {

                        $errors[] =
                            "Failed to create user. Please try again.";

                    }


                    $stmt->close();

                }

            }

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

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">


                <div>

                    <h1 class="h3 fw-bold mb-1">

                        <i class="bi bi-person-plus me-2"></i>

                        Add User

                    </h1>

                    <p class="text-muted mb-0">

                        Create a new user account for the SmartPOS system.

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
                                        <?= htmlspecialchars($error) ?>
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
                 ADD USER FORM
                 ========================================================= -->

            <div class="row justify-content-center">


                <div class="col-12 col-xl-9">


                    <div class="card border-0 shadow-sm">


                        <!-- Card Header -->

                        <div class="card-header bg-white border-0 py-3">


                            <div class="d-flex align-items-center">


                                <div
                                    class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-3"
                                    style="width: 45px; height: 45px;"
                                >

                                    <i class="bi bi-person-plus fs-5"></i>

                                </div>


                                <div>

                                    <h5 class="fw-bold mb-1">
                                        User Account Details
                                    </h5>

                                    <small class="text-muted">
                                        Enter the information for the new user.
                                    </small>

                                </div>


                            </div>


                        </div>


                        <!-- Card Body -->

                        <div class="card-body p-4">


                            <form
                                method="POST"
                                action="add.php"
                                autocomplete="off"
                            >


                                <!-- =================================================
                                     BASIC INFORMATION
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-person me-2 text-primary"></i>

                                    Basic Information

                                </h6>


                                <div class="row g-4 mb-4">


                                    <!-- Full Name -->

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
                                            value="<?= htmlspecialchars($fullName) ?>"
                                            placeholder="Enter full name"
                                            required
                                        >


                                        <div class="form-text">

                                            Maximum 100 characters.

                                        </div>


                                    </div>


                                    <!-- Username -->

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
                                                value="<?= htmlspecialchars($username) ?>"
                                                placeholder="Enter username"
                                                required
                                            >


                                        </div>


                                        <div class="form-text">

                                            Letters, numbers, dots, underscores and hyphens only.

                                        </div>


                                    </div>


                                    <!-- Email -->

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
                                                value="<?= htmlspecialchars($email) ?>"
                                                placeholder="user@example.com"
                                            >


                                        </div>


                                    </div>


                                </div>


                                <hr class="my-4">


                                <!-- =================================================
                                     SECURITY
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-shield-lock me-2 text-primary"></i>

                                    Account Security

                                </h6>


                                <div class="row g-4 mb-4">


                                    <!-- Password -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="password"
                                            class="form-label fw-semibold"
                                        >

                                            Password

                                            <span class="text-danger">*</span>

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
                                                placeholder="Enter password"
                                                autocomplete="new-password"
                                                required
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


                                    <!-- Confirm Password -->

                                    <div class="col-12 col-md-6">


                                        <label
                                            for="confirm_password"
                                            class="form-label fw-semibold"
                                        >

                                            Confirm Password

                                            <span class="text-danger">*</span>

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
                                                placeholder="Confirm password"
                                                autocomplete="new-password"
                                                required
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
                                     ROLE & STATUS
                                     ================================================= -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-person-badge me-2 text-primary"></i>

                                    Access & Status

                                </h6>


                                <div class="row g-4 mb-4">


                                    <!-- Role -->

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


                                        <div class="form-text">

                                            Determines what parts of the POS system
                                            the user can access.

                                        </div>


                                    </div>


                                    <!-- Status -->

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


                                        <div class="form-text">

                                            Inactive users will not be able to log in.

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
                                                Password Security
                                            </h6>

                                            <p class="mb-0">

                                                The password will be securely
                                                hashed before it is stored in
                                                the database. SmartPOS never
                                                stores plain-text passwords.

                                            </p>

                                        </div>


                                    </div>


                                </div>


                                <!-- =================================================
                                     FORM BUTTONS
                                     ================================================= -->

                                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">


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

                                        <i class="bi bi-person-plus me-1"></i>

                                        Create User

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
     PASSWORD VISIBILITY SCRIPT
     ================================================================ -->

<script>

document.addEventListener("DOMContentLoaded", function () {


    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    */

    const passwordInput =
        document.getElementById("password");

    const togglePassword =
        document.getElementById("togglePassword");


    if (passwordInput && togglePassword) {

        togglePassword.addEventListener("click", function () {

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

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */

    const confirmPasswordInput =
        document.getElementById("confirm_password");

    const toggleConfirmPassword =
        document.getElementById("toggleConfirmPassword");


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
    | Confirm Password Validation
    |--------------------------------------------------------------------------
    */

    const form =
        document.querySelector("form");

    if (form) {

        form.addEventListener("submit", function (event) {

            const password =
                passwordInput.value;

            const confirmPassword =
                confirmPasswordInput.value;


            if (password !== confirmPassword) {

                event.preventDefault();

                alert(
                    "Password and confirm password do not match."
                );

                confirmPasswordInput.focus();

            }

        });

    }

});

</script>