<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Expenses";

function money($value)
{
    return number_format((float) $value, 2);
}

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$errors = [];

/*
|--------------------------------------------------------------------------
| Validate Search
|--------------------------------------------------------------------------
*/

if (strlen($search) > 150) {
    $errors[] = "Search text is too long.";
    $search = substr($search, 0, 150);
}

/*
|--------------------------------------------------------------------------
| Allowed Expense Categories
|--------------------------------------------------------------------------
*/

$allowedCategories = [
    'Rent',
    'Utilities',
    'Salaries',
    'Transport',
    'Maintenance',
    'Supplies',
    'Marketing',
    'Other'
];

if ($category !== '' && !in_array($category, $allowedCategories, true)) {
    $errors[] = "Invalid expense category.";
    $category = '';
}

/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

function validDate($date)
{
    if ($date === '') {
        return true;
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return $dateObject &&
        $dateObject->format('Y-m-d') === $date;
}

if (!validDate($fromDate)) {
    $errors[] = "Invalid start date.";
    $fromDate = '';
}

if (!validDate($toDate)) {
    $errors[] = "Invalid end date.";
    $toDate = '';
}

if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {
    $errors[] = "Start date cannot be after end date.";
}

/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.id,
        e.title,
        e.category,
        e.amount,
        e.expense_date,
        e.description,
        e.created_at,
        u.full_name AS created_by_name
    FROM expenses e
    LEFT JOIN users u
        ON e.created_by = u.id
    WHERE 1 = 1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            e.title LIKE ?
            OR e.category LIKE ?
            OR e.description LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category !== '') {

    $sql .= " AND e.category = ?";

    $params[] = $category;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    $sql .= " AND e.expense_date >= ?";

    $params[] = $fromDate;
    $types .= "s";
}

if ($toDate !== '') {

    $sql .= " AND e.expense_date <= ?";

    $params[] = $toDate;
    $types .= "s";
}

$sql .= "
    ORDER BY
        e.expense_date DESC,
        e.id DESC
";

/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$expenses = [];
$totalAmount = 0;
$averageAmount = 0;

if (empty($errors)) {

    try {

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare expense query.");
        }

        if (!empty($params)) {
            $stmt->bind_param(
                $types,
                ...$params
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to load expenses.");
        }

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $expenses[] = $row;

            $totalAmount += (float) $row['amount'];
        }

        $stmt->close();

        if (count($expenses) > 0) {
            $averageAmount =
                $totalAmount / count($expenses);
        }

    } catch (Throwable $e) {

        error_log(
            "SmartPOS Expenses Index Error: " .
            $e->getMessage()
        );

        $errors[] =
            "Unable to load expenses. Please try again.";
    }
}

/*
|--------------------------------------------------------------------------
| Category List
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categoryResult = $conn->query("
        SELECT DISTINCT category
        FROM expenses
        WHERE category IS NOT NULL
          AND category <> ''
        ORDER BY category ASC
    ");

    if ($categoryResult) {

        while ($row = $categoryResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

} catch (Throwable $e) {

    error_log(
        "SmartPOS Expense Category Error: " .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Close Database
|--------------------------------------------------------------------------
*/

$conn->close();

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">

                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-wallet2"></i>
                        Expenses
                    </h2>

                    <p class="text-muted mb-0">
                        Manage business expenses
                    </p>
                </div>

                <div class="mt-3 mt-md-0">

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus-circle"></i>
                        Add Expense
                    </a>

                </div>

            </div>

            <!-- Alerts -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li><?= e($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>

                <div class="alert alert-success alert-dismissible fade show">

                    <?= e($_GET['success']) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>

                <div class="alert alert-danger alert-dismissible fade show">

                    <?= e($_GET['error']) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <!-- Summary Cards -->

            <div class="row g-3 mb-4">

                <div class="col-md-4">

                    <div class="card shadow-sm border-0 h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>
                                    <p class="text-muted mb-1">
                                        Total Expenses
                                    </p>

                                    <h4 class="fw-bold mb-0">
                                        <?= count($expenses) ?>
                                    </h4>
                                </div>

                                <div class="fs-2 text-primary">
                                    <i class="bi bi-receipt"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-md-4">

                    <div class="card shadow-sm border-0 h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>
                                    <p class="text-muted mb-1">
                                        Total Amount
                                    </p>

                                    <h4 class="fw-bold mb-0">
                                        LKR <?= money($totalAmount) ?>
                                    </h4>
                                </div>

                                <div class="fs-2 text-danger">
                                    <i class="bi bi-cash-stack"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-md-4">

                    <div class="card shadow-sm border-0 h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>
                                    <p class="text-muted mb-1">
                                        Average Expense
                                    </p>

                                    <h4 class="fw-bold mb-0">
                                        LKR <?= money($averageAmount) ?>
                                    </h4>
                                </div>

                                <div class="fs-2 text-warning">
                                    <i class="bi bi-calculator"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Filters -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <form method="GET">

                        <div class="row g-3">

                            <div class="col-md-4">

                                <label class="form-label">
                                    Search
                                </label>

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    value="<?= e($search) ?>"
                                    maxlength="150"
                                    placeholder="Search expenses..."
                                >

                            </div>

                            <div class="col-md-2">

                                <label class="form-label">
                                    Category
                                </label>

                                <select
                                    name="category"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Categories
                                    </option>

                                    <?php foreach ($categories as $cat): ?>

                                        <option
                                            value="<?= e($cat) ?>"
                                            <?= $category === $cat ? 'selected' : '' ?>
                                        >
                                            <?= e($cat) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <label class="form-label">
                                    From Date
                                </label>

                                <input
                                    type="date"
                                    name="from_date"
                                    class="form-control"
                                    value="<?= e($fromDate) ?>"
                                >

                            </div>

                            <div class="col-md-2">

                                <label class="form-label">
                                    To Date
                                </label>

                                <input
                                    type="date"
                                    name="to_date"
                                    class="form-control"
                                    value="<?= e($toDate) ?>"
                                >

                            </div>

                            <div class="col-md-2 d-flex align-items-end">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >
                                    <i class="bi bi-search"></i>
                                    Filter
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

            <!-- Expense Table -->

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white">

                    <h5 class="mb-0 fw-bold">
                        Expense Records
                    </h5>

                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>Date</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Created By</th>
                                    <th class="text-end">
                                        Amount
                                    </th>
                                    <th class="text-center">
                                        Actions
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($expenses)): ?>

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="text-center py-5 text-muted"
                                        >
                                            <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>

                                            No expenses found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($expenses as $expense): ?>

                                        <tr>

                                            <td>

                                                <?php

                                                $displayDate = $expense['expense_date'];

                                                $dateObject = DateTime::createFromFormat(
                                                    'Y-m-d',
                                                    $expense['expense_date']
                                                );

                                                if ($dateObject) {
                                                    $displayDate =
                                                        $dateObject->format('d M Y');
                                                }

                                                ?>

                                                <?= e($displayDate) ?>

                                            </td>

                                            <td>

                                                <strong>
                                                    <?= e($expense['title']) ?>
                                                </strong>

                                            </td>

                                            <td>

                                                <span class="badge bg-secondary">

                                                    <?= e($expense['category']) ?>

                                                </span>

                                            </td>

                                            <td>

                                                <?php

                                                $description =
                                                    trim(
                                                        (string) $expense['description']
                                                    );

                                                if ($description === '') {

                                                    echo '<span class="text-muted">—</span>';

                                                } else {

                                                    if (
                                                        function_exists('mb_strlen') &&
                                                        mb_strlen($description, 'UTF-8') > 80
                                                    ) {

                                                        $description =
                                                            mb_substr(
                                                                $description,
                                                                0,
                                                                80,
                                                                'UTF-8'
                                                            ) . '...';

                                                    } elseif (
                                                        strlen($description) > 80
                                                    ) {

                                                        $description =
                                                            substr(
                                                                $description,
                                                                0,
                                                                80
                                                            ) . '...';
                                                    }

                                                    echo e($description);
                                                }

                                                ?>

                                            </td>

                                            <td>

                                                <?= e(
                                                    $expense['created_by_name']
                                                    ?? 'System'
                                                ) ?>

                                            </td>

                                            <td class="text-end fw-bold">

                                                LKR
                                                <?= money($expense['amount']) ?>

                                            </td>

                                            <td class="text-center">

                                                <a
                                                    href="edit.php?id=<?= (int) $expense['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit Expense"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="delete.php"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this expense?');"
                                                >

                                                    <?= csrfField() ?>

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $expense['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete Expense"
                                                    >
                                                        <i class="bi bi-trash"></i>
                                                    </button>

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php include "../includes/footer.php"; ?>