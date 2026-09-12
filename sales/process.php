<?php


require_once "../config/auth.php";

requireLogin();


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

require_once "../config/csrf.php";


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
|
| Sales must only be created through POST.
|
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    header("Location: index.php?error=" . urlencode(
        "Invalid request method."
    ));

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

requireCsrfToken();


/*
|--------------------------------------------------------------------------
| Redirect With Error
|--------------------------------------------------------------------------
*/

function redirectWithError($message)
{
    header(
        "Location: index.php?error=" .
            urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Money Value
|--------------------------------------------------------------------------
|
| Reject:
| - Negative values
| - NaN
| - Infinity
| - Excessively large values
|
*/

function validateMoneyValue($value)
{
    if (!is_numeric($value)) {
        return false;
    }

    $value = (float) $value;

    if (!is_finite($value)) {
        return false;
    }

    if ($value < 0) {
        return false;
    }

    if ($value > 999999999.99) {
        return false;
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Read POST Data
|--------------------------------------------------------------------------
*/

$cartJson = $_POST["cart"] ?? "";

$customerId = filter_input(
    INPUT_POST,
    "customer_id",
    FILTER_VALIDATE_INT
);

$discountInput = $_POST["discount"] ?? 0;

$taxInput = $_POST["tax"] ?? 0;

$paymentMethod = trim(
    $_POST["payment_method"] ?? ""
);

$cashReceivedInput = $_POST["cash_received"] ?? 0;


/*
|--------------------------------------------------------------------------
| Basic Cart Validation
|--------------------------------------------------------------------------
*/

if (!is_string($cartJson)) {

    redirectWithError(
        "Invalid cart data."
    );
}


if ($cartJson === "") {

    redirectWithError(
        "Your cart is empty."
    );
}


/*
|--------------------------------------------------------------------------
| Limit Cart JSON Size
|--------------------------------------------------------------------------
|
| Prevent unnecessarily large POST payloads.
|
*/

if (strlen($cartJson) > 1000000) {

    redirectWithError(
        "Cart data is too large."
    );
}


/*
|--------------------------------------------------------------------------
| Validate Money Inputs
|--------------------------------------------------------------------------
*/

if (!validateMoneyValue($discountInput)) {

    redirectWithError(
        "Invalid discount value."
    );
}


if (!validateMoneyValue($taxInput)) {

    redirectWithError(
        "Invalid tax value."
    );
}


if (!validateMoneyValue($cashReceivedInput)) {

    redirectWithError(
        "Invalid cash received value."
    );
}


$discount = round(
    (float) $discountInput,
    2
);


$tax = round(
    (float) $taxInput,
    2
);


$cashReceived = round(
    (float) $cashReceivedInput,
    2
);


/*
|--------------------------------------------------------------------------
| Allowed Payment Methods
|--------------------------------------------------------------------------
*/

$allowedPaymentMethods = [
    "cash",
    "card",
    "bank_transfer",
    "mobile"
];


if (
    !in_array(
        $paymentMethod,
        $allowedPaymentMethods,
        true
    )
) {

    redirectWithError(
        "Invalid payment method."
    );
}


/*
|--------------------------------------------------------------------------
| Decode Cart
|--------------------------------------------------------------------------
*/

$cart = json_decode(
    $cartJson,
    true
);


/*
|--------------------------------------------------------------------------
| Validate JSON
|--------------------------------------------------------------------------
*/

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($cart) ||
    empty($cart)
) {

    redirectWithError(
        "Invalid cart data."
    );
}


/*
|--------------------------------------------------------------------------
| Limit Number Of Cart Items
|--------------------------------------------------------------------------
|
| Prevent an unnecessarily large number of products
| being submitted in a single request.
|
*/

if (count($cart) > 500) {

    redirectWithError(
        "Too many products in the cart."
    );
}


/*
|--------------------------------------------------------------------------
| Customer Validation
|--------------------------------------------------------------------------
*/

if (
    $customerId !== false &&
    $customerId !== null &&
    $customerId > 0
) {

    $customerStmt = $conn->prepare("
        SELECT
            id

        FROM customers

        WHERE id = ?
          AND status = 'active'

        LIMIT 1
    ");


    if (!$customerStmt) {

        redirectWithError(
            "Unable to verify customer."
        );
    }


    $customerStmt->bind_param(
        "i",
        $customerId
    );


    if (!$customerStmt->execute()) {

        $customerStmt->close();

        redirectWithError(
            "Unable to verify customer."
        );
    }


    $customerResult =
        $customerStmt->get_result();


    $customerExists =
        $customerResult->fetch_assoc();


    $customerResult->free();

    $customerStmt->close();


    if (!$customerExists) {

        redirectWithError(
            "Selected customer was not found."
        );
    }
} else {

    $customerId = null;
}


/*
|--------------------------------------------------------------------------
| Validate Cart Items
|--------------------------------------------------------------------------
*/

$validatedCart = [];


foreach ($cart as $cartItem) {

    /*
    |--------------------------------------------------------------------------
    | Validate Item Structure
    |--------------------------------------------------------------------------
    */

    if (
        !is_array($cartItem) ||
        !array_key_exists("product_id", $cartItem) ||
        !array_key_exists("quantity", $cartItem)
    ) {

        redirectWithError(
            "Invalid product information in cart."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Product ID
    |--------------------------------------------------------------------------
    */

    $productId = filter_var(
        $cartItem["product_id"],
        FILTER_VALIDATE_INT
    );


    if (
        $productId === false ||
        $productId <= 0
    ) {

        redirectWithError(
            "Invalid product selected."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Quantity
    |--------------------------------------------------------------------------
    |
    | Quantity must be numeric and finite.
    |
    */

    $quantityInput =
        $cartItem["quantity"];


    if (
        !is_numeric($quantityInput)
    ) {

        redirectWithError(
            "Invalid product quantity."
        );
    }


    $quantity =
        (float) $quantityInput;


    if (
        !is_finite($quantity) ||
        $quantity <= 0
    ) {

        redirectWithError(
            "Invalid product quantity."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum Quantity
    |--------------------------------------------------------------------------
    */

    if (
        $quantity > 999999999
    ) {

        redirectWithError(
            "Product quantity is too large."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decimal Precision
    |--------------------------------------------------------------------------
    |
    | Database uses DECIMAL(12,2), therefore quantities
    | are limited to two decimal places.
    |
    */

    if (
        round($quantity, 2) != $quantity
    ) {

        redirectWithError(
            "Product quantity can contain a maximum of two decimal places."
        );
    }


    $quantity =
        round($quantity, 2);


    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Products
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $validatedCart[$productId]
        )
    ) {

        redirectWithError(
            "Duplicate products were detected in the cart."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Validated Cart Item
    |--------------------------------------------------------------------------
    */

    $validatedCart[$productId] = [

        "product_id" =>
        $productId,

        "quantity" =>
        $quantity

    ];
}


/*
|--------------------------------------------------------------------------
| Start Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Product Verification Query
    |--------------------------------------------------------------------------
    |
    | The browser never controls:
    | - selling price
    | - purchase price
    | - stock
    | - product status
    |
    | Everything is loaded from the database.
    |
    */

    $productStmt = $conn->prepare("
        SELECT
            id,
            sku,
            name,
            selling_price,
            purchase_price,
            stock_quantity,
            unit,
            status

        FROM products

        WHERE id = ?

        FOR UPDATE
    ");


    if (!$productStmt) {

        throw new Exception(
            "Unable to prepare product verification."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Initialize Subtotal
    |--------------------------------------------------------------------------
    */

    $subtotal = 0.00;


    /*
    |--------------------------------------------------------------------------
    | Final Validated Items
    |--------------------------------------------------------------------------
    */

    $finalItems = [];


    /*
    |--------------------------------------------------------------------------
    | Validate Every Product
    |--------------------------------------------------------------------------
    */

    foreach (
        $validatedCart as $cartItem
    ) {

        $productId =
            $cartItem["product_id"];


        $quantity =
            $cartItem["quantity"];


        /*
        |--------------------------------------------------------------------------
        | Execute Product Query
        |--------------------------------------------------------------------------
        */

        $productStmt->bind_param(
            "i",
            $productId
        );


        if (
            !$productStmt->execute()
        ) {

            throw new Exception(
                "Unable to verify product."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get Product
        |--------------------------------------------------------------------------
        */

        $productResult =
            $productStmt->get_result();


        $product =
            $productResult->fetch_assoc();


        $productResult->free();


        /*
        |--------------------------------------------------------------------------
        | Product Exists
        |--------------------------------------------------------------------------
        */

        if (!$product) {

            throw new Exception(
                "A selected product no longer exists."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Product Active
        |--------------------------------------------------------------------------
        */

        if (
            $product["status"] !== "active"
        ) {

            throw new Exception(
                "Product '" .
                    $product["name"] .
                    "' is no longer active."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Current Stock
        |--------------------------------------------------------------------------
        */

        $currentStock =
            (float) $product["stock_quantity"];


        /*
        |--------------------------------------------------------------------------
        | Validate Current Stock
        |--------------------------------------------------------------------------
        */

        if (
            !is_finite($currentStock) ||
            $currentStock < 0
        ) {

            throw new Exception(
                "Invalid stock quantity for '" .
                    $product["name"] .
                    "'."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Stock Validation
        |--------------------------------------------------------------------------
        */

        if (
            $quantity >
            $currentStock
        ) {

            throw new Exception(
                "Insufficient stock for '" .
                    $product["name"] .
                    "'. Available: " .
                    number_format(
                        $currentStock,
                        2
                    ) .
                    " " .
                    $product["unit"] .
                    "."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Server-Side Selling Price
        |--------------------------------------------------------------------------
        |
        | NEVER trust price sent by browser.
        |
        */

        $unitPrice =
            (float) $product["selling_price"];


        /*
        |--------------------------------------------------------------------------
        | Validate Selling Price
        |--------------------------------------------------------------------------
        */

        if (
            !is_finite($unitPrice) ||
            $unitPrice < 0
        ) {

            throw new Exception(
                "Invalid selling price for '" .
                    $product["name"] .
                    "'."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Historical Purchase Cost
        |--------------------------------------------------------------------------
        */

        $unitCost =
            (float) $product["purchase_price"];


        /*
        |--------------------------------------------------------------------------
        | Validate Purchase Cost
        |--------------------------------------------------------------------------
        */

        if (
            !is_finite($unitCost) ||
            $unitCost < 0
        ) {

            throw new Exception(
                "Invalid purchase cost for '" .
                    $product["name"] .
                    "'."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Item Total
        |--------------------------------------------------------------------------
        */

        $itemTotal =
            round(
                $quantity *
                    $unitPrice,
                2
            );


        if (
            !is_finite($itemTotal) ||
            $itemTotal < 0
        ) {

            throw new Exception(
                "Invalid item total."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add To Subtotal
        |--------------------------------------------------------------------------
        */

        $subtotal =
            round(
                $subtotal +
                    $itemTotal,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | Protect Against Excessive Total
        |--------------------------------------------------------------------------
        */

        if (
            $subtotal >
            999999999.99
        ) {

            throw new Exception(
                "Sale total is too large."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Store Validated Item
        |--------------------------------------------------------------------------
        */

        $finalItems[] = [

            "product_id" =>
            $productId,

            "product_name" =>
            $product["name"],

            "sku" =>
            $product["sku"],

            "quantity" =>
            $quantity,

            "unit_price" =>
            $unitPrice,

            "unit_cost" =>
            $unitCost,

            "total" =>
            $itemTotal
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Close Product Statement
    |--------------------------------------------------------------------------
    */

    $productStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Validate Discount
    |--------------------------------------------------------------------------
    */

    if (
        $discount >
        $subtotal
    ) {

        throw new Exception(
            "Discount cannot be greater than the subtotal."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Grand Total
    |--------------------------------------------------------------------------
    */

    $grandTotal =
        round(
            $subtotal -
                $discount +
                $tax,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Validate Grand Total
    |--------------------------------------------------------------------------
    */

    if (
        !is_finite($grandTotal) ||
        $grandTotal < 0
    ) {

        throw new Exception(
            "Invalid sale total."
        );
    }


    if (
        $grandTotal >
        999999999.99
    ) {

        throw new Exception(
            "Sale total is too large."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Payment Validation
    |--------------------------------------------------------------------------
    */

    if (
        $paymentMethod === "cash"
    ) {

        /*
        |--------------------------------------------------------------------------
        | Cash Payment
        |--------------------------------------------------------------------------
        */

        if (
            $cashReceived <
            $grandTotal
        ) {

            throw new Exception(
                "Cash received is less than the sale total."
            );
        }
    } else {

        /*
        |--------------------------------------------------------------------------
        | Non-Cash Payment
        |--------------------------------------------------------------------------
        */

        $cashReceived =
            $grandTotal;
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Change
    |--------------------------------------------------------------------------
    */

    $changeAmount =
        round(
            max(
                0,
                $cashReceived -
                    $grandTotal
            ),
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Generate Invoice Number
    |--------------------------------------------------------------------------
    */

    $invoiceNumber =
        "INV-" .
        date("Ymd-His") .
        "-" .
        random_int(
            100000,
            999999
        );


    /*
    |--------------------------------------------------------------------------
    | Verify Invoice Number
    |--------------------------------------------------------------------------
    */

    $invoiceCheckStmt =
        $conn->prepare("
            SELECT
                id

            FROM sales

            WHERE invoice_number = ?

            LIMIT 1
        ");


    if (!$invoiceCheckStmt) {

        throw new Exception(
            "Unable to generate invoice number."
        );
    }


    $invoiceCheckStmt->bind_param(
        "s",
        $invoiceNumber
    );


    if (
        !$invoiceCheckStmt->execute()
    ) {

        $invoiceCheckStmt->close();

        throw new Exception(
            "Unable to verify invoice number."
        );
    }


    $invoiceResult =
        $invoiceCheckStmt->get_result();


    if (
        $invoiceResult->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | Regenerate Invoice Number
        |--------------------------------------------------------------------------
        */

        $invoiceNumber =
            "INV-" .
            date("Ymd-His") .
            "-" .
            random_int(
                100000,
                999999
            );
    }


    $invoiceResult->free();

    $invoiceCheckStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    $createdBy =
        filter_var(
            $_SESSION["user_id"] ?? null,
            FILTER_VALIDATE_INT
        );


    if (
        $createdBy === false ||
        $createdBy <= 0
    ) {

        throw new Exception(
            "Invalid logged-in user."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Sale
    |--------------------------------------------------------------------------
    */

    $saleStmt =
        $conn->prepare("
            INSERT INTO sales
            (
                invoice_number,
                customer_id,
                cashier_id,
                sale_date,
                subtotal,
                discount,
                tax,
                total,
                status,
                notes
            )

            VALUES
            (
                ?,
                ?,
                ?,
                CURDATE(),
                ?,
                ?,
                ?,
                ?,
                'completed',
                NULL
            )
        ");


    if (!$saleStmt) {

        throw new Exception(
            "Unable to prepare sale."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Bind Sale Parameters
    |--------------------------------------------------------------------------
    */

    $saleStmt->bind_param(
        "siidddd",
        $invoiceNumber,
        $customerId,
        $createdBy,
        $subtotal,
        $discount,
        $tax,
        $grandTotal
    );


    /*
    |--------------------------------------------------------------------------
    | Execute Sale
    |--------------------------------------------------------------------------
    */

    if (
        !$saleStmt->execute()
    ) {

        /*
        |--------------------------------------------------------------------------
        | Duplicate Invoice Protection
        |--------------------------------------------------------------------------
        */

        if ($saleStmt->errno === 1062) {

            throw new Exception(
                "Invoice number collision occurred. Please try again."
            );
        }

        throw new Exception(
            "Unable to create sale."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Sale ID
    |--------------------------------------------------------------------------
    */

    $saleId =
        $conn->insert_id;


    if (
        $saleId <= 0
    ) {

        $saleStmt->close();

        throw new Exception(
            "Unable to create sale."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Close Sale Statement
    |--------------------------------------------------------------------------
    */

    $saleStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Prepare Sale Item Statement
    |--------------------------------------------------------------------------
    */

    $itemStmt =
        $conn->prepare("
            INSERT INTO sale_items
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
                0,
                ?
            )
        ");


    if (!$itemStmt) {

        throw new Exception(
            "Unable to prepare sale items."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prepare Stock Update
    |--------------------------------------------------------------------------
    */

    $stockStmt =
        $conn->prepare("
            UPDATE products

            SET
                stock_quantity =
                    stock_quantity - ?,

                updated_at =
                    CURRENT_TIMESTAMP

            WHERE id = ?

              AND status = 'active'

              AND stock_quantity >= ?
        ");


    if (!$stockStmt) {

        throw new Exception(
            "Unable to prepare stock update."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prepare Stock Movement
    |--------------------------------------------------------------------------
    */

    $movementStmt =
        $conn->prepare("
            INSERT INTO stock_movements
            (
                product_id,
                user_id,
                movement_type,
                quantity,
                reference_type,
                reference_id,
                notes
            )

            VALUES
            (
                ?,
                ?,
                'sale',
                ?,
                'sale',
                ?,
                ?
            )
        ");


    if (!$movementStmt) {

        throw new Exception(
            "Unable to prepare stock movement."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Process Sale Items
    |--------------------------------------------------------------------------
    */

    foreach (
        $finalItems as $item
    ) {

        $productId =
            $item["product_id"];

        $quantity =
            $item["quantity"];

        $unitPrice =
            $item["unit_price"];

        $unitCost =
            $item["unit_cost"];

        $itemTotal =
            $item["total"];


        /*
        |--------------------------------------------------------------------------
        | Insert Sale Item
        |--------------------------------------------------------------------------
        */

        $itemStmt->bind_param(
            "iidddd",
            $saleId,
            $productId,
            $quantity,
            $unitPrice,
            $unitCost,
            $itemTotal
        );


        if (
            !$itemStmt->execute()
        ) {

            throw new Exception(
                "Unable to save sale item."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Reduce Stock
        |--------------------------------------------------------------------------
        */

        $stockStmt->bind_param(
            "did",
            $quantity,
            $productId,
            $quantity
        );


        if (
            !$stockStmt->execute()
        ) {

            throw new Exception(
                "Unable to update stock."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Confirm Stock Was Updated
        |--------------------------------------------------------------------------
        */

        if (
            $stockStmt->affected_rows <= 0
        ) {

            throw new Exception(
                "Stock changed before the sale could be completed. Please try again."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create Stock Movement
        |--------------------------------------------------------------------------
        */

        $negativeQuantity =
            -$quantity;


        /*
        |--------------------------------------------------------------------------
        | Limit Movement Note Length
        |--------------------------------------------------------------------------
        */

        $productName =
            (string) $item["product_name"];


        if (
            mb_strlen($productName) > 150
        ) {

            $productName =
                mb_substr(
                    $productName,
                    0,
                    150
                );
        }


        $movementNotes =
            "Sale " .
            $invoiceNumber .
            " - " .
            $productName;


        /*
        |--------------------------------------------------------------------------
        | Insert Stock Movement
        |--------------------------------------------------------------------------
        */

        $movementStmt->bind_param(
            "iidis",
            $productId,
            $createdBy,
            $negativeQuantity,
            $saleId,
            $movementNotes
        );


        if (
            !$movementStmt->execute()
        ) {

            throw new Exception(
                "Unable to create stock movement."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Close Item / Stock Statements
    |--------------------------------------------------------------------------
    */

    $itemStmt->close();

    $stockStmt->close();

    $movementStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    $paymentStmt =
        $conn->prepare("
            INSERT INTO payments
            (
                sale_id,
                payment_method,
                amount,
                cash_received,
                change_amount,
                reference_number
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                NULL
            )
        ");


    if (!$paymentStmt) {

        throw new Exception(
            "Unable to prepare payment."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Payment Amount
    |--------------------------------------------------------------------------
    */

    $paymentAmount =
        $grandTotal;


    /*
    |--------------------------------------------------------------------------
    | Save Payment
    |--------------------------------------------------------------------------
    */

    $paymentStmt->bind_param(
        "isddd",
        $saleId,
        $paymentMethod,
        $paymentAmount,
        $cashReceived,
        $changeAmount
    );


    if (
        !$paymentStmt->execute()
    ) {

        throw new Exception(
            "Unable to save payment."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Close Payment Statement
    |--------------------------------------------------------------------------
    */

    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Close Database
    |--------------------------------------------------------------------------
    */

    $conn->close();


    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $message =
        "Sale completed successfully. Invoice: " .
        $invoiceNumber;


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: receipt.php?id=" .
            $saleId
    );

    exit;
    
} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    try {

        $conn->rollback();
    } catch (Throwable $rollbackError) {

        // Ignore rollback failure.
    }


    /*
    |--------------------------------------------------------------------------
    | Development Logging
    |--------------------------------------------------------------------------
    |
    | Do NOT expose database/system errors to customers.
    |
    | Log the technical error instead.
    |
    */

    error_log(
        "SmartPOS Sale Error: " .
            $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | Close Connection
    |--------------------------------------------------------------------------
    */

    $conn->close();


    /*
    |--------------------------------------------------------------------------
    | Generic User Error
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?error=" .
            urlencode(
                "Unable to complete the sale. Please check the information and try again."
            )
    );

    exit;
}
