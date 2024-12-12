<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = $_POST['account_id'];
    $amount = $_POST['amount'];

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $account_id]);

    $stmt = $pdo->prepare("INSERT INTO transactions (account_id, type, amount) VALUES (?, 'deposit', ?)");
    $stmt->execute([$account_id, $amount]);

    $pdo->commit();
    echo "Deposit successful!";
}
?>
<form method="POST">
    Account ID: <input type="text" name="account_id" required><br>
    Amount: <input type="number" step="0.01" name="amount" required><br>
    <button type="submit">Deposit</button>
</form>
