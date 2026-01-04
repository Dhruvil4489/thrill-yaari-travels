<?php
// signup.php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
        header('Location: index.php?signup=invalid');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $email, $hash);

    if ($stmt->execute()) {
        // Redirect back to home and maybe auto-login
        header('Location: index.php?signup=success');
    } else {
        // handle duplicate email etc
        header('Location: index.php?signup=error');
    }
    $stmt->close();
    $conn->close();
}
?>
