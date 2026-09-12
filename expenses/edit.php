<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Edit Expense";

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
| Validate Expense ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Invalid expense ID.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Form Defaults
|--------------------------------------------------------------------------
*/

$title = '';
$category = '';
$amount = '';
$expenseDate = '';
$description = '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Load Expense
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            category,
            amount,
            expense_date,
            description
        FROM expenses
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare expense lookup."
        );
    }

    $stmt->bind_param(
        "i",
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Failed to load expense."
        );
    }

    $result = $stmt->get_result();

    $expense = $result->fetch_assoc();

    $stmt->close();

    if (!$expense) {

        $conn->close();

        header(
            "Location: index.php?error=" .
            urlencode("Expense not found.")
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Populate Form
    |--------------------------------------------------------------------------
    */

    $title = $expense['title'];
    $category = $expense['category'];
    $amount = $expense['amount'];
    $expenseDate = $expense['expense_date'];
    $description = $expense['description'] ?? '';

} catch (Throwable $e) {

    error_log(
        "SmartPOS Expense Edit Load Error: " .
        $e->getMessage()
    );

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to load expense.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Process Update
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

        $errors[] =
            "Expense title is required.";

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

        $errors[] =
            "Expense category is required.";

    } elseif (
        !in_array(
            $category,
            $allowedCategories,
            true
        )
    ) {

        $errors[] =
            "Invalid expense category.";
    }

    /*
    |--------------------------------------------------------------------------
    | Amount Validation
    |--------------------------------------------------------------------------
    */

    if ($amount === '') {

        $errors[] =
            "Expense amount is required.";

    } elseif (!is_numeric($amount)) {

        $errors[] =
            "Expense amount must be a valid number.";

    } elseif (!is_finite((float) $amount)) {

        $errors[] =
            "Expense amount is invalid.";

    } elseif ((float) $amount <= 0) {

        $errors[] =
            "Expense amount must be greater than zero.";

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
    | Update Database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $amountValue = round(
            (float) $amount,
            2
        );

        try {

            $stmt = $conn->prepare("
                UPDATE expenses
                SET
                    title = ?,
                    category = ?,
                    amount = ?,
                    expense_date = ?,
                    description = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare expense update."
                );
            }

            $stmt->bind_param(
                "ssdssi",
                $title,
                $category,
                $amountValue,
                $expenseDate,
                $description,
                $id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Failed to update expense."
                );
            }

            $stmt->close();

            $conn->close();

            header(
                "Location: index.php?success=" .
                urlencode(
                    "Expense updated successfully."
                )
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                "SmartPOS Expense Edit Error: " .
                $e->getMessage()
            );

            $errors[] =
                "Unable to update the expense. Please try again.";
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

                        <i class="bi bi-pencil-square"></i>

                        Edit Expense

                    </h2>

                    <p class="text-muted mb-0">
                        Update expense information
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

            <!-- Edit Form -->

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
                                <i class="bi bi-save"></i>
                                Update Expense
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<?php include "../includes/footer.php"; ?>