<?php
$pnr = strtoupper(substr(md5(time()), 0, 8)); // Random PNR
$date = date("d-m-Y");
$time = date("h:i A");

echo "
<div style='max-width:600px;margin:30px auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 12px rgba(0,0,0,.2);text-align:center;'>

<h2 style='color:#28a745;'>✅ Ticket Confirmed</h2>
<p><strong>PNR:</strong> $pnr</p>
<p><strong>Seat:</strong> {$_POST['seat_no']}</p>
<p><strong>Name:</strong> {$_POST['name']}</p>
<p><strong>Phone:</strong> {$_POST['phone']}</p>
<p><strong>Age:</strong> {$_POST['age']}</p>
<p><strong>Date:</strong> $date</p>
<p><strong>Time:</strong> $time</p>
<p><strong>Amount Paid:</strong> ₹{$_POST['amount']}</p>

<hr>
<p>🎉 Thank you for choosing <strong>Thrill Yari</strong>!</p>
</div>
";
?>
