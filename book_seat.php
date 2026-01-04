<?php
// book_seat.php
session_start();
include 'db.php';

// Required inputs from previous step (your JS already posts these)
$bus_id = (int)($_POST['bus_id'] ?? 0);
$seat_no = trim($_POST['seat_no'] ?? '');

if ($bus_id <= 0 || $seat_no === '') {
  die("<p style='font-family:Inter;max-width:720px;margin:30px auto'>
        Invalid request. Missing bus or seat. <a href='index.php'>Go back</a>
      </p>");
}

// Fetch bus info
$stmt = $conn->prepare("SELECT id, from_city, to_city, bus_name, bus_type, departure_time, arrival_time, price, IFNULL(rating,4) rating FROM buses WHERE id=? LIMIT 1");
$stmt->bind_param("i",$bus_id);
$stmt->execute();
$bus = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bus) die("<p style='font-family:Inter;max-width:720px;margin:30px auto'>Bus not found.</p>");

// Keep minimal session for next steps
$_SESSION['bus_booking'] = [
  'bus_id' => $bus['id'],
  'seat_no'=> $seat_no,
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Bus Passenger Details • Thrill Yari</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root{--pri:#0d6efd;--line:#e5e7eb;--ink:#0f172a;--mut:#6b7280;}
  *{box-sizing:border-box} body{font-family:Inter,system-ui;background:#f6f7fb;margin:0}
  .wrap{max-width:960px;margin:26px auto;padding:0 16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 18px rgba(15,23,42,.06);padding:16px}
  h2{margin:0 0 8px} .small{color:var(--mut);font-size:13px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .in,.sel,textarea{width:100%;height:44px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;font-size:14px}
  textarea{height:80px;resize:vertical}
  .pill{background:#eef2ff;color:#1d4ed8;border:1px solid #c7d2fe;border-radius:999px;padding:4px 10px;font-weight:800;font-size:12px}
  .row{display:flex;align-items:center;gap:8px}
  .actions{display:flex;gap:12px;justify-content:flex-end;margin-top:14px}
  .btn{height:44px;border:none;border-radius:12px;padding:0 18px;font-weight:900;cursor:pointer}
  .ghost{background:#fff;border:1px solid var(--line);color:#111}
  .pri{background:var(--pri);color:#fff}
  @media(max-width:840px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card" style="margin-bottom:12px">
      <h2>Passenger Details</h2>
      <div class="small">Selected Seat: <b><?= htmlspecialchars($seat_no) ?></b></div>
      <div class="row" style="margin-top:8px;flex-wrap:wrap;gap:10px">
        <span class="pill"><?= htmlspecialchars($bus['bus_name']) ?> • <?= htmlspecialchars($bus['bus_type']) ?></span>
        <span class="pill"><?= htmlspecialchars($bus['from_city']) ?> → <?= htmlspecialchars($bus['to_city']) ?></span>
        <span class="pill"><?= htmlspecialchars($bus['departure_time']) ?> → <?= htmlspecialchars($bus['arrival_time']) ?></span>
        <span class="pill">Fare: ₹ <?= number_format($bus['price']) ?></span>
      </div>
    </div>

    <form class="card" method="post" action="bus_payment.php">
      <!-- Hidden booking context -->
      <input type="hidden" name="bus_id"  value="<?= $bus['id'] ?>">
      <input type="hidden" name="seat_no" value="<?= htmlspecialchars($seat_no) ?>">

      <div class="grid">
        <div>
          <label>Full Name</label>
          <input class="in" name="name" required placeholder="e.g., Rahul Sharma">
        </div>
        <div>
          <label>Mobile Number</label>
          <input class="in" name="mobile" required pattern="[0-9]{10}" placeholder="10-digit number">
        </div>
        <div>
          <label>Email</label>
          <input class="in" type="email" name="email" required placeholder="you@example.com">
        </div>
        <div>
          <label>Age</label>
          <input class="in" type="number" name="age" min="1" max="120" required>
        </div>
        <div>
          <label>Gender</label>
          <select class="sel" name="gender" required>
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
        <div>
          <label>City</label>
          <input class="in" name="city" placeholder="Passenger city (optional)">
        </div>
        <div>
          <label>ID Proof Type</label>
          <select class="sel" name="id_type">
            <option>Aadhar</option><option>PAN</option><option>Driving Licence</option><option>Voter ID</option>
          </select>
        </div>
        <div>
          <label>ID Number</label>
          <input class="in" name="id_no" placeholder="Document number">
        </div>
        <div>
          <label>Emergency Contact Name</label>
          <input class="in" name="emg_name" placeholder="Person to contact">
        </div>
        <div>
          <label>Emergency Contact Mobile</label>
          <input class="in" name="emg_mobile" pattern="[0-9]{10}" placeholder="10-digit number">
        </div>
      </div>

      <div style="margin-top:10px">
        <label>Special Request (optional)</label>
        <textarea name="note" placeholder="Any special assistance / instructions"></textarea>
      </div>

      <div class="actions">
        <a class="btn ghost" href="index.php">Cancel</a>
        <button class="btn pri" type="submit">Proceed to Payment</button>
      </div>
    </form>
  </div>
</body>
</html>
