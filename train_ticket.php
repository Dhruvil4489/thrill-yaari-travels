<?php
session_start();

/* Receive Data (from payment success or confirm) */
$pnr       = $_SESSION['train_pnr'];
$train     = $_SESSION['train_name'];
$train_no  = $_SESSION['train_no'];
$from      = $_SESSION['from_city'];
$to        = $_SESSION['to_city'];
$date      = $_SESSION['travel_date'];
$dep       = $_SESSION['dep_time'];
$arr       = $_SESSION['arr_time'];
$class     = $_SESSION['travel_class'];
$quota     = "General (GN)";
$coach     = $_SESSION['coach'];
$seat      = $_SESSION['seat'];
$name      = $_SESSION['passenger_name'];
$age       = $_SESSION['passenger_age'];
$gender    = $_SESSION['passenger_gender'];
$fare      = $_SESSION['fare_total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Train Ticket</title>
<style>
body{font-family:Inter,system-ui; background:#eef2f7; margin:0; padding:0;}
.ticket-box{
  width:650px; background:#fff;margin:30px auto;padding:24px;border-radius:14px;
  box-shadow:0 4px 18px rgba(0,0,0,.12);
}
.hd{font-weight:800;font-size:20px;text-align:center;margin-bottom:12px;}
.row{margin:4px 0;font-size:14px;}
.lab{font-weight:700;}
.divider{border-bottom:1px dashed #999;margin:14px 0;}
.btns{display:flex;gap:10px;justify-content:center;margin-top:18px;}
.btn{padding:12px 16px;border-radius:10px;border:none;cursor:pointer;font-weight:700;font-size:14px;}
.print{background:#0066ff;color:#fff;}
.wa{background:#25D366;color:#fff;}
.done{background:#ddd;color:#555;}
@media print {
  .btns{display:none;}
  body{background:#fff;}
  .ticket-box{box-shadow:none;margin:0;width:100%;border-radius:0;}
}
</style>
</head>

<body>

<div class="ticket-box">

<div class="hd">🚆 Indian Railways • e-Ticket (Confirmed)</div>

<div class="row"><span class="lab">PNR:</span> <?= $pnr ?> </div>
<div class="row"><span class="lab">Train:</span> <?= $train ?> (<?= $train_no ?>)</div>
<div class="row"><span class="lab">Route:</span> <?= $from ?> ➝ <?= $to ?></div>
<div class="row"><span class="lab">Date:</span> <?= $date ?> &nbsp;&nbsp; Dep: <?= $dep ?> • Arr: <?= $arr ?></div>
<div class="row"><span class="lab">Class:</span> <?= $class ?> &nbsp;&nbsp; <span class="lab">Quota:</span> <?= $quota ?></div>

<div class="divider"></div>

<div class="row"><span class="lab">Passenger:</span> <?= $name ?> (<?= $age ?>, <?= $gender ?>)</div>
<div class="row"><span class="lab">Coach:</span> <?= $coach ?> &nbsp;&nbsp; <span class="lab">Seat:</span> <?= $seat ?></div>

<div class="divider"></div>

<div class="row"><span class="lab">Total Fare Paid:</span> ₹ <?= number_format($fare) ?></div>

<div class="divider"></div>
<div class="row" style="text-align:center;font-weight:700;">✅ Ticket Confirmed — Show at Boarding</div>

<div class="btns">
  <button class="btn print" onclick="window.print()">Print Ticket</button>
  <button class="btn wa" onclick="shareWA()">Share on WhatsApp</button>
</div>

</div>

<script>
function shareWA(){
  let text = "🎟 TRAIN TICKET CONFIRMED%0A"+
             "PNR: <?= $pnr ?>%0A"+
             "<?= $train ?> (<?= $train_no ?>)%0A"+
             "<?= $from ?> ➝ <?= $to ?>%0A"+
             "Seat: <?= $coach ?> / <?= $seat ?>%0A"+
             "Passenger: <?= $name ?> (<?= $age ?>)";
  window.open("https://wa.me/?text="+text,"_blank");
}
</script>

</body>
</html>
