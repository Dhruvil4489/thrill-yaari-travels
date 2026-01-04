<?php
session_start();
$data = $_SESSION['ticket_data'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Boarding Pass - Thrill Yari</title>

<style>
body {font-family: Arial, sans-serif; background:#eef2f7; margin:0; padding:20px; display:flex; justify-content:center;}
.ticket {
  width: 750px;
  background: #ffffff;
  border-radius: 16px;
  border:1px solid #d4d7db;
  overflow: hidden;
}
.header {
  background: #0a58ff;
  padding: 16px 22px;
  color: white;
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-size:20px;
  font-weight:700;
}
.section {
  padding: 22px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap:14px;
}
.section div b {color:#111;}
.label {font-size:14px; color:#6b7280;}
.value {font-size:18px; font-weight:700; color:#111;}
.route {
  font-size:28px;
  text-align:center;
  padding:14px 0;
  border-top:1px dashed #bfc6d4;
  border-bottom:1px dashed #bfc6d4;
  background:#f6f8fb;
}
.print-area {text-align:center; padding:20px;}
.print-btn {
 background:#007bff; border:none; color:#fff; padding:12px 18px; font-size:18px;
 border-radius:10px; cursor:pointer; width:300px;
}
.print-btn:hover {filter:brightness(.9);}

@media print {
  .print-btn, .print-area {display:none;}
  body {background:white;}
  .ticket {border:none; width:100%;}
}
</style>

</head>
<body>

<div class="ticket">

  <div class="header">
    BOARDING PASS • Thrill Yari
    <span style="font-size:16px;">PNR: <?= $data['ticket'] ?></span>
  </div>

  <div class="route">
    <?= $data['from'] ?> ✈ <?= $data['to'] ?>
  </div>

  <div class="section">
    <div><div class="label">Passenger</div><div class="value"><?= $data['name'] ?> (<?= $data['age'] ?>)</div></div>
    <div><div class="label">Mobile</div><div class="value"><?= $data['phone'] ?></div></div>

    <div><div class="label">Flight</div><div class="value"><?= $data['airline'] ?> (<?= $data['code'] ?>)</div></div>
    <div><div class="label">Seat</div><div class="value"><?= $data['seat'] ?></div></div>

    <div><div class="label">Email</div><div class="value"><?= $data['email'] ?></div></div>
    <div><div class="label">Fare Paid</div><div class="value">₹ <?= number_format($data['fare']) ?></div></div>
  </div>

  <div class="print-area">
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
  </div>

</div>

</body>
</html>
