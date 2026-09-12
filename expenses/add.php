<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Add Expense";

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
| Allowed Categories
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

/*
|--------------------------------------------------------------------------
| Form Defaults
|--------------------------------------------------------------------------
*/

$title = '';
$category = '';
$amount = '';
$expenseDate = date('Y-m-d');
$description = '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireCsrfToken();

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $expenseDate = trim($_POST['expense_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Title Validation
    |--------------------------------------------------------------------------
    */

    if ($title === '') {

        $errors[] = "Expense title is required.";

    } elseif (
        function_exists('mb_strlen')
            ? mb_strlen($title, 'UTF-8') > 150
            : strlen($title) > 150
    ) {

        $errors[] =
            "Expense title must not exceed 150 characters.";
    }

    /*
    |--------------------------------------------------------------------------
    | Category Validation
    |--------------------------------------------------------------------------
    */

    if ($category === '') {

        $errors[] = "Expense category is required.";

    } elseif (
        !in_array(
            $category,
            $allowedCategories,
            true
        )
    ) {

        $errors[] = "Invalid expense category.";
    }

    /*
    |--------------------------------------------------------------------------
    | Amount Validation
    |--------------------------------------------------------------------------
    */

    if ($amount === '') {

        $errors[] = "Expense amount is required.";

    } elseif (!is_numeric($amount)) {

        $errors[] = "Expense amount must be a valid number.";

    } elseif (!is_finite((float) $amount)) {

        $errors[] = "Expense amount is invalid.";

    } elseif ((float) $amount <= 0) {

        $errors[] = "Expense amount must be greater than zero.";

    } elseif ((float) $amount > 9999999999.99) {

        $errors[] =
            "Expense amount is too large.";

    } elseif (
        preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $amount
        ) !== 1
    ) {

        $errors[] =
            "Expense amount can have a maximum of 2 decimal places.";
    }

    /*
    |--------------------------------------------------------------------------
    | Date Validation
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $expenseDate
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $expenseDate
    ) {

        $errors[] =
            "Please enter a valid expense date.";
    }

    /*
    |--------------------------------------------------------------------------
    | Description Validation
    |--------------------------------------------------------------------------
    */

    if (
        function_exists('mb_strlen')
            ? mb_strlen($description, 'UTF-8') > 500
            : strlen($description) > 500
    ) {

        $errors[] =
            "Description must not exceed 500 characters.";
    }

    /*
    |--------------------------------------------------------------------------
    | Save Expense
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $createdBy = filter_var(
            $_SESSION['user_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$createdBy || $createdBy <= 0) {

            $errors[] =
                "Invalid user session. Please log in again.";

        } else {

            $amountValue = round(
                (float) $amount,
                2
            );

            try {

                $stmt = $conn->prepare("
                    INSERT INTO expenses
                    (
                        title,
                        category,
                        amount,
                        expense_date,
                        description,
                        created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Failed to prepare expense insert."
                    );
                }

                $stmt->bind_param(
                    "ssdssi",
                    $title,
                    $category,
                    $amountValue,
                    $expenseDate,
                    $description,
                    $createdBy
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Failed to save expense."
                    );
                }

                $stmt->close();

                $conn->close();

                header(
                    "Location: index.php?success=" .
                    urlencode(
                        "Expense added successfully."
                    )
                );

                exit;

            } catch (Throwable $e) {

                error_log(
                    "SmartPOS Expense Add Error: " .
                    $e->getMessage()
                );

                $errors[] =
                    "Unable to save the expense. Please try again.";
            }
        }
    }
}

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

                        Add Expense

                    </h2>

                    <p class="text-muted mb-0">
                        Record a new business expense
                    </p>

                </div>

                <div class="mt-3 mt-md-0">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Back to Expenses
                    </a>

                </div>

            </div>

            <!-- Errors -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li><?= e($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <!-- Form -->

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white">

                    <h5 class="fw-bold mb-0">
                        Expense Information
                    </h5>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <?= csrfField() ?>

                        <div class="row g-3">

                            <!-- Title -->

                            <div class="col-md-6">

                                <label class="form-label">
                                    Expense Title
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="title"
                                    class="form-control"
                                    value="<?= e($title) ?>"
                                    maxlength="150"
                                    required
                                    placeholder="e.g. Electricity Bill"
                                >

                            </div>

                            <!-- Category -->

                            <div class="col-md-6">

                                <label class="form-label">
                                    Category
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="category"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select Category
                                    </option>

                                    <?php foreach ($allowedCategories as $item): ?>

                                        <option
                                            value="<?= e($item) ?>"
                                            <?= $category === $item ? 'selected' : '' ?>
                                        >
                                            <?= e($item) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- Amount -->

                            <div class="col-md-6">

                                <label class="form-label">
                                    Amount
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        LKR
                                    </span>

                                    <input
                                        type="text"
                                        name="amount"
                                        class="form-control"
                                        value="<?= e($amount) ?>"
                                        inputmode="decimal"
                                        maxlength="15"
                                        required
                                        placeholder="0.00"
                                    >

                                </div>

                                <small class="text-muted">
                                    Maximum 2 decimal places.
                                </small>

                            </div>

                            <!-- Date -->

                            <div class="col-md-6">

                                <label class="form-label">
                                    Expense Date
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    name="expense_date"
                                    class="form-control"
                                    value="<?= e($expenseDate) ?>"
                                    required
                                >

                            </div>

                            <!-- Description -->

                            <div class="col-12">

                                <label class="form-label">
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                    class="form-control"
                                    rows="4"
                                    maxlength="500"
                                    placeholder="Enter additional details..."
                                ><?= e($description) ?></textarea>

                                <small class="text-muted">
                                    Maximum 500 characters.
                                </small>

                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-check-circle"></i>
                                Save Expense
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<?php include "../includes/footer.php"; ?>