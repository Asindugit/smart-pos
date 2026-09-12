<?php


// Prevent MIME-type sniffing
header("X-Content-Type-Options: nosniff");


// Prevent the site from being embedded in frames
header("X-Frame-Options: SAMEORIGIN");


// Control referrer information
header("Referrer-Policy: strict-origin-when-cross-origin");


// Disable unnecessary browser features
header(
    "Permissions-Policy: " .
    "geolocation=(), " .
    "microphone=(), " .
    "camera=()"
);


/*
|--------------------------------------------------------------------------
| Content Security Policy
|--------------------------------------------------------------------------
|
| SmartPOS uses:
|
| - Bootstrap 5 from jsDelivr
| - Bootstrap Icons from jsDelivr
| - Inline JavaScript in some pages
| - Inline styles in some pages
|
| 'unsafe-inline' is enabled here because the current SmartPOS pages
| use inline <script> and style="" code.
|
*/

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
    "font-src 'self' https://cdn.jsdelivr.net data:; " .
    "img-src 'self' data: blob:; " .
    "connect-src 'self' https://cdn.jsdelivr.net; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'self';"
);


/*
|--------------------------------------------------------------------------
| HTTPS Security
|--------------------------------------------------------------------------
*/

if (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
) {

    header(
        "Strict-Transport-Security: " .
        "max-age=31536000; includeSubDomains"
    );
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = $pageTitle ?? "SmartPOS";

?>


<!DOCTYPE html>

<html lang="en">

<head>


    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <title>
        <?= htmlspecialchars(
            $pageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?> | SmartPOS
    </title>


    <!-- Bootstrap 5 -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <!-- SmartPOS CSS -->

    <link
        rel="stylesheet"
        href="/smart-pos/assets/css/style.css"
    >


</head>


<body>