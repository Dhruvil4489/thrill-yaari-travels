<?php
session_start();
include 'db.php'; // आपका डेटाबेस कनेक्शन

// 1. JS से JSON डेटा लें
$data = json_decode(file_get_contents('php://input'), true);

// 2. यूज़र आईडी सेशन से लें
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id == 0 || !$data) {
    echo json_encode(['success' => false, 'error' => 'Not logged in or no data.']);
    exit();
}

try {
    // 3. डेटा को वैरिएबल में डालें
    $booking_id = $data['pnr']; // 'TY-123456'
    $package_name = $data['packageName'];
    $travel_date = $data['travelDate'];
    $total_price = $data['price'];
    $payment_method = $data['paymentMethod'];
    $passenger_name = $data['leadName'];
    $passenger_phone = $data['leadPhone'];
    $passenger_email = $data['leadEmail'];
    $passenger_id_proof = $data['leadID'];

    // 4. डेटाबेस में इंसर्ट करें
    $stmt = $conn->prepare(
        "INSERT INTO package_bookings 
        (booking_id, user_id, package_name, travel_date, total_price, payment_method, passenger_name, passenger_phone, passenger_email, passenger_id_proof, booking_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed')"
    );
    
    $stmt->bind_param(
        "sissssssss",
        $booking_id,
        $user_id,
        $package_name,
        $travel_date,
        $total_price,
        $payment_method,
        $passenger_name,
        $passenger_phone,
        $passenger_email,
        $passenger_id_proof
    );

    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>