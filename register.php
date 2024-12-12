<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Function to generate Finnish IBAN
    function generateFinnishIBAN($pdo) {
        do {
            $countryCode = "FI";
            $bankCode = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $accountNumber = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);
            $bban = $bankCode . $accountNumber;

            $ibanNumeric = str_replace(
                range('A', 'Z'),
                range(10, 35),
                $bban . $countryCode . "00"
            );
            $checksum = 98 - bcmod($ibanNumeric, 97);
            $checksum = str_pad($checksum, 2, '0', STR_PAD_LEFT);

            $iban = $countryCode . $checksum . $bban;

            // Check if IBAN is unique
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE iban = ?");
            $stmt->execute([$iban]);
            $exists = $stmt->fetchColumn();
        } while ($exists > 0); // Ensure IBAN is unique

        return $iban;
    }

    // Start a transaction to ensure atomicity
    $pdo->beginTransaction();

    try {
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $password]);
        $user_id = $pdo->lastInsertId(); // Get the newly created user ID

        // Generate a unique Finnish IBAN
        $iban = generateFinnishIBAN($pdo);

        // Insert a new account with 100€ balance
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, iban, balance) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $iban, 100.00]);

        // Commit the transaction
        $pdo->commit();

        echo "<p class='success'>Registration successful! You can now log in.</p>";
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback in case of an error
        echo "<p class='error'>Something went wrong. Please try again later.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank App - Register</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to external CSS file -->
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Register for Our Bank</h1>

            <form method="POST" class="form">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
            </form>

            <p class="register-text">Already have an account? <a href="index.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
