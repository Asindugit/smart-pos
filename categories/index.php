<?php

require_once "../config/auth.php";
requireRole(['admin', 'manager']);

require_once "../config/database.php";
require_once "../config/csrf.php";

$pageTitle = "Categories";

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Validate Search
|--------------------------------------------------------------------------
*/

if (strlen($search) > 150) {
    $search = substr($search, 0, 150);
}

/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            status,
            created_at
        FROM categories
        WHERE name LIKE ?
           OR description LIKE ?
        ORDER BY id DESC
    ");

    if (!$stmt) {
        die("Unable to load categories.");
    }

    $searchTerm = "%" . $search . "%";

    $stmt->bind_param(
        "ss",
        $searchTerm,
        $searchTerm
    );

    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query("
        SELECT
            id,
            name,
            description,
            status,
            created_at
        FROM categories
        ORDER BY id DESC
    ");

    if (!$result) {
        die("Unable to load categories.");
    }

}

?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

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
                        Categories
                    </h2>

                    <p class="text-muted mb-0">
                        Manage your product categories
                    </p>

                </div>

                <div>

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-plus-lg me-1"></i>

                        Add Category

                    </a>

                </div>

            </div>


            <!-- SUCCESS MESSAGE -->

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


            <!-- ERROR MESSAGE -->

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


            <!-- SEARCH -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <form
                        method="GET"
                        class="row g-2"
                    >

                        <div class="col-12 col-md-8">

                            <div class="input-group">

                                <span class="input-group-text bg-white">

                                    <i class="bi bi-search"></i>

                                </span>

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Search categories..."
                                    value="<?= htmlspecialchars(
                                        $search,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    maxlength="150"
                                >

                            </div>

                        </div>

                        <div class="col-6 col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >

                                <i class="bi bi-search me-1"></i>

                                Search

                            </button>

                        </div>

                        <div class="col-6 col-md-2">

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary w-100"
                            >

                                <i
                                    class="bi bi-arrow-counterclockwise me-1"
                                ></i>

                                Reset

                            </a>

                        </div>

                    </form>

                </div>

            </div>


            <!-- CATEGORY LIST -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div class="d-flex align-items-center gap-2">

                            <div
                                class="section-icon section-icon-blue"
                            >

                                <i class="bi bi-tags"></i>

                            </div>

                            <div>

                                <h5 class="mb-0 fw-semibold">
                                    Category List
                                </h5>

                                <small class="text-muted">
                                    Manage all product categories
                                </small>

                            </div>

                        </div>

                        <span class="badge bg-light text-dark">

                            <?= $result->num_rows ?>

                            <?= $result->num_rows === 1
                                ? 'Category'
                                : 'Categories'
                            ?>

                        </span>

                    </div>

                </div>


                <div class="card-body p-0">

                    <?php if ($result->num_rows > 0): ?>

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
                                            Category Name
                                        </th>

                                        <th>
                                            Description
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

                                    <?php

                                    $number = 1;

                                    while (
                                        $category = $result->fetch_assoc()
                                    ):

                                    ?>

                                        <tr>

                                            <td class="ps-4">

                                                <?= $number++ ?>

                                            </td>


                                            <td>

                                                <div
                                                    class="d-flex align-items-center gap-2"
                                                >

                                                    <div
                                                        class="section-icon section-icon-blue"
                                                        style="
                                                            width: 36px;
                                                            height: 36px;
                                                            font-size: 15px;
                                                        "
                                                    >

                                                        <i class="bi bi-tag"></i>

                                                    </div>

                                                    <div>

                                                        <div
                                                            class="fw-semibold"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $category['name'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </td>


                                            <td>

                                                <?php

                                                $description =
                                                    $category['description'];

                                                if ($description) {

                                                    echo htmlspecialchars(
                                                        strlen($description) > 60
                                                            ? substr(
                                                                $description,
                                                                0,
                                                                60
                                                            ) . "..."
                                                            : $description,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );

                                                } else {

                                                    echo '<span class="text-muted">No description</span>';

                                                }

                                                ?>

                                            </td>


                                            <td>

                                                <?php if (
                                                    $category['status'] === 'active'
                                                ): ?>

                                                    <span
                                                        class="badge bg-success-subtle text-success"
                                                    >

                                                        <i
                                                            class="bi bi-check-circle me-1"
                                                        ></i>

                                                        Active

                                                    </span>

                                                <?php else: ?>

                                                    <span
                                                        class="badge bg-secondary-subtle text-secondary"
                                                    >

                                                        <i
                                                            class="bi bi-dash-circle me-1"
                                                        ></i>

                                                        Inactive

                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <span class="text-muted">

                                                    <?= htmlspecialchars(
                                                        date(
                                                            'd M Y',
                                                            strtotime(
                                                                $category['created_at']
                                                            )
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>

                                            </td>


                                            <!-- ACTIONS -->

                                            <td class="text-end pe-4">

                                                <div
                                                    class="btn-group"
                                                    role="group"
                                                >

                                                    <!-- EDIT -->

                                                    <a
                                                        href="edit.php?id=<?= (int) $category['id'] ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit Category"
                                                    >

                                                        <i class="bi bi-pencil"></i>

                                                    </a>


                                                    <!-- DELETE - ADMIN ONLY -->

                                                    <?php if (
                                                        $_SESSION['user_role'] === 'admin'
                                                    ): ?>

                                                        <form
                                                            method="POST"
                                                            action="delete.php"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this category?');"
                                                        >

                                                            <?= csrfField() ?>

                                                            <input
                                                                type="hidden"
                                                                name="id"
                                                                value="<?= (int) $category['id'] ?>"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm btn-outline-danger"
                                                                title="Delete Category"
                                                            >

                                                                <i
                                                                    class="bi bi-trash"
                                                                ></i>

                                                            </button>

                                                        </form>

                                                    <?php endif; ?>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <!-- EMPTY STATE -->

                        <div class="text-center py-5">

                            <div class="empty-state">

                                <div class="empty-icon mb-3">

                                    <i class="bi bi-tags"></i>

                                </div>

                                <?php if ($search !== ''): ?>

                                    <h5 class="fw-semibold">
                                        No categories found
                                    </h5>

                                    <p class="text-muted mb-3">

                                        No categories match your search:

                                        <strong>
                                            <?= htmlspecialchars(
                                                $search,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                    </p>

                                    <a
                                        href="index.php"
                                        class="btn btn-outline-secondary me-2"
                                    >

                                        <i
                                            class="bi bi-arrow-counterclockwise me-1"
                                        ></i>

                                        Clear Search

                                    </a>

                                    <a
                                        href="add.php"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-plus-lg me-1"></i>

                                        Add Category

                                    </a>

                                <?php else: ?>

                                    <h5 class="fw-semibold">
                                        No categories found
                                    </h5>

                                    <p class="text-muted mb-3">
                                        Start by adding your first category.
                                    </p>

                                    <a
                                        href="add.php"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-plus-lg me-1"></i>

                                        Add Category

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>


<?php

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}

$conn->close();

include "../includes/footer.php";

?>