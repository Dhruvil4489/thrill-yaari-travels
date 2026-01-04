<?php
session_start();
if(!isset($_SESSION['user_id'])){
  header("Location: index.php");
  exit;
}

$bus_id = $_POST['bus_id'] ?? '';
$seat_no = $_POST['seat_no'] ?? '';
$from = $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
<title>Passenger Details</title>

<style>
body{font-family:Inter;background:#f2f6ff;margin:0;padding:0;}
.box{max-width:780px;margin:40px auto;background:#fff;padding:30px;border-radius:14px;box-shadow:0 8px 25px rgba(0,0,0,.12);}
h2{text-align:center;margin-bottom:20px;color:#0d6efd;}
input,select,textarea{
  width:100%;padding:11px;margin-top:3px;margin-bottom:12px;
  border:1px solid #cfd5e1;border-radius:8px;font-size:15px;background:#fff;
}
button{
  background:#0d6efd;color:#fff;padding:14px;width:100%;
  border:none;border-radius:10px;font-weight:700;cursor:pointer;
}
button:hover{filter:brightness(.94);}
</style>

</head>
<body>

<div class="box">
  <h2>Passenger Details</h2>

  <p><b>Route:</b> <?= $from ?> → <?= $to ?> &nbsp; | &nbsp; <b>Seat:</b> <?= $seat_no ?></p>

  <form action="bus_payment.php" method="POST">

<input type="hidden" name="bus_id" value="<?= $_POST['bus_id'] ?>">
<input type="hidden" name="seat_no" value="<?= $_POST['seat_no'] ?>">

<label>Full Name *</label>
<input type="text" name="name" placeholder="Your full name" required>

<label>Mobile Number *</label>
<input type="text" name="mobile" placeholder="10-digit number" required>

<label>Email *</label>
<input type="email" name="email" placeholder="example@gmail.com" required>

<label>Age *</label>
<input type="number" name="age" placeholder="Age" required>

<label>Gender *</label>
<select name="gender" required>
  <option value="">Select…</option>
  <option>Male</option>
  <option>Female</option>
  <option>Other</option>
</select>

<label>City *</label>
<input type="text" name="city" placeholder="Your city" required>

<label>ID Proof Type *</label>
<select name="id_type" required>
  <option value="">Select…</option>
  <option>Aadhar</option>
  <option>PAN</option>
  <option>Driving License</option>
</select>

<label>ID Number *</label>
<input type="text" name="id_no" placeholder="Document number" required>

<label>Emergency Contact (optional)</label>
<input type="text" name="emg_mobile" placeholder="Emergency phone (optional)">

<button type="submit" style="background:#0066ff;color:#fff;padding:12px;border:none;border-radius:10px;font-size:16px;width:100%;margin-top:15px;cursor:pointer;">
  Proceed to Payment
</button>

</form>

</div>

</body>
</html>
