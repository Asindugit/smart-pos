<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Add Supplier";

$error = "";

$supplierCode = "SUP-" . date("YmdHis");
$name = "";
$companyName = "";
$phone = "";
$email = "";
$address = "";
$status = "active";

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    requireCsrfToken();

    $supplierCode = trim($_POST["supplier_code"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $companyName = trim($_POST["company_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $status = $_POST["status"] ?? "active";

    /*
    |--------------------------------------------------------------------------
    | Input Validation
    |--------------------------------------------------------------------------
    */

    if ($supplierCode === "") {

        $error = "Supplier code is required.";

    } elseif (mb_strlen($supplierCode) > 50) {

        $error = "Supplier code cannot exceed 50 characters.";

    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $supplierCode)) {

        $error = "Supplier code can contain only letters, numbers, dots, underscores and hyphens.";

    } elseif ($name === "") {

        $error = "Supplier name is required.";

    } elseif (mb_strlen($name) > 150) {

        $error = "Supplier name cannot exceed 150 characters.";

    } elseif (mb_strlen($companyName) > 150) {

        $error = "Company name cannot exceed 150 characters.";

    } elseif (mb_strlen($phone) > 30) {

        $error = "Phone number cannot exceed 30 characters.";

    } elseif (
        $phone !== "" &&
        !preg_match('/^[0-9+\-\s().]+$/', $phone)
    ) {

        $error = "Please enter a valid phone number.";

    } elseif (mb_strlen($email) > 150) {

        $error = "Email address cannot exceed 150 characters.";

    } elseif (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = "Please enter a valid email address.";

    } elseif (mb_strlen($address) > 500) {

        $error = "Address cannot exceed 500 characters.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid supplier status.";
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Supplier Code
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $checkStmt = $conn->prepare("
            SELECT id
            FROM suppliers
            WHERE supplier_code = ?
            LIMIT 1
        ");

        if (!$checkStmt) {

            $error = "Unable to validate supplier code.";

        } else {

            $checkStmt->bind_param(
                "s",
                $supplierCode
            );

            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $error = "Supplier code already exists.";
            }

            $checkStmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Supplier
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO suppliers
            (
                supplier_code,
                name,
                company_name,
                phone,
                email,
                address,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {

            $error = "Unable to add supplier.";

        } else {

            $stmt->bind_param(
                "sssssss",
                $supplierCode,
                $name,
                $companyName,
                $phone,
                $email,
                $address,
                $status
            );

            if ($stmt->execute()) {

                $stmt->close();
                $conn->close();

                header(
                    "Location: index.php?success=" .
                    urlencode("Supplier added successfully.")
                );

                exit;

            } else {

                if ($stmt->errno === 1062) {

                    $error = "Supplier code already exists.";

                } else {

                    $error = "Unable to add supplier. Please try again.";
                }
            }

            $stmt->close();
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

            <!-- Page Header -->

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">
                            <i class="bi bi-truck"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            Add Supplier
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Create a new supplier record.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Suppliers
                </a>

            </div>

            <!-- Error -->

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <!-- Form -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 p-4">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">
                            <i class="bi bi-building-add"></i>
                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Supplier Information
                            </h5>

                            <small class="text-muted">
                                Enter the supplier's business and contact information.
                            </small>

                        </div>

                    </div>

                </div>

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action=""
                    >

                        <?= csrfField() ?>

                        <div class="row g-4">

                            <!-- Supplier Code -->

                            <div class="col-12 col-md-6">

                                <label
                                    for="supplier_code"
                                    class="form-label fw-semibold"
                                >
                                    Supplier Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="supplier_code"
                                    name="supplier_code"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $supplierCode,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="50"
                                    required
                                >

                                <div class="form-text">
                                    Letters, numbers, dots, underscores and hyphens only.
                                </div>

                            </div>

                            <!-- Supplier Name -->

                            <div class="col-12 col-md-6">

                                <label
                                    for="name"
                                    class="form-label fw-semibold"
                                >
                                    Supplier Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $name,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="150"
                                    placeholder="Enter supplier name"
                                    required
                                >

                            </div>

                            <!-- Company -->

                            <div class="col-12 col-md-6">

                                <label
                                    for="company_name"
                                    class="form-label fw-semibold"
                                >
                                    Company Name
                                </label>

                                <input
                                    type="text"
                                    id="company_name"
                                    name="company_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $companyName,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="150"
                                    placeholder="Enter company name"
                                >

                            </div>

                            <!-- Phone -->

                            <div class="col-12 col-md-6">

                                <label
                                    for="phone"
                                    class="form-label fw-semibold"
                                >
                                    Phone Number
                                </label>

                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $phone,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="30"
                                    placeholder="Enter phone number"
                                >

                            </div>

                            <!-- Email -->

                            <div class="col-12 col-md-6">

                                <label
                                    for="email"
                                    class="form-label fw-semibold"
                                >
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $email,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="150"
                                    placeholder="supplier@example.com"
                                >

                            </div>

                            <!-- Status -->

                            <div class="col-12 col-md-6">

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

                                    <option
                                        value="active"
                                        <?= $status === "active" ? "selected" : "" ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $status === "inactive" ? "selected" : "" ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <!-- Address -->

                            <div class="col-12">

                                <label
                                    for="address"
                                    class="form-label fw-semibold"
                                >
                                    Address
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    class="form-control"
                                    rows="4"
                                    maxlength="500"
                                    placeholder="Enter supplier address"
                                ><?= htmlspecialchars(
                                    $address,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?></textarea>

                                <div class="form-text">
                                    Maximum 500 characters.
                                </div>

                            </div>

                            <!-- Buttons -->

                            <div class="col-12">

                                <hr class="my-2">

                                <div class="d-flex gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-check-circle me-1"></i>
                                        Save Supplier
                                    </button>

                                    <a
                                        href="index.php"
                                        class="btn btn-outline-secondary"
                                    >
                                        Cancel
                                    </a>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<?php

$conn->close();

?>

<?php include "../includes/footer.php"; ?>