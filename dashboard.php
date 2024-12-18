<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');  // Redirect to login if not logged in
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Fetch user information
$stmt = $pdo->prepare("SELECT username, is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user exists
if (!$user) {
    echo "Error: User not found.";
    exit;
}

// Get user accounts and balances
$stmt = $pdo->prepare("SELECT accounts.id, accounts.name, accounts.iban, accounts.balance, accounts.is_delete_requested 
    FROM accounts WHERE accounts.user_id = ?");
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();

// Handle new account creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_account'])) {
    $accountName = $_POST['account_name'];

    if (empty($accountName)) {
        echo "Account name is required.";
        exit;
    }

    try {
        $newIBAN = generateFinnishIBAN($pdo);
        // Set is_approved to FALSE for new accounts
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, name, iban, balance, is_approved, is_delete_requested) 
            VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $accountName, $newIBAN]);

        echo "Account created successfully! IBAN: " . htmlspecialchars($newIBAN) . ". Waiting for admin approval.";
    } catch (Exception $e) {
        echo "Error creating account: " . htmlspecialchars($e->getMessage());
    }
}

// Handle account deletion request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_deletion'])) {
    $account_id = $_POST['account_id'];

    // Check if balance is 0
    $stmt = $pdo->prepare("SELECT balance FROM accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    $account = $stmt->fetch();

    if ($account && $account['balance'] == 0) {
        $stmt = $pdo->prepare("UPDATE accounts SET is_delete_requested = 1 WHERE id = ?");
        $stmt->execute([$account_id]);
        echo "Deletion request sent. Awaiting admin approval.";
    } else {
        echo "Account balance must be 0 to request deletion.";
    }
}

// Handle default account deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_default_account'])) {
    $account_id = $_POST['account_id_to_delete'];

    // Check if the account is the only account left and has a balance of 0
    $stmt = $pdo->prepare("SELECT balance, (SELECT COUNT(*) FROM accounts WHERE user_id = (SELECT user_id FROM accounts WHERE id = ?)) AS account_count FROM accounts WHERE id = ?");
    $stmt->execute([$account_id, $account_id]);
    $account = $stmt->fetch();

    if ($account && $account['balance'] == 0 && $account['account_count'] == 1) {
        // Proceed with account deletion
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        echo "Default account has been deleted.";
    } else {
        echo "Cannot delete the default account. It must be the only account and have a balance of 0.";
    }
}

// Handle transfer form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['from_iban'], $_POST['to_iban'], $_POST['amount'])) {
    $from_iban = $_POST['from_iban'];
    $to_iban = $_POST['to_iban'];
    $amount = floatval($_POST['amount']);

    if ($amount <= 0) {
        echo "Invalid amount. Please enter a positive value.";
        exit;
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Check sender's account balance
        $stmt = $pdo->prepare("SELECT balance FROM accounts WHERE iban = ? AND user_id = ?");
        $stmt->execute([$from_iban, $user_id]);
        $sender_account = $stmt->fetch();

        if (!$sender_account) {
            throw new Exception("Sender's account not found or unauthorized.");
        }

        if ($sender_account['balance'] < $amount) {
            throw new Exception("Insufficient funds in the sender's account.");
        }

        // Check recipient's account
        $stmt = $pdo->prepare("SELECT user_id FROM accounts WHERE iban = ?");
        $stmt->execute([$to_iban]);
        $recipient_account = $stmt->fetch();

        if (!$recipient_account) {
            throw new Exception("Recipient's account not found.");
        }

        // // Perform the transfer: Deduct from sender
        // $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE iban = ?");
        // $stmt->execute([$amount, $from_iban]);

        // // Add to recipient
        // $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE iban = ?");
        // $stmt->execute([$amount, $to_iban]);

        // Log the transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (iban, user_id, to_iban, to_user_id, amount, info) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $from_iban,
            $user_id,
            $to_iban,
            $recipient_account['user_id'],
            $amount,
            'Transfer from ' . $from_iban
        ]);

        // Commit transaction
        $pdo->commit();

        echo "Transfer successful!";
    } catch (Exception $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        echo "Failed to complete the transfer: " . htmlspecialchars($e->getMessage());
    }
}

// Fetch transaction history
$stmt = $pdo->prepare("
    SELECT t.id, t.iban, t.to_iban, t.amount, t.transaction_date, a.balance AS sender_balance, b.balance AS recipient_balance
    FROM transactions t
    LEFT JOIN accounts a ON a.iban = t.iban
    LEFT JOIN accounts b ON b.iban = t.to_iban
    WHERE t.user_id = ? OR t.to_user_id = ?
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$user_id, $user_id]);
$transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
</head>
<body>
    <div class="container">
        <h1>Welcome, <?= htmlspecialchars($user['username']) ?>!</h1>

        <!-- Accounts Overview -->
        <h2>Your Accounts</h2>
        <table>
            <tr>
                <th>Account Name</th>
                <th>IBAN</th>
                <th>Balance (€)</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($accounts as $account): ?>
            <tr>
                <td><?= htmlspecialchars($account['name']) ?></td>
                <td><?= htmlspecialchars($account['iban']) ?></td>
                <td style="text-align: left;"><?= number_format($account['balance'], 2) ?></td>
                <td>
                    <?php if ($account['balance'] == 0 && $account['is_delete_requested'] == 0 && $account['name'] != 'Default Account'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="account_id" value="<?= $account['id'] ?>">
                            <button type="submit" name="request_deletion">Request Deletion</button>
                        </form>
                    <?php elseif ($account['is_delete_requested'] == 1): ?>
                        <span>Deletion Pending Approval</span>
                    <?php endif; ?>
                    <!-- Default account deletion form -->
                    <?php if ($account['name'] == 'Default Account' && $account['balance'] == 0 && count($accounts) == 1): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="account_id_to_delete" value="<?= $account['id'] ?>">
                            <button type="submit" name="delete_default_account">Delete Default Account</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Create New Account</h2>
        <form method="POST">
            <label for="account_name">Account Name:</label>
            <input type="text" name="account_name" required>
            <button type="submit" name="create_account">Create Account</button>
        </form>

        <!-- Transfer Form -->
        <h2>Transfer Money</h2>
        <form method="POST">
            <label for="from_iban">From Account:</label>
            <select name="from_iban" required>
                <?php foreach ($accounts as $account): ?>
                <option value="<?= htmlspecialchars($account['iban']) ?>">
                    <?= htmlspecialchars($account['name']) ?> (<?= htmlspecialchars($account['iban']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <br>

            <label for="to_iban">To IBAN:</label>
            <input type="text" name="to_iban" required>
            <br>

            <label for="amount">Amount (€):</label>
            <input type="number" name="amount" min="0.01" step="0.01" required>
            <br>

            <button type="submit">Transfer</button>
        </form>

        <!-- Transaction History -->
        <h2>Transaction History</h2>
        <table>
            <tr>
                <th>Transaction ID</th>
                <th>From IBAN</th>
                <th>To IBAN</th>
                <th>Amount (€)</th>
                <th>Transaction Date</th>
            </tr>
            <?php foreach ($transactions as $transaction): ?>
            <tr>
                <td><?= htmlspecialchars($transaction['id']) ?></td>
                <td><?= htmlspecialchars($transaction['iban']) ?></td>
                <td><?= htmlspecialchars($transaction['to_iban']) ?></td>
                <td><?= number_format($transaction['amount'], 2) ?></td>
                <td style="text-align: left;"><?= htmlspecialchars($transaction['transaction_date']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <a href="logout.php">Logout</a>
        <?php if ($user['is_admin']): ?>
            <a href="admin_dashboard.php">Admin Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>
