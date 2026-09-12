<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Add Category";

$name = "";
$description = "";
$status = "active";

$errors = [];


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    requireCsrfToken();

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $status = $_POST["status"] ?? "active";


    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */

    if ($name === "") {

        $errors[] = "Category name is required.";

    } elseif (strlen($name) < 2) {

        $errors[] =
            "Category name must contain at least 2 characters.";

    } elseif (strlen($name) > 100) {

        $errors[] =
            "Category name cannot exceed 100 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Description
    |--------------------------------------------------------------------------
    */

    if (strlen($description) > 1000) {

        $errors[] =
            "Description cannot exceed 1000 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $status,
        ["active", "inactive"],
        true
    )) {

        $errors[] = "Invalid category status.";

    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $errors[] =
                "Unable to validate category. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $name
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $errors[] =
                    "A category with this name already exists.";

            }

            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Insert Category
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $conn->prepare("
            INSERT INTO categories
            (
                name,
                description,
                status
            )
            VALUES (?, ?, ?)
        ");

        if (!$stmt) {

            $errors[] =
                "Unable to save category. Please try again.";

        } else {

            $stmt->bind_param(
                "sss",
                $name,
                $description,
                $status
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: index.php?success=" .
                    urlencode("Category added successfully.")
                );

                exit;

            }

            /*
            |--------------------------------------------------------------
            | Handle Duplicate Database Constraint
            |--------------------------------------------------------------
            */

            if ($stmt->errno === 1062) {

                $errors[] =
                    "A category with this name already exists.";

            } else {

                $errors[] =
                    "Failed to add category. Please try again.";

            }

            $stmt->close();

        }

    }

}


include "../includes/header.php";
include "../includes/navbar.php";

?>

<div class="main-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- PAGE HEADER -->

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >

                <div>

                    <h2 class="fw-bold mb-1">
                        Add Category
                    </h2>

                    <p class="text-muted mb-0">
                        Create a new product category
                    </p>

                </div>

                <div>

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back to Categories

                    </a>

                </div>

            </div>


            <!-- ERRORS -->

            <?php if (!empty($errors)): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <div class="fw-semibold mb-2">

                        <i
                            class="bi bi-exclamation-triangle me-2"
                        ></i>

                        Please fix the following:

                    </div>

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li>

                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- FORM -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <div
                        class="d-flex align-items-center gap-2"
                    >

                        <div
                            class="section-icon section-icon-blue"
                        >

                            <i class="bi bi-tags"></i>

                        </div>

                        <div>

                            <h5 class="mb-0 fw-semibold">
                                Category Information
                            </h5>

                            <small class="text-muted">
                                Enter the details for your new category
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

                            <!-- NAME -->

                            <div class="col-12 col-md-8">

                                <label
                                    for="name"
                                    class="form-label fw-semibold"
                                >

                                    Category Name

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="Example: Beverages"
                                    maxlength="100"
                                    required
                                    autofocus
                                >

                                <div class="form-text">
                                    Enter a unique category name.
                                </div>

                            </div>


                            <!-- STATUS -->

                            <div class="col-12 col-md-4">

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
                                        <?= $status === "active"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $status === "inactive"
                                            ? "selected"
                                            : "" ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                                <div class="form-text">
                                    Inactive categories can be disabled from use.
                                </div>

                            </div>


                            <!-- DESCRIPTION -->

                            <div class="col-12">

                                <label
                                    for="description"
                                    class="form-label fw-semibold"
                                >

                                    Description

                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    class="form-control"
                                    rows="5"
                                    maxlength="1000"
                                    placeholder="Enter a short description..."
                                ><?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                                <div class="form-text">
                                    Optional. Maximum 1000 characters.
                                </div>

                            </div>

                        </div>


                        <!-- BUTTONS -->

                        <div
                            class="d-flex flex-column flex-sm-row gap-2 mt-4 pt-4 border-top"
                        >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-check-lg me-1"></i>

                                Save Category

                            </button>

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-x-lg me-1"></i>

                                Cancel

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>


<?php

$conn->close();

include "../includes/footer.php";

?>