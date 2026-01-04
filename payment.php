<?php
session_start();
include 'db.php';

// Validate and sanitize input
$bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
$seat_no = isset($_POST['seat_no']) ? trim($_POST['seat_no']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;

// Validate required fields
if ($bus_id <= 0 || empty($seat_no) || empty($name) || empty($phone) || $age <= 0) {
    die("Invalid request. Please go back and fill all required fields.");
}

// Get Bus Price using prepared statement
$stmt = $conn->prepare("SELECT * FROM buses WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();
$stmt->close();

if (!$bus) {
    die("Bus not found.");
}

$price = (float)$bus['price'];

?>

<div style="padding:30px;text-align:center;">
    <h2>Passenger Details</h2>
    <p><b>Name:</b> <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Mobile:</b> <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Age:</b> <?= (int)$age ?></p>
    <p><b>Seat Selected:</b> <?= htmlspecialchars($seat_no, ENT_QUOTES, 'UTF-8') ?></p>
    <p><b>Ticket Price:</b> ₹ <?= number_format($price, 2) ?></p>

    <h3>Select Payment Method</h3>
    <form action="confirm_ticket.php" method="POST">
        <input type="hidden" name="bus_id" value="<?= (int)$bus_id ?>">
        <input type="hidden" name="seat_no" value="<?= htmlspecialchars($seat_no, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="phone" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="age" value="<?= (int)$age ?>">
        <input type="hidden" name="price" value="<?= number_format($price, 2) ?>">

        <select name="method" required style="padding:8px;width:200px;">
            <option value="">-- Select Payment Method --</option>
            <option value="UPI">UPI</option>
            <option value="Debit Card">Debit Card</option>
            <option value="Credit Card">Credit Card</option>
            <option value="Net Banking">Net Banking</option>
        </select><br><br>

        <button style="background:#0d6efd;color:#fff;padding:10px 20px;border:none;border-radius:8px;">
            Pay Now
        </button>
    </form>
</div>
