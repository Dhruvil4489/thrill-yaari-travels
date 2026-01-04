<?php
session_start();

// Validate and sanitize input
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$airline = isset($_POST['airline']) ? trim($_POST['airline']) : '';
$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$from = isset($_POST['from']) ? trim($_POST['from']) : '';
$to = isset($_POST['to']) ? trim($_POST['to']) : '';
$fare = isset($_POST['fare']) ? (float)$_POST['fare'] : 0;
$seat = isset($_POST['seat']) ? trim($_POST['seat']) : '';

// Validate required fields
if (empty($name) || empty($phone) || $age <= 0 || empty($email) || 
    empty($airline) || empty($code) || empty($from) || empty($to) || 
    $fare <= 0 || empty($seat)) {
    header('Location: index.php?error=invalid_flight_data');
    exit;
}

// Generate Ticket Number
$ticket = "TY" . rand(100000,999999);

$_SESSION['ticket_data'] = compact('name','phone','age','email','airline','code','from','to','fare','seat','ticket');
?>
<!DOCTYPE html>
<html>
<head>
<title>Flight Summary</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
.summary-box{background:#fff;border-radius:12px;padding:20px;box-shadow:0 0 12px rgba(0,0,0,.1);}
.pay-box button{margin-top:10px;}
</style>
</head>
<body class="bg-light">

<div class="container py-4">
<h2 class="mb-4 text-center">Review Your Booking</h2>

<div class="summary-box">
<p><strong>Passenger:</strong> <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> (<?= (int)$age ?>)</p>
<p><strong>Mobile:</strong> <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>

<hr>

<p><strong>Flight:</strong> <?= htmlspecialchars($airline, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>)</p>
<p><strong>Route:</strong> <?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?></p>
<p><strong>Seat:</strong> <?= htmlspecialchars($seat, ENT_QUOTES, 'UTF-8') ?></p>

<hr>
<h4>Total Fare: ₹ <?= number_format($fare) ?></h4>
</div>

<br>

<h4>Select Payment Method</h4>
<form action="payment_gateway.php" method="POST">
<div class="pay-box">
  <select name="payment_method" class="form-control" required>
    <option value="">-- Select Payment Method --</option>
    <option value="UPI">UPI</option>
    <option value="Debit Card">Debit Card</option>
    <option value="Credit Card">Credit Card</option>
    <option value="Net Banking">Net Banking</option>
  </select>
  <button class="btn btn-primary w-100" type="submit">Proceed to Pay</button>
</div>
</form>

</div>
</body>
</html>
