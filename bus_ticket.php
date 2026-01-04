<?php
session_start();
if(!isset($_SESSION['bus_booking'])) die("No ticket data.");

$B = $_SESSION['bus_booking'];
$P = $B['passenger'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Bus Ticket Confirmed - Thrill Yari</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{
  font-family:Poppins, sans-serif;
  background:#eef3ff;
  margin:0;
  padding:40px 0;
  display:flex;
  justify-content:center;
}
.btn-area{
  margin-top:30px;
  display:flex;
  justify-content:center;
  gap:16px;
}

.btn{
  padding:10px 20px;
  border-radius:8px;
  border:none;
  cursor:pointer;
  font-weight:600;
  font-size:15px;
}

.download-btn{
  background:#0d6efd;
  color:#fff;
}

.home-btn{
  background:#22b573;
  color:#fff;
}

.home-btn-link { text-decoration:none; }

@media print{
  #buttons{ display:none; }
}

.ticket-box{
  width:90%;
  max-width:650px;
  background:#fff;
  padding:40px 35px;
  border-radius:20px;
  box-shadow:0 12px 40px rgba(0,0,0,.12);
  text-align:center;
  border:1px solid #d9e3ff;
}
h1{
  font-size:30px;
  margin-bottom:4px;
  font-weight:700;
}
.sub{
  color:#5b6785;
  margin-bottom:25px;
  font-size:15px;
}
.info{
  font-size:18px;
  margin-bottom:10px;
  font-weight:600;
  color:#000;
}
.sep{
  width:100%;
  border-top:1px dashed #c6d3ff;
  margin:22px 0;
}
.label{
  font-size:14px;
  color:#7d89a6;
}
.value{
  font-size:18px;
  font-weight:600;
  margin-bottom:12px;
  color:#111;
}
.grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:15px 25px;
  text-align:left;
}
.btn-area{
  margin-top:30px;
  display:flex;
  justify-content:center;
  gap:12px;
}
.btn{
  padding:12px 22px;
  border-radius:10px;
  border:none;
  cursor:pointer;
  font-weight:600;
}
.download-btn{
  background:#0d6efd;
  color:#fff;
}
.home-btn{
  background:#23b26a;
  color:#fff;
}

/* ✅ PDF me buttons hide hoga */
@media print{
  .btn-area{ display:none; }
}
</style>
</head>
<body>

<div class="ticket-box" id="ticket">

  <h1>🎫 Bus Ticket Confirmed!</h1>
  <div class="sub">Thank you for choosing Thrill Yari</div>

  <div class="info"><?= htmlspecialchars($B['from_city']) ?> → <?= htmlspecialchars($B['to_city']) ?></div>
  <div class="info">Seat: <b><?= htmlspecialchars($B['seat_no']) ?></b></div>

  <div class="sep"></div>

  <div class="grid">
    <div>
      <div class="label">Passenger Name</div>
      <div class="value"><?= htmlspecialchars($P['name']) ?></div>
    </div>
    <div>
      <div class="label">Age / Gender</div>
      <div class="value"><?= (int)$P['age'] ?> / <?= htmlspecialchars($P['gender']) ?></div>
    </div>

    <div>
      <div class="label">Phone</div>
      <div class="value"><?= htmlspecialchars($P['mobile']) ?></div>
    </div>
    <div>
      <div class="label">Email</div>
      <div class="value"><?= htmlspecialchars($P['email']) ?></div>
    </div>

    <div>
      <div class="label">From</div>
      <div class="value"><?= htmlspecialchars($B['from_city']) ?></div>
    </div>
    <div>
      <div class="label">To</div>
      <div class="value"><?= htmlspecialchars($B['to_city']) ?></div>
    </div>

    <div>
      <div class="label">Total Paid</div>
      <div class="value">₹ <?= number_format($B['fare_total']) ?></div>
    </div>
  </div>

</div>

<div class="btn-area" id="buttons">
  <button class="btn download-btn" onclick="downloadPDF()">Download Ticket (PDF)</button>
  <a href="index.php" class="home-btn-link"><button class="btn home-btn">Return Home</button></a>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
<script>
function downloadPDF(){
  const t = document.getElementById("ticket");
  document.querySelector(".btn-area").style.display="none"; // Hide for PDF
  html2pdf().from(t).save("Bus_Ticket.pdf").then(()=>{
    document.querySelector(".btn-area").style.display="flex"; // Show back
  });
}
</script>

</body>
</html>
