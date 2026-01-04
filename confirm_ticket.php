<?php
session_start();
include 'db.php';

// Validate and sanitize input
$bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
$seat_no = isset($_POST['seat_no']) ? trim($_POST['seat_no']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$method = isset($_POST['method']) ? trim($_POST['method']) : '';

// Validate required fields
if ($bus_id <= 0 || empty($seat_no) || empty($name) || empty($phone) || $age <= 0 || $price <= 0) {
    header('Location: index.php?error=invalid_data');
    exit;
}

// Generate PNR
$PNR = "TY" . mt_rand(100000, 999999);

// Get Bus Info using prepared statement
$stmt = $conn->prepare("SELECT * FROM buses WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();
$stmt->close();

if (!$bus) {
    header('Location: index.php?error=bus_not_found');
    exit;
}

// Save booking using prepared statement
$stmt = $conn->prepare("INSERT INTO bus_bookings (bus_id, passenger_name, phone, age, seat_no, pnr, price, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ississss", $bus_id, $name, $phone, $age, $seat_no, $PNR, $price, $method);
$stmt->execute();
$stmt->close();

// Mark seat as booked using prepared statement
$stmt = $conn->prepare("UPDATE bus_seats SET status = 'booked' WHERE bus_id = ? AND seat_no = ?");
$stmt->bind_param("is", $bus_id, $seat_no);
$stmt->execute();
$stmt->close();
?>

<div style="padding:30px;text-align:center;">
    <h2 style="color:green;">🎉 Booking Confirmed!</h2>
    <h3>PNR: <span style="color:red;"><?= $PNR ?></span></h3>

    <p><b>Name:</b> <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Seat:</b> <?= htmlspecialchars($seat_no, ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>From:</b> <?= htmlspecialchars($bus['from_city'], ENT_QUOTES, 'UTF-8') ?> → <b>To:</b> <?= htmlspecialchars($bus['to_city'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Departure:</b> <?= htmlspecialchars($bus['departure_time'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Price Paid:</b> ₹ <?= number_format($price, 2) ?> (<?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?>)</p>

    <button onclick="window.print()" style="background:#0066cc;color:#fff;padding:10px 20px;border:none;border-radius:8px;margin-top:20px;">
        Print Ticket
    </button>
    <?php
$name_escaped = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$phone_escaped = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$seat_escaped = htmlspecialchars($seat_no, ENT_QUOTES, 'UTF-8');
$from_escaped = htmlspecialchars($bus['from_city'], ENT_QUOTES, 'UTF-8');
$to_escaped = htmlspecialchars($bus['to_city'], ENT_QUOTES, 'UTF-8');
$departure_escaped = htmlspecialchars($bus['departure_time'], ENT_QUOTES, 'UTF-8');
$method_escaped = htmlspecialchars($method, ENT_QUOTES, 'UTF-8');

$ticketText = urlencode("
🎫 *Thrill Yari Bus Ticket*
--------------------------
👤 Name: $name_escaped
📞 Mobile: $phone_escaped
🎫 Seat: $seat_escaped
🚍 Route: $from_escaped → $to_escaped
⏰ Departure: $departure_escaped
💺 PNR: $PNR
💰 Paid: ₹ " . number_format($price, 2) . " ($method_escaped)
--------------------------
✅ Thank You for Booking with Thrill Yari!
");
$whatsappLink = "https://wa.me/" . urlencode($phone_escaped) . "?text=$ticketText";
?>

<a href="<?= $whatsappLink ?>" target="_blank">
  <button style="background:#25D366;color:#fff;padding:10px 20px;border:none;border-radius:8px;margin-top:15px;">
    Send Ticket on WhatsApp
  </button>
</a>

</div>
