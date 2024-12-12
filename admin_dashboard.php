<?php
require 'config.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');  // Redirect to login if not logged in
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if the logged-in user is an admin
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !$user['is_admin']) {
    echo "You are not authorized to view this page.";
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add money to user account
    if (isset($_POST['add_money'])) {
        $account_id = $_POST['account_id'];
        $amount = $_POST['amount'];

        // Add money to the user's account
        $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $account_id]);

        echo "<p class='success'>Successfully added €$amount to account ID $account_id.</p>";
    }
    // Create new user
    elseif (isset($_POST['create_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $initial_balance = $_POST['initial_balance'];

        // Create a new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, false]);
        $user_id = $pdo->lastInsertId();

        // Create an account for the new user with the initial balance, IBAN, and is_approved status
        $iban = 'FI' . str_pad(rand(1000000000000000, 9999999999999999), 18, '0', STR_PAD_LEFT);  // Generate a random Finnish IBAN
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, iban, balance, is_approved) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $iban, $initial_balance, false]);

        echo "<p class='success'>User '$username' created with an initial balance of €$initial_balance.</p>";
    }
    // Assign admin privileges
    elseif (isset($_POST['assign_admin'])) {
        $user_id_to_promote = $_POST['user_id_to_promote'];

        // Assign admin privileges to a user
        $stmt = $pdo->prepare("UPDATE users SET is_admin = TRUE WHERE id = ?");
        $stmt->execute([$user_id_to_promote]);

        echo "<p class='success'>User ID $user_id_to_promote is now an admin.</p>";
    }
    // Remove admin privileges
    elseif (isset($_POST['remove_admin'])) {
        $user_id_to_demote = $_POST['user_id_to_demote'];

        // Remove admin privileges from a user
        $stmt = $pdo->prepare("UPDATE users SET is_admin = FALSE WHERE id = ?");
        $stmt->execute([$user_id_to_demote]);

        echo "<p class='success'>Admin privileges removed from User ID $user_id_to_demote.</p>";
    }
    // Approve an account
    elseif (isset($_POST['approve_account'])) {
        $account_id = $_POST['account_id_to_approve'];

        // Update the account status to approved
        $stmt = $pdo->prepare("UPDATE accounts SET is_approved = TRUE WHERE id = ?");
        $stmt->execute([$account_id]);

        echo "<p class='success'>Account ID $account_id has been approved.</p>";
    }
    // Reject an account
    elseif (isset($_POST['reject_account'])) {
        $account_id = $_POST['account_id_to_reject'];

        // Delete the account (or you can set is_approved to FALSE again depending on your approach)
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);

        echo "<p class='success'>Account ID $account_id has been rejected and removed.</p>";
    }
}

// Get all users and their account information (including balance, IBAN, and approval status)
$stmt = $pdo->prepare("
    SELECT users.id, users.username, users.is_admin, accounts.id AS account_id, accounts.balance, accounts.iban, accounts.is_approved
    FROM users
    LEFT JOIN accounts ON users.id = accounts.user_id
");
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">  <!-- Link to the separate CSS file -->
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>

        <!-- Add money to a user -->
        <div class="form-container">
            <h2>Add Money to User</h2>
            <form method="POST">
                Account ID: <input type="number" name="account_id" required><br>
                Amount: <input type="number" name="amount" required><br>
                <button type="submit" name="add_money">Add Money</button>
            </form>
        </div>

        <!-- Create a new user -->
        <div class="form-container">
            <h2>Create New User</h2>
            <form method="POST">
                Username: <input type="text" name="username" required><br>
                Password: <input type="password" name="password" required><br>
                Initial Balance: <input type="number" name="initial_balance" required><br>
                <button type="submit" name="create_user">Create User</button>
            </form>
        </div>

        <!-- Assign admin privileges -->
        <div class="form-container">
            <h2>Assign Admin Privileges</h2>
            <form method="POST">
                User ID: <input type="number" name="user_id_to_promote" required><br>
                <button type="submit" name="assign_admin">Assign Admin</button>
            </form>
        </div>

        <!-- Remove admin privileges -->
        <div class="form-container">
            <h2>Remove Admin Privileges</h2>
            <form method="POST">
                User ID: <input type="number" name="user_id_to_demote" required><br>
                <button type="submit" name="remove_admin">Remove Admin</button>
            </form>
        </div>

        <h2>Users List</h2>
        <div class="table-container">
            <table>
                <tr>
                    <th>Username</th>
                    <th>Admin Status</th>
                    <th>Balance (€)</th>
                    <th>IBAN</th>
                    <th>Approval Status</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td class="admin-status <?= $user['is_admin'] ? 'admin' : 'user' ?>"><?= $user['is_admin'] ? 'Admin' : 'User' ?></td>
                    <td><?= number_format($user['balance'], 2) ?></td>
                    <td><?= htmlspecialchars($user['iban']) ?></td>
                    <td><?= $user['is_approved'] ? 'Approved' : 'Pending' ?></td>
                    <td>
                        <?php if (!$user['is_approved']): ?>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="approve_account" value="Approve" style="background-color: green; color: white;">
                                    Approve
                                </button>
                                <input type="hidden" name="account_id_to_approve" value="<?= $user['account_id'] ?>">
                            </form>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="reject_account" value="Reject" style="background-color: red; color: white;">
                                    Reject
                                </button>
                                <input type="hidden" name="account_id_to_reject" value="<?= $user['account_id'] ?>">
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</body>
</html>
