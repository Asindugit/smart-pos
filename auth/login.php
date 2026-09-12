<?php


if (session_status() === PHP_SESSION_NONE) {

    /*
    |--------------------------------------------------------------------------
    | Detect HTTPS
    |--------------------------------------------------------------------------
    */

    $secure = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    /*
    |--------------------------------------------------------------------------
    | Secure Session Cookie
    |--------------------------------------------------------------------------
    */

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Additional Session Security
    |--------------------------------------------------------------------------
    */

    ini_set(
        'session.use_only_cookies',
        '1'
    );

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.use_trans_sid',
        '0'
    );

    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE & CSRF
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";
require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| REDIRECT ALREADY LOGGED-IN USER
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    header(
        "Location: ../dashboard/index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGIN ERROR
|--------------------------------------------------------------------------
*/

$error = "";


/*
|--------------------------------------------------------------------------
| LOGIN FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF PROTECTION
    |--------------------------------------------------------------------------
    */

    if (!verifyCsrfToken()) {

        $error =
            "Your security token is invalid or expired. " .
            "Please refresh the page and try again.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET FORM DATA
        |--------------------------------------------------------------------------
        */

        $username = trim(
            $_POST["username"] ?? ""
        );

        $password =
            $_POST["password"] ?? "";


        /*
        |--------------------------------------------------------------------------
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $username === "" ||
            $password === ""
        ) {

            $error =
                "Please enter your username and password.";

        } elseif (strlen($username) > 50) {

            $error =
                "Invalid username or password.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | FIND USER
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    full_name,
                    username,
                    password,
                    role,
                    status
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            /*
            |--------------------------------------------------------------------------
            | PREPARE ERROR
            |--------------------------------------------------------------------------
            */

            if (!$stmt) {

                error_log(
                    "SmartPOS Login Prepare Error: " .
                    $conn->error
                );

                $error =
                    "Unable to process your login request. " .
                    "Please try again later.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | BIND USERNAME
                |--------------------------------------------------------------------------
                */

                $stmt->bind_param(
                    "s",
                    $username
                );


                /*
                |--------------------------------------------------------------------------
                | EXECUTE QUERY
                |--------------------------------------------------------------------------
                */

                if (!$stmt->execute()) {

                    error_log(
                        "SmartPOS Login Execute Error: " .
                        $stmt->error
                    );

                    $error =
                        "Unable to process your login request. " .
                        "Please try again later.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | GET RESULT
                    |--------------------------------------------------------------------------
                    */

                    $result =
                        $stmt->get_result();


                    /*
                    |--------------------------------------------------------------------------
                    | USER FOUND
                    |--------------------------------------------------------------------------
                    */

                    if ($result->num_rows === 1) {

                        $user =
                            $result->fetch_assoc();


                        /*
                        |--------------------------------------------------------------------------
                        | CHECK ACCOUNT STATUS
                        |--------------------------------------------------------------------------
                        */

                        if (
                            ($user["status"] ?? "")
                            !== "active"
                        ) {

                            $error =
                                "Your account is inactive.";

                        }

                        /*
                        |--------------------------------------------------------------------------
                        | VERIFY PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            password_verify(
                                $password,
                                $user["password"]
                            )
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | SESSION FIXATION PROTECTION
                            |--------------------------------------------------------------------------
                            |
                            | Generate a completely new session ID
                            | after successful authentication.
                            |
                            */

                            session_regenerate_id(true);


                            /*
                            |--------------------------------------------------------------------------
                            | STORE MINIMUM REQUIRED USER DATA
                            |--------------------------------------------------------------------------
                            */

                            $_SESSION["user_id"] =
                                (int) $user["id"];

                            $_SESSION["user_name"] =
                                $user["full_name"];

                            $_SESSION["username"] =
                                $user["username"];

                            $_SESSION["user_role"] =
                                $user["role"];


                            /*
                            |--------------------------------------------------------------------------
                            | SESSION SECURITY TIMESTAMPS
                            |--------------------------------------------------------------------------
                            |
                            | These values are used by config/auth.php
                            | for session timeout and regeneration.
                            |
                            */

                            $currentTime = time();

                            $_SESSION["login_time"] =
                                $currentTime;

                            $_SESSION["session_created_at"] =
                                $currentTime;

                            $_SESSION["last_activity"] =
                                $currentTime;

                            $_SESSION["last_regeneration"] =
                                $currentTime;


                            /*
                            |--------------------------------------------------------------------------
                            | SUCCESSFUL LOGIN
                            |--------------------------------------------------------------------------
                            */

                            $stmt->close();
                            $conn->close();

                            header(
                                "Location: ../dashboard/index.php"
                            );

                            exit;

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | INVALID PASSWORD
                            |--------------------------------------------------------------------------
                            |
                            | Do not reveal whether the username exists.
                            |
                            */

                            $error =
                                "Invalid username or password.";
                        }

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | USER NOT FOUND
                        |--------------------------------------------------------------------------
                        */

                        $error =
                            "Invalid username or password.";
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CLOSE STATEMENT
                |--------------------------------------------------------------------------
                */

                $stmt->close();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <meta
        name="referrer"
        content="strict-origin-when-cross-origin"
    >

    <title>Login | SmartPOS</title>


    <!--
    |--------------------------------------------------------------------------
    | Bootstrap 5
    |--------------------------------------------------------------------------
    -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!--
    |--------------------------------------------------------------------------
    | Bootstrap Icons
    |--------------------------------------------------------------------------
    -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <!--
    |--------------------------------------------------------------------------
    | SmartPOS CSS
    |--------------------------------------------------------------------------
    -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>


<div class="login-page">

    <div class="container">

        <div
            class="row justify-content-center align-items-center min-vh-100"
        >

            <div
                class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4"
            >

                <div
                    class="card border-0 shadow-lg login-card"
                >

                    <div
                        class="card-body p-4 p-md-5"
                    >


                        <!--
                        ------------------------------------------------------
                        LOGIN HEADER
                        ------------------------------------------------------
                        -->

                        <div
                            class="text-center mb-4"
                        >

                            <div
                                class="login-logo mb-3"
                            >

                                <i class="bi bi-shop"></i>

                            </div>


                            <h2 class="fw-bold mb-1">
                                SmartPOS
                            </h2>


                            <p class="text-muted mb-0">
                                Sign in to your account
                            </p>

                        </div>


                        <!--
                        ------------------------------------------------------
                        SESSION TIMEOUT MESSAGE
                        ------------------------------------------------------
                        -->

                        <?php if (isset($_GET['timeout'])): ?>

                            <div
                                class="alert alert-warning"
                                role="alert"
                            >

                                <i
                                    class="bi bi-clock-history me-2"
                                ></i>

                                Your session expired due to
                                inactivity. Please log in again.

                            </div>

                        <?php endif; ?>


                        <!--
                        ------------------------------------------------------
                        SESSION MAXIMUM LIFETIME MESSAGE
                        ------------------------------------------------------
                        -->

                        <?php if (isset($_GET['expired'])): ?>

                            <div
                                class="alert alert-warning"
                                role="alert"
                            >

                                <i
                                    class="bi bi-shield-exclamation me-2"
                                ></i>

                                Your session has expired.
                                Please log in again.

                            </div>

                        <?php endif; ?>


                        <!--
                        ------------------------------------------------------
                        ERROR MESSAGE
                        ------------------------------------------------------
                        -->

                        <?php if ($error !== ""): ?>

                            <div
                                class="alert alert-danger"
                                role="alert"
                            >

                                <i
                                    class="bi bi-exclamation-circle me-2"
                                ></i>

                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <!--
                        ------------------------------------------------------
                        LOGIN FORM
                        ------------------------------------------------------
                        -->

                        <form
                            method="POST"
                            action=""
                            autocomplete="on"
                        >


                            <!--
                            --------------------------------------------------
                            CSRF TOKEN
                            --------------------------------------------------
                            -->

                            <?= csrfField() ?>


                            <!--
                            --------------------------------------------------
                            USERNAME
                            --------------------------------------------------
                            -->

                            <div class="mb-3">

                                <label
                                    for="username"
                                    class="form-label fw-semibold"
                                >
                                    Username
                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="bi bi-person"
                                        ></i>

                                    </span>


                                    <input
                                        type="text"
                                        class="form-control"
                                        id="username"
                                        name="username"
                                        placeholder="Enter username"
                                        autocomplete="username"
                                        maxlength="50"
                                        required
                                        autofocus
                                        value="<?= htmlspecialchars(
                                            $_POST['username'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                </div>

                            </div>


                            <!--
                            --------------------------------------------------
                            PASSWORD
                            --------------------------------------------------
                            -->

                            <div class="mb-4">

                                <label
                                    for="password"
                                    class="form-label fw-semibold"
                                >
                                    Password
                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="bi bi-lock"
                                        ></i>

                                    </span>


                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="Enter password"
                                        autocomplete="current-password"
                                        required
                                    >

                                </div>

                            </div>


                            <!--
                            --------------------------------------------------
                            SIGN IN BUTTON
                            --------------------------------------------------
                            -->

                            <button
                                type="submit"
                                class="btn btn-primary w-100 py-2 fw-semibold"
                            >

                                <i
                                    class="bi bi-box-arrow-in-right me-2"
                                ></i>

                                Sign In

                            </button>


                        </form>

                    </div>

                </div>


                <!--
                --------------------------------------------------------------
                COPYRIGHT
                --------------------------------------------------------------
                -->

                <p
                    class="text-center text-muted small mt-3"
                >

                    © <?= date("Y") ?> SmartPOS

                </p>

            </div>

        </div>

    </div>

</div>


</body>

</html>