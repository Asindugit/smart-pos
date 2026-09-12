<?php


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| GENERATE CSRF TOKEN
|--------------------------------------------------------------------------
|
| Creates one secure token per session.
|
*/

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN HTML FIELD
|--------------------------------------------------------------------------
|
| Use this inside POST forms.
|
*/

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(
            csrfToken(),
            ENT_QUOTES,
            'UTF-8'
        ) .
        '">';
}


/*
|--------------------------------------------------------------------------
| VERIFY CSRF TOKEN
|--------------------------------------------------------------------------
|
| Returns true when the submitted token matches
| the session token.
|
*/

function verifyCsrfToken()
{
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRE VALID CSRF TOKEN
|--------------------------------------------------------------------------
|
| Stops the request when the token is invalid.
|
*/

function requireCsrfToken()
{
    if (!verifyCsrfToken()) {

        http_response_code(403);

        die("
            <div style='
                font-family: Arial, sans-serif;
                padding: 40px;
                text-align: center;
            '>

                <h1>403 - Invalid Request</h1>

                <p>
                    Your security token is invalid or expired.
                </p>

                <p>
                    Please go back and try again.
                </p>

            </div>
        ");
    }
}

?>