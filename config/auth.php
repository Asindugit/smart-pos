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
    | Additional PHP Session Security
    |--------------------------------------------------------------------------
    */

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();
}


const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes
const SESSION_MAX_LIFETIME = 28800; // 8 hours

/*
|--------------------------------------------------------------------------
| Initialize Session Security
|--------------------------------------------------------------------------
*/

function initializeSessionSecurity()
{
    if (!isset($_SESSION['session_created_at'])) {

        $_SESSION['session_created_at'] = time();
    }

    if (!isset($_SESSION['last_activity'])) {

        $_SESSION['last_activity'] = time();
    }

    if (!isset($_SESSION['last_regeneration'])) {

        $_SESSION['last_regeneration'] = time();
    }
}

/*
|--------------------------------------------------------------------------
| Destroy Session
|--------------------------------------------------------------------------
*/

function destroySession()
{
    $_SESSION = [];

    /*
    |--------------------------------------------------------------------------
    | Delete Session Cookie
    |--------------------------------------------------------------------------
    */

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }

    session_destroy();
}

/*
|--------------------------------------------------------------------------
| Check Session Timeout
|--------------------------------------------------------------------------
*/

function checkSessionTimeout()
{
    /*
    |--------------------------------------------------------------------------
    | Only check logged-in sessions
    |--------------------------------------------------------------------------
    */

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $now = time();

    /*
    |--------------------------------------------------------------------------
    | Initialize Missing Security Values
    |--------------------------------------------------------------------------
    */

    initializeSessionSecurity();

    /*
    |--------------------------------------------------------------------------
    | Idle Timeout
    |--------------------------------------------------------------------------
    */

    $idleTime =
        $now - (int) $_SESSION['last_activity'];

    if ($idleTime > SESSION_IDLE_TIMEOUT) {

        destroySession();

        header(
            "Location: /smart-pos/auth/login.php?timeout=1"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Absolute Session Lifetime
    |--------------------------------------------------------------------------
    */

    $sessionAge =
        $now - (int) $_SESSION['session_created_at'];

    if ($sessionAge > SESSION_MAX_LIFETIME) {

        destroySession();

        header(
            "Location: /smart-pos/auth/login.php?expired=1"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Activity Time
    |--------------------------------------------------------------------------
    */

    $_SESSION['last_activity'] = $now;

    /*
    |--------------------------------------------------------------------------
    | Periodic Session ID Regeneration
    |--------------------------------------------------------------------------
    |
    | Regenerate the session ID every 15 minutes.
    |
    */

    if (
        $now - (int) $_SESSION['last_regeneration']
        > 900
    ) {

        session_regenerate_id(true);

        $_SESSION['last_regeneration'] = $now;
    }
}

/*
|--------------------------------------------------------------------------
| Run Session Security
|--------------------------------------------------------------------------
*/

initializeSessionSecurity();

checkSessionTimeout();

/*
|--------------------------------------------------------------------------
| Check Login Status
|--------------------------------------------------------------------------
*/

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isLoggedIn()) {

        header(
            "Location: /smart-pos/auth/login.php"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Re-check Session Security
    |--------------------------------------------------------------------------
    */

    checkSessionTimeout();
}

/*
|--------------------------------------------------------------------------
| Role-Based Access Control
|--------------------------------------------------------------------------
*/

function requireRole($roles = [])
{
    requireLogin();

    /*
    |--------------------------------------------------------------------------
    | Validate User Role
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_SESSION['user_role']) ||
        !is_string($_SESSION['user_role'])
    ) {

        destroySession();

        header(
            "Location: /smart-pos/auth/login.php"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Allowed Roles
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $_SESSION['user_role'],
            $roles,
            true
        )
    ) {

        http_response_code(403);

        die("
            <!DOCTYPE html>

            <html lang='en'>

            <head>

                <meta charset='UTF-8'>

                <meta
                    name='viewport'
                    content='width=device-width, initial-scale=1.0'
                >

                <title>403 - Access Denied</title>

                <style>

                    body {
                        font-family: Arial, sans-serif;
                        background: #f8f9fa;
                        margin: 0;
                        padding: 0;
                    }

                    .container {
                        max-width: 600px;
                        margin: 100px auto;
                        background: #ffffff;
                        padding: 40px;
                        text-align: center;
                        border-radius: 10px;
                        box-shadow:
                            0 4px 20px
                            rgba(0, 0, 0, 0.08);
                    }

                    h1 {
                        margin-bottom: 15px;
                    }

                    p {
                        color: #6c757d;
                    }

                    a {
                        display: inline-block;
                        margin-top: 15px;
                        padding: 10px 18px;
                        background: #0d6efd;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 6px;
                    }

                    a:hover {
                        background: #0b5ed7;
                    }

                </style>

            </head>

            <body>

                <div class='container'>

                    <h1>403 - Access Denied</h1>

                    <p>
                        You do not have permission
                        to access this page.
                    </p>

                    <a
                        href='/smart-pos/dashboard/index.php'
                    >
                        Return to Dashboard
                    </a>

                </div>

            </body>

            </html>
        ");

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

function currentUser()
{
    return [
        'id' =>
            $_SESSION['user_id'] ?? null,

        'name' =>
            $_SESSION['user_name'] ?? null,

        'username' =>
            $_SESSION['username'] ?? null,

        'role' =>
            $_SESSION['user_role'] ?? null
    ];
}

?>