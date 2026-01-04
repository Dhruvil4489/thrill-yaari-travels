<?php
include 'db.php';

// Validate and sanitize input
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$guest_name = isset($_POST['guest_name']) ? trim($_POST['guest_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$hotel_name = isset($_POST['hotel_name']) ? trim($_POST['hotel_name']) : '';
$room_no = isset($_POST['room_no']) ? trim($_POST['room_no']) : '';
$check_in = isset($_POST['check_in']) ? trim($_POST['check_in']) : '';
$check_out = isset($_POST['check_out']) ? trim($_POST['check_out']) : '';
$nights = isset($_POST['nights']) ? (int)$_POST['nights'] : 0;
$base_price = isset($_POST['base_price']) ? (float)$_POST['base_price'] : 0;
$taxes = isset($_POST['taxes']) ? (float)$_POST['taxes'] : 0;
$total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
$payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';

// Validate required fields
if (empty($guest_name) || empty($phone) || empty($email) || empty($hotel_name) || 
    empty($check_in) || empty($check_out) || $nights <= 0 || $total_amount <= 0) {
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

$stmt = $conn->prepare("INSERT INTO hotel_bookings 
(user_id, guest_name, phone, email, hotel_name, room_no, check_in, check_out, nights, base_price, taxes, total_amount, payment_id) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("isssssssiisis",
  $user_id,
  $guest_name,
  $phone,
  $email,
  $hotel_name,
  $room_no,
  $check_in,
  $check_out,
  $nights,
  $base_price,
  $taxes,
  $total_amount,
  $payment_id
);

$stmt->execute();
$stmt->close();
$conn->close();
echo "OK";
