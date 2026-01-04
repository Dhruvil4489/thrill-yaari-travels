<?php
// feedback.php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $feedback = $_POST['feedback'] ?? '';

    $stmt = $conn->prepare("INSERT INTO feedback (name, email, feedback) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $name, $email, $feedback);

    if ($stmt->execute()) {
        header('Location: index.php?feedback=thanks');
    } else {
        header('Location: index.php?feedback=error');
    }
    $stmt->close();
    $conn->close();
}
?>
