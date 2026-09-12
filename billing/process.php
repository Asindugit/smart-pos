<?php

/*
|--------------------------------------------------------------------------
| TIMEZONE
|--------------------------------------------------------------------------
| Sri Lanka Standard Time
|--------------------------------------------------------------------------
*/

date_default_timezone_set("Asia/Colombo");


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once "../config/auth.php";
requireLogin();


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): void {

    http_response_code($statusCode);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode(
        [
            "success" => $success,
            "message" => $message,
            "data" => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ONLY POST REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    jsonResponse(
        false,
        "Invalid request method.",
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| READ JSON REQUEST
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents("php://input");

if ($rawInput === false || trim($rawInput) === "") {

    jsonResponse(
        false,
        "No request data received.",
        [],
        400
    );
}


$data = json_decode(
    $rawInput,
    true
);


if (!is_array($data)) {

    jsonResponse(
        false,
        "Invalid JSON request.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| GET CART ITEMS
|--------------------------------------------------------------------------
*/

$items = $data["items"] ?? [];

if (!is_array($items) || empty($items)) {

    jsonResponse(
        false,
        "Please add at least one product to the cart.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| CUSTOMER ID
|--------------------------------------------------------------------------
*/

$customerId = $data["customer_id"] ?? null;


/*
|--------------------------------------------------------------------------
| WALK-IN CUSTOMER
|--------------------------------------------------------------------------
*/

if (
    $customerId === null ||
    $customerId === "" ||
    $customerId === "0" ||
    $customerId === 0
) {

    $customerId = null;

} else {

    $customerId = filter_var(
        $customerId,
        FILTER_VALIDATE_INT
    );


    if (
        $customerId === false ||
        $customerId <= 0
    ) {

        jsonResponse(
            false,
            "Invalid customer.",
            [],
            400
        );
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD
|--------------------------------------------------------------------------
*/

$paymentMethod = strtolower(
    trim(
        (string) (
            $data["payment_method"] ?? "cash"
        )
    )
);


/*
|--------------------------------------------------------------------------
| ALLOWED PAYMENT METHODS
|--------------------------------------------------------------------------
*/

$allowedPaymentMethods = [
    "cash",
    "card",
    "bank_transfer",
    "mobile",
    "other"
];


if (!in_array(
    $paymentMethod,
    $allowedPaymentMethods,
    true
)) {

    jsonResponse(
        false,
        "Invalid payment method.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| DISCOUNT
|--------------------------------------------------------------------------
*/

$discount = isset($data["discount"])
    ? (float) $data["discount"]
    : 0.00;


/*
|--------------------------------------------------------------------------
| TAX
|--------------------------------------------------------------------------
*/

$tax = isset($data["tax"])
    ? (float) $data["tax"]
    : 0.00;


/*
|--------------------------------------------------------------------------
| PREVENT NEGATIVE VALUES
|--------------------------------------------------------------------------
*/

if ($discount < 0) {
    $discount = 0.00;
}


if ($tax < 0) {
    $tax = 0.00;
}


/*
|--------------------------------------------------------------------------
| CASHIER
|--------------------------------------------------------------------------
*/

$cashierId = $_SESSION["user_id"] ?? null;


if (!$cashierId) {

    jsonResponse(
        false,
        "Your session has expired. Please login again.",
        [],
        401
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE CUSTOMER
|--------------------------------------------------------------------------
*/

if ($customerId !== null) {

    $customerStmt = $conn->prepare(
        "SELECT id
         FROM customers
         WHERE id = ?
         LIMIT 1"
    );


    if (!$customerStmt) {

        jsonResponse(
            false,
            "Unable to validate customer.",
            [],
            500
        );
    }


    $customerStmt->bind_param(
        "i",
        $customerId
    );


    $customerStmt->execute();


    $customerResult =
        $customerStmt->get_result();


    if ($customerResult->num_rows === 0) {

        $customerStmt->close();

        jsonResponse(
            false,
            "Selected customer was not found.",
            [],
            400
        );
    }


    $customerStmt->close();
}


/*
|--------------------------------------------------------------------------
| BEGIN TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | PRODUCT QUERY
    |--------------------------------------------------------------------------
    |
    | Exact columns from your products table:
    |
    | id
    | sku
    | barcode
    | name
    | description
    | purchase_price
    | selling_price
    | stock_quantity
    | reorder_level
    | unit
    | image
    | status
    | created_at
    | updated_at
    |
    */

    $productStmt = $conn->prepare(
        "SELECT
            id,
            name,
            purchase_price,
            selling_price,
            stock_quantity,
            status
         FROM products
         WHERE id = ?
         AND status = 'active'
         FOR UPDATE"
    );


    if (!$productStmt) {

        throw new Exception(
            "Unable to prepare product query."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATED ITEMS
    |--------------------------------------------------------------------------
    */

    $validatedItems = [];


    /*
    |--------------------------------------------------------------------------
    | SUBTOTAL
    |--------------------------------------------------------------------------
    */

    $subtotal = 0.00;


    /*
    |--------------------------------------------------------------------------
    | PROCESS EACH PRODUCT
    |--------------------------------------------------------------------------
    */

    foreach ($items as $item) {

        /*
        |--------------------------------------------------------------------------
        | PRODUCT ID
        |--------------------------------------------------------------------------
        */

        $productId =
            $item["id"] ?? null;


        $productId =
            filter_var(
                $productId,
                FILTER_VALIDATE_INT
            );


        if (
            $productId === false ||
            $productId <= 0
        ) {

            throw new Exception(
                "Invalid product ID."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | QUANTITY
        |--------------------------------------------------------------------------
        */

        $quantity =
            $item["quantity"] ?? 0;


        if (
            !is_numeric($quantity) ||
            (float)$quantity <= 0
        ) {

            throw new Exception(
                "Invalid quantity for product ID " .
                $productId .
                "."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | POS USES WHOLE QUANTITY
        |--------------------------------------------------------------------------
        */

        $quantity = (int)$quantity;


        if ($quantity <= 0) {

            throw new Exception(
                "Quantity must be at least 1."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET PRODUCT
        |--------------------------------------------------------------------------
        */

        $productStmt->bind_param(
            "i",
            $productId
        );


        $productStmt->execute();


        $productResult =
            $productStmt->get_result();


        if ($productResult->num_rows === 0) {

            throw new Exception(
                "Product ID " .
                $productId .
                " was not found or is inactive."
            );
        }


        $product =
            $productResult->fetch_assoc();


        /*
        |--------------------------------------------------------------------------
        | PRODUCT NAME
        |--------------------------------------------------------------------------
        */

        $productName =
            (string)$product["name"];


        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        $availableStock =
            (int)$product["stock_quantity"];


        if ($availableStock < $quantity) {

            throw new Exception(
                "Insufficient stock for \"" .
                $productName .
                "\". " .
                "Available stock: " .
                $availableStock .
                ", requested: " .
                $quantity .
                "."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SELLING PRICE
        |--------------------------------------------------------------------------
        */

        $unitPrice =
            (float)$product["selling_price"];


        if ($unitPrice < 0) {

            throw new Exception(
                "Invalid selling price for \"" .
                $productName .
                "\"."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PURCHASE PRICE
        |--------------------------------------------------------------------------
        */

        $unitCost =
            (float)$product["purchase_price"];


        /*
        |--------------------------------------------------------------------------
        | ITEM DISCOUNT
        |--------------------------------------------------------------------------
        */

        $itemDiscount = 0.00;


        /*
        |--------------------------------------------------------------------------
        | ITEM TOTAL
        |--------------------------------------------------------------------------
        */

        $itemTotal =
            ($unitPrice * $quantity)
            - $itemDiscount;


        if ($itemTotal < 0) {

            $itemTotal = 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | ADD TO SUBTOTAL
        |--------------------------------------------------------------------------
        */

        $subtotal += $itemTotal;


        /*
        |--------------------------------------------------------------------------
        | STORE ITEM
        |--------------------------------------------------------------------------
        */

        $validatedItems[] = [

            "product_id" =>
                $productId,

            "product_name" =>
                $productName,

            "quantity" =>
                $quantity,

            "unit_price" =>
                $unitPrice,

            "unit_cost" =>
                $unitCost,

            "discount" =>
                $itemDiscount,

            "total" =>
                $itemTotal
        ];
    }


    $productStmt->close();


    /*
    |--------------------------------------------------------------------------
    | VALIDATE DISCOUNT
    |--------------------------------------------------------------------------
    */

    if ($discount > $subtotal) {

        $discount = $subtotal;
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE FINAL TOTAL
    |--------------------------------------------------------------------------
    */

    $total =
        $subtotal
        - $discount
        + $tax;


    if ($total < 0) {

        $total = 0.00;
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE INVOICE NUMBER
    |--------------------------------------------------------------------------
    */

    do {

        $invoiceNumber =
            "INV-" .
            date("YmdHis") .
            "-" .
            strtoupper(
                bin2hex(
                    random_bytes(3)
                )
            );


        $checkInvoiceStmt =
            $conn->prepare(
                "SELECT id
                 FROM sales
                 WHERE invoice_number = ?
                 LIMIT 1"
            );


        if (!$checkInvoiceStmt) {

            throw new Exception(
                "Unable to check invoice number."
            );
        }


        $checkInvoiceStmt->bind_param(
            "s",
            $invoiceNumber
        );


        $checkInvoiceStmt->execute();


        $checkInvoiceResult =
            $checkInvoiceStmt->get_result();


        $invoiceExists =
            $checkInvoiceResult->num_rows > 0;


        $checkInvoiceStmt->close();

    } while ($invoiceExists);


    /*
    |--------------------------------------------------------------------------
    | SALE DATE
    |--------------------------------------------------------------------------
    |
    | Uses Asia/Colombo because the timezone
    | is set at the beginning of this file.
    |
    */

    $saleDate =
        date("Y-m-d H:i:s");


    /*
    |--------------------------------------------------------------------------
    | INSERT SALE
    |--------------------------------------------------------------------------
    */

    $saleStmt = $conn->prepare(
        "INSERT INTO sales
        (
            invoice_number,
            customer_id,
            cashier_id,
            sale_date,
            subtotal,
            discount,
            tax,
            total,
            payment_method,
            status,
            notes,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'completed',
            NULL,
            NOW()
        )"
    );


    if (!$saleStmt) {

        throw new Exception(
            "Unable to prepare sale statement."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BIND SALE DATA
    |--------------------------------------------------------------------------
    */

    $saleStmt->bind_param(
        "siisdddds",
        $invoiceNumber,
        $customerId,
        $cashierId,
        $saleDate,
        $subtotal,
        $discount,
        $tax,
        $total,
        $paymentMethod
    );


    /*
    |--------------------------------------------------------------------------
    | EXECUTE SALE
    |--------------------------------------------------------------------------
    */

    if (!$saleStmt->execute()) {

        throw new Exception(
            "Failed to create sale: " .
            $saleStmt->error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SALE ID
    |--------------------------------------------------------------------------
    */

    $saleId =
        (int)$conn->insert_id;


    $saleStmt->close();


    /*
    |--------------------------------------------------------------------------
    | INSERT SALE ITEMS
    |--------------------------------------------------------------------------
    */

    $saleItemStmt = $conn->prepare(
        "INSERT INTO sale_items
        (
            sale_id,
            product_id,
            quantity,
            unit_price,
            unit_cost,
            discount,
            total
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );


    if (!$saleItemStmt) {

        throw new Exception(
            "Unable to prepare sale item statement."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT STOCK
    |--------------------------------------------------------------------------
    */

    $stockStmt = $conn->prepare(
        "UPDATE products
         SET stock_quantity = stock_quantity - ?
         WHERE id = ?
         AND stock_quantity >= ?"
    );


    if (!$stockStmt) {

        throw new Exception(
            "Unable to prepare stock update statement."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE ITEMS
    |--------------------------------------------------------------------------
    */

    foreach ($validatedItems as $saleItem) {

        $productId =
            $saleItem["product_id"];

        $quantity =
            $saleItem["quantity"];

        $unitPrice =
            $saleItem["unit_price"];

        $unitCost =
            $saleItem["unit_cost"];

        $itemDiscount =
            $saleItem["discount"];

        $itemTotal =
            $saleItem["total"];


        /*
        |--------------------------------------------------------------------------
        | INSERT SALE ITEM
        |--------------------------------------------------------------------------
        */

        $saleItemStmt->bind_param(
            "iiidddd",
            $saleId,
            $productId,
            $quantity,
            $unitPrice,
            $unitCost,
            $itemDiscount,
            $itemTotal
        );


        if (!$saleItemStmt->execute()) {

            throw new Exception(
                "Failed to save sale item for product ID " .
                $productId .
                ": " .
                $saleItemStmt->error
            );
        }


        /*
        |--------------------------------------------------------------------------
        | REDUCE STOCK
        |--------------------------------------------------------------------------
        */

        $stockStmt->bind_param(
            "iii",
            $quantity,
            $productId,
            $quantity
        );


        if (!$stockStmt->execute()) {

            throw new Exception(
                "Failed to update stock for \"" .
                $saleItem["product_name"] .
                "\"."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK STOCK UPDATE
        |--------------------------------------------------------------------------
        */

        if ($stockStmt->affected_rows !== 1) {

            throw new Exception(
                "Unable to reduce stock for \"" .
                $saleItem["product_name"] .
                "\". Stock may have changed."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE STATEMENTS
    |--------------------------------------------------------------------------
    */

    $saleItemStmt->close();

    $stockStmt->close();


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        "Sale completed successfully.",
        [

            "sale_id" =>
                $saleId,

            "invoice_number" =>
                $invoiceNumber,

            "payment_method" =>
                $paymentMethod,

            "sale_date" =>
                $saleDate,

            "subtotal" =>
                number_format(
                    $subtotal,
                    2,
                    ".",
                    ""
                ),

            "discount" =>
                number_format(
                    $discount,
                    2,
                    ".",
                    ""
                ),

            "tax" =>
                number_format(
                    $tax,
                    2,
                    ".",
                    ""
                ),

            "total" =>
                number_format(
                    $total,
                    2,
                    ".",
                    ""
                )
        ]
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | LOG ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        "SmartPOS Sale Error: " .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | RETURN ERROR
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        false,
        $e->getMessage(),
        [],
        400
    );
}