<?php
// booking.php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $name = $_POST['name'] ?? '';
    $from_city = $_POST['from_city'] ?? null;
    $to_city = $_POST['to_city'] ?? null;
    $hotel_name = $_POST['hotel_name'] ?? null;
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;

    $stmt = $conn->prepare("
        INSERT INTO bookings (type, name, from_city, to_city, hotel_name, start_date, end_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssss', $type, $name, $from_city, $to_city, $hotel_name, $start_date, $end_date);

    if ($stmt->execute()) {
        header('Location: index.php?booking=success');
    } else {
        header('Location: index.php?booking=error');
    }
    $stmt->close();
    $conn->close();
}
?>
