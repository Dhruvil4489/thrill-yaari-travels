<?php
session_start();
$data = $_SESSION['ticket_data'];
$method = $_POST['payment_method'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Payment - Thrill Yari</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5 text-center">
<h2>Processing Payment via <?= $method ?>...</h2>
<p>Amount: <strong>₹ <?= number_format($data['fare']) ?></strong></p>

<button onclick="completePay()" class="btn btn-success mt-3">Pay Now</button>
</div>

<script>
function completePay(){
  window.location = "ticket_confirm.php";
}
</script>
</body>
</html>
