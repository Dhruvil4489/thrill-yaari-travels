<?php
// bus_payment.php
session_start();
include 'db.php';

// Gather POST + fetch bus
$bus_id = (int)($_POST['bus_id'] ?? 0);
$seat_no= trim($_POST['seat_no'] ?? '');
if ($bus_id<=0 || $seat_no==='') die("Invalid booking context.");

$stmt = $conn->prepare("SELECT id, from_city, to_city, bus_name, bus_type, departure_time, arrival_time, price FROM buses WHERE id=? LIMIT 1");
$stmt->bind_param("i",$bus_id);
$stmt->execute();
$bus = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$bus) die("Bus not found.");

// Passenger bundle
$P = [
  'name'=>trim($_POST['name']??''), 'mobile'=>trim($_POST['mobile']??''),
  'email'=>trim($_POST['email']??''), 'age'=>(int)($_POST['age']??0),
  'gender'=>trim($_POST['gender']??''), 'city'=>trim($_POST['city']??''),
  'id_type'=>trim($_POST['id_type']??''), 'id_no'=>trim($_POST['id_no']??''),
  'emg_name'=>trim($_POST['emg_name']??''), 'emg_mobile'=>trim($_POST['emg_mobile']??''),
  'note'=>trim($_POST['note']??'')
];

// Basic validation
if($P['name']==='' || $P['mobile']==='' || $P['email']==='' || $P['age']<=0){
  die("<p style='font-family:Inter;max-width:720px;margin:30px auto'>Missing passenger details. <a href='javascript:history.back()'>Go back</a></p>");
}

// Fare calc
$base = (float)$bus['price'];
$gst  = round($base * 0.05);          // 5% GST
$conv = 25;                           // convenience fee
$total = $base + $gst + $conv;

// Keep for ticket page
$_SESSION['bus_booking'] = [
  'bus_id' => $bus_id,
  'seat_no' => $seat_no,
  'from_city' => $bus['from_city'],
  'to_city' => $bus['to_city'],
  'fare_base' => $base,
  'fare_gst' => $gst,
  'fare_conv' => $conv,
  'fare_total' => $total,
  'passenger' => $P
];


?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Bus Payment • Thrill Yari</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root{
    --pri:#0d6efd;
    --line:#e5e7eb;
    --ink:#0f172a;
  }
  body{
    background:#f5f7fc;
    margin:0;
    font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;
  }
  .wrap{
    max-width:900px;
    margin:35px auto;
    padding:0 18px;
  }
  .title{
    font-size:28px;
    font-weight:900;
    color:var(--ink);
    margin-bottom:10px;
  }
  .top-summary{
    background:#fff;
    padding:18px;
    border-radius:16px;
    border:1px solid var(--line);
    box-shadow:0 6px 24px rgba(15,23,42,.06);
    margin-bottom:20px;
    line-height:1.5;
    font-weight:600;
    color:#374151;
  }
  .grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
  }
  .card{
    background:#fff;
    padding:18px;
    border-radius:16px;
    border:1px solid var(--line);
    box-shadow:0 6px 18px rgba(15,23,42,.05);
  }
  .heading{
    font-size:16px;
    font-weight:800;
    margin-bottom:6px;
  }
  .small{
    font-size:14px;
    color:#4b5563;
    line-height:1.6;
  }
  hr{
    border:none;
    border-top:1px solid var(--line);
    margin:12px 0;
  }
  .total{
    font-size:20px;
    font-weight:900;
    color:#111;
  }
  .actions{
    display:flex;
    justify-content:flex-end;
    gap:14px;
    margin-top:24px;
  }
  .btn{
    padding:12px 24px;
    border-radius:12px;
    border:none;
    cursor:pointer;
    font-weight:800;
    font-size:15px;
  }
  .btn-outline{
    background:#fff;
    border:1px solid var(--line);
    color:#111;
  }
  .btn-primary{
    background:var(--pri);
    color:#fff;
  }
</style>

</head>
<body>
  <div class="wrap">

  <div class="title">Review & Pay</div>

  <div class="top-summary">
    <?= htmlspecialchars($bus['bus_name']) ?> • <?= htmlspecialchars($bus['bus_type']) ?>  
    — Seat <b><?= htmlspecialchars($seat_no) ?></b>  
    — <?= htmlspecialchars($bus['from_city']) ?> → <?= htmlspecialchars($bus['to_city']) ?>  
    — <?= htmlspecialchars($bus['departure_time']) ?> → <?= htmlspecialchars($bus['arrival_time']) ?>
  </div>

  <div class="grid">
    
    <div class="card">
      <div class="heading">Passenger</div>
      <div class="small">
        <b><?= htmlspecialchars($P['name']) ?></b> (<?= (int)$P['age'] ?>, <?= htmlspecialchars($P['gender']) ?>)<br>
        <?= htmlspecialchars($P['mobile']) ?> • <?= htmlspecialchars($P['email']) ?><br>
        <?= htmlspecialchars($P['id_type']) ?>: <?= htmlspecialchars($P['id_no']) ?><br>
        <?= htmlspecialchars($P['city']) ?>
      </div>
    </div>

    <div class="card">
      <div class="heading">Fare Summary</div>
      <div class="small">Base Fare: ₹ <?= number_format($base) ?></div>
      <div class="small">GST (5%): ₹ <?= number_format($gst) ?></div>
      <div class="small">Convenience Fee: ₹ <?= number_format($conv) ?></div>
      <hr>
      <div class="total">Total: ₹ <?= number_format($total) ?></div>
    </div>

  </div>

  <form class="card" method="post" action="bus_ticket.php?final=1" id="payForm">
    <div class="card" style="margin-top:20px">
      <div class="heading">Payment Method</div>
      <select name="payment_method" style="width:100%;height:44px;border:1px solid var(--line);border-radius:10px;padding:10px;">
        <option>UPI</option>
        <option>Debit Card</option>
        <option>Credit Card</option>
        <option>Net Banking</option>
      </select>
    </div>

    <div class="actions">
      <a href="javascript:history.back()" class="btn btn-outline">Back</a>
      <button class="btn btn-primary" type="submit" name="confirm_pay" value="1">
        Pay ₹ <?= number_format($total) ?> & Continue
      </button>
    </div>
  </form>

  </div>
</body>
</html>
