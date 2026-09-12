<?php
// SmartPOS Landing Page
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SmartPOS - Point of Sale System</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        /* =========================
           HEADER
        ========================== */

        header {
            width: 100%;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            max-width: 1200px;
            margin: auto;
            padding: 18px 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
        }

        .logo span {
            color: #2563eb;
        }

        .login-btn {
            text-decoration: none;
            background: #2563eb;
            color: white;

            padding: 11px 24px;

            border-radius: 7px;

            font-weight: bold;

            transition: 0.3s;
        }

        .login-btn:hover {
            background: #1d4ed8;
        }


        /* =========================
           HERO
        ========================== */

        .hero {
            max-width: 1200px;
            margin: auto;

            min-height: 600px;

            padding: 80px 25px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 50px;
        }

        .hero-content {
            flex: 1;
        }

        .hero-content h1 {
            font-size: 52px;
            line-height: 1.15;

            margin-bottom: 20px;

            color: #0f172a;
        }

        .hero-content h1 span {
            color: #2563eb;
        }

        .hero-content p {
            font-size: 18px;
            line-height: 1.7;

            color: #64748b;

            max-width: 600px;

            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
        }

        .primary-btn {
            display: inline-block;

            padding: 14px 28px;

            background: #2563eb;
            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

            transition: 0.3s;
        }

        .primary-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .secondary-btn {
            display: inline-block;

            padding: 14px 28px;

            border: 1px solid #cbd5e1;

            color: #334155;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;
        }

        .secondary-btn:hover {
            background: #f1f5f9;
        }


        /* =========================
           HERO CARD
        ========================== */

        .hero-image {
            flex: 1;

            display: flex;
            justify-content: center;
        }

        .pos-card {
            width: 430px;
            background: white;

            border-radius: 18px;

            padding: 25px;

            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);

            border: 1px solid #e2e8f0;
        }

        .pos-header {
            display: flex;
            justify-content: space-between;

            margin-bottom: 25px;
        }

        .pos-title {
            font-size: 18px;
            font-weight: bold;
        }

        .status {
            background: #dcfce7;
            color: #15803d;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;
        }

        .product {
            display: flex;
            justify-content: space-between;

            padding: 15px 0;

            border-bottom: 1px solid #e2e8f0;
        }

        .product-name {
            font-weight: bold;
        }

        .product-price {
            color: #2563eb;
            font-weight: bold;
        }

        .total {
            display: flex;
            justify-content: space-between;

            margin-top: 20px;

            font-size: 20px;
            font-weight: bold;
        }

        .checkout {
            margin-top: 20px;

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 8px;

            background: #2563eb;

            color: white;

            font-size: 15px;

            font-weight: bold;
        }


        /* =========================
           ABOUT
        ========================== */

        .about {
            background: white;

            padding: 80px 25px;
        }

        .section {
            max-width: 1100px;
            margin: auto;

            text-align: center;
        }

        .section h2 {
            font-size: 35px;

            margin-bottom: 15px;

            color: #0f172a;
        }

        .section-description {
            max-width: 700px;

            margin: auto;

            color: #64748b;

            line-height: 1.7;
        }


        /* =========================
           FEATURES
        ========================== */

        .features {
            max-width: 1100px;

            margin: 50px auto 0;

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 25px;
        }

        .feature-card {
            padding: 30px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 12px;

            text-align: left;

            transition: 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);

            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .feature-icon {
            width: 50px;
            height: 50px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #dbeafe;

            color: #2563eb;

            border-radius: 10px;

            font-size: 23px;

            margin-bottom: 18px;
        }

        .feature-card h3 {
            margin-bottom: 10px;

            color: #0f172a;
        }

        .feature-card p {
            color: #64748b;

            font-size: 14px;

            line-height: 1.6;
        }


        /* =========================
           CTA
        ========================== */

        .cta {
            max-width: 1100px;

            margin: 70px auto;

            padding: 50px 30px;

            background: #0f172a;

            color: white;

            border-radius: 16px;

            text-align: center;
        }

        .cta h2 {
            font-size: 32px;

            margin-bottom: 15px;
        }

        .cta p {
            color: #cbd5e1;

            margin-bottom: 25px;
        }


        /* =========================
           FOOTER
        ========================== */

        footer {
            background: #020617;

            color: #94a3b8;

            text-align: center;

            padding: 25px;

            font-size: 14px;
        }


        /* =========================
           MOBILE
        ========================== */

        @media (max-width: 900px) {

            .hero {
                flex-direction: column;

                text-align: center;

                padding-top: 60px;
            }

            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .features {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .navbar {
                padding: 15px 18px;
            }

            .logo {
                font-size: 22px;
            }

            .login-btn {
                padding: 9px 17px;
            }

            .hero {
                padding: 50px 20px;
            }

            .hero-content h1 {
                font-size: 38px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .primary-btn,
            .secondary-btn {
                width: 100%;
            }

            .pos-card {
                width: 100%;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .section h2 {
                font-size: 28px;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     NAVIGATION
========================= -->

<header>

    <div class="navbar">

        <div class="logo">
            Smart<span>POS</span>
        </div>

        <a href="auth/login.php" class="login-btn">
            Login
        </a>

    </div>

</header>


<!-- =========================
     HERO
========================= -->

<section class="hero">

    <div class="hero-content">

        <h1>
            Smart & Simple
            <span>Point of Sale</span>
            System
        </h1>

        <p>
            SmartPOS is a modern point-of-sale management system
            designed to help businesses manage sales, products,
            customers, inventory and reports efficiently from
            one simple platform.
        </p>

        <div class="hero-buttons">

            <a href="auth/login.php" class="primary-btn">
                Login to SmartPOS
            </a>

            <a href="#features" class="secondary-btn">
                Explore Features
            </a>

        </div>

    </div>


    <!-- POS Preview -->

    <div class="hero-image">

        <div class="pos-card">

            <div class="pos-header">

                <div class="pos-title">
                    New Sale
                </div>

                <div class="status">
                    Ready
                </div>

            </div>


            <div class="product">

                <div>
                    <div class="product-name">
                        Product A
                    </div>

                    <small>
                        Qty: 2
                    </small>
                </div>

                <div class="product-price">
                    LKR 200.00
                </div>

            </div>


            <div class="product">

                <div>
                    <div class="product-name">
                        Product B
                    </div>

                    <small>
                        Qty: 1
                    </small>
                </div>

                <div class="product-price">
                    LKR 150.00
                </div>

            </div>


            <div class="product">

                <div>
                    <div class="product-name">
                        Product C
                    </div>

                    <small>
                        Qty: 3
                    </small>
                </div>

                <div class="product-price">
                    LKR 300.00
                </div>

            </div>


            <div class="total">

                <span>
                    Total
                </span>

                <span>
                    LKR 650.00
                </span>

            </div>


            <button class="checkout">
                Complete Sale
            </button>

        </div>

    </div>

</section>


<!-- =========================
     ABOUT
========================= -->

<section class="about">

    <div class="section">

        <h2>
            Everything You Need to Run Your Business
        </h2>

        <p class="section-description">

            SmartPOS provides an easy-to-use platform for
            managing daily business operations. From processing
            sales to monitoring inventory and generating reports,
            everything is available in one centralized system.

        </p>


        <!-- FEATURES -->

        <div class="features" id="features">


            <div class="feature-card">

                <div class="feature-icon">
                    🛒
                </div>

                <h3>
                    Fast Sales
                </h3>

                <p>
                    Process customer transactions quickly
                    and efficiently using the POS interface.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    📦
                </div>

                <h3>
                    Product Management
                </h3>

                <p>
                    Add, update and manage products,
                    prices and product information.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    📊
                </div>

                <h3>
                    Reports
                </h3>

                <p>
                    Analyze sales and business performance
                    through useful reports.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    👥
                </div>

                <h3>
                    Customer Management
                </h3>

                <p>
                    Maintain customer information and
                    transaction history.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    📋
                </div>

                <h3>
                    Inventory Management
                </h3>

                <p>
                    Monitor stock levels and keep track
                    of your available products.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    🔐
                </div>

                <h3>
                    Secure Access
                </h3>

                <p>
                    Protect your business information
                    using secure user authentication.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     LOGIN CTA
========================= -->

<section class="cta">

    <h2>
        Ready to Get Started?
    </h2>

    <p>
        Login to your SmartPOS account and manage
        your business from one place.
    </p>

    <a href="auth/login.php" class="primary-btn">
        Login to SmartPOS
    </a>

</section>


<!-- =========================
     FOOTER
========================= -->

<footer>

    SmartPOS System © <?php echo date("Y"); ?>

</footer>


</body>

</html>