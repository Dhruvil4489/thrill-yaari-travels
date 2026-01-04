<?php
// contact.php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? null;
    $message = $_POST['message'] ?? '';

    $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $phone, $message);

    if ($stmt->execute()) {
        header('Location: index.php?contact=thanks');
    } else {
        header('Location: index.php?contact=error');
    }
    $stmt->close();
    $conn->close();
}
?>
