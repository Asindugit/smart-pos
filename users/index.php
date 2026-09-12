<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
| Only administrators can manage users.
|--------------------------------------------------------------------------
*/

requireRole(['admin']);


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle = "User Management";


/*
|--------------------------------------------------------------------------
| CURRENT LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$currentUser = currentUser();

$currentUserId = (int) (
    $currentUser['id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| SEARCH AND FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$role = trim(
    $_GET['role'] ?? ''
);

$status = trim(
    $_GET['status'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDATE ROLE FILTER
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'admin',
    'manager',
    'cashier'
];

if (
    $role !== '' &&
    !in_array($role, $allowedRoles, true)
) {

    $role = '';
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS FILTER
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive'
];

if (
    $status !== '' &&
    !in_array($status, $allowedStatuses, true)
) {

    $status = '';
}


/*
|--------------------------------------------------------------------------
| BUILD USER QUERY
|--------------------------------------------------------------------------
*/

$sql = "
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
    WHERE 1 = 1
";


$params = [];

$types = "";


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            full_name LIKE ?
            OR username LIKE ?
            OR email LIKE ?
        )
    ";

    $searchValue =
        "%" . $search . "%";


    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}


/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

if ($role !== '') {

    $sql .= "
        AND role = ?
    ";

    $params[] = $role;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $sql .= "
        AND status = ?
    ";

    $params[] = $status;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| ORDERING
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        CASE
            WHEN status = 'active' THEN 1
            ELSE 2
        END,

        CASE
            WHEN role = 'admin' THEN 1
            WHEN role = 'manager' THEN 2
            ELSE 3
        END,

        full_name ASC
";


/*
|--------------------------------------------------------------------------
| PREPARE USER QUERY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {

    error_log(
        "SmartPOS users query prepare failed: " .
        $conn->error
    );

    $conn->close();

    die("Unable to load users.");
}


/*
|--------------------------------------------------------------------------
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


/*
|--------------------------------------------------------------------------
| EXECUTE USER QUERY
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    error_log(
        "SmartPOS users query execution failed: " .
        $stmt->error
    );

    $stmt->close();
    $conn->close();

    die("Unable to load users.");
}


$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| FETCH USERS
|--------------------------------------------------------------------------
*/

$users = [];

while ($row = $result->fetch_assoc()) {

    $users[] = $row;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
*/

$statsSql = "
    SELECT

        COUNT(*) AS total_users,

        SUM(
            CASE
                WHEN status = 'active'
                THEN 1
                ELSE 0
            END
        ) AS active_users,

        SUM(
            CASE
                WHEN status = 'inactive'
                THEN 1
                ELSE 0
            END
        ) AS inactive_users,

        SUM(
            CASE
                WHEN role = 'admin'
                THEN 1
                ELSE 0
            END
        ) AS admin_users,

        SUM(
            CASE
                WHEN role = 'manager'
                THEN 1
                ELSE 0
            END
        ) AS manager_users,

        SUM(
            CASE
                WHEN role = 'cashier'
                THEN 1
                ELSE 0
            END
        ) AS cashier_users

    FROM users
";


$statsResult =
    $conn->query($statsSql);


if ($statsResult) {

    $stats =
        $statsResult->fetch_assoc();

} else {

    error_log(
        "SmartPOS user statistics query failed: " .
        $conn->error
    );

    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'admin_users' => 0,
        'manager_users' => 0,
        'cashier_users' => 0
    ];
}


/*
|--------------------------------------------------------------------------
| CONVERT STATISTICS TO INTEGERS
|--------------------------------------------------------------------------
*/

$totalUsers =
    (int) (
        $stats['total_users'] ?? 0
    );

$activeUsers =
    (int) (
        $stats['active_users'] ?? 0
    );

$inactiveUsers =
    (int) (
        $stats['inactive_users'] ?? 0
    );

$adminUsers =
    (int) (
        $stats['admin_users'] ?? 0
    );

$managerUsers =
    (int) (
        $stats['manager_users'] ?? 0
    );

$cashierUsers =
    (int) (
        $stats['cashier_users'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| HELPER: ROLE BADGE
|--------------------------------------------------------------------------
*/

function userRoleBadge($role)
{
    switch ($role) {

        case 'admin':

            return '
                <span class="badge text-bg-danger">
                    <i class="bi bi-shield-lock me-1"></i>
                    Admin
                </span>
            ';

        case 'manager':

            return '
                <span class="badge text-bg-primary">
                    <i class="bi bi-person-badge me-1"></i>
                    Manager
                </span>
            ';

        case 'cashier':

            return '
                <span class="badge text-bg-info">
                    <i class="bi bi-person-check me-1"></i>
                    Cashier
                </span>
            ';

        default:

            return '
                <span class="badge text-bg-secondary">
                    Unknown
                </span>
            ';
    }
}


/*
|--------------------------------------------------------------------------
| HELPER: STATUS BADGE
|--------------------------------------------------------------------------
*/

function userStatusBadge($status)
{
    if ($status === 'active') {

        return '
            <span class="badge text-bg-success">
                <i class="bi bi-check-circle me-1"></i>
                Active
            </span>
        ';
    }

    return '
        <span class="badge text-bg-secondary">
            <i class="bi bi-x-circle me-1"></i>
            Inactive
        </span>
    ';
}


/*
|--------------------------------------------------------------------------
| INCLUDE HEADER
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

                        User Management

                    </h1>


                    <p class="text-muted mb-0">

                        Manage SmartPOS administrators,
                        managers and cashiers.

                    </p>

                </div>


                <div>

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-person-plus me-1"></i>

                        Add User

                    </a>

                </div>

            </div>


            <!-- =========================================================
                 SUCCESS MESSAGE
                 ========================================================= -->

            <?php if (isset($_GET['success'])): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars(
                        $_GET['success'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- =========================================================
                 ERROR MESSAGE
                 ========================================================= -->

            <?php if (isset($_GET['error'])): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars(
                        $_GET['error'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- =========================================================
                 STATISTICS
                 ========================================================= -->

            <div class="row g-4 mb-4">


                <!-- Total Users -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Total Users
                                    </p>

                                    <h3 class="fw-bold mb-0">
                                        <?= $totalUsers ?>
                                    </h3>

                                </div>


                                <div class="fs-2 text-primary">

                                    <i class="bi bi-people"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Active Users -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Active Users
                                    </p>

                                    <h3 class="fw-bold text-success mb-0">
                                        <?= $activeUsers ?>
                                    </h3>

                                </div>


                                <div class="fs-2 text-success">

                                    <i class="bi bi-person-check"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Managers -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Managers
                                    </p>

                                    <h3 class="fw-bold text-primary mb-0">
                                        <?= $managerUsers ?>
                                    </h3>

                                </div>


                                <div class="fs-2 text-primary">

                                    <i class="bi bi-person-badge"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Cashiers -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <div>

                                    <p class="text-muted mb-1">
                                        Cashiers
                                    </p>

                                    <h3 class="fw-bold text-info mb-0">
                                        <?= $cashierUsers ?>
                                    </h3>

                                </div>


                                <div class="fs-2 text-info">

                                    <i class="bi bi-person-check"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


            </div>


            <!-- =========================================================
                 SEARCH & FILTERS
                 ========================================================= -->

            <div class="card border-0 shadow-sm mb-4">


                <div class="card-header bg-white border-0 py-3">


                    <div class="d-flex align-items-center">

                        <i class="bi bi-funnel fs-5 me-2 text-primary"></i>

                        <div>

                            <h5 class="mb-0 fw-bold">
                                Search & Filter Users
                            </h5>

                            <small class="text-muted">
                                Find users by name, username or email.
                            </small>

                        </div>

                    </div>


                </div>


                <div class="card-body p-4">


                    <form
                        method="GET"
                        action="index.php"
                    >

                        <div class="row g-3 align-items-end">


                            <!-- Search -->

                            <div class="col-12 col-lg-5">

                                <label
                                    for="search"
                                    class="form-label fw-semibold"
                                >
                                    Search
                                </label>


                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="bi bi-search"></i>

                                    </span>


                                    <input
                                        type="text"
                                        id="search"
                                        name="search"
                                        class="form-control"
                                        placeholder="Name, username or email..."
                                        value="<?= htmlspecialchars(
                                            $search,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                </div>

                            </div>


                            <!-- Role -->

                            <div class="col-12 col-sm-6 col-lg-3">

                                <label
                                    for="role"
                                    class="form-label fw-semibold"
                                >
                                    Role
                                </label>


                                <select
                                    id="role"
                                    name="role"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Roles
                                    </option>


                                    <option
                                        value="admin"
                                        <?= $role === 'admin'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Admin
                                    </option>


                                    <option
                                        value="manager"
                                        <?= $role === 'manager'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Manager
                                    </option>


                                    <option
                                        value="cashier"
                                        <?= $role === 'cashier'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Cashier
                                    </option>

                                </select>

                            </div>


                            <!-- Status -->

                            <div class="col-12 col-sm-6 col-lg-2">

                                <label
                                    for="status"
                                    class="form-label fw-semibold"
                                >
                                    Status
                                </label>


                                <select
                                    id="status"
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Status
                                    </option>


                                    <option
                                        value="active"
                                        <?= $status === 'active'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Active
                                    </option>


                                    <option
                                        value="inactive"
                                        <?= $status === 'inactive'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>


                            <!-- Buttons -->

                            <div
                                class="col-12 col-lg-2 d-flex gap-2"
                            >

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >

                                    <i class="bi bi-search me-1"></i>

                                    Search

                                </button>


                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary"
                                    title="Clear filters"
                                >

                                    <i class="bi bi-arrow-clockwise"></i>

                                </a>

                            </div>


                        </div>

                    </form>

                </div>

            </div>


            <!-- =========================================================
                 USERS TABLE
                 ========================================================= -->

            <div class="card border-0 shadow-sm">


                <div class="card-header bg-white border-0 py-3">


                    <div
                        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
                    >


                        <div>

                            <h5 class="fw-bold mb-1">

                                <i class="bi bi-people me-2"></i>

                                System Users

                            </h5>


                            <small class="text-muted">

                                Showing

                                <strong>
                                    <?= count($users) ?>
                                </strong>

                                user(s).

                            </small>

                        </div>


                        <div class="d-flex gap-2 flex-wrap">

                            <span class="badge text-bg-danger">

                                <?= $adminUsers ?> Admin

                            </span>


                            <span class="badge text-bg-primary">

                                <?= $managerUsers ?> Manager

                            </span>


                            <span class="badge text-bg-info">

                                <?= $cashierUsers ?> Cashier

                            </span>

                        </div>

                    </div>

                </div>


                <div class="card-body p-0">


                    <?php if (empty($users)): ?>


                        <!-- =================================================
                             EMPTY STATE
                             ================================================= -->

                        <div
                            class="text-center py-5 px-3"
                        >

                            <div class="fs-1 text-muted mb-3">

                                <i class="bi bi-people"></i>

                            </div>


                            <h5 class="fw-bold">
                                No Users Found
                            </h5>


                            <p class="text-muted mb-4">

                                No users match your current
                                search or filters.

                            </p>


                            <a
                                href="add.php"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-person-plus me-1"></i>

                                Add First User

                            </a>

                        </div>


                    <?php else: ?>


                        <!-- =================================================
                             RESPONSIVE TABLE
                             ================================================= -->

                        <div class="table-responsive">


                            <table
                                class="table table-hover align-middle mb-0"
                            >


                                <thead class="table-light">

                                    <tr>

                                        <th class="ps-4">
                                            #
                                        </th>

                                        <th>
                                            User
                                        </th>

                                        <th>
                                            Username
                                        </th>

                                        <th>
                                            Email
                                        </th>

                                        <th>
                                            Role
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Created
                                        </th>

                                        <th class="text-end pe-4">
                                            Actions
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach ($users as $user): ?>


                                        <?php

                                        $userId =
                                            (int) $user['id'];

                                        $isCurrentUser =
                                            $userId === $currentUserId;

                                        ?>


                                        <tr>


                                            <!-- ID -->

                                            <td class="ps-4">

                                                <span class="text-muted">

                                                    #<?= $userId ?>

                                                </span>

                                            </td>


                                            <!-- USER -->

                                            <td>

                                                <div
                                                    class="d-flex align-items-center gap-3"
                                                >


                                                    <div
                                                        class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                                                        style="width: 42px; height: 42px;"
                                                    >

                                                        <i
                                                            class="bi bi-person fs-5"
                                                        ></i>

                                                    </div>


                                                    <div>

                                                        <div class="fw-semibold">

                                                            <?= htmlspecialchars(
                                                                $user['full_name'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>


                                                            <?php if ($isCurrentUser): ?>

                                                                <span
                                                                    class="badge text-bg-dark ms-1"
                                                                >
                                                                    You
                                                                </span>

                                                            <?php endif; ?>

                                                        </div>


                                                        <small class="text-muted">

                                                            User ID:
                                                            <?= $userId ?>

                                                        </small>

                                                    </div>

                                                </div>

                                            </td>


                                            <!-- USERNAME -->

                                            <td>

                                                <code>

                                                    <?= htmlspecialchars(
                                                        $user['username'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </code>

                                            </td>


                                            <!-- EMAIL -->

                                            <td>

                                                <?php if (!empty($user['email'])): ?>

                                                    <a
                                                        href="mailto:<?= htmlspecialchars(
                                                            $user['email'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        class="text-decoration-none"
                                                    >

                                                        <i
                                                            class="bi bi-envelope me-1"
                                                        ></i>

                                                        <?= htmlspecialchars(
                                                            $user['email'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>

                                                    </a>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        Not provided
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- ROLE -->

                                            <td>

                                                <?= userRoleBadge(
                                                    $user['role']
                                                ) ?>

                                            </td>


                                            <!-- STATUS -->

                                            <td>

                                                <?= userStatusBadge(
                                                    $user['status']
                                                ) ?>

                                            </td>


                                            <!-- CREATED -->

                                            <td>

                                                <?php

                                                $createdTimestamp =
                                                    strtotime(
                                                        $user['created_at']
                                                    );

                                                ?>

                                                <div>

                                                    <?= $createdTimestamp
                                                        ? date(
                                                            'd M Y',
                                                            $createdTimestamp
                                                        )
                                                        : 'N/A'
                                                    ?>

                                                </div>


                                                <?php if ($createdTimestamp): ?>

                                                    <small class="text-muted">

                                                        <?= date(
                                                            'h:i A',
                                                            $createdTimestamp
                                                        ) ?>

                                                    </small>

                                                <?php endif; ?>

                                            </td>


                                            <!-- ACTIONS -->

                                            <td class="text-end pe-4">


                                                <div
                                                    class="d-inline-flex gap-1"
                                                >


                                                    <!-- EDIT -->

                                                    <a
                                                        href="edit.php?id=<?= $userId ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit User"
                                                    >

                                                        <i
                                                            class="bi bi-pencil"
                                                        ></i>

                                                    </a>


                                                    <!-- =================================================
                                                         TOGGLE STATUS
                                                         ================================================= -->

                                                    <?php if (!$isCurrentUser): ?>


                                                        <form
                                                            method="POST"
                                                            action="toggle-status.php"
                                                            class="d-inline"
                                                        >

                                                            <!-- CSRF TOKEN -->

                                                            <?= csrfField() ?>


                                                            <input
                                                                type="hidden"
                                                                name="id"
                                                                value="<?= $userId ?>"
                                                            >


                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm <?= $user['status'] === 'active'
                                                                    ? 'btn-outline-warning'
                                                                    : 'btn-outline-success' ?>"
                                                                title="<?= $user['status'] === 'active'
                                                                    ? 'Deactivate User'
                                                                    : 'Activate User' ?>"
                                                            >

                                                                <i
                                                                    class="bi <?= $user['status'] === 'active'
                                                                        ? 'bi-person-dash'
                                                                        : 'bi-person-check' ?>"
                                                                ></i>

                                                            </button>

                                                        </form>


                                                    <?php else: ?>


                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="You cannot change your own status"
                                                        >

                                                            <i
                                                                class="bi bi-person-lock"
                                                            ></i>

                                                        </button>

                                                    <?php endif; ?>


                                                    <!-- =================================================
                                                         DELETE
                                                         ================================================= -->

                                                    <?php if (!$isCurrentUser): ?>


                                                        <form
                                                            method="POST"
                                                            action="delete.php"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                                        >

                                                            <!-- CSRF TOKEN -->

                                                            <?= csrfField() ?>


                                                            <input
                                                                type="hidden"
                                                                name="id"
                                                                value="<?= $userId ?>"
                                                            >


                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm btn-outline-danger"
                                                                title="Delete User"
                                                            >

                                                                <i
                                                                    class="bi bi-trash"
                                                                ></i>

                                                            </button>

                                                        </form>


                                                    <?php else: ?>


                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="You cannot delete your own account"
                                                        >

                                                            <i
                                                                class="bi bi-shield-lock"
                                                            ></i>

                                                        </button>

                                                    <?php endif; ?>


                                                </div>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>

                            </table>

                        </div>


                    <?php endif; ?>


                </div>

            </div>


            <!-- =========================================================
                 SECURITY INFORMATION
                 ========================================================= -->

            <div
                class="alert alert-info mt-4 mb-0"
            >

                <div class="d-flex gap-3">


                    <div class="fs-4">

                        <i class="bi bi-shield-check"></i>

                    </div>


                    <div>

                        <h6 class="fw-bold mb-1">
                            User Management Security
                        </h6>


                        <p class="mb-0">

                            Only administrators can manage
                            system users.

                            Your own account cannot be
                            deactivated or deleted from
                            this page.

                        </p>

                    </div>


                </div>

            </div>


        </div>


    </main>


</div>


<?php

/*
|--------------------------------------------------------------------------
| CLOSE DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$conn->close();

?>


<?php include "../includes/footer.php"; ?>