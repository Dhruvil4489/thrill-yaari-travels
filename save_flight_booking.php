<?php
session_start();
include 'db.php';

// Validate and sanitize input
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$name = isset($_POST['passenger_name']) ? trim($_POST['passenger_name']) : '';
$mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$airline = isset($_POST['airline']) ? trim($_POST['airline']) : '';
$code = isset($_POST['flight_code']) ? trim($_POST['flight_code']) : '';
$origin = isset($_POST['origin']) ? trim($_POST['origin']) : '';
$destination = isset($_POST['destination']) ? trim($_POST['destination']) : '';
$seat = isset($_POST['seat']) ? trim($_POST['seat']) : '';
$fare = isset($_POST['fare']) ? (float)$_POST['fare'] : 0;
$pnr = isset($_POST['pnr']) ? trim($_POST['pnr']) : '';
$payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';

// Validate required fields
if (empty($name) || empty($mobile) || $age <= 0 || empty($email) || 
    empty($airline) || empty($code) || empty($origin) || empty($destination) || 
    $fare <= 0 || empty($pnr)) {
    http_response_code(400);
    echo "ERROR: Missing required fields";
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "ERROR: Invalid email address";
    exit;
}

$stmt = $conn->prepare("INSERT INTO flight_bookings
(user_id, passenger_name, mobile, age, email, airline, flight_code, origin, destination, seat, fare, pnr, payment_id)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("ississsssssdss", $user_id, $name, $mobile, $age, $email, $airline, $code, $origin, $destination, $seat, $fare, $pnr, $payment_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo "OK";
?>
