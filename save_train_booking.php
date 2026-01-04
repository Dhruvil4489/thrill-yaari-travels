<?php
session_start();
include 'db.php';

// Validate and sanitize input
$train_no = isset($_POST['train_no']) ? trim($_POST['train_no']) : '';
$train_name = isset($_POST['train_name']) ? trim($_POST['train_name']) : '';
$from = isset($_POST['from_city']) ? trim($_POST['from_city']) : '';
$to = isset($_POST['to_city']) ? trim($_POST['to_city']) : '';
$date = isset($_POST['travel_date']) ? trim($_POST['travel_date']) : '';
$dep = isset($_POST['dep_time']) ? trim($_POST['dep_time']) : '';
$arr = isset($_POST['arr_time']) ? trim($_POST['arr_time']) : '';
$class = isset($_POST['travel_class']) ? trim($_POST['travel_class']) : '';
$coach = isset($_POST['coach']) ? trim($_POST['coach']) : '';
$seat = isset($_POST['seat']) ? trim($_POST['seat']) : '';

$passenger_name = isset($_POST['passenger_name']) ? trim($_POST['passenger_name']) : '';
$passenger_age = isset($_POST['passenger_age']) ? (int)$_POST['passenger_age'] : 0;
$passenger_gender = isset($_POST['passenger_gender']) ? trim($_POST['passenger_gender']) : '';

$fare_total = isset($_POST['fare_total']) ? (float)$_POST['fare_total'] : 0;

// Validate required fields
if (empty($train_no) || empty($train_name) || empty($from) || empty($to) || 
    empty($date) || empty($passenger_name) || $passenger_age <= 0 || $fare_total <= 0) {
    header('Location: index.php?error=invalid_booking_data');
    exit;
}

// Generate Realistic IRCTC Style PNR (6 digits)
$pnr = strtoupper(substr(md5(time() . rand()), 0, 6));

// Store Everything in Session to use in ticket page
$_SESSION['train_pnr'] = $pnr;
$_SESSION['train_name'] = $train_name;
$_SESSION['train_no'] = $train_no;
$_SESSION['from_city'] = $from;
$_SESSION['to_city'] = $to;
$_SESSION['travel_date'] = $date;
$_SESSION['dep_time'] = $dep;
$_SESSION['arr_time'] = $arr;
$_SESSION['travel_class'] = $class;
$_SESSION['coach'] = $coach;
$_SESSION['seat'] = $seat;
$_SESSION['passenger_name'] = $passenger_name;
$_SESSION['passenger_age'] = $passenger_age;
$_SESSION['passenger_gender'] = $passenger_gender;
$_SESSION['fare_total'] = $fare_total;

// Redirect to the ticket page
header("Location: train_ticket.php");
exit();
?>
