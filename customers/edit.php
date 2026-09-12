<?php

require_once "../config/auth.php";
requireLogin();

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Edit Customer";

$error = "";

/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid customer ID.")
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        customer_code,
        name,
        phone,
        email,
        address,
        status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Unable to load customer.")
    );

    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=" .
        urlencode("Customer not found.")
    );

    exit;
}

$customer = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$customerCode = $customer["customer_code"];
$name = $customer["name"];
$phone = $customer["phone"];
$email = $customer["email"];
$address = $customer["address"];
$status = $customer["status"];

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    requireCsrfToken();

    $customerCode = trim($_POST["customer_code"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $status = $_POST["status"] ?? "active";

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($customerCode === "") {

        $error = "Customer code is required.";

    } elseif (strlen($customerCode) > 50) {

        $error = "Customer code cannot exceed 50 characters.";

    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $customerCode)) {

        $error = "Customer code contains invalid characters.";

    } elseif ($name === "") {

        $error = "Customer name is required.";

    } elseif (strlen($name) < 2) {

        $error = "Customer name must contain at least 2 characters.";

    } elseif (strlen($name) > 100) {

        $error = "Customer name cannot exceed 100 characters.";

    } elseif (strlen($phone) > 30) {

        $error = "Phone number cannot exceed 30 characters.";

    } elseif ($email !== "" && strlen($email) > 150) {

        $error = "Email address cannot exceed 150 characters.";

    } elseif (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($address) > 500) {

        $error = "Address cannot exceed 500 characters.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid customer status.";

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Customer Code
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $checkStmt = $conn->prepare("
            SELECT id
            FROM customers
            WHERE customer_code = ?
              AND id != ?
            LIMIT 1
        ");

        if (!$checkStmt) {

            $error = "Unable to validate customer code.";

        } else {

            $checkStmt->bind_param(
                "si",
                $customerCode,
                $id
            );

            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $error = "Customer code already exists.";
            }

            $checkStmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Customer
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmt = $conn->prepare("
            UPDATE customers
            SET
                customer_code = ?,
                name = ?,
                phone = ?,
                email = ?,
                address = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$stmt) {

            $error = "Unable to prepare customer update.";

        } else {

            $stmt->bind_param(
                "ssssssi",
                $customerCode,
                $name,
                $phone,
                $email,
                $address,
                $status,
                $id
            );

            if ($stmt->execute()) {

                $stmt->close();
                $conn->close();

                header(
                    "Location: index.php?success=" .
                    urlencode("Customer updated successfully.")
                );

                exit;

            } else {

                if ($conn->errno === 1062) {
                    $error = "Customer code already exists.";
                } else {
                    $error = "Unable to update customer. Please try again.";
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

            <div class="dashboard-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <span class="dashboard-title-icon">
                            <i class="bi bi-pencil-square"></i>
                        </span>

                        <h3 class="mb-0 fw-bold">
                            Edit Customer
                        </h3>

                    </div>

                    <p class="text-muted mb-0">
                        Update customer information.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Customers
                </a>

            </div>


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


            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 p-4">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon section-icon-blue">
                            <i class="bi bi-person-vcard"></i>
                        </div>

                        <div>

                            <h5 class="mb-1 fw-bold">
                                Customer Information
                            </h5>

                            <small class="text-muted">
                                Update the selected customer's information.
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

                            <div class="col-12 col-md-6">

                                <label
                                    for="customer_code"
                                    class="form-label fw-semibold"
                                >
                                    Customer Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="customer_code"
                                    name="customer_code"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $customerCode,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    maxlength="50"
                                    required
                                >

                            </div>


                            <div class="col-12 col-md-6">

                                <label
                                    for="name"
                                    class="form-label fw-semibold"
                                >
                                    Customer Name
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
                                    maxlength="100"
                                    required
                                >

                            </div>


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
                                >

                            </div>


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
                                >

                            </div>


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
                                ><?= htmlspecialchars(
                                    $address,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?></textarea>

                            </div>


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


                            <div class="col-12">

                                <hr class="my-2">

                                <div class="d-flex gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-check-circle me-1"></i>
                                        Update Customer
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