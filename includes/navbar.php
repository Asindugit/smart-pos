<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">

    <div class="container-fluid">

        <button
            class="btn btn-outline-secondary d-lg-none me-2"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
        >
            <i class="bi bi-list"></i>
        </button>

        <a
            class="navbar-brand fw-bold"
            href="/smart-pos/dashboard/index.php"
        >
            <i class="bi bi-shop"></i>
            SmartPOS
        </a>

        <div class="ms-auto d-flex align-items-center gap-3">

            <span class="text-muted d-none d-md-block">
                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
            </span>

            <a
                href="/smart-pos/auth/logout.php"
                class="btn btn-outline-danger btn-sm"
            >
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </div>

</nav>