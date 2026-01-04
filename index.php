<?php
session_start();
 require_once 'helpers.php';
include 'db.php'; // ✅ सबसे ऊपर लोड करें

// --- (नया) यूज़र डेटा और बुकिंग लोड करें ---
$user_data = null; // Default
$booking_history = []; // Default
$default_avatar = 'https://via.placeholder.com/120/E0E0E0/808080?text=??'; // Default pic

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // 1. यूज़र का डेटा Fetch करें
    // (मान लें कि users टेबल में phone, address, profile_pic_path कॉलम हैं)
    $stmt_user = $conn->prepare("SELECT full_name, email, phone, address, profile_pic_path FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        // सेशन को भी अपडेट रखें
        $_SESSION['user_name'] = $user_data['full_name'];
        $_SESSION['user_email'] = $user_data['email'];
    }
    $stmt_user->close();

    // 2. बुकिंग हिस्ट्री Fetch करें (सिर्फ 'trip' टेबल से)
    // (असली ऐप में, आप flight_bookings, hotel_bookings आदि से भी डेटा लाएँगे)
    $stmt_bookings = $conn->prepare("SELECT 'Package' as type, destination, total_cost, id as pnr, 'Confirmed' as status FROM trip WHERE user_id = ? ORDER BY id DESC");
    $stmt_bookings->bind_param("i", $userId);
    $stmt_bookings->execute();
    $result_bookings = $stmt_bookings->get_result();
    while ($row = $result_bookings->fetch_assoc()) {
        $booking_history[] = $row;
    }
    $stmt_bookings->close();
}
 
// Add Trip
if (isset($_POST['add_trip'])) {
    // ✅ User details (maan lo login ke baad session me save ho gaya hai)
    $user_id = $_SESSION["user_id"] ?? 0;
 
    // ✅ Form data - validate and sanitize
    $destination = isset($_POST['destination']) ? trim($_POST['destination']) : '';
    $flight      = isset($_POST['flight']) ? (float)$_POST['flight'] : 0;
    $hotel       = isset($_POST['hotel']) ? (float)$_POST['hotel'] : 0;
    $transport   = isset($_POST['transport']) ? (float)$_POST['transport'] : 0;
    $days        = isset($_POST['days']) ? (int)$_POST['days'] : 0;
    
    // Validate input
    if (empty($destination) || $days <= 0) {
        set_flash_message("Please fill all required fields.", "error");
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
 
    // ✅ Calculate total & final
    $total = $flight + $hotel + $transport;
    $final = $total * 1.05; // 5% extra
 
    // ✅ Insert query without user_name
    $stmt = $conn->prepare("INSERT INTO trip
        (user_id, destination, flight_cost, hotel_cost, transport_cost, duration_days, total_cost, final_cost)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddddd", $user_id, $destination, $flight, $hotel, $transport, $days, $total, $final);
    $stmt->execute();
    $stmt->close();
 
    set_flash_message("Trip added successfully!", "success");
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}
 
// Fetch trip data for display (user_name removed)
// 🔧 Fixed: $trips_final now includes user_id (was missing earlier)
// Using prepared statements where possible, but these are read-only queries
$trips_all    = $conn->query("SELECT * FROM trip");
$trips_costly = $conn->query("SELECT * FROM trip WHERE total_cost > 100000");
$trips_final  = $conn->query("SELECT user_id, destination, final_cost FROM trip");
$trips_long   = $conn->query("SELECT * FROM trip WHERE duration_days > 7");
$trips_top3   = $conn->query("SELECT * FROM trip ORDER BY total_cost DESC LIMIT 3");
 
/* -------------------------------------------------
   ✅ (नया) Login, Signup & Profile Handling
   --------------------------------------------------*/

// --- (नया) Signup Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup_submit"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $username = $_POST["username"]; // यह 'full_name' है

    // पासवर्ड को हैश करें (Hash)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (email, password, full_name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hashed_password, $username);
        $stmt->execute();
        
        // तुरंत लॉग इन करें
        $_SESSION["user_id"] = $stmt->insert_id;
        $_SESSION["user_email"] = $email; // हम email को 'username' की तरह इस्तेमाल करेंगे
        $_SESSION["user_name"] = $username;
        $_SESSION["logged_in"] = true;
        
        setcookie("username", $email, time() + (86400 * 7), "/");
        set_flash_message("Signup Successful! Welcome, $username!", "success");
        
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) { // 1062 = Duplicate entry
            set_flash_message("This email is already registered.", "error");
        } else {
            set_flash_message("An error occurred: " . $e->getMessage(), "error");
        }
    }
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// --- (नया) Login Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login_submit"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // पासवर्ड वेरिफ़ाई करें
        if (password_verify($password, $user['password'])) {
            // पासवर्ड सही है!
            $_SESSION["user_id"] = $user['id'];
            $_SESSION["user_email"] = $email;
            $_SESSION["user_name"] = $user['full_name'];
            $_SESSION["logged_in"] = true;
            
            setcookie("username", $email, time() + (86400 * 7), "/");
            set_flash_message("Login Successful! Welcome back!", "success");
        } else {
            // पासवर्ड गलत है
            set_flash_message("Invalid email or password.", "error");
        }
    } else {
        // यूज़र नहीं मिला
        set_flash_message("Invalid email or password.", "error");
    }
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// --- (नया) Profile Save Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_personal_info"])) {
    $newName = $_POST['full_name'];
    $newPhone = $_POST['phone'];
    $newAddress = $_POST['address'];
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssi", $newName, $newPhone, $newAddress, $userId);
    $stmt->execute();
    
    // सेशन को भी अपडेट करें
    $_SESSION['user_name'] = $newName;
    set_flash_message("Profile updated successfully!", "success");
    header("Location: " . $_SERVER["PHP_SELF"] . "?tab=profile"); // JS को बताएं कि प्रोफाइल टैब दिखाना है
    exit();
}

// --- (नया) Password Change Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_password"])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $userId = $_SESSION['user_id'];

    if ($newPassword !== $confirmPassword) {
        set_flash_message("New passwords do not match.", "error");
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (password_verify($currentPassword, $user['password'])) {
            // पुराना पासवर्ड सही है
            $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newHashedPassword, $userId);
            $updateStmt->execute();
            set_flash_message("Password updated successfully!", "success");
        } else {
            // पुराना पासवर्ड गलत है
            set_flash_message("Incorrect current password.", "error");
        }
    }
    header("Location: " . $_SERVER["PHP_SELF"] . "?tab=profile_security"); // JS को बताएं कि सिक्योरिटी टैब दिखाना है
    exit();
}
 
/* -------------------------------------------------
   ✅ Logout
--------------------------------------------------*/
if (isset($_GET["logout"])) {
    setcookie("username", "", time() - 3600, "/");
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}
 
/* -------------------------------------------------
   ✅ Trip Cost Calculator
--------------------------------------------------*/
$result = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    $destination = $_POST['destination'];
    $flightCost = (float)$_POST['flightCost'];
    $hotelCost = (float)$_POST['hotelCost'];
    $days = (int)$_POST['days'];
 
    $transportCost = 5000;
    $totalBaseCost = $flightCost + $hotelCost + $transportCost;
 
    $discount = ($days > 5) ? (0.10 * $totalBaseCost) : 0;
    $afterDiscount = $totalBaseCost - $discount;
 
    $gst = 0.05 * $afterDiscount;
    $finalCost = $afterDiscount + $gst;
 
    $tripType = ($finalCost > 100000) ? "Luxury Trip" : "Standard Trip";
    $freeTour = ($days >= 5) ? "Free City Tour Included" : "No Free Tour";
 
    $result = "
      <h3>Cost Breakdown for $destination</h3>
      <p><strong>Base Cost:</strong> ₹" . number_format($totalBaseCost, 2) . "</p>
      <p><strong>Discount:</strong> ₹" . number_format($discount, 2) . "</p>
      <p><strong>GST (5%):</strong> ₹" . number_format($gst, 2) . "</p>
      <p><strong>Final Payable Amount:</strong> ₹" . number_format($finalCost, 2) . "</p>
      <p><strong>Trip Type:</strong> $tripType</p>
      <p><strong>Offer:</strong> $freeTour</p>";
}
 
/* -------------------------------------------------
   ✅ Profile Picture Upload
--------------------------------------------------*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["uploadBtn"])) {
    
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // ✅ डुप्लिकेट फ़ाइलों से बचने के लिए यूनिक नाम बनाएँ
    $imageFileType = strtolower(pathinfo(basename($_FILES["uploadFile"]["name"]), PATHINFO_EXTENSION));
    $uniqueFileName = uniqid('avatar_', true) . '.' . $imageFileType;
    $targetFile = $targetDir . $uniqueFileName;
    
    $userId = $_SESSION["user_id"] ?? 0;

    if ($userId == 0) {
         set_flash_message("You must be logged in to upload.", "error");
    } elseif ($_FILES["uploadFile"]["size"] > 2 * 1024 * 1024) { // 2MB limit
         set_flash_message("File too large (max 2MB).", "error");
    } elseif (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
         set_flash_message("Only JPG, JPEG, PNG & GIF allowed.", "error");
    } else {
        if (move_uploaded_file($_FILES["uploadFile"]["tmp_name"], $targetFile)) {
            
            // ✅ (बदलाव) user_profiles की जगह 'users' टेबल को अपडेट करें
            $stmt = $conn->prepare("UPDATE users SET profile_pic_path = ? WHERE id = ?");
            $stmt->bind_param("si", $targetFile, $userId);
            $stmt->execute();
            $stmt->close();
            
            set_flash_message("Profile picture updated!", "success");
            
            // ✅ पेज रिफ्रेश पर तुरंत दिखाने के लिए $user_data को अपडेट करें
            if ($user_data) {
                $user_data['profile_pic_path'] = $targetFile;
            }
        } else {
            set_flash_message("Upload error.", "error");
        }
    }
    // ✅ प्रोफाइल टैब पर वापस भेजें
    header("Location: " . $_SERVER["PHP_SELF"] . "?tab=profile"); 
    exit();
}
 
/* -------------------------------------------------
   ✅ Flash Message (shows once)
--------------------------------------------------*/
$flash_message = display_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Thrill Yaari - Explore the World</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">


  <style>
    body { 
    font-family: 'Inter', sans-serif; /* ◀️ यह नई लाइन जोड़ें */
    margin: 0; 
    background: #f7f7f7; 
    color: #333; 
}
    header { background-color: #0066cc; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; }
    header h1 { margin: 0; font-size: 1.8rem; }
    nav a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: bold; cursor: pointer; transition: color 0.3s, transform 0.3s; }
    nav a:hover { color: #ffcc00; transform: scale(1.05); }
    .hero { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e') no-repeat center center/cover; height: 70vh; display: flex; align-items: center; justify-content: center; position: relative; text-align: center; color: #fff; }
    .hero h2 { font-size: 3rem; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
    .hero p { font-size: 1.2rem; text-shadow: 1px 1px 3px rgba(0,0,0,0.5); }
    #profile img.profile { max-width: 200px; margin-top: 15px; border-radius: 10px; display: block; }
    footer { background-color: #222; color: #fff; text-align: center; padding: 20px 10px; }
    footer p { margin: 10px 0; }
    .scrolling-images { display: flex; overflow-x: auto; gap: 15px; padding: 15px; justify-content: center; align-items: center; }
    .scrolling-images img { height: 80px; border-radius: 10px; transition: transform 0.3s; }
    .scrolling-images img:hover { transform: scale(1.1); }
    @media (max-width: 600px) { .hero h2 { font-size: 2rem; } nav a { margin-left: 10px; font-size: 0.9rem; } }
    .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
    .tabs { display: flex; justify-content: space-around; margin-bottom: 20px; }
    .tab-button { background: #eee; border: none; padding: 10px 20px; font-size: 1rem; cursor: pointer; transition: background 0.3s; border-radius: 5px; }
    .tab-button.active { background: #0066cc; color: #fff; }
    .form-section, .section-content { display: none; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .form-section.active, .section-content.active { display: block; }
    input, select, textarea, button { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
    button { background-color: #0066cc; color: #fff; border: none; cursor: pointer; transition: background 0.3s; }
    button:hover { background-color: #004999; }
    /* ... आपके अन्य CSS के साथ ... */

#auth-modal { 
  position: fixed; /* पूरी स्क्रीन पर फिक्स */
  top: 0; 
  left: 0; 
  width: 100%; 
  height: 100%; 
  background: rgba(0,0,0,0.7); /* काला ओवरले */
  display: flex; /* यह JS द्वारा 'flex' पर सेट किया जाएगा */
  justify-content: center; 
  align-items: center; 
  z-index: 9999; /* सबसे ऊपर */
  display: none; /* ✅ (बदलाव) डिफ़ॉल्ट रूप से छिपाएँ */
}

#auth-content { 
  background: #fff; 
  padding: 30px; 
  border-radius: 10px; /* गोल कोने */
  width: 350px; 
  text-align: center; 
  box-shadow: 0 5px 15px rgba(0,0,0,0.3); /* शैडो */
}

.auth-tab { 
    margin: 0 10px; 
    padding: 10px 20px; 
    cursor: pointer; 
    border-bottom: 2px solid transparent; 
}
.auth-tab.active { 
    border-bottom: 2px solid #0066cc; /* एक्टिव टैब के नीचे नीला अंडरलाइन */
    font-weight: bold; 
}
.auth-form { display: none; }
.auth-form.active { display: block; }
    .hidden { display: none !important; }
    .destination-list li { margin-bottom: 20px; }
    .destination-list ul { margin-left: 20px; }
     #bus-final-passenger-form { display: none; }

    /* Trip Cost Calculator Styles */
    #trip-result h3, #trip-result p { margin: 5px 0; }
    .book-btn{ background:#007bff; color:#fff; border:none; padding:8px 15px; border-radius:6px; cursor:pointer; }
    .book-btn:hover{ background:#0056b3; }
    
    /* Flash Message Styles */
    .alert {
      padding: 12px 20px;
      margin-bottom: 20px;
      border: 1px solid transparent;
      border-radius: 8px;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .alert-success {
      background-color: #d4edda;
      border-color: #c3e6cb;
      color: #155724;
    }
    .alert-error {
      background-color: #f8d7da;
      border-color: #f5c6cb;
      color: #721c24;
    }
    .alert-warning {
      background-color: #fff3cd;
      border-color: #ffeeba;
      color: #856404;
    }
    .alert-info {
      background-color: #d1ecf1;
      border-color: #bee5eb;
      color: #0c5460;
    }
    .btn-close {
      float: right;
      font-size: 20px;
      font-weight: bold;
      line-height: 1;
      color: inherit;
      opacity: 0.5;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 0;
      margin-left: 15px;
    }
    .btn-close:hover {
      opacity: 1;
    }
 
    /* Seat styles (modal) */
    .seat { border:1px solid #cfd8dc; background:#f5f5f5; border-radius:10px; padding:10px 2px; text-align:center; font-weight:700; cursor:pointer; user-select:none; }
    .seat.available { background:#43a047; color:#fff; border-color:#a5d6a7; }
    .seat.booked   { background:#ef5350; color:#fff; border-color:#ffcdd2; cursor:not-allowed; opacity:.8; }
    .seat.selected { background:#e3f2fd; color:#0d47a1; border-color:#90caf9; outline:2px solid #0d6efd; }
    /* ---- Generic show/hide for tab sections ---- */
.form-section { display: none; }
.form-section.active { display: block; }
 
/* Train section ko bhi toggleable banao */
.ty-train { display: none; }
.ty-train.active { display: block; }
.section-content {
  display: none;
}
.section-content.active {
  display: block;
}
 
 .dest-container{
  max-width:1200px;
  margin:auto;
  text-align:center;
  padding:40px 20px;
  font-family:Inter, sans-serif;
}
.dest-title{
  font-size:34px;
  font-weight:900;
  color:#0f172a;
}
.dest-sub{
  color:#64748b;
  margin-bottom:35px;
}
.dest-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(260px,1fr));
  gap:25px;
}
.dest-card{
  position:relative;
  overflow:hidden;
  border-radius:18px;
  cursor:pointer;
  box-shadow:0 6px 20px rgba(0,0,0,0.12);
  transition:transform .35s;
}
.dest-card:hover{
  transform:scale(1.06);
}
.dest-card img{
  width:100%;
  height:260px;
  object-fit:cover;
  transition:.4s;
}
.dest-card:hover img{
  filter:brightness(65%);
}
.dest-info{
  position:absolute;
  bottom:20px;
  left:20px;
  color:#fff;
  text-align:left;
}
.dest-info h3{
  font-size:22px;
  margin:0;
  font-weight:800;
}
.dest-info p{
  font-size:14px;
  margin-top:3px;
  opacity:.9;
}
.dest-info button{
  margin-top:10px;
  background:#fff;
  color:#111;
  padding:8px 16px;
  border:none;
  border-radius:12px;
  font-weight:700;
  cursor:pointer;
}
/* =============================
   1. PREMIUM NAVBAR CSS (FIX)
   (पुराने header, nav, nav a के CSS को इससे बदलें)
   ============================= */
header {
    background-color: #0066cc;
    color: #fff;
    padding: 10px 30px; /* थोड़ा ज़्यादा पैडिंग */
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); /* बेहतर शैडो */
    z-index: 1000;
}

header h1 {
    margin: 0;
    font-size: 1.8rem;
}

/* --- यह है Navbar फिक्स --- */
nav {
    display: flex; /* इसे हमेशा flex-row (हॉरिजॉन्टल) रखें */
    flex-direction: row;
    align-items: center;
    gap: 5px; /* लिंक्स के बीच कम गैप */
}

header nav a {
    display: flex; /* आइकन और टेक्स्ट को अलाइन करें */
    align-items: center;
    gap: 8px; /* आइकन और टेक्स्ट के बीच जगह */
    color: #fff;
    text-decoration: none;
    padding: 10px 15px; /* बटन जैसा लुक */
    border-radius: 8px;
    font-weight: 600; /* थोड़ा बोल्ड */
    font-size: 0.95rem;
    transition: background-color 0.3s ease, color 0.3s ease;
}
header nav a:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

header nav a.active {
    background-color: #ffcc00; /* आपका पीला रंग */
    color: #004999; /* गहरा नीला टेक्स्ट */
    font-weight: 700;
}

header nav a i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

/* --- मोबाइल के लिए CSS (यहाँ हम इसे vertical करेंगे) --- */
@media (max-width: 1024px) { /* 1024px से कम पर */
    header {
        flex-direction: column;
        align-items: flex-start;
    }
    nav {
        flex-wrap: wrap; /* अगर जगह न हो तो अगली लाइन में जाएँ */
        margin-top: 10px;
    }
}

@media (max-width: 768px) { /* 768px से कम पर */
    header nav a span {
        display: none; /* टेक्स्ट छिपाएँ */
    }
    header nav a {
        padding: 10px; /* सिर्फ आइकन के लिए पैडिंग */
    }
    nav {
        width: 100%;
        justify-content: space-around; /* आइकन्स को बराबर फैला दें */
    }
}



 
  </style>
</head>
<body>
 
<!-- City & Country Datalists -->
<datalist id="city-list">
<?php
// 🔧 Use alias 'city' so both SELECTs return same key
$cities = $conn->query("SELECT DISTINCT from_city AS city FROM buses UNION SELECT DISTINCT to_city AS city FROM buses");
if ($cities) {
  while($c = $cities->fetch_assoc()){
    echo "<option value='{$c['city']}'></option>";
  }
}
?>
</datalist>
 
<datalist id="country-list">
  <option value="USA"></option>
  <option value="UK"></option>
  <option value="France"></option>
  <option value="Japan"></option>
  <option value="India"></option>
</datalist>
 
<!-- 🔹 Login/Signup Modal -->
<?php if (!isset($_SESSION['user_id'])): ?>
<div id="auth-modal"> 
  <div id="auth-content"> <div class="auth-tabs" style="display: flex; justify-content: space-around; margin-bottom: 20px;">
      <div class="auth-tab active" onclick="switchAuth('login')">Login</div>
      <div class="auth-tab" onclick="switchAuth('signup')">Sign Up</div>
    </div>
    
    <form id="login-form" class="auth-form active" action="" method="POST">
      <h2>Login to Thrill Yari</h2>
      <input type="email" id="login-email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="login_submit">Login</button>
    </form>
    
    <form id="signup-form" class="auth-form" action="" method="POST">
      <h2>Create Your Account</h2>
      <input type="text" id="signup-username" name="username" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="signup_submit">Sign Up</button>
    </form>
  </div>
</div>
<?php endif; ?>
 
<!-- Main Content -->
<div id="main-content">
  <!-- Flash Message Display -->
  <?php if (!empty($flash_message)): ?>
    <div style="position: fixed; top: 80px; left: 50%; transform: translateX(-50%); z-index: 10000; max-width: 500px; width: 90%;">
      <?php echo $flash_message; ?>
    </div>
  <?php endif; ?>
  
  <header>
    <div style="display:flex;align-items:center;">
      <img src="https://i.postimg.cc/8PxPKN8x/logo-png.jpg" style="height:50px;margin-right:15px;">
      <h1>Thrill Yari</h1>
    </div>
    <nav>
    <a data-section="home" onclick="showSection('home', this)" class="active"><i class="fa-solid fa-house"></i> <span>Home</span></a>
    
    <a data-section="packages" onclick="showSection('packages', this)"><i class="fa-solid fa-person-hiking"></i> <span>Packages</span></a>
    
    <a data-section="booking" onclick="showSection('booking', this)"><i class="fa-solid fa-map-location-dot"></i> <span>Booking</span></a>
    <a data-section="destinations" onclick="showSection('destinations', this)"><i class="fa-solid fa-signs-post"></i> <span>Destinations</span></a>
    <a data-section="trip-calculator" onclick="showSection('trip-calculator', this)"><i class="fa-solid fa-calculator"></i> <span>Calculator</span></a>
    <a data-section="contact" onclick="showSection('contact', this)"><i class="fa-solid fa-phone"></i> <span>Contact</span></a>
    <a data-section="profile" onclick="showSection('profile', this)"><i class="fa-solid fa-user-circle"></i> <span>Profile</span></a>
    <a data-section="trip-section" onclick="showSection('trip-section', 'this')"><i class="fa-solid fa-suitcase-rolling"></i> <span>My Trips</span></a>
    
    <?php if(isset($_SESSION['logged_in'])): ?>
        <a href="?logout=true"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
    <?php endif; ?>
</nav>
  </header>
 

 
  <!-- Home Section -->
   <div id="home" class="section-content" data-page="home">
  <style>
    /* पुराने हीरो CSS को ओवरराइट करें */
    .home-hero {
      position: relative;
      height: 90vh; /* थोड़ी और ऊंचाई */
      width: 100%;
      background-size: cover;
      background-position: center;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #fff;
      animation: fadeBG 15s infinite alternate; /* धीमा एनीमेशन */
    }
    @keyframes fadeBG {
      0% { background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e'); } /* Beach */
      50% { background-image: url('https://images.unsplash.com/photo-1526772662000-3f88f10405ff'); } /* Mountain */
      100% { background-image: url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee'); } /* Road trip */
    }
    
    /* यह नया हीरो ओवरले और सर्च बार है */
    .home-overlay-new {
      background: rgba(0, 0, 0, 0.3); /* हल्का डार्क ओवरले */
      padding: 40px;
      border-radius: 16px;
      backdrop-filter: blur(8px); /* ग्लास इफ़ेक्ट */
      border: 1px solid rgba(255, 255, 255, 0.2);
      width: 80%;
      max-width: 900px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .home-title-new {
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 10px;
      text-shadow: 2px 2px 10px rgba(0,0,0,0.7);
    }
    .home-subtitle-new {
      font-size: 1.3rem;
      margin-bottom: 30px; /* ज़्यादा जगह */
      text-shadow: 1px 1px 6px rgba(0,0,0,0.6);
    }
    
    /* हीरो सर्च बार */
    .hero-search-bar {
      display: grid;
      grid-template-columns: 2fr 1fr auto; /* 3 कॉलम: कहाँ, कब, बटन */
      gap: 15px;
      background: rgba(255, 255, 255, 0.2);
      padding: 15px;
      border-radius: 12px;
    }
    .search-input {
      display: flex;
      align-items: center;
      background: #fff;
      border-radius: 8px;
      padding: 0 15px;
    }
    .search-input i {
      color: #555;
      font-size: 1.1rem;
    }
    .search-input input {
      width: 100%;
      padding: 12px 10px;
      border: none;
      outline: none;
      font-size: 1rem;
      background: transparent;
      color: #333;
    }
    .search-input input::placeholder {
      color: #777;
    }
    
    .hero-search-btn {
      background: #ffcc00; /* आपका पीला रंग */
      color: #111;
      border: none;
      padding: 12px 25px;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .hero-search-btn:hover {
      background: #fff;
      transform: scale(1.03);
    }

    /* मोबाइल के लिए */
    @media (max-width: 768px) {
      .hero-search-bar {
        grid-template-columns: 1fr; /* एक के नीचे एक */
      }
      .home-title-new {
        font-size: 2.5rem;
      }
    }
    
  </style>

  <div class="home-hero">
    <div class="home-overlay-new">
      <h2 class="home-title-new">Discover Your Next Journey</h2>
      <p class="home-subtitle-new">Plan · Book · Travel · Relive the Experience</p>
      
      <div class="hero-search-bar">
        <div class="search-input">
          <i class="fa-solid fa-location-dot"></i>
          <input type="text" id="hero-search-city" placeholder="Where are you going? (e.g., Mumbai)">
        </div>
        <div class="search-input">
          <i class="fa-solid fa-calendar-days"></i>
          <input type="text" onfocus="(this.type='date')" onblur="(this.type='text')" placeholder="Select Date" id="hero-search-date">
        </div>
        <button class="hero-search-btn" onclick="searchFromHero()">
          <i class="fa-solid fa-search"></i> Search
        </button>
      </div>
    </div>
  </div>
</div>
<section id="tour-packages" class="package-container" data-page="home">
  <style>
    .package-container {
      padding: 50px 20px;
      background: #f4f7f9; /* यह आपके Destinations से मेल खाएगा */
    }
    .section-title {
    text-align: center;
    font-size: 2.8rem; /* थोड़ा बड़ा */
    font-weight: 900; /* सबसे बोल्ड */
    color: #1a202c; /* ज़्यादा डार्क (ब्लैक) */
    margin-bottom: 10px;
} 
    .section-subtitle {
      text-align: center;
      font-size: 1.1rem;
      color: #64748b;
      margin-bottom: 40px;
    }
    .package-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
      max-width: 1200px;
      margin: auto;
    }
    .package-card {
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
    }
    .package-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }
    .package-card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
    }
    .package-info {
      padding: 20px;
    }
    .package-info h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #333;
      margin: 0 0 10px 0;
    }
    .package-info .duration {
      font-size: 1rem;
      color: #0066cc;
      margin-bottom: 15px;
      font-weight: 600;
    }
    .package-info .duration i {
      margin-right: 5px;
    }
    .price-box {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid #eee;
      padding-top: 15px;
      margin-top: 15px;
    }
    .price-box .price {
      font-size: 1.2rem;
      font-weight: 700;
      color: #111;
    }
    .price-box .view-btn {
      background: #0066cc;
      color: #fff;
      border: none;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .price-box .view-btn:hover {
      background: #004999;
    }
  </style>

  <h2 class="section-title">Best Tour Packages</h2>
  <p class="section-subtitle">Handpicked tours to make your dream vacation unforgettable.</p>

  <div class="package-grid">
    
    <div class="package-card">
      <img src="https://www.sikhtours.in/uploads/post/g25.jpg" alt="Golden Triangle">
      <div class="package-info">
        <h3>The Golden Triangle</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 6 Days / 5 Nights</p>
        <p>Explore the classic Indian circuit of Delhi, Agra (Taj Mahal), and Jaipur.</p>
        <div class="price-box">
          <span class="price">₹22,500</span>
         <button class="view-btn" onclick="showPackage('golden-triangle')">View Details</button>
        </div>
      </div>
    </div>

    <div class="package-card">
      <img src="https://activeindiaholidays.com/blog/wp-content/uploads/2025/01/blog-banner.jpg" alt="Kerala Backwaters">
      <div class="package-info">
        <h3>Kerala Backwaters</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 5 Days / 4 Nights</p>
        <p>Relax in a traditional houseboat on the serene backwaters of Alleppey.</p>
        <div class="price-box">
          <span class="price">₹18,000</span>
          <button class="view-btn" onclick="showPackage('kerala')">View Details</button>
        </div>
      </div>
    </div>

    <div class="package-card">
      <img src="https://www.udaipurtours.in/images/himalayan-tour-img.webp" alt="Himalayan Adventure">
      <div class="package-info">
        <h3>Himalayan Adventure</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 7 Days / 6 Nights</p>
        <p>Experience the thrill of the Himalayas with a trip to Manali and Solang Valley.</p>
        <div class="price-box">
          <span class="price">₹25,000</span>
          <button class="view-btn" onclick="showPackage('himalayan')">View Details</button>
        </div>
      </div>
    </div>

  </div>
</section>


<section class="property-type-container" data-page="home">
  <style>
    .property-type-container {
      padding: 50px 20px;
      background: #f4f7f9; /* हल्का ग्रे बैकग्राउंड */
    }
    .property-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      max-width: 1200px;
      margin: auto;
    }
    .property-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      cursor: pointer;
      height: 250px;
    }
    .property-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }
    .property-card:hover img {
      transform: scale(1.1);
    }
    .property-card-info {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 20px;
      color: #fff;
      font-weight: 700;
      font-size: 1.3rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
      background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.7) 100%);
    }
  </style>

  <h2 class="section-title">Browse by Property Type</h2>
  <p class="section-subtitle">Find the perfect stay that fits your style.</p>
  
  <div class="property-grid">
    <div class="property-card" onclick="showBooking('hotel')">
      <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&h=400&fit=crop" alt="Hotels">
      <div class="property-card-info">Hotels</div>
    </div>
    <div class="property-card" onclick="showBooking('hotel')">
      <img src="https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=600&h=400&fit=crop" alt="Villas">
      <div class="property-card-info">Villas</div>
    </div>
    <div class="property-card" onclick="showBooking('hotel')">
      <img src="https://www.shutterstock.com/image-photo/new-modern-apartment-buildings-vancouver-600nw-2326087651.jpg" alt="Apartments">
      <div class="property-card-info">Apartments</div>
    </div>
    <div class="property-card" onclick="showBooking('hotel')">
      <img src="https://cf.bstatic.com/xdata/images/hotel/max1024x768/656411102.jpg?k=7e1185b2b5cee354505b239d9266d7b4e14922dcc32f66404c06554bacdbac1a&o=" alt="Resorts">
      <div class="property-card-info">Resorts</div>
    </div>
    <div class="property-card" onclick="showBooking('hotel')">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS0_mrYd5QFyLaRAhuM-Mw8MojS2-fqixvxtw&s" alt="Cabins">
      <div class="property-card-info">Cabins</div>
    </div>
  </div>
</section>


<section class="inspiration-container" data-page="home">
  <style>
    .inspiration-container {
      padding: 50px 20px;
      background: #f4f7f9; /* हल्का ग्रे */
    }
    .inspiration-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
      max-width: 1200px;
      margin: auto;
    }
    /* यह कार्ड 'package-card' जैसा ही है, बस थोड़ा अलग है */
    .inspiration-card {
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .inspiration-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }
    .inspiration-card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
    }
    .inspiration-info {
      padding: 20px;
    }
    .inspiration-info .tag {
      display: inline-block;
      background: #e0e7ff; /* हल्का नीला */
      color: #0066cc;
      padding: 5px 12px;
      border-radius: 50px;
      font-size: 0.8rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .inspiration-info h3 {
      font-size: 1.3rem;
      font-weight: 700;
      color: #333;
      margin: 0 0 10px 0;
      line-height: 1.4;
    }
    .inspiration-info .read-more {
      color: #0066cc;
      font-weight: 700;
      text-decoration: none;
    }
    .inspiration-info .read-more:hover {
      text-decoration: underline;
    }
  </style>
  
  <h2 class="section-title">Travel Inspiration</h2>
  <p class="section-subtitle">Find tips, guides, and ideas for your next trip.</p>
  
  <div class="inspiration-grid">
    <div class="inspiration-card" onclick="alert('Blog post coming soon!')">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS2d9h8yEpM8CWw-m-PFqv3jUOtyXCTcPlqNA&s" alt="Backpacking">
      <div class="inspiration-info">
        <span class="tag">GUIDE</span>
        <h3>Top 10 Tips for Your First Backpacking Trip</h3>
        <a href="#" class="read-more">Read More &rarr;</a>
      </div>
    </div>
    <div class="inspiration-card" onclick="alert('Blog post coming soon!')">
      <img src="https://www.shutterstock.com/image-photo/import-food-around-world-real-260nw-2567114123.jpg" alt="Food Travel">
      <div class="inspiration-info">
        <span class="tag">FOOD</span>
        <h3>A Foodie's Guide to Delhi: Must-Try Street Food</h3>
        <a href="#" class="read-more">Read More &rarr;</a>
      </div>
    </div>
    <div class="inspiration-card" onclick="alert('Blog post coming soon!')">
      <img src="https://thumbs.dreamstime.com/b/woman-traveler-backpack-hiking-norway-mountains-enjoying-blavatnet-lake-view-active-adventure-travel-solo-woman-393408758.jpg" alt="Solo Travel">
      <div class="inspiration-info">
        <span class="tag">SOLO TRAVEL</span>
        <h3>Why Shimla is the Perfect Getaway for Solo Travelers</h3>
        <a href="#" class="read-more">Read More &rarr;</a>
      </div>
    </div>
  </div>
</section>

<section class="testimonial-container" data-page="home">
  <style>
    .testimonial-container {
      padding: 50px 20px 70px 20px;
      background: #fff; /* सफेद बैकग्राउंड */
    }
    .testimonial-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
      max-width: 1200px;
      margin: auto;
    }
    .testimonial-card {
      background: #f4f7f9; /* हल्का ग्रे */
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 30px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }
    .testimonial-card-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }
    .testimonial-card-header img {
      width: 55px;
      height: 55px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 15px;
      border: 2px solid #0066cc;
    }
    .testimonial-card-header .user-info {
      display: flex;
      flex-direction: column;
    }
    .testimonial-card-header .user-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: #111;
    }
    .testimonial-card-header .user-trip {
      font-size: 0.9rem;
      color: #555;
    }
    .testimonial-card .stars {
      color: #ffcc00; /* आपका पीला रंग */
      font-size: 1.1rem;
      margin-bottom: 15px;
    }
    .testimonial-card .quote {
      font-size: 1.05rem;
      color: #333;
      line-height: 1.7;
      font-style: italic;
      position: relative;
      padding-left: 20px;
      border-left: 3px solid #0066cc;
    }
  </style>
  
  <h2 class="section-title">What Our Travelers Say</h2>
  <p class="section-subtitle">Real stories from our happy customers.</p>
  
  <div class="testimonial-grid">
    <div class="testimonial-card">
      <div class="testimonial-card-header">
        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop" alt="User 1">
        <div class="user-info">
          <span class="user-name">Rohan Sharma</span>
          <span class="user-trip">Golden Triangle Tour</span>
        </div>
      </div>
      <div class="stars">
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
      </div>
      <p class="quote">"Thrill Yari made our dream trip possible. Everything was perfectly organized, from hotels to the driver. Highly recommended!"</p>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-card-header">
        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop" alt="User 2">
        <div class="user-info">
          <span class="user-name">Priya Patel</span>
          <span class="user-trip">Kerala Backwaters</span>
        </div>
      </div>
      <div class="stars">
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star-half-stroke"></i>
      </div>
      <p class="quote">"The houseboat experience in Kerala was magical. The booking was easy and the customer support was very helpful."</p>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-card-header">
        <img src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=100&h=100&fit=crop" alt="User 3">
        <div class="user-info">
          <span class="user-name">Ankit Singh</span>
          <span class="user-trip">Himalayan Adventure</span>
        </div>
      </div>
      <div class="stars">
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
      </div>
      <p class="quote">"Booked the Manali tour. The bus was comfortable, and the package was totally worth the price. Amazing adventure!"</p>
    </div>
  </div>
</section>
<section class="why-choose-us-container" data-page="home">
  <style>
    .why-choose-us-container {
      padding: 50px 20px;
      background: #fff; /* सफेद बैकग्राउंड */
    }
    .why-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 30px;
      max-width: 1200px;
      margin: auto;
    }
    .why-card {
      text-align: center;
      padding: 20px;
    }
    .why-card i {
      font-size: 2.5rem;
      color: #0066cc; /* आपका थीम रंग */
      margin-bottom: 15px;
    }
    .why-card h3 {
      font-size: 1.3rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 10px;
    }
    .why-card p {
      color: #666;
      line-height: 1.6;
    }
  </style>
  
  <h2 class="section-title">Why Choose Thrill Yari?</h2>
  <p class="section-subtitle">Your perfect trip, perfectly planned.</p>
  
  <div class="why-grid">
    <div class="why-card">
      <i class="fa-solid fa-hand-holding-dollar"></i>
      <h3>Best Price Guarantee</h3>
      <p>Find unbeatable prices on flights, hotels, and packages.</p>
    </div>
    <div class="why-card">
      <i class="fa-solid fa-headset"></i>
      <h3>24/7 Customer Support</h3>
      <p>Our team is always available to help you, anytime, anywhere.</p>
    </div>
    <div class="why-card">
      <i class="fa-solid fa-map-marked-alt"></i>
      <h3>Handpicked Tours</h3>
      <p>Curated experiences and tours you won't find anywhere else.</p>
    </div>
    <div class="why-card">
      <i class="fa-solid fa-shield-halved"></i>
      <h3>Secure Booking</h3>
      <p>Book with confidence using our secure payment gateways.</p>
    </div>
  </div>
</section>
 <div id="packages" class="section-content">
  <style>
    /* सिर्फ 'Packages' पेज के लिए स्टाइल */
    .package-page-container {
      max-width: 1100px;
      margin: 30px auto;
      padding: 20px;
    }
    .package-page-title {
      font-size: 2.8rem;
      font-weight: 900;
      color: #0f172a;
      text-align: center;
      margin-bottom: 30px;
    }
    .package-detail-card {
      display: grid;
      grid-template-columns: 1fr 1fr; /* 50/50 लेआउट */
      gap: 30px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      overflow: hidden;
      margin-bottom: 40px;
    }
    .package-detail-card:nth-child(even) {
        grid-template-columns: 1fr 1fr; /* इसे स्टेबल रखने के लिए */
        /* आप चाहें तो `direction: rtl;` ट्राई कर सकते हैं */
    }
    .package-detail-image img {
      width: 100%;
      height: auto;     /* ◀️ यह बदलाव 1 है (इमेज को अपनी ऊंचाई खुद तय करने दें) */
      display: block;   /* ◀️ यह किसी भी एक्स्ट्रा स्पेस को हटाता है */
    }
    .package-detail-info {
      padding: 30px;
      align-self: start;  /* ◀️ यह बदलाव 2 है (ताकि जानकारी ऊपर से शुरू हो) */
    }
    .package-detail-info h3 {
      font-size: 2rem;
      font-weight: 700;
      color: #0066cc;
      margin-top: 0;
    }
    .package-detail-info .duration {
      font-size: 1.1rem;
      color: #555;
      font-weight: 600;
      margin-bottom: 15px;
    }
    .package-detail-info .duration i {
      color: #0066cc;
      margin-right: 8px;
    }
    .package-detail-info .price {
      font-size: 1.8rem;
      font-weight: 800;
      color: #111;
      margin-bottom: 20px;
    }
    .package-detail-info .price span {
      font-size: 1rem;
      color: #777;
      font-weight: 400;
    }
    .package-detail-info h4 {
      font-size: 1.2rem;
      color: #333;
      border-bottom: 2px solid #eee;
      padding-bottom: 5px;
    }
    .package-detail-info ul {
      padding-left: 20px;
      list-style-type: '✔';
    }
    .package-detail-info ul li {
      margin-bottom: 8px;
      padding-left: 10px;
    }
    
    /* बुकिंग फॉर्म स्टाइल */
    .package-booking-form {
      background: #f4f7f9;
      border: 1px solid #ddd;
      padding: 20px;
      border-radius: 12px;
      margin-top: 20px;
    }
    .package-booking-form h4 { margin-top: 0; text-align: center; }
    .package-booking-form input {
      width: 100%;
      padding: 12px;
      margin-bottom: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    .package-book-btn {
      width: 100%;
      padding: 14px;
      background: #ffcc00;
      color: #111;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
    }

    @media (max-width: 768px) {
        .package-detail-card {
            grid-template-columns: 1fr; /* मोबाइल पर स्टैक */
        }
    }
  </style>
  
  <div class="package-page-container">
    <h2 class="package-page-title">Explore Our Tour Packages</h2>
    
    <div class="package-detail-card" id="package-detail-golden-triangle">
      <div class="package-detail-image">
        <img src="https://www.sikhtours.in/uploads/post/g25.jpg" alt="Golden Triangle">
      </div>
      <div class="package-detail-info">
        <h3>The Golden Triangle</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 6 Days / 5 Nights</p>
        <p class="price">₹22,500 <span>per person</span></p>
        <p>Explore the classic Indian circuit of Delhi, Agra (Taj Mahal), and Jaipur. Witness the grandeur of Mughal and Rajput architecture.</p>
        
        <h4>What's Included</h4>
        <ul>
          <li>4-Star Hotel Accommodation</li>
          <li>Private A/C Car</li>
          <li>Daily Buffet Breakfast</li>
          <li>All Sightseeing Tours</li>
        </ul>

        <div class="package-booking-form">
  <h4>Book This Tour!</h4>
  <input type="text" class="pkg-name" placeholder="Your Name" required>
  <input type="email" class="pkg-email" placeholder="Your Email" required>
  <input type="date" class="pkg-date" required>
  <button type="button" class="package-book-btn" onclick="handlePackageBooking(this, 'The Golden Triangle', '₹22,500')">Confirm Booking</button>
</div>
      </div>
    </div>
    
    <div class="package-detail-card" id="package-detail-kerala">
      <div class="package-detail-image">
        <img src="https://activeindiaholidays.com/blog/wp-content/uploads/2025/01/blog-banner.jpg" alt="Kerala Backwaters">
      </div>
      <div class="package-detail-info">
        <h3>Kerala Backwaters</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 5 Days / 4 Nights</p>
        <p class="price">₹18,000 <span>per person</span></p>
        <p>Relax in a traditional houseboat on the serene backwaters of Alleppey. Enjoy lush greenery and authentic Keralan cuisine.</p>
        
        <h4>What's Included</h4>
        <ul>
          <li>1 Night A/C Houseboat</li>
          <li>3 Nights Hotel Stay</li>
          <li>All Meals on Houseboat</li>
          <li>Airport Transfers</li>
        </ul>
        
        <div class="package-booking-form">
  <h4>Book This Tour!</h4>
  <input type="text" class="pkg-name" placeholder="Your Name" required>
  <input type="email" class="pkg-email" placeholder="Your Email" required>
  <input type="date" class="pkg-date" required>
  <button type="button" class="package-book-btn" onclick="handlePackageBooking(this, 'Kerala Backwaters', '₹18,000')">Confirm Booking</button>
</div>
      </div>
    </div>
    
    <div class="package-detail-card" id="package-detail-himalayan">
      <div class="package-detail-image">
        <img src="https://www.udaipurtours.in/images/himalayan-tour-img.webp" alt="Himalayan Adventure">
      </div>
      <div class="package-detail-info">
        <h3>Himalayan Adventure</h3>
        <p class="duration"><i class="fa-solid fa-clock"></i> 7 Days / 6 Nights</p>
        <p class="price">₹25,000 <span>per person</span></p>
        <p>Experience the thrill of the Himalayas with a trip to Manali and Solang Valley. Includes paragliding and trekking options.</p>
        
        <h4>What's Included</h4>
        <ul>
          <li>Riverside Camp/Hotel Stay</li>
          <li>Daily Breakfast & Dinner</li>
          <li>Trek to Solang Valley</li>
          <li>All Transfers</li>
        </ul>
        
        <div class="package-booking-form">
  <h4>Book This Tour!</h4>
  <input type="text" class="pkg-name" placeholder="Your Name" required>
  <input type="email" class="pkg-email" placeholder="Your Email" required>
  <input type="date" class="pkg-date" required>
  <button type="button" class="package-book-btn" onclick="handlePackageBooking(this, 'Himalayan Adventure', '₹25,000')">Confirm Booking</button>
</div>
      </div>
    </div>

  </div>
</div>
  <!-- ✅ Booking Section -->
  <div id="booking" class="section-content">
    <div class="container">
      <h2>Book Your Travel</h2>
      <div class="tabs">
        <button class="tab-button" onclick="showForm('hotel', event)">Hotel</button>
        <button class="tab-button" onclick="showForm('flight', event)">Flight</button>
        <button class="tab-button" onclick="showForm('ty-train', event)">Train</button>
        <button class="tab-button" onclick="showForm('bus', event)">Bus</button>
      </div>
 
      <!-- ✅ Hotel Form -->
      <!-- ✅ Hotel Form (wrapped correctly) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
 
<style>
:root{
  --hy-primary:#0d6efd;
  --hy-primary-dark:#0b58d1;
  --hy-bg:#f5f7fc;
  --hy-card:#ffffff;
  --hy-text:#1f2739;
  --hy-sub:#6b7280;
  --hy-line:#e5e7eb;
  --hy-green:#16a34a;
}

.hy *{box-sizing:border-box}
.hy{font-family:Inter,system-ui,Segoe UI,Roboto,Arial;color:var(--hy-text);background:var(--hy-bg);}

/* --- Search Bar --- */
.hy .hy-wrap{max-width:1250px;margin:auto;padding:22px;}
.hy .hy-bar{
  display:grid;grid-template-columns:2fr 1.2fr 1.2fr 1.4fr auto;
  background:#fff;padding:14px;border-radius:14px;
  border:1px solid #d6dae3;gap:12px;
  box-shadow:0 4px 18px rgba(0,0,0,0.06);
}
.hy .hy-input, .hy select{
  height:48px;border:1px solid var(--hy-line);
  border-radius:12px;padding:10px 14px;
  font-size:14px;background:#fff;
}
.hy .hy-btn{
  background:var(--hy-primary);color:#fff;border:none;padding:0 28px;
  height:48px;border-radius:12px;font-weight:700;cursor:pointer;
}
.hy .hy-btn:hover{background:var(--hy-primary-dark);}

/* --- Layout Grid --- */
.hy .hy-grid{display:grid;grid-template-columns:300px 1fr;gap:24px;margin-top:20px;}

/* --- Sidebar --- */
.hy .hy-side{
  background:#fff;padding:22px;border-radius:18px;
  border:1px solid #e2e6ef;
  box-shadow:0 8px 22px rgba(0,0,0,0.06);
  position:sticky;top:90px;
}
.hy .hy-side h4{font-size:17px;font-weight:800;margin-bottom:12px;color:#111}

/* Map Button */
.hy .hy-mapbtn{
  width:100%;padding:12px;border-radius:12px;
  background:#eef6ff;border:1px solid #bed8ff;
  font-weight:600;color:#1454c4;
}
.hy .hy-mapbtn:hover{background:#dbeeff}

/* Price Input */
#hy-min{
  border:1px solid #cbd5e1;border-radius:10px;
  padding:10px;font-weight:600;font-size:14px;
}

/* Slider */
#hy-range{
  appearance:none;width:100%;height:6px;border-radius:5px;
  background:#d4d7e1;cursor:pointer;margin:10px 0;
}
#hy-range::-webkit-slider-thumb{
  appearance:none;width:18px;height:18px;background:var(--hy-primary);
  border-radius:50%;border:3px solid #fff;box-shadow:0 0 6px rgba(0,0,0,0.25);
}

/* Filter Checkboxes */
.hy .hy-star label{
  display:flex;align-items:center;gap:10px;
  font-size:14px;color:#374151;padding:6px 0;cursor:pointer;
}
.hy .hy-stars{color:#f59e0b;font-size:16px;font-weight:600}

/* --- Results Header --- */
.hy .hy-results{display:flex;justify-content:space-between;margin-bottom:10px;}
.hy .hy-sort{font-size:14px;color:#555;gap:8px;display:flex;align-items:center}

/* --- Hotel Card --- */
.hy .hy-list{display:flex;flex-direction:column;gap:16px;}
.hy .hy-card{
  background:#fff;border-radius:16px;border:1px solid #dfe3ec;
  display:grid;grid-template-columns:260px 1fr 220px;overflow:hidden;
  transition:0.2s;
  box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
.hy .hy-card:hover{box-shadow:0 6px 22px rgba(0,0,0,0.08);transform:translateY(-2px)}

.hy .hy-img{width:100%;height:205px;object-fit:cover;}

.hy .hy-hinfo{padding:14px;}
.hy .hy-hname{font-size:19px;font-weight:800;margin-bottom:6px;color:#111;}

.hy .hy-meta{font-size:13px;color:#555;display:flex;flex-wrap:wrap;gap:12px;margin-bottom:6px;}
.hy .hy-chip{
  background:#eaf0ff;color:#2b49c7;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;
}
.hy .hy-amen{font-size:13px;color:#666;display:flex;gap:14px;margin-top:10px}

/* Price Box */
.hy .hy-right{
  border-left:1px dashed #dbe0ea;padding:14px;
  display:flex;flex-direction:column;justify-content:center;align-items:flex-end;
}
.hy .hy-price{font-size:22px;font-weight:800;margin-bottom:4px}
.hy .hy-note{font-size:12px;color:#777;margin-bottom:3px}
.hy .hy-btn{width:auto;margin-top:10px;padding:10px 18px;border-radius:10px}

/* Mobile */
@media (max-width:1000px){
  .hy .hy-grid{grid-template-columns:1fr;}
  .hy .hy-card{grid-template-columns:1fr;}
  .hy .hy-right{border-left:none;border-top:1px dashed #dde2ea;align-items:flex-start;}
  .hy .hy-img{width:100%;height:220px;}
}
</style>

 
<div id="hotel" class="form-section active">
  <div class="hy" id="hotel-finder">
    <div class="hy-wrap">
      <!-- Search Bar -->
      <div class="hy-bar">
        <input id="hy-city" class="hy-input" list="hy-cities" placeholder="Where are you going? (e.g., Ahmedabad)">
        <datalist id="hy-cities">
          <option value="Ahmedabad"></option><option value="Mumbai"></option>
          <option value="Delhi"></option><option value="Kolkata"></option><option value="Jaipur"></option>
        </datalist>
        <input id="hy-in" class="hy-input" type="date">
        <input id="hy-out" class="hy-input" type="date">
        <select id="hy-guests" class="hy-input">
          <option value="2-1">2 adults · 0 children · 1 room</option>
          <option value="1-1">1 adult · 1 room</option>
          <option value="3-2">3 adults · 1 child · 2 rooms</option>
          <option value="4-2">4 adults · 2 rooms</option>
        </select>
        <button class="hy-btn" id="hy-search">Search</button>
      </div>
 
      <!-- Grid -->
      <div class="hy-grid">
        <!-- Sidebar -->
        <aside class="hy-side">
          <div class="hy-topline"><h4>Your budget (per night)</h4></div>
          <div>
            <input id="hy-min" class="hy-input" type="number" value="500" min="0" step="50" placeholder="Min ₹">
            <div class="hy-range"><input id="hy-range" type="range" min="500" max="4000" value="4000" style="width:100%"></div>
            <div style="display:flex;justify-content:space-between;color:var(--hy-sub);font-size:13px"><span>₹ 500</span><span>₹ 4,000+</span></div>
          </div>
          <div style="margin-top:16px">
            <h4>Popular filters</h4>
            <div class="hy-star">
              <label><input type="checkbox" class="hy-starcb" value="5"> <span class="hy-stars">★★★★★</span> 5 star</label>
              <label><input type="checkbox" class="hy-starcb" value="4"> <span class="hy-stars">★★★★☆</span> 4 star</label>
              <label><input type="checkbox" class="hy-starcb" value="3"> <span class="hy-stars">★★★☆☆</span> 3 star</label>
              <label><input type="checkbox" id="hy-can" value="free"> Free cancellation</label>
              <label><input type="checkbox" id="hy-bfast" value="breakfast"> Breakfast included</label>
            </div>
          </div>
        </aside>
 
        <!-- Results -->
        <section>
          <div class="hy-results">
            <div id="hy-count" style="font-weight:700">Results</div>
            <div class="hy-sort">
              <span>Sort by:</span>
              <select id="hy-sortsel" class="hy-input" style="height:38px">
                <option value="top">Top picks for long stays</option>
                <option value="price-asc">Price (low → high)</option>
                <option value="price-desc">Price (high → low)</option>
                <option value="rating-desc">Rating (high → low)</option>
              </select>
            </div>
          </div>
          <div id="hy-list" class="hy-list"></div>
          <div id="hy-empty" class="hy-empty" style="display:none">No properties match your filters. Try adjusting budget or stars.</div>
        </section>
      </div>
    </div>
  </div>
</div> <!-- ✅ end of #hotel form-section -->
 
<script>
/* ===== Hotel data & logic (UNCHANGED) ===== */
const HY_HOTELS = [
  {
    id: 1,
    city: "Ahmedabad",
    name: "HOTEL SUN town",
    stars: 2,
    rating: 8.1,
    area: "Near City Centre (10.3 km)",
    image: "https://q-xx.bstatic.com/xdata/images/hotel/max500/26830014.jpg?k=6d60b099b6c6d4bb1a3224d82177e911821f6a06739449a21a485574278f229c&o=",
    price: 880,
    breakfast: true,
    amenities: ["24h front desk", "Parking", "Restaurant"]
  },
  {
    id: 2,
    city: "Ahmedabad",
    name: "Hotel Riverfront Residency",
    stars: 3,
    rating: 8.6,
    area: "Sabarmati Riverfront (6.2 km)",
    image: "https://media.istockphoto.com/id/503016934/photo/entrance-of-luxury-hotel.jpg?s=612x612&w=0&k=20&c=DXFzucB2xWGf3PI6_yjhLKDvrFcGlOpOjXh6KDI8rqU=",
    price: 1450,
    amenities: ["Free WiFi", "Parking"]
  },
  {
    id: 3,
    city: "Ahmedabad",
    name: "The Heritage Courtyard",
    stars: 4,
    rating: 9.0,
    area: "Old City (3.1 km)",
    image: "https://media.istockphoto.com/id/903417402/photo/luxury-construction-hotel-with-swimming-pool-at-sunset.jpg?s=612x612&w=0&k=20&c=NyPC_c-wE3W_CImA4t57FpyGy6f428CYROd80jxVC4A=",
    price: 2400,
    breakfast: true,
    free_cancel: true,
    amenities: ["Breakfast", "Wifi", "Pool"]
  },
  {
    id: 4,
    city: "Mumbai",
    name: "Seaside Bay Hotel",
    stars: 5,
    rating: 9.1,
    area: "Marine Drive",
    image: "https://r1imghtlak.mmtcdn.com/57663784996211e8bfae0adfcdb46c1c.jpg?&output-quality=75&downsize=910:612&crop=910:612;4,0&output-format=webp",
    price: 4200,
    breakfast: true,
    free_cancel: false,
    amenities: ["Sea view", "Pool", "Spa"]
  },
  {
    id: 5,
    city: "Delhi",
    name: "Capitol Residency",
    stars: 4,
    rating: 8.4,
    area: "Connaught Place",
    image: "https://dynamic-media-cdn.tripadvisor.com/media/photo-o/08/86/8a/0f/the-lalit-jaipur.jpg?w=1200&h=-1&s=1",
    price: 2600,
    amenities: ["Wifi", "Gym"]
  }
];
 
const $ = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
 
function nightsBetween(inDate, outDate){
  if(!inDate || !outDate) return 1;
  const a = new Date(inDate), b = new Date(outDate);
  const diff = Math.ceil((b - a)/(1000*60*60*24));
  return diff > 0 ? diff : 1;
}
function starString(n){ return "★★★★★".slice(0,n) + "☆☆☆☆☆".slice(0,5-n); }
 
function renderList(){
  const city = $('#hy-city').value.trim();
  const min = Number($('#hy-min').value || 0);
  const max = Number($('#hy-range').value || 999999);
  const wantCancel = $('#hy-can').checked;
  const wantBfast = $('#hy-bfast').checked;
  const starVals = $$('.hy-starcb:checked').map(i=>Number(i.value));
  const sort = $('#hy-sortsel').value;
 
  const inD = $('#hy-in').value, outD = $('#hy-out').value;
  const nights = nightsBetween(inD, outD);
 
  let list = HY_HOTELS.filter(h=>{
    if(city && h.city.toLowerCase() !== city.toLowerCase()) return false;
    if(h.price < min || h.price > max) return false;
    if(wantCancel && !h.free_cancel) return false;
    if(wantBfast && !h.breakfast) return false;
    if(starVals.length && !starVals.includes(h.stars)) return false;
    return true;
  });
 
  switch(sort){
    case 'price-asc': list.sort((a,b)=>a.price-b.price); break;
    case 'price-desc': list.sort((a,b)=>b.price-a.price); break;
    case 'rating-desc': list.sort((a,b)=>b.rating-a.rating); break;
    default: list.sort((a,b)=>(b.rating + (b.breakfast?0.2:0)) - (a.rating + (a.breakfast?0.2:0)));
  }
 
  $('#hy-count').textContent = city ? `${city}: ${list.length} properties found` : `${list.length} properties found`;
 
  const root = $('#hy-list'); root.innerHTML = '';
  if(list.length===0){ $('#hy-empty').style.display='block'; return; } else { $('#hy-empty').style.display='none'; }
 
  list.forEach(h=>{
    const nights = nightsBetween($('#hy-in').value,$('#hy-out').value);
    const total = h.price * nights;
    const taxes = Math.round(total * 0.05);
    const final = total + taxes;
 
    const card = document.createElement('article');
    card.className = 'hy-card';
    card.innerHTML = `
      <img class="hy-img" src="${h.image}" alt="${h.name}">
      <div class="hy-hinfo">
        <div class="hy-row" style="justify-content:space-between;align-items:flex-start">
          <h3 class="hy-hname">${h.name} ${h.stars?`<span title="${h.stars} star" style="font-size:13px;color:#f59e0b;margin-left:6px">${'★'.repeat(h.stars)}</span>`:''}</h3>
          ${h.badge ? `<span class="hy-badge">${h.badge}</span>`:''}
        </div>
        <div class="hy-meta">
          <span class="hy-chip">${h.city}</span>
          <span>${h.area || ''}</span>
          <span>Rating: <b>${h.rating}</b>/10</span>
          ${h.breakfast?'<span>🍳 Breakfast included</span>':''}
        </div>
        <div class="hy-amen">${h.amenities.map(a=>`<span>• ${a}</span>`).join('')}</div>
      </div>
      <div class="hy-right">
        <div style="text-align:right">
          <div class="hy-note">${nights} night${nights>1?'s':''}, taxes extra</div>
          <div class="hy-price">₹ ${final.toLocaleString()}</div>
          <div class="hy-note">+ ₹ ${taxes.toLocaleString()} taxes & charges</div>
        </div>
        <button class="hy-btn" data-id="${h.id}">See availability</button>
      </div>`;
    root.appendChild(card);
  });
 
  $$('#hy-list .hy-btn').forEach(btn=>{
    btn.onclick = (e)=>{
      const id = Number(e.currentTarget.dataset.id);
      const hotel = HY_HOTELS.find(x=>x.id===id);
      openAvailability(hotel, nightsBetween($('#hy-in').value,$('#hy-out').value));
    };
  });
}
 
let modalEl;
function openAvailability(hotel, nights){
  const rooms = ["101", "102", "103", "201", "202", "203"]; // You can add more rooms
 
  let modal = document.createElement("div");
  modal.style = "position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;justify-content:center;align-items:center;z-index:9999";
 
  modal.innerHTML = `
    <div style="background:#fff;width:380px;border-radius:14px;padding:20px;">
      <h3 style="margin-bottom:10px;color:#0d6efd;">${hotel.name}</h3>
      <p>Select Room Number:</p>
 
      <select id="room-select" style="width:100%;padding:8px;margin:10px 0;border-radius:8px;">
        ${rooms.map(r=>`<option value="${r}">Room ${r}</option>`).join("")}
      </select>
 
      <button onclick="openHotelReview('${hotel.name}', document.getElementById('room-select').value)"
style="background:#0d6efd;color:#fff;padding:10px;width:100%;border:none;border-radius:8px;">
Confirm
</button>
 
<button onclick="this.parentElement.parentElement.remove()" 
style="margin-top:8px;background:#eee;padding:8px;width:100%;border:none;border-radius:8px;">
Cancel
</button>
 
    </div>
  `;
 
  document.body.appendChild(modal);
}
 
function proceedHotelPaymentFinal(hotel, room, name, phone, email, age, aadhar, guests, base, tax, total, checkIn, checkOut, nights){
 
  // Show Payment Mode Page
  document.body.innerHTML = `
  <div style="max-width:460px;margin:40px auto;background:#fff;border-radius:14px;padding:25px;font-family:Inter;box-shadow:0 4px 18px rgba(0,0,0,0.12)">
    <h2 style="text-align:center;color:#0d6efd;">Select Payment Method</h2>
 
    <select id="pay-method" style="width:100%;padding:12px;margin-top:15px;border:1px solid #ccc;border-radius:10px;font-size:15px;">
      <option value="UPI">UPI</option>
      <option value="Debit Card">Debit Card</option>
      <option value="Credit Card">Credit Card</option>
      <option value="Net Banking">Net Banking</option>
    </select>
 
    <button onclick="confirmHotelFakePayment('${hotel}','${room}','${name}','${phone}','${email}','${age}','${aadhar}',${guests},${base},${tax},${total},'${checkIn}','${checkOut}',${nights})"
    style="background:#138a36;color:#fff;padding:15px;width:100%;border:none;border-radius:10px;font-weight:700;margin-top:20px;">
      Pay ₹ ${total} & Continue
    </button>
 
    <button onclick="location.reload()" style="margin-top:10px;background:#eee;padding:12px;width:100%;border:none;border-radius:8px;">
      Cancel
    </button>
  </div>`;
}
function confirmHotelFakePayment(hotel, room, name, phone, email, age, aadhar, guests, base, tax, total, checkIn, checkOut, nights){
 
  const paymentMode = document.getElementById("pay-method").value;
  const ticketNo = "HT" + Math.floor(100000 + Math.random()*900000);
 
  const message = `🏨 *Hotel Booking Confirmed!*  
Ticket: ${ticketNo}
Hotel: ${hotel}
Room: ${room}
Guest: ${name} (${age})
Check-in: ${checkIn}
Check-out: ${checkOut}
Total Paid: ₹${total}`;
 
  document.body.innerHTML = `
  <style>
  @media print {
 
    /* Hide all action buttons */
    #no-print,
    button,
    a {
      display: none !important;
      visibility: hidden !important;
    }
 
    /* Remove page margins to center ticket */
    body, html {
      padding: 0 !important;
      margin: 0 !important;
    }
 
    /* Clean ticket presentation */
    #ticket-card {
      box-shadow: none !important;
      border: none !important;
      margin: 0 auto !important;
      width: 100% !important;
      page-break-inside: avoid;
    }
 
    /* Remove dotted outline highlight */
    * {
      -webkit-print-color-adjust: exact !important;
      outline: none !important;
      border-radius: 0 !important;
    }
  }
</style>
 
 
  <div id="ticket-card" style="max-width:620px;margin:35px auto;border-radius:14px;background:#fff;
      font-family:Inter,system-ui;box-shadow:0 5px 25px rgba(0,0,0,0.20);padding:28px;border:1px solid #e5e7eb;">
 
    <h2 style="text-align:center;margin-bottom:10px;color:#0a58ff;font-weight:900">
      ✅ Hotel Booking Confirmed
    </h2>
 
    <div style="text-align:center;font-size:14px;color:#6b7280;margin-bottom:18px;">
      Ticket No: <b>${ticketNo}</b>
    </div>
 
    <div style="border-left:4px solid #0a58ff;padding-left:14px;line-height:1.8;font-size:15px;">
      <b>Guest Name:</b> ${name} (${age}) <br>
      <b>Phone:</b> ${phone} <br>
      <b>Email:</b> ${email} <br>
      <b>Aadhar:</b> ${aadhar || 'Not Provided'} <br><br>
 
      <b>Hotel:</b> ${hotel} <br>
      <b>Room:</b> ${room} (for ${guests} person(s)) <br><br>
 
      <b>Check-in:</b> ${checkIn} <br>
      <b>Check-out:</b> ${checkOut} <br>
      <b>Nights:</b> ${nights} <br><br>
 
      <b>Payment Method:</b> ${paymentMode} <br>
      <b>Base Price:</b> ₹ ${base} <br>
      <b>Taxes (5%):</b> ₹ ${tax} <br>
      <b style="font-size:18px;">Total Paid: ₹ ${total}</b>
    </div>
 
    <div id="no-print" style="margin-top:25px;display:flex;flex-direction:column;gap:10px;">
 
      <button onclick="window.print()" 
        style="background:#0a58ff;color:#fff;padding:14px;border:none;border-radius:10px;font-weight:700;">
        Print / Download Ticket
      </button>
 
      <a href="https://wa.me/?text=${encodeURIComponent(message)}" target="_blank"
        style="background:#25D366;color:#fff;text-align:center;padding:14px;border-radius:10px;font-weight:700;text-decoration:none;">
        Share Ticket on WhatsApp
      </a>
 
      <button onclick="location.href='index.php'" 
        style="background:#eee;color:#111;padding:12px;border:none;border-radius:10px;">
        Done
      </button>
 
    </div>
 
  </div>`;
}
 
  
 
 
$('#hy-range').addEventListener('input', e=>{
  const v = Number(e.target.value);
  const min = Number($('#hy-min').value||0);
  if(min>v) $('#hy-min').value = v;
  renderList();
});
$('#hy-min').addEventListener('change', ()=>{
  const min = Number($('#hy-min').value||0);
  const slider = $('#hy-range');
  if(min>Number(slider.value)) slider.value = min;
  renderList();
});
$$('.hy-starcb').forEach(cb=>cb.addEventListener('change', renderList));
$('#hy-can').addEventListener('change', renderList);
$('#hy-bfast').addEventListener('change', renderList);
$('#hy-sortsel').addEventListener('change', renderList);
$('#hy-search').addEventListener('click', renderList);
 
// Defaults
(function(){
  const today = new Date();
  const tomorrow = new Date(Date.now()+86400000);
  const fmt = d => d.toISOString().slice(0,10);
  $('#hy-in').value = fmt(today);
  $('#hy-out').value = fmt(tomorrow);
})();
renderList();
</script>
 
 
      <!-- ✅ FLIGHT SEARCH (STATIC, OFFLINE) -->
<!-- ✅ FLIGHT SEARCH — MakeMyTrip style (drop-in) -->
<!-- =========================
     ✈️ FLIGHT BOOKING (Razorpay)
     Drop-in, self-contained
========================== -->
<div id="flight" class="form-section">
  <style>
    .flx *{box-sizing:border-box;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .flx{--pri:#0066ff;--line:#e6e8ef;--ink:#0f172a;--sub:#6b7280;--chip:#edf4ff;--ok:#16a34a}
    .flx .shell{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px}
    .flx .bar{display:grid;grid-template-columns:1.3fr 1.3fr 1fr 1fr .9fr auto;gap:10px}
    .flx .in,.flx select{height:46px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;font-size:14px}
    .flx .btn{height:46px;border:none;border-radius:12px;background:linear-gradient(135deg,#1f7bff,#0061ff);color:#fff;font-weight:800;cursor:pointer;padding:0 18px}
    .flx .btn:hover{filter:brightness(.96)}
    .flx .tabs{display:flex;gap:8px;margin-top:12px}
    .flx .tab{padding:8px 14px;border:1px solid var(--line);border-radius:999px;background:#fff;font-weight:700;color:#111;cursor:pointer}
    .flx .tab.active{background:#eaf0ff;border-color:#b7ccff;color:#1d4ed8}
    .flx .grid{display:grid;grid-template-columns:280px 1fr;gap:14px;margin-top:12px}
    .flx .left{border:1px solid var(--line);border-radius:14px;padding:12px;position:sticky;top:80px;height:fit-content}
    .flx h4{margin:6px 0 10px 0}
    .flx .row{display:flex;align-items:center;gap:8px;color:var(--sub);margin:8px 0}
    .flx .range{width:100%}
    .flx .results-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
    .flx .count{font-weight:900}
    .flx .note{font-size:12px;color:var(--sub)}
    .flx .list{display:flex;flex-direction:column;gap:10px}
    .flx .card{border:1px solid var(--line);border-radius:14px;overflow:hidden;display:grid;grid-template-columns:1fr 180px;background:#fff}
    .flx .cL{padding:12px;display:grid;grid-template-columns:140px 1fr 120px;gap:12px;align-items:center}
    .flx .air{display:flex;align-items:center;gap:8px}
    .flx .logo{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;color:#fff;font-weight:900}
    .flx .ak{background:#ff7b00}.flx .ai{background:#e21b22}.flx .i6{background:#1f4bd7}
    .flx .airname{font-weight:800}
    .flx .tiny{font-size:12px;color:var(--sub)}
    .flx .time{font-size:18px;font-weight:900}
    .flx .dur{color:var(--sub);font-size:12px;margin-top:2px}
    .flx .line{height:4px;background:#eef2ff;border-radius:999px;position:relative;margin-top:8px}
    .flx .dot{position:absolute;top:-4px;width:12px;height:12px;border-radius:50%;background:#60a5fa}
    .flx .chip{background:var(--chip,#edf4ff);border:1px solid #d7e6ff;border-radius:999px;padding:2px 8px;font-weight:700;font-size:12px;color:#1d4ed8}
    .flx .ok{color:var(--ok)}
    .flx .cR{border-left:1px dashed var(--line);background:#fbfdff;padding:12px;display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end}
    .flx .price{font-size:22px;font-weight:900}
    .flx .link{border:1px solid #cfe0ff;background:#eef6ff;border-radius:10px;padding:10px 12px;font-weight:800;color:#1d4ed8;cursor:pointer}
 
    /* Modal */
    .flx .modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:9999}
    .flx .modal.show{display:flex}
    .flx .mcard{background:#fff;width:min(900px,94%);border-radius:16px;overflow:hidden}
    .flx .mhd{padding:12px 16px;background:#0a58ff;color:#fff;display:flex;justify-content:space-between;align-items:center}
    .flx .mbd{padding:14px}
    .flx .two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .flx .three{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    .flx .pay{width:100%;height:44px;border:none;border-radius:12px;background:#0a58ff;color:#fff;font-weight:900;cursor:pointer}
 
    /* Seats */
    .seat-wrap{margin-top:10px;border:1px dashed var(--line);border-radius:12px;padding:12px}
    .seat-legend{display:flex;gap:12px;margin-bottom:8px;font-size:12px;color:#374151}
    .lg-dot{display:inline-block;width:14px;height:14px;border-radius:4px;margin-right:6px;vertical-align:-2px}
    .sg{
  display:grid;
  grid-template-columns:repeat(6,40px);
  gap:8px;
  justify-content:center;
  max-height:240px;        /* NEW: Limit height */
  overflow-y:auto;         /* NEW: Scroll if large */
  padding-right:6px;
}
 
    .seat{width:40px;height:40px;border-radius:10px;font-weight:800;border:1px solid #cbd5e1;display:grid;place-items:center;cursor:pointer;user-select:none}
    .seat.avail{background:#16a34a;color:#fff;border-color:#a7f3d0}
    .seat.book{background:#ef4444;color:#fff;border-color:#fecaca;cursor:not-allowed;opacity:.9}
    .seat.pick{background:#2563eb;color:#fff;border-color:#bfdbfe;outline:2px solid #93c5fd}
 
    /* Boarding Pass */
    #fx-pass{position:fixed;inset:0;background:#f6f7fb;display:none;overflow:auto;z-index:10000}
    #fx-pass .wrap{max-width:880px;margin:24px auto;padding:0 16px}
    #fx-pass .ticket{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
    #fx-pass .t-hd{background:#0a58ff;color:#fff;padding:16px 18px;display:flex;align-items:center;justify-content:space-between}
    #fx-pass .t-bd{padding:18px}
    #fx-pass .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    #fx-pass .pill{background:#eef2ff;color:#1d4ed8;border:1px solid #c7d2fe;border-radius:999px;padding:4px 10px;font-weight:700;font-size:12px}
    #fx-pass .act{display:flex;gap:10px;margin-top:12px}
    #fx-pass .btn{border:1px solid #cfe0ff;background:#eef6ff;border-radius:10px;padding:10px 12px;font-weight:800;color:#1d4ed8;cursor:pointer;text-decoration:none}
  </style>
 
  <div class="flx" id="flx">
    <div class="shell">
      <!-- Search -->
      <div class="bar">
        <input id="fx-from" class="in" list="fx-cities" placeholder="From (city or code)">
        <input id="fx-to" class="in" list="fx-cities" placeholder="To (city or code)">
        <input id="fx-out" class="in" type="date">
        <input id="fx-ret" class="in" type="date" title="Return (optional)">
        <select id="fx-trv" class="in">
          <option>1 adult</option><option>2 adults</option><option>3 adults</option>
        </select>
        <button id="fx-search" class="btn">Search</button>
      </div>
 
      <datalist id="fx-cities">
        <option value="Ahmedabad (AMD)"></option>
        <option value="Surat (STV)"></option>
        <option value="Mumbai (BOM)"></option>
        <option value="Delhi (DEL)"></option>
        <option value="Pune (PNQ)"></option>
        <option value="Jaipur (JAI)"></option>
        <option value="Kolkata (CCU)"></option>
        <option value="Hyderabad (HYD)"></option>
        <option value="Bangalore (BLR)"></option>
      </datalist>
 
      <!-- Tabs -->
      <div class="tabs">
        <button data-sort="best" class="tab active">Best</button>
        <button data-sort="cheap" class="tab">Cheapest</button>
        <button data-sort="fast" class="tab">Fastest</button>
        <label class="tab" style="cursor:default">
          <input id="fx-direct" type="checkbox" style="vertical-align:middle;margin-right:6px"> Direct only
        </label>
      </div>
 
      <div class="grid">
        <!-- Filters -->
        <aside class="left">
          <h4>Stops</h4>
          <label class="row"><input type="radio" name="fx-stops" value="any" checked> Any</label>
          <label class="row"><input type="radio" name="fx-stops" value="direct"> Direct only</label>
          <label class="row"><input type="radio" name="fx-stops" value="max1"> 1 stop max</label>
 
          <h4 style="margin-top:12px">Airlines</h4>
          <label class="row"><input class="fx-air" type="checkbox" value="Akasa Air" checked> Akasa Air</label>
          <label class="row"><input class="fx-air" type="checkbox" value="IndiGo" checked> IndiGo</label>
          <label class="row"><input class="fx-air" type="checkbox" value="Air India" checked> Air India</label>
 
          <h4 style="margin-top:12px">Price (max)</h4>
          <input id="fx-price" class="range" type="range" min="3000" max="20000" step="100" value="20000">
          <div class="row"><b>₹ <span id="fx-price-val">20,000</span></b></div>
 
          <h4 style="margin-top:12px">Departure window</h4>
          <label class="row"><input class="fx-time" type="checkbox" value="00-06"> 00:00–05:59</label>
          <label class="row"><input class="fx-time" type="checkbox" value="06-12"> 06:00–11:59</label>
          <label class="row"><input class="fx-time" type="checkbox" value="12-18"> 12:00–17:59</label>
          <label class="row"><input class="fx-time" type="checkbox" value="18-24"> 18:00–23:59</label>
        </aside>
 
        <!-- Results -->
        <section>
          <div class="results-hd">
            <div class="count" id="fx-count">Results</div>
            <div class="note" id="fx-note"></div>
          </div>
          <div id="fx-list" class="list"></div>
          <div id="fx-empty" class="empty" style="display:none">No flights match your filters.</div>
        </section>
      </div>
    </div>
 
    <!-- Modal -->
    <div id="fx-modal" class="modal">
      <div class="mcard">
        <div class="mhd">
          <strong id="fx-m-title">Flight</strong>
          <button onclick="this.closest('.modal').classList.remove('show')" style="background:transparent;border:none;color:#fff;font-size:22px;cursor:pointer">&times;</button>
        </div>
        <div class="mbd">
          <div id="fx-m-seg" class="tiny" style="margin-bottom:10px"></div>
 
          <div class="three">
            <div>
              <label>Full name</label>
              <input id="fx-name" class="in" placeholder="Your name">
            </div>
            <div>
              <label>Mobile</label>
              <input id="fx-phone" class="in" placeholder="10-digit">
            </div>
            <div>
              <label>Age</label>
              <input id="fx-age" class="in" type="number" min="1" max="120" value="22">
            </div>
          </div>
 
          <div class="two" style="margin-top:8px">
            <div>
              <label>Email</label>
              <input id="fx-email" class="in" placeholder="you@example.com">
            </div>
            <div>
              <label>Fare</label>
              <input id="fx-fare" class="in" readonly>
            </div>
          </div>
 
          <!-- Seat picker -->
          <div class="seat-wrap">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <strong>Select your seat</strong>
            </div>
 
            <div class="seat-legend">
              <span><i class="lg-dot" style="background:#16a34a;border:1px solid #a7f3d0"></i>Available</span>
              <span><i class="lg-dot" style="background:#ef4444;border:1px solid #fecaca"></i>Booked</span>
              <span><i class="lg-dot" style="background:#2563eb;border:1px solid #bfdbfe"></i>Selected</span>
            </div>
 
            <div id="seat-grid" class="sg"></div>
            <div id="seat-msg" class="tiny" style="margin-top:8px;color:#2563eb;font-weight:700"></div>
 
            <div style="display:flex;gap:10px;margin-top:14px;justify-content:flex-end">
              <button id="seat-cancel" type="button" style="padding:10px 14px;border-radius:10px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-weight:600">Cancel</button>
              <button id="seat-confirm" type="button" style="padding:10px 14px;border-radius:10px;border:none;background:#2563eb;color:#fff;cursor:pointer;font-weight:700">Confirm Seat</button>
            </div>
          </div>
 
          <!-- Review & Pay -->
          <div id="review-box" style="display:none;margin-top:18px;border-top:1px solid #e5e7eb;padding-top:14px">
            <h4 style="margin-bottom:8px;">Review Your Booking</h4>
            <div id="review-details" style="font-size:14px;line-height:1.6"></div>
            <button id="fx-pay" class="pay" style="margin-top:14px;width:100%">Proceed to Payment</button>
          </div>
        </div>
      </div>
    </div>
 
    <!-- Boarding Pass -->
    <div id="fx-pass">
      <div class="wrap">
        <div class="ticket">
          <div class="t-hd">
            <div style="font-size:18px;font-weight:900">Boarding Pass • Thrill Yari</div>
            <div id="bp-pnr" style="font-weight:900">PNR: TY000000</div>
          </div>
          <div class="t-bd">
            <div id="bp-when" class="tiny" style="margin-bottom:10px"></div>
 
            <div class="grid2">
              <div>
                <div style="font-weight:800">Passenger</div>
                <div id="bp-passenger"></div>
                <div id="bp-email" class="tiny"></div>
              </div>
              <div>
                <div style="font-weight:800">Payment</div>
                <div>Total Fare: <b id="bp-fare"></b></div>
              </div>
            </div>
 
            <div class="grid2" style="margin-top:10px">
              <div>
                <div style="font-weight:800;margin-bottom:6px">Flight</div>
                <div id="bp-route"></div>
                <div id="bp-times" class="tiny"></div>
                <div id="bp-air" class="tiny"></div>
              </div>
              <div>
                <div style="font-weight:800;margin-bottom:6px">Seat & Gate</div>
                <div>Seat: <b id="bp-seat"></b> • Gate: <b id="bp-gate"></b> • Terminal: <b id="bp-term"></b></div>
                <div class="tiny">Boarding: <span id="bp-board"></span></div>
              </div>
            </div>
 
            <div class="act">
              <button onclick="window.print()" class="btn">Print / Save PDF</button>
              <a id="bp-share" class="btn" target="_blank">Share on WhatsApp</a>
              <a class="btn" href="#" onclick="document.getElementById('fx-pass').style.display='none';return false;">Close</a>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /#fx-pass -->
  </div>
</div>
 
<!-- Razorpay Checkout script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
 
<script>
(() => {
  /* ---------- Data ---------- */
  const CITY_TO_IATA = {
    "AHMEDABAD":"AMD","SURAT":"STV","MUMBAI":"BOM","DELHI":"DEL","PUNE":"PNQ",
    "JAIPUR":"JAI","KOLKATA":"CCU","HYDERABAD":"HYD","BANGALORE":"BLR"
  };
 
  const FLIGHTS = [
    {id:101, from:"AMD", to:"BOM", airline:"Akasa Air", code:"QP 1311", depart:"19:45", arrive:"21:00", durMin:75,  price:7324, stops:0},
    {id:102, from:"AMD", to:"BOM", airline:"IndiGo",    code:"6E 5231", depart:"16:05", arrive:"17:10", durMin:65,  price:7324, stops:0},
    {id:103, from:"AMD", to:"BOM", airline:"Air India", code:"AI 0632", depart:"11:35", arrive:"13:05", durMin:90,  price:6801, stops:0},
    {id:104, from:"BOM", to:"AMD", airline:"Air India", code:"AI 0631", depart:"18:40", arrive:"19:45", durMin:65,  price:6801, stops:0},
    {id:105, from:"BOM", to:"AMD", airline:"Air India", code:"AI 0635", depart:"09:55", arrive:"11:05", durMin:70,  price:6801, stops:0},
 
    {id:201, from:"AMD", to:"STV", airline:"IndiGo",    code:"6E 7123", depart:"07:05", arrive:"08:10", durMin:65,  price:2899, stops:0},
    {id:202, from:"AMD", to:"STV", airline:"Akasa Air", code:"QP 0443", depart:"20:10", arrive:"21:15", durMin:65,  price:2799, stops:0},
    {id:203, from:"STV", to:"AMD", airline:"IndiGo",    code:"6E 8122", depart:"10:30", arrive:"11:35", durMin:65,  price:2699, stops:0},
 
    {id:301, from:"AMD", to:"BOM", airline:"IndiGo",    code:"6E 9011", depart:"13:20", arrive:"15:40", durMin:140, price:5400, stops:1},
    {id:302, from:"AMD", to:"STV", airline:"Air India", code:"AI 2012", depart:"12:10", arrive:"14:00", durMin:110, price:3200, stops:1},
  ];
 
  /* ---------- Utils ---------- */
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const rs = id => document.getElementById(id);
  const fmt = n => n.toLocaleString('en-IN');
 
  function normalizeCity(input){
    if(!input) return '';
    const up = input.toUpperCase().trim();
    const codeMatch = up.match(/\(([A-Z]{3})\)$/);
    if(codeMatch) return codeMatch[1];
    if (CITY_TO_IATA[up]) return CITY_TO_IATA[up];
    if (/^[A-Z]{3}$/.test(up)) return up;
    return up;
  }
  function timeToMin(t){ const [H,M]=t.split(':').map(Number); return H*60+M; }
  function inWindow(t,w){ const m=timeToMin(t); if(w==='00-06')return m<360; if(w==='06-12')return m>=360&&m<720; if(w==='12-18')return m>=720&&m<1080; if(w==='18-24')return m>=1080; return true;}
  function logo(air){
    if(air==='Akasa Air') return '<span class="logo ak">A</span>';
    if(air==='Air India') return '<span class="logo ai">AI</span>';
    return '<span class="logo i6">6E</span>';
  }
 
  // Seat store
  const seatKey = id => `ty_flight_seats_${id}`;
  const getBooked = id => JSON.parse(localStorage.getItem(seatKey(id))||'[]');
  const addBooked  = (id, seat) => {
    const cur = new Set(getBooked(id)); cur.add(seat);
    localStorage.setItem(seatKey(id), JSON.stringify([...cur]));
  };
 
  /* ---------- State ---------- */
  let SORT = 'best';
  let SELECTED = null;      // selected flight
  let TOTAL_FARE = 0;
  let PICKED_SEAT = null;
 
  /* ---------- Defaults ---------- */
  (function initDefaults(){
    rs('fx-from').value = 'Ahmedabad (AMD)';
    rs('fx-to').value   = 'Surat (STV)';
    const today = new Date();
    const ret   = new Date(Date.now()+7*86400000);
    rs('fx-out').value = today.toISOString().slice(0,10);
    rs('fx-ret').value = ret.toISOString().slice(0,10);
    rs('fx-price-val').textContent = fmt(Number(rs('fx-price').value));
  })();
 
  /* ---------- Events ---------- */
  rs('fx-price').addEventListener('input', e => rs('fx-price-val').textContent = fmt(Number(e.target.value)));
  rs('fx-search').addEventListener('click', render);
  rs('fx-direct').addEventListener('change', render);
  $$('input[name="fx-stops"]').forEach(r=>r.addEventListener('change', render));
  $$('.fx-air').forEach(cb=>cb.addEventListener('change', render));
  $$('.fx-time').forEach(cb=>cb.addEventListener('change', render));
  $$('.tab').forEach(t=>t.addEventListener('click', e=>{
    if(!e.currentTarget.dataset.sort) return;
    $$('.tab').forEach(x=>x.classList.remove('active'));
    e.currentTarget.classList.add('active');
    SORT = e.currentTarget.dataset.sort;
    render();
  }));
 
  /* ---------- Render flights ---------- */
  function render(){
    const FROM = normalizeCity(rs('fx-from').value);
    const TO   = normalizeCity(rs('fx-to').value);
    const outDate = rs('fx-out').value;
    const retDate = rs('fx-ret').value;
 
    const priceMax = Number(rs('fx-price').value);
    const directOnly = rs('fx-direct').checked;
    const stopRule = (Array.from(document.querySelectorAll('input[name="fx-stops"]:checked'))[0]||{}).value || 'any';
    const allowedAir = $$('.fx-air:checked').map(x=>x.value);
    const depWins = $$('.fx-time:checked').map(x=>x.value);
 
    let out = FLIGHTS.filter(f => f.from===FROM && f.to===TO);
    let ret = FLIGHTS.filter(f => retDate && f.from===TO && f.to===FROM);
 
    const apply = list => list.filter(f=>{
      if(f.price>priceMax) return false;
      if(directOnly && f.stops!==0) return false;
      if(stopRule==='direct' && f.stops!==0) return false;
      if(stopRule==='max1' && f.stops>1) return false;
      if(allowedAir.length && !allowedAir.includes(f.airline)) return false;
      if(depWins.length && !depWins.some(w=>inWindow(f.depart,w))) return false;
      return true;
    });
 
    let outF = apply(out), retF = apply(ret);
 
    let note = '';
    if(outF.length===0 && out.length){ outF = out.slice(); note='Showing closest matches for these routes (filters relaxed).'; }
    if(retDate && retF.length===0 && ret.length){ retF = ret.slice(); note='Showing closest matches for these routes (filters relaxed).'; }
    rs('fx-note').textContent = note;
 
    const sorter = {best:(a,b)=>(a.price + a.durMin/3)-(b.price + b.durMin/3), cheap:(a,b)=>a.price-b.price, fast:(a,b)=>a.durMin-b.durMin}[SORT];
    outF.sort(sorter); retF.sort(sorter);
 
    const list = rs('fx-list'); list.innerHTML='';
    const empty = rs('fx-empty');
 
    const makeCard = (f, dir) => `
      <article class="card">
        <div class="cL">
          <div class="air">
            ${logo(f.airline)}
            <div>
              <div class="airname">${f.airline}</div>
              <div class="tiny">${f.code} • ${dir}</div>
            </div>
          </div>
          <div>
            <div class="time">${f.depart} — ${f.arrive}</div>
            <div class="dur">${Math.floor(f.durMin/60)}h ${f.durMin%60}m • ${f.stops===0?'<span class="ok">Direct</span>':(f.stops+' stop')}</div>
            <div class="line"><span class="dot" style="left:6%"></span><span class="dot" style="left:92%"></span></div>
            <div class="chips"><span class="chip">Cabin bag</span><span class="chip">Checked bag</span><span class="chip">Free reschedule*</span></div>
          </div>
          <div class="tiny">
            <div><b>${FROM}</b> → <b>${TO}</b></div>
            <div>${dir==='Outbound' ? (outDate||'Your date') : (retDate||'Return date')}</div>
          </div>
        </div>
        <div class="cR">
          <div style="text-align:right">
            <div class="price">₹ ${fmt(f.price)}</div>
            <div class="tiny">taxes included</div>
          </div>
          <button class="link" data-id="${f.id}" data-dir="${dir}">View details</button>
        </div>
      </article>
    `;
 
    let html = '';
    if(outF.length){
      html += `<div class="tiny" style="margin:4px 2px;font-weight:800">Outbound • ${FROM} → ${TO}</div>`;
      outF.forEach(f => html += makeCard(f,'Outbound'));
    }
    if(retDate && retF.length){
      html += `<div class="tiny" style="margin:10px 2px 4px;font-weight:800">Return • ${TO} → ${FROM}</div>`;
      retF.forEach(f => html += makeCard(f,'Return'));
    }
 
    if(!html){
      empty.style.display='block';
      rs('fx-count').textContent='0 results';
    }else{
      empty.style.display='none';
      list.innerHTML = html;
      const total = outF.length + (retDate?retF.length:0);
      rs('fx-count').textContent = `${total} result${total>1?'s':''}`;
    }
 
    // attach detail buttons
    $$('#fx-list .link').forEach(b => b.onclick = (e)=>{
      const id  = Number(e.currentTarget.dataset.id);
      const dir = e.currentTarget.dataset.dir;
      const f = FLIGHTS.find(x=>x.id===id);
      openModal(dir,f,FROM,TO,outDate,retDate);
    });
  }
 
  /* ---------- Seats ---------- */
  function buildSeats(flightId){
    const grid = rs('seat-grid'); grid.innerHTML='';
    PICKED_SEAT = null;
    const booked = new Set(getBooked(flightId));
    const cols = ['A','B','C','D','E','F'];
    for(let r=1;r<=10;r++){
      for(let c=0;c<6;c++){
        const name = `${r}${cols[c]}`;
        const btn = document.createElement('div');
        if(booked.has(name)){
          btn.className = 'seat book';
        } else {
          btn.className = 'seat avail';
          btn.onclick = ()=>{
            grid.querySelectorAll('.seat.pick').forEach(x=>x.classList.remove('pick'));
            btn.classList.add('pick');
            PICKED_SEAT = name;
            rs('seat-msg').textContent = `Selected Seat: ${name}`;
          };
        }
        btn.textContent = name;
        grid.appendChild(btn);
      }
    }
    rs('seat-msg').textContent = 'Select your seat';
  }
 
  /* ---------- Modal + Review + Razorpay ---------- */
  function openModal(dir, f, FROM, TO, outDate, retDate){
    SELECTED = f;
    TOTAL_FARE = f.price;
    rs('fx-m-title').textContent = `${f.airline} • ${f.code}`;
    rs('fx-m-seg').textContent = `${dir}: ${FROM} → ${TO} • ${f.depart}–${f.arrive} • ${Math.floor(f.durMin/60)}h ${f.durMin%60}m`;
    rs('fx-fare').value = '₹ ' + fmt(TOTAL_FARE);
    buildSeats(f.id);
    rs('fx-modal').classList.add('show');
  }
 
  // Confirm / Cancel seat (single binding)
  rs('seat-confirm').addEventListener('click', () => {
 
  if(!PICKED_SEAT){
    alert("Please select a seat");
    return;
  }
 
  let data = {
    name: rs('fx-name').value,
    phone: rs('fx-phone').value,
    age: rs('fx-age').value,
    email: rs('fx-email').value,
    airline: SELECTED.airline,
    code: SELECTED.code,
    from: SELECTED.from,
    to: SELECTED.to,
    fare: TOTAL_FARE,
    seat: PICKED_SEAT
  };
 
  let form = document.createElement("form");
  form.method = "POST";
  form.action = "flight_summary.php";
 
  for (let key in data){
    let input = document.createElement("input");
    input.type = "hidden";
    input.name = key;
    input.value = data[key];
    form.appendChild(input);
  }
 
  document.body.appendChild(form);
  form.submit();
});
 
 
 
  // Razorpay Checkout
  rs('fx-pay').addEventListener('click', () => {
    // Basic validations
    if(!rs('fx-name').value.trim() || !rs('fx-phone').value.trim() || !rs('fx-email').value.trim()){
      alert('Please enter passenger details.');
      return;
    }
    if(!PICKED_SEAT){
      alert('Please confirm seat first.');
      return;
    }
 
    // Create order on server in real app.
    // For demo, we'll pass amount directly to Checkout.
    const options = {
      key: "RAZORPAY_KEY_HERE",                // <-- replace with your Test Key ID
      amount: Math.round(TOTAL_FARE * 100),    // paise
      currency: "INR",
      name: "Thrill Yari",
      description: `${SELECTED.airline} ${SELECTED.code} • ${SELECTED.from}→${SELECTED.to}`,
      image: "https://i.postimg.cc/8PxPKN8x/logo-png.jpg",
      prefill: {
        name: rs('fx-name').value,
        email: rs('fx-email').value,
        contact: rs('fx-phone').value
      },
      notes: {
        seat: PICKED_SEAT,
        route: `${SELECTED.from}→${SELECTED.to}`
      },
      theme: { color: "#0a58ff" },
      handler: function (response){
        // Payment success handler
        // Mark seat booked locally
        addBooked(SELECTED.id, PICKED_SEAT);
        // ---- SAVE BOOKING INTO DATABASE ----
fetch("save_flight_booking.php", {
  method: "POST",
  headers: { "Content-Type": "application/x-www-form-urlencoded" },
  body: new URLSearchParams({
    user_id: "<?php echo $_SESSION['user_id'] ?? 0; ?>",
    passenger_name: rs('fx-name').value,
    mobile: rs('fx-phone').value,
    age: rs('fx-age').value,
    email: rs('fx-email').value,
    airline: SELECTED.airline,
    flight_code: SELECTED.code,
    origin: SELECTED.from,
    destination: SELECTED.to,
    seat: PICKED_SEAT,
    fare: TOTAL_FARE,
    pnr: pnr,
    payment_id: response.razorpay_payment_id
  })
});
 
 
        // Generate Boarding Pass
        const pnr = 'TY' + Math.floor(100000 + Math.random()*900000);
        const when = new Date();
        const boardTime = new Date(when.getTime() + 60*60*1000);
        const gate = 'G' + Math.floor(1+Math.random()*9);
        const terminal = ['T1','T2','T3'][Math.floor(Math.random()*3)];
 
        rs('bp-pnr').textContent = `PNR: ${pnr}`;
        rs('bp-when').textContent = when.toLocaleString();
        rs('bp-passenger').textContent = `${rs('fx-name').value} • ${rs('fx-phone').value} • ${rs('fx-age').value||'-'} yrs`;
        rs('bp-email').textContent = rs('fx-email').value;
        rs('bp-fare').textContent = '₹ ' + fmt(TOTAL_FARE);
 
        rs('bp-route').innerHTML = `<span class="pill">${SELECTED.from}</span> → <span class="pill">${SELECTED.to}</span>`;
        rs('bp-times').textContent = `Departure ${rs('fx-out').value||'—'} ${SELECTED.depart} • Arrival ${rs('fx-out').value||'—'} ${SELECTED.arrive}`;
        rs('bp-air').textContent = `${SELECTED.airline} (${SELECTED.code})`;
        rs('bp-seat').textContent = PICKED_SEAT;
        rs('bp-gate').textContent = gate;
        rs('bp-term').textContent = terminal;
        rs('bp-board').textContent = boardTime.toLocaleTimeString();
 
        const msg = `✈️ Thrill Yari Boarding Pass
PNR: ${pnr}
Passenger: ${rs('fx-name').value}
Route: ${SELECTED.from}→${SELECTED.to}
Flight: ${SELECTED.airline} ${SELECTED.code}
Seat: ${PICKED_SEAT}  Gate: ${gate}  Terminal: ${terminal}
Depart: ${rs('fx-out').value||''} ${SELECTED.depart}
Fare: ₹ ${fmt(TOTAL_FARE)}
Razorpay Ref: ${response.razorpay_payment_id}`;
 
        rs('bp-share').href = 'https://wa.me/?text=' + encodeURIComponent(msg);
 
        rs('fx-modal').classList.remove('show');
        rs('fx-pass').style.display='block';
      },
      modal: {
        ondismiss: function(){
          // User closed payment popup
          // (Optional) show message/restore UI
        }
      }
    };
 
    const rzp = new Razorpay(options);
    rzp.open();
  });
 
  // Initial render
  render();
})();
</script>
 
 
<!-- ========== 🚆 TRAIN BOOKING (IRCTC-Style, Style-B Ticket) ========== -->
<!-- ===========================
     THRILL YARI — TRAIN BOOKING (Style 3 • Red/White • Full IRCTC form)
     Drop-in Component (No dependencies)
=========================== -->
<section id="ty-train" class="form-section ty-train">
  <style>
    .ty-train{--pri:#cc102c;--pri-700:#a10d23;--ink:#0f172a;--mut:#6b7280;--line:#e5e7eb;--chip:#f8fafc;--ok:#16a34a;--warn:#d97706;--bg:#ffffff;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .ty-train *{box-sizing:border-box}
    .tt-wrap{max-width:1100px;margin:24px auto;padding:0 16px}
    .tt-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px rgba(15,23,42,.06)}
    .tt-row{display:flex;gap:12px;align-items:center}
    .tt-col{display:flex;flex-direction:column}
    .tt-bold{font-weight:800}
    .tt-small{font-size:12px;color:var(--mut)}
    .tt-btn{height:44px;border:none;border-radius:12px;background:var(--pri);color:#fff;font-weight:800;padding:0 18px;cursor:pointer}
    .tt-btn:hover{background:var(--pri-700)}
    .tt-ghost{border:1px solid #fecdd3;background:#fff;color:#991b1b}
    .tt-input,.tt-sel{height:44px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;font-size:14px;background:#fff}
    .tt-tag{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:999px;padding:2px 10px;font-weight:800;font-size:12px}
    .tt-chip{background:var(--chip);border:1px solid #dbeafe;border-radius:999px;padding:4px 10px;font-weight:700;font-size:12px;color:#1d4ed8}
    .tt-green{color:var(--ok);font-weight:800}
    .tt-yellow{color:#b45309;font-weight:800}
    .tt-sep{height:1px;background:var(--line);margin:12px 0}
    .tt-tabs{display:flex;gap:8px}
    .tt-tab{padding:10px 12px;border:1px solid var(--line);border-radius:999px;cursor:pointer;font-weight:700}
    .tt-tab.active{background:#fee2e2;border-color:#fecaca;color:#991b1b}
    .tt-head{display:grid;grid-template-columns:1.2fr 1.2fr .9fr .9fr 1fr auto;gap:10px;padding:12px}
    .tt-list{padding:12px}
    .tt-train{border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:12px;background:#fff}
    .tt-body{display:grid;grid-template-columns:1fr 180px;gap:8px}
    .tt-left{padding:12px;display:grid;grid-template-columns:170px 1fr 160px;gap:12px;align-items:center}
    .tt-trn{display:flex;gap:8px;align-items:flex-start}
    .tt-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px}
    .tt-time{font-size:20px;font-weight:900}
    .tt-dur{font-size:12px;color:var(--mut)}
    .tt-classes{display:flex;gap:8px;flex-wrap:wrap}
    .tt-class{display:flex;gap:6px;align-items:center;border:1px solid #e2e8f0;background:#f8fafc;padding:6px 10px;border-radius:10px;font-size:12px}
    .tt-av{font-weight:900}
    .tt-right{border-left:1px dashed var(--line);background:#fff;display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;padding:12px}
    .tt-price{font-size:22px;font-weight:900}
    .tt-note{font-size:12px;color:var(--mut)}
    /* Modal */
    .tt-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:9999}
    .tt-modal.show{display:flex}
    .tt-mcard{background:#fff;width:min(920px,94%);border-radius:16px;overflow:hidden;border:1px solid var(--line)}
    .tt-mhd{padding:12px 16px;background:var(--pri);color:#fff;display:flex;justify-content:space-between;align-items:center}
    .tt-mbd{
  padding:16px;
  max-height:70vh;        /* ✅ limit height */
  overflow-y:auto;        /* ✅ allow scroll */
  scrollbar-width:thin;   /* (optional) smooth scroll */
}
 
    .tt-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .tt-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    /* Ticket (print clean) */
    #tt-ticket{position:fixed;inset:0;background:#f8fafc;display:none;overflow:auto;z-index:10000}
    #tt-ticket .t-wrap{max-width:860px;margin:24px auto;padding:0 16px}
    #tt-ticket .t-card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
    #tt-ticket .t-hd{background:var(--pri);color:#fff;padding:16px 18px;display:flex;align-items:center;justify-content:space-between}
    #tt-ticket .t-bd{padding:18px}
    #tt-ticket .pill{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:999px;padding:4px 10px;font-weight:800;font-size:12px}
    #tt-actions{display:flex;gap:10px;margin-top:12px}
    #tt-actions .btn{border:1px solid #fecaca;background:#fff;border-radius:10px;padding:10px 12px;font-weight:800;color:#b91c1c;text-decoration:none;cursor:pointer}
    @media print{
      #tt-actions,.tt-modal,.tt-head,.tt-list,.tt-search,.tt-filters,header,nav,footer,button,a{display:none !important}
      #tt-ticket{display:block !important}
      #tt-ticket .t-card{box-shadow:none;border:none}
      *{outline:none !important;-webkit-print-color-adjust:exact}
    }
    /* Filters panel */
    .tt-layout{display:grid;grid-template-columns:280px 1fr;gap:14px}
    .tt-fbox{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;position:sticky;top:20px;height:fit-content}
    .tt-frow{display:flex;align-items:center;gap:8px;margin:8px 0;color:#374151}
    .tt-check{width:18px;height:18px}
    @media(max-width:980px){
      .tt-head{grid-template-columns:1fr 1fr 1fr 1fr}
      .tt-body{grid-template-columns:1fr}
      .tt-left{grid-template-columns:1fr}
      .tt-layout{grid-template-columns:1fr}
    }
  </style>
 
  <div class="tt-wrap">
    <!-- SEARCH BAR -->
    <div class="tt-card tt-search">
      <div class="tt-head">
        <select id="tt-from" class="tt-sel">
          <option>Ahmedabad (ADI)</option><option>Mumbai (MMCT)</option><option>Delhi (NDLS)</option>
          <option>Surat (ST)</option><option>Jaipur (JP)</option><option>Kolkata (HWH)</option>
          <option>Hyderabad (HYB)</option><option>Chennai (MAS)</option><option>Bangalore (SBC)</option>
        </select>
        <select id="tt-to" class="tt-sel">
          <option>Mumbai (MMCT)</option><option>Ahmedabad (ADI)</option><option>Delhi (NDLS)</option>
          <option>Surat (ST)</option><option>Jaipur (JP)</option><option>Kolkata (HWH)</option>
          <option>Hyderabad (HYB)</option><option>Chennai (MAS)</option><option>Bangalore (SBC)</option>
        </select>
        <input id="tt-date" class="tt-input" type="date">
        <select id="tt-quota" class="tt-sel">
          <option value="GN">General (GN)</option>
          <option value="TQ">Tatkal (TQ)</option>
          <option value="LD">Ladies (LD)</option>
        </select>
        <select id="tt-pax" class="tt-sel">
          <option>1 Passenger</option><option>2 Passengers</option><option>3 Passengers</option>
          <option>4 Passengers</option><option>5 Passengers</option><option>6 Passengers</option>
        </select>
        <button id="tt-search" class="tt-btn">Search Trains</button>
      </div>
    </div>
 
    <div class="tt-layout" style="margin-top:12px">
      <!-- FILTERS -->
      <aside class="tt-fbox tt-filters">
        <div class="tt-bold" style="margin-bottom:6px">Filters</div>
        <label class="tt-frow"><input id="f-super" class="tt-check" type="checkbox"> Superfast only</label>
        <label class="tt-frow"><input id="f-morn" class="tt-check" type="checkbox"> Morning Departures</label>
        <label class="tt-frow"><input id="f-night" class="tt-check" type="checkbox"> Night Departures</label>
 
        <div class="tt-sep"></div>
        <div class="tt-bold" style="margin:6px 0">Preferred Classes</div>
        <label class="tt-frow"><input class="tt-check fc" data-cl="SL" type="checkbox" checked> Sleeper (SL)</label>
        <label class="tt-frow"><input class="tt-check fc" data-cl="3A" type="checkbox" checked> AC 3-Tier (3A)</label>
        <label class="tt-frow"><input class="tt-check fc" data-cl="2A" type="checkbox" checked> AC 2-Tier (2A)</label>
        <label class="tt-frow"><input class="tt-check fc" data-cl="1A" type="checkbox" checked> AC First (1A)</label>
      </aside>
 
      <!-- RESULTS -->
      <div class="tt-card">
        <div class="tt-list" id="tt-list">
          <div class="tt-small">Search to see trains…</div>
        </div>
      </div>
    </div>
  </div>
 
  <!-- BOOKING MODAL -->
  <div id="tt-modal" class="tt-modal">
    <div class="tt-mcard">
      <div class="tt-mhd">
        <strong id="bm-title">Train</strong>
        <button onclick="document.getElementById('tt-modal').classList.remove('show')" class="tt-btn tt-ghost" style="height:36px">✕ Close</button>
      </div>
      <div class="tt-mbd">
        <div id="bm-seg" class="tt-small" style="margin-bottom:8px"></div>
 
        <div class="tt-grid3" id="bm-classes"></div>
 
        <div id="bm-farebox" style="margin-top:14px;display:none">
          <div class="tt-bold" style="margin-bottom:6px">Fare Summary</div>
          <div id="bm-fare" class="tt-small"></div>
          <div class="tt-sep"></div>
 
          <div id="bm-passengers"></div>
<!-- CONFIRM / CANCEL BUTTONS -->
<div style="display:flex; justify-content:flex-end; gap:12px; margin-top:18px;">
  <button id="bm-cancel" class="tt-btn tt-ghost" style="padding:10px 18px;">Cancel</button>
</div>
 
          <div class="tt-sep"></div>
          <div class="tt-grid3">
            <div>
              <label class="tt-small">Payment Method</label>
              <select id="bm-pay" class="tt-sel">
                <option>UPI</option><option>Debit Card</option><option>Credit Card</option><option>Net Banking</option>
              </select>
            </div>
            <div class="tt-col">
              <span class="tt-small">Quota</span>
              <div id="bm-quota" class="tt-tag">GN</div>
            </div>
            <div class="tt-col" style="align-items:flex-end;justify-content:flex-end">
              <button id="bm-paygo" class="tt-btn" style="width:100%">Pay & Generate Ticket</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
 
  <!-- TICKET -->
  <div id="tt-ticket">
    <div class="t-wrap">
      <div class="t-card">
        <div class="t-hd">
          <div style="font-size:18px;font-weight:900">Thrill Yari • Railway E-Ticket</div>
          <div id="tk-pnr" style="font-weight:900">PNR: TY000000</div>
        </div>
        <div class="t-bd">
          <div id="tk-when" class="tt-small" style="margin-bottom:10px"></div>
 
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <div class="tt-bold">Passenger(s)</div>
              <div id="tk-pax"></div>
            </div>
            <div>
              <div class="tt-bold">Payment</div>
              <div>Total Fare: <b id="tk-fare"></b></div>
              <div>Method: <span id="tk-pay"></span></div>
            </div>
          </div>
 
          <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <div class="tt-bold" style="margin-bottom:6px">Journey</div>
              <div>Train: <b id="tk-train"></b></div>
              <div>Route: <span class="pill" id="tk-from"></span> → <span class="pill" id="tk-to"></span></div>
              <div id="tk-times" class="tt-small"></div>
              <div id="tk-class" class="tt-small"></div>
            </div>
            <div>
              <div class="tt-bold" style="margin-bottom:6px">Coach & Berth</div>
              <div id="tk-seat"></div>
              <div class="tt-small">Chart Status: Not Prepared</div>
            </div>
          </div>
 
          <div id="tt-actions">
            <button onclick="window.print()" class="btn">Print / Save PDF</button>
            <a id="tk-share" class="btn" target="_blank">Share on WhatsApp</a>
            <a class="btn" href="#" onclick="document.getElementById('tt-ticket').style.display='none';return false;">Close</a>
          </div>
        </div>
      </div>
    </div>
  </div>
 
  <script>
    ;(()=>{
      /* ---------------- Data ---------------- */
      const CITY = {
        "Ahmedabad (ADI)":"ADI", "Mumbai (MMCT)":"MMCT", "Delhi (NDLS)":"NDLS",
        "Surat (ST)":"ST", "Jaipur (JP)":"JP", "Kolkata (HWH)":"HWH",
        "Hyderabad (HYB)":"HYB","Chennai (MAS)":"MAS","Bangalore (SBC)":"SBC"
      };
 
      // Distance matrix (approx km)
      const KM = {
        "ADI-MMCT":491,"MMCT-ADI":491,
        "ADI-ST":265,"ST-ADI":265,
        "ADI-NDLS":936,"NDLS-ADI":936,
        "MMCT-NDLS":1384,"NDLS-MMCT":1384,
        "JP-NDLS":303,"NDLS-JP":303,
        "HWH-NDLS":1450,"NDLS-HWH":1450,
        "HYB-MAS":710,"MAS-HYB":710,
        "SBC-MAS":362,"MAS-SBC":362,
        "MMCT-ST":231,"ST-MMCT":231,
        "SBC-MMCT":984,"MMCT-SBC":984
      };
 
      // Sample trains (superfast flag affects fare)
      const TRAINS = [
        {no:"12215", name:"Garib Rath", superfast:true, runs:"Daily",
          legs:[
            seg("ADI","MMCT","06:05","13:41"), seg("MMCT","ADI","16:10","23:00")
          ],
          classes:["SL","3A","2A","1A"]
        },
        {no:"22691", name:"Rajdhani (YPR-NDLS)", superfast:true, runs:"Daily",
          legs:[ seg("ADI","MMCT","09:20","16:45") ], classes:["1A","2A","3A","SL"]
        },
        {no:"12934", name:"Karnavati Express", superfast:true, runs:"Daily",
          legs:[ seg("ADI","MMCT","08:40","15:50"), seg("MMCT","ADI","18:20","01:10") ],
          classes:["CC","2S","3A","SL"]
        },
        {no:"22953", name:"Gujarat SF Express", superfast:true, runs:"Daily",
          legs:[ seg("ST","MMCT","07:10","10:40"), seg("MMCT","ST","18:05","21:35") ],
          classes:["SL","3A","2A"]
        },
        {no:"12479", name:"Surya Nagri Express", superfast:true, runs:"Daily",
          legs:[ seg("JP","NDLS","17:00","22:10") ],
          classes:["SL","3A","2A","1A"]
        }
      ];
      function seg(from,to,dep,arr){ return {from,to,dep,arr}; }
 
      // Random availability generator (stable per search)
      function seatAvail(key, cls){
        const base = Math.abs(hashCode(key+cls))%90 + 10;
        return base;
      }
      function hashCode(s){ let h=0; for(let i=0;i<s.length;i++) h=((h<<5)-h)+s.charCodeAt(i), h|=0; return h; }
 
      /* ----------- Fare Engine (IRCTC-ish) ----------- */
      // Base per-km slab approx (₹/km)
      const SLAB = { SL:0.6, "3A":2.5, "2A":3.7, "1A":5.0, "CC":1.2, "2S":0.4 };
      const RESV = { SL:20, "3A":40, "2A":50, "1A":60, "CC":40, "2S":15 }; // Reservation charge
      const SF_SUR = 45;              // Superfast charge (flat)
      const GST = (cls)=> (["1A","2A","3A","CC"].includes(cls)? 0.05 : 0);
      // Tatkal min/ceil approx (per pax)
      const TATKAL = { SL:[100,200], "3A":[300,400], "2A":[400,500], "1A":[500,600], "CC":[150,250], "2S":[10,75] };
 
      function computeFare(km, cls, superfast, quota, pax){
        let base = Math.max(20, Math.round(km * (SLAB[cls]||1)));
        let superf = superfast? SF_SUR : 0;
        let res = RESV[cls]||20;
        let tatkal = 0;
        if(quota==="TQ"){
          const [mn,mx]=TATKAL[cls]||[100,200];
          tatkal = Math.min(mx, Math.max(mn, Math.round(base*0.3)));
        }
        let sub = base + superf + res + tatkal;
        let gst = Math.round(sub * GST(cls));
        let total = (sub + gst) * pax;
        return {base,superf,res,tatkal,gst,total,per:sub+gst};
      }
 
      /* ---------------- State ---------------- */
      const $ = s => document.querySelector(s);
      const $$ = s => Array.from(document.querySelectorAll(s));
      const rs = id => document.getElementById(id);
 
      (function defaults(){
        const today = new Date();
        rs('tt-date').value = today.toISOString().slice(0,10);
        rs('tt-from').value = "Ahmedabad (ADI)";
        rs('tt-to').value = "Mumbai (MMCT)";
      })();
 
      rs('tt-search').addEventListener('click', render);
      $$('.tt-check, .fc').forEach(c=>c.addEventListener('change', render));
 
      function render(){
        const FROM = CITY[rs('tt-from').value];
        const TO   = CITY[rs('tt-to').value];
        if(FROM===TO){ rs('tt-list').innerHTML = `<div class="tt-small">From/To cannot be same.</div>`; return; }
 
        const quota = rs('tt-quota').value;
        const pax = Number(rs('tt-pax').value.match(/\d/)[0]);
        const fSuper = rs('f-super').checked, fM = rs('f-morn').checked, fN = rs('f-night').checked;
 
        const allowedClasses = new Set($$('.fc:checked').map(x=>x.dataset.cl));
 
        // Find matching legs
        let items=[];
        for(const tr of TRAINS){
          for(const leg of tr.legs){
            if(leg.from===FROM && leg.to===TO){
              items.push({train:tr,leg});
            }
          }
        }
 
        // Filters: time windows
        items = items.filter(it=>{
          const H = Number(it.leg.dep.split(':')[0]);
          if(fM && H>=12) return false;
          if(fN && (H<20 && H>=6)) return false;
          if(fSuper && !it.train.superfast) return false;
          return true;
        });
 
        // Render
        if(!items.length){ rs('tt-list').innerHTML = `<div class="tt-small">No trains for this route.</div>`; return; }
 
        const list = items.map(it=>{
          const key = `${it.train.no}-${it.leg.from}-${it.leg.to}-${rs('tt-date').value}`;
          const km = KM[`${it.leg.from}-${it.leg.to}`] || 500;
          const dur = duration(it.leg.dep, it.leg.arr);
          // Primary fare preview: SL or 3A
          const previewClass = allowedClasses.has("SL") ? "SL" : (allowedClasses.has("3A")?"3A":"2A");
          const farePrev = computeFare(km, previewClass, it.train.superfast, quota, pax).per;
 
          const classChips = it.train.classes.filter(c=>allowedClasses.has(c)).map(c=>{
            const avail = seatAvail(key,c);
            return `<span class="tt-class">
              <b>${c}</b> <span class="tt-small">•</span>
              <span class="tt-av ${avail>50?'tt-green':(avail>20?'tt-yellow':'')}">${avail} avail</span>
            </span>`;
          }).join('');
 
          return `
          <article class="tt-train">
            <div class="tt-body">
              <div class="tt-left">
                <div class="tt-trn">
                  <div>
                    <div class="tt-bold">${it.train.name}</div>
                    <div class="tt-small">${it.train.no} • ${it.train.superfast?'Superfast':'Mail/Exp'} • Runs ${it.train.runs}</div>
                    <div class="tt-badges"><span class="tt-tag">${quota}</span><span class="tt-chip">${km} km</span></div>
                  </div>
                </div>
                <div>
                  <div class="tt-time">${it.leg.dep} → ${it.leg.arr}</div>
                  <div class="tt-dur">${dur} • ${codeToName(it.leg.from)} → ${codeToName(it.leg.to)}</div>
                </div>
                <div class="tt-classes">${classChips}</div>
              </div>
              <div class="tt-right">
                <div style="text-align:right">
                  <div class="tt-price">₹ ${farePrev.toLocaleString('en-IN')}</div>
                  <div class="tt-note">per pax • preview (${previewClass})</div>
                </div>
                <button class="tt-btn" data-no="${it.train.no}" data-from="${it.leg.from}" data-to="${it.leg.to}">View / Book</button>
              </div>
            </div>
          </article>`;
        }).join('');
 
        rs('tt-list').innerHTML = list;
 
        // Bind view/book
        $$('#tt-list .tt-btn').forEach(b=>b.onclick=(e)=>{
          const no = e.currentTarget.dataset.no;
          const from = e.currentTarget.dataset.from;
          const to = e.currentTarget.dataset.to;
          openBooking(no, from, to);
        });
      }
 
      function openBooking(no, from, to){
        const tr = TRAINS.find(t=>t.no===no);
        const leg = tr.legs.find(l=>l.from===from && l.to===to);
        const quota = rs('tt-quota').value;
        const pax = Number(rs('tt-pax').value.match(/\d/)[0]);
        const dt = rs('tt-date').value;
        const km = KM[`${from}-${to}`] || 500;
 
        rs('bm-title').textContent = `${tr.name} • ${tr.no}`;
        rs('bm-seg').textContent = `${codeToName(from)} → ${codeToName(to)} • ${leg.dep}–${leg.arr} • ${km} km • ${tr.superfast?'Superfast':''}`;
 
        const allowedClasses = new Set($$('.fc:checked').map(x=>x.dataset.cl));
        const key = `${tr.no}-${from}-${to}-${dt}`;
        rs('bm-classes').innerHTML = tr.classes
          .filter(c=>allowedClasses.has(c))
          .map(c=>{
            const av = seatAvail(key,c);
            const f = computeFare(km,c,tr.superfast,quota,1);
            return `<div class="tt-card" style="padding:12px;border-radius:14px;border-color:#fee2e2">
              <div class="tt-bold">${c}</div>
              <div class="tt-small">Fare (per pax): ₹ ${f.per.toLocaleString('en-IN')}</div>
              <div class="tt-small">Availability: <b class="${av>50?'tt-green':'tt-yellow'}">${av} available</b></div>
              <button class="tt-btn" data-cl="${c}" data-av="${av}" style="margin-top:8px;width:100%">Select ${c}</button>
            </div>`;
          }).join('');
 
        rs('bm-farebox').style.display='none';
        rs('tt-modal').classList.add('show');
 
        // handle class select
        $$('#bm-classes .tt-btn').forEach(btn=>{
          btn.onclick = ()=>{
            const cls = btn.dataset.cl;
            const av  = Number(btn.dataset.av);
            if(av < pax){ alert('Not enough seats available in selected class.'); return; }
 
            const fare = computeFare(km, cls, tr.superfast, quota, pax);
 
            rs('bm-fare').innerHTML = `
              <div><b>Base Fare:</b> ₹ ${fare.base.toLocaleString('en-IN')}</div>
              ${tr.superfast?`<div><b>Superfast charge:</b> ₹ ${fare.superf}</div>`:''}
              <div><b>Reservation charge:</b> ₹ ${fare.res}</div>
              ${fare.tatkal?`<div><b>Tatkal surcharge:</b> ₹ ${fare.tatkal}</div>`:''}
              <div><b>GST:</b> ₹ ${fare.gst}</div>
              <div class="tt-sep"></div>
              <div><b>Grand Total (×${pax} pax):</b> ₹ ${fare.total.toLocaleString('en-IN')}</div>
            `;
 
            rs('bm-quota').textContent = quota;
 
            // Build passenger forms (Full IRCTC)
            const paxWrap = rs('bm-passengers');
            paxWrap.innerHTML = `<div class="tt-bold" style="margin-bottom:6px">Passenger Details</div>` +
              Array.from({length:pax}, (_,i)=>`
                <div class="tt-card" style="padding:12px;margin-bottom:10px">
                  <div class="tt-bold">Passenger ${i+1}</div>
                  <div class="tt-grid3" style="margin-top:8px">
                    <input class="tt-input pax-name" placeholder="Full Name">
                    <input class="tt-input pax-age" type="number" min="1" max="120" placeholder="Age">
                    <select class="tt-sel pax-gen"><option>Male</option><option>Female</option><option>Other</option></select>
                  </div>
                  <div class="tt-grid3" style="margin-top:8px">
                    <select class="tt-sel pax-berth">
                      <option>No Preference</option><option>Lower</option><option>Middle</option><option>Upper</option>
                      <option>Side Lower</option><option>Side Upper</option>
                    </select>
                    <select class="tt-sel pax-idtype">
                      <option>Aadhar</option><option>PAN</option><option>Driving Licence</option><option>Voter ID</option>
                    </select>
                    <input class="tt-input pax-id" placeholder="ID Number">
                  </div>
                </div>
              `).join('');
 
            rs('bm-farebox').style.display='block';
rs('bm-farebox').scrollIntoView({behavior:'smooth'});
 
// Confirm button → show payment
 
// Cancel button → close the booking modal
rs('bm-cancel').onclick = ()=>{
  document.getElementById('tt-modal').classList.remove('show');
};
 
 
            // payment click -> ticket
            rs('bm-paygo').onclick = ()=>{
              const names=[...$$('.pax-name')].map(i=>i.value.trim());
              const ages=[...$$('.pax-age')].map(i=>i.value.trim());
              const gens=[...$$('.pax-gen')].map(i=>i.value);
              const berth=[...$$('.pax-berth')].map(i=>i.value);
              const idt=[...$$('.pax-idtype')].map(i=>i.value);
              const ids=[...$$('.pax-id')].map(i=>i.value.trim());
              if(names.some(x=>!x) || ages.some(x=>!x) || ids.some(x=>!x)){ alert("Please fill all passenger details."); return; }
 
              // Fake payment instantly succeeds
              const pnr = 'TY' + Math.floor(100000 + Math.random()*900000);
              const when = new Date();
              rs('tk-pnr').textContent = `PNR: ${pnr}`;
              rs('tk-when').textContent = when.toLocaleString();
 
              // Pax block
              rs('tk-pax').innerHTML = names.map((n,i)=>`${n} • ${ages[i]} • ${gens[i]} • ${idt[i]}: ${ids[i]} • Berth: ${berth[i]}`).join('<br>');
              rs('tk-fare').textContent = '₹ ' + fare.total.toLocaleString('en-IN');
              rs('tk-pay').textContent = rs('bm-pay').value;
 
              rs('tk-train').textContent = `${tr.name} (${tr.no}) • ${cls}`;
              rs('tk-from').textContent = codeToName(from);
              rs('tk-to').textContent = codeToName(to);
              rs('tk-times').textContent = `Departure ${rs('tt-date').value} ${leg.dep} • Arrival ${rs('tt-date').value} ${leg.arr}`;
              rs('tk-class').textContent = `Class: ${cls} • Quota: ${quota}`;
 
              // Simple berth/coach mock
              rs('tk-seat').innerHTML = names.map((n,i)=>`Coach: ${cls==='SL'?'S':'A'}${Math.ceil(Math.random()*6)} • Berth: ${berth[i]==='No Preference'?'Auto':berth[i]} • ${Math.ceil(Math.random()*72)}`).join('<br>');
 
              const msg = `🚆 Thrill Yari Ticket
PNR: ${pnr}
Train: ${tr.name} ${tr.no}
Route: ${codeToName(from)} → ${codeToName(to)}
Date: ${rs('tt-date').value}  ${leg.dep} → ${leg.arr}
Class: ${cls}  Quota: ${quota}
Fare: ₹ ${fare.total.toLocaleString('en-IN')}
Pax: ${names.join(', ')}`;
 
              rs('tk-share').href = 'https://wa.me/?text=' + encodeURIComponent(msg);
 
              // show ticket
              rs('tt-modal').classList.remove('show');
              rs('tt-ticket').style.display='block';
              window.scrollTo({top:0,behavior:'smooth'});
            };
          };
        });
      }
 
      function duration(dep,arr){
        // dep/arr are HH:MM 24h same-day approx
        const [dh,dm]=dep.split(':').map(Number), [ah,am]=arr.split(':').map(Number);
        let m = (ah*60+am) - (dh*60+dm);
        if(m<0) m += 24*60;
        const H = Math.floor(m/60), M = m%60;
        return `${H}h ${M}m`;
      }
      function codeToName(code){
        const map = {ADI:"Ahmedabad",MMCT:"Mumbai",NDLS:"Delhi",ST:"Surat",JP:"Jaipur",HWH:"Kolkata",HYB:"Hyderabad",MAS:"Chennai",SBC:"Bangalore"};
        return map[code]||code;
      }
 
      // initial render
      render();
    })();
  </script>
</section>
 
 
      
      <!-- ✅ Bus Form -->
       <!-- ========== 🚌 THRILL YARI — BUS BOOKING (RedBus Style) ========== -->
<!-- ========== 🚌 THRILL YARI — BUS BOOKING (Full RedBus-Style Module like Train) ========== -->
<section id="bus" class="form-section">
  <style>
    .bb *{box-sizing:border-box}
    .bb{--pri:#0d6efd;--ink:#0f172a;--mut:#64748b;--line:#e5e7eb;--bg:#ffffff;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .bb .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
 
    /* Search card */
    .bb .card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px rgba(15,23,42,.06)}
    .bb .head{display:grid;grid-template-columns:1.2fr 1.2fr .9fr auto;gap:10px;padding:12px}
    .bb .in,.bb .sel{height:44px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;font-size:14px;background:#fff}
    .bb .btn{height:44px;border:none;border-radius:12px;background:var(--pri);color:#fff;font-weight:900;padding:0 18px;cursor:pointer}
    .bb .btn:hover{filter:brightness(.96)}
 
    /* Layout */
    .bb .layout{display:grid;grid-template-columns:280px 1fr;gap:14px;margin-top:12px}
    @media (max-width:980px){ .bb .head{grid-template-columns:1fr 1fr 1fr} .bb .layout{grid-template-columns:1fr} }
 
    /* Filters */
    .bb .fbox{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;position:sticky;top:20px;height:fit-content}
    .bb .frow{display:flex;align-items:center;gap:8px;margin:8px 0;color:#374151}
    .bb .check{width:18px;height:18px}
    .bb .sep{height:1px;background:var(--line);margin:12px 0}
 
    /* Results */
    .bb .list{padding:12px}
    .bb .rb-card{border:1px solid var(--line);border-radius:14px;overflow:hidden;display:grid;grid-template-columns:1fr 160px;background:#fff;margin-bottom:12px}
    .bb .rb-left{padding:14px;display:grid;grid-template-columns:180px 1fr 140px;gap:12px;align-items:center}
    .bb .rb-brand{font-weight:900}
    .bb .rb-tiny{font-size:12px;color:#64748b}
    .bb .rb-time{font-size:20px;font-weight:900}
    .bb .rb-tag{background:#f1f5f9;border:1px solid #e2e8f0;padding:2px 8px;border-radius:8px;font-size:12px}
    .bb .rb-right{border-left:1px dashed var(--line);padding:12px;display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;background:#fbfdff}
    .bb .rb-fare{font-size:22px;font-weight:900}
    .bb .rb-view{border:1px solid #cfe0ff;background:#eef6ff;border-radius:10px;padding:8px 10px;font-weight:800;color:#1d4ed8;cursor:pointer}
    .bb .stars{color:#f59e0b;font-weight:800}
    .bb .results-hd{display:flex;align-items:center;justify-content:space-between;padding:10px 12px}
    .bb .sort{display:flex;align-items:center;gap:8px}
    .bb .pill{background:#eef2ff;border:1px solid #c7d2fe;color:#1d4ed8;border-radius:999px;padding:4px 10px;font-weight:800;font-size:12px}
 
    /* Seat Modal (container only; seats HTML comes from fetch_seats.php) */
    #seatModal{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:9999}
    #seatModal .mwrap{background:#fff;width:min(900px,95%);border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.3)}
    #seatModal .mhd{padding:12px 16px;background:#0d6efd;color:#fff;display:flex;align-items:center;justify-content:space-between}
    #seatModal .mbd{padding:14px;max-height:70vh;overflow:auto}
    #seatModal .act{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
    #seatModal .btn{border:1px solid #cfe0ff;background:#eef6ff;border-radius:10px;padding:10px 12px;font-weight:800;color:#1d4ed8;cursor:pointer}
    #seatModal .btn.p{background:#0d6efd;border-color:#0d6efd;color:#fff}
 
    .hidden{display:none !important}
  </style>
 
  <div class="bb" id="bb">
    <div class="wrap">
      <!-- SEARCH BAR -->
      <div class="card">
        <div class="head">
          <input id="bb-from" class="in" list="bb-cities" placeholder="From City" required>
          <input id="bb-to" class="in" list="bb-cities" placeholder="To City" required>
          <input id="bb-date" class="in" type="date" required>
          <button id="bb-search" class="btn">Search Buses</button>
        </div>
      </div>
 
      <!-- City list (Option A: 9 cities same as Train) -->
      <datalist id="bb-cities">
        <option value="Ahmedabad"></option><option value="Mumbai"></option><option value="Delhi"></option>
        <option value="Surat"></option><option value="Jaipur"></option><option value="Kolkata"></option>
        <option value="Hyderabad"></option><option value="Chennai"></option><option value="Bangalore"></option>
      </datalist>
 
      <div class="layout">
        <!-- FILTERS -->
        <aside class="fbox">
          <div style="font-weight:900;margin-bottom:6px">Filters</div>
 
          <div style="font-weight:700;margin:6px 0">Bus type</div>
          <label class="frow"><input class="check bt" type="checkbox" value="AC" checked> AC</label>
          <label class="frow"><input class="check bt" type="checkbox" value="Non-AC" checked> Non-AC</label>
          <label class="frow"><input class="check bt" type="checkbox" value="Sleeper" checked> Sleeper</label>
          <label class="frow"><input class="check bt" type="checkbox" value="Seater" checked> Seater</label>
          <label class="frow"><input class="check bt" type="checkbox" value="Volvo" checked> Volvo</label>
 
          <div class="sep"></div>
          <div style="font-weight:700;margin:6px 0">Departure window</div>
          <label class="frow"><input class="check win" type="checkbox" value="00-06"> 00:00–05:59</label>
          <label class="frow"><input class="check win" type="checkbox" value="06-12"> 06:00–11:59</label>
          <label class="frow"><input class="check win" type="checkbox" value="12-18"> 12:00–17:59</label>
          <label class="frow"><input class="check win" type="checkbox" value="18-24"> 18:00–23:59</label>
 
          <div class="sep"></div>
          <div style="font-weight:700;margin:6px 0">Price (max)</div>
          <input id="bb-price" class="in" type="range" min="200" max="3000" step="50" value="3000">
          <div class="frow">₹ <b id="bb-price-val">3,000</b></div>
 
          <div class="sep"></div>
          <div style="font-weight:700;margin:6px 0">Min Rating</div>
          <input id="bb-rate" class="in" type="range" min="0" max="5" step="1" value="0">
          <div class="frow"><b id="bb-rate-val">0+</b> stars</div>
        </aside>
 
        <!-- RESULTS -->
        <div class="card">
          <div class="results-hd">
            <div style="font-weight:900"><span id="bb-count">Results</span></div>
            <div class="sort">
              <span class="bb .rb-tiny" style="color:var(--mut)">Sort by:</span>
              <select id="bb-sort" class="sel">
                <option value="fare-asc">Price (low → high)</option>
                <option value="fare-desc">Price (high → low)</option>
                <option value="dep-asc">Departure (early → late)</option>
                <option value="dep-desc">Departure (late → early)</option>
                <option value="rating-desc">Rating (high → low)</option>
              </select>
            </div>
          </div>
          <div id="bb-note" class="bb rb-tiny" style="padding:0 12px;color:#6b7280"></div>
          <div id="bus-results" class="list">
            <div class="bb rb-tiny" style="padding:8px 12px">Search to see buses…</div>
          </div>
        </div>
      </div>
    </div>
  </div>
 
  <!-- New Seat Modal (A: Mixed Sleeper + Seater — content injected from fetch_seats.php) -->
  <div id="seatModal">
    <div class="mwrap">
      <div class="mhd">
        <strong id="bm-title">Bus</strong>
        <button onclick="closeSeatModal()" class="btn" style="height:36px;background:#fff;color:#0d6efd;border-color:#fff">✕ Close</button>
      </div>
      <div class="mbd">
        <div id="bm-sub" class="bb rb-tiny" style="margin-bottom:8px"></div>
        <div id="seat-layout"><!-- seats HTML will load here --></div>
        <div class="act">
          <button onclick="closeSeatModal()" class="btn">Cancel</button>
          <button id="bb-continue" class="btn p" disabled>Continue</button>
        </div>
      </div>
    </div>
  </div>
</section>
 
<script>
/* ============================
   🚌 BUS LOGIC (Works with your PHP)
============================ */
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const rs = id => document.getElementById(id);
 
  // Defaults
  (function defaults(){
    const today = new Date();
    rs('bb-date').value = today.toISOString().slice(0,10);
    rs('bb-from').value = "Ahmedabad";
    rs('bb-to').value   = "Mumbai";
    rs('bb-price-val').textContent = Number(rs('bb-price').value).toLocaleString('en-IN');
    rs('bb-rate-val').textContent  = rs('bb-rate').value + '+';
  })();
 
  // Search click
  rs('bb-search').addEventListener('click', fetchBuses);
 
  // Filters & sort
  rs('bb-price').addEventListener('input', e => {
    rs('bb-price-val').textContent = Number(e.target.value).toLocaleString('en-IN');
    applyBusRefine();
  });
  rs('bb-rate').addEventListener('input', e => {
    rs('bb-rate-val').textContent = e.target.value + '+';
    applyBusRefine();
  });
  $$('.bt').forEach(c=>c.addEventListener('change', applyBusRefine));
  $$('.win').forEach(c=>c.addEventListener('change', applyBusRefine));
  rs('bb-sort').addEventListener('change', applyBusRefine);
 
  // Helpers
  function toMin12h(t){ // "09:30 PM" -> minutes since 00:00
    if(!t) return 0;
    const m = t.match(/(\d{1,2}):(\d{2})\s*([AP]M)/i);
    if(!m) return 0;
    let h = parseInt(m[1],10), min = parseInt(m[2],10);
    const ampm = m[3].toUpperCase();
    if(ampm==='PM' && h!==12) h+=12;
    if(ampm==='AM' && h===12) h=0;
    return h*60+min;
  }
  function inWindow(minutes, w){
    if(!w) return true;
    const [a,b]=w.split('-').map(Number);
    return minutes>=a*60 && minutes< b*60;
  }
  function ratingFromStars(node){
    const el = node.querySelector('.stars');
    if(!el) return 0;
    const txt = el.textContent || '';
    // stars are repeated '★', count them
    return (txt.match(/★/g)||[]).length;
  }
 
  // Fetch buses (uses your existing fetch_buses.php which returns HTML with data-attrs)
  function fetchBuses(e){
    if(e) e.preventDefault();
    const from = rs('bb-from').value.trim();
    const to   = rs('bb-to').value.trim();
    if(!from || !to || from.toLowerCase()===to.toLowerCase()){
      alert('Please choose different From and To cities.'); return;
    }
    rs('bb-note').textContent = '';
    const fd = new FormData();
    fd.append('from', from);
    fd.append('to',   to);
    fetch('fetch_buses.php', { method:'POST', body:fd })
      .then(r=>r.text())
      .then(html=>{
        rs('bus-results').innerHTML = html;
        // Attach seat buttons to our openSeatLayout
        rs('bus-results').querySelectorAll('.rb-view').forEach(btn=>{
          // Already calls openSeatModal in your PHP; we’ll replace handler here to ensure consistency
          btn.onclick = (ev)=>{
            ev.preventDefault();
            const card = btn.closest('.rb-card');
            const name = card.querySelector('.rb-brand')?.textContent?.trim() || 'Bus';
            const type = card.getAttribute('data-type') || '';
            const dep  = card.getAttribute('data-dep')  || '';
            const fare = Number(card.getAttribute('data-fare')||0);
            const id   = Number(btn.getAttribute('onclick')?.match(/\((\d+)/)?.[1] || 0); // fallback parse from inline
            const arr  = (card.querySelector('.rb-time')?.textContent.split('→')[1]||'').trim();
            openSeatLayout(id, type, name, fare, dep, arr);
          };
        });
        applyBusRefine(true);
      })
      .catch(err=>{
        console.error(err);
        rs('bus-results').innerHTML = `<div class="bb rb-tiny" style="padding:8px 12px;color:#b91c1c">Error loading buses.</div>`;
      });
  }
 
  // Refine & Sort on client using data-* from your PHP cards
  function applyBusRefine(first=false){
    const cards = $$('#bus-results .rb-card');
    if(cards.length===0){
      rs('bb-count').textContent = '0 results';
      return;
    }
    const priceMax = Number(rs('bb-price').value);
    const minStars = Number(rs('bb-rate').value);
    const typesAllowed = new Set($$('.bt:checked').map(x=>x.value.toLowerCase()));
    const wins = $$('.win:checked').map(x=>x.value);
 
    let visible = [];
    cards.forEach(card=>{
      const fare = Number(card.getAttribute('data-fare') || 0);
      const type = (card.getAttribute('data-type') || '').toLowerCase();
      const dep  = card.getAttribute('data-dep') || '';
      const depMin = toMin12h(dep);
      const stars = ratingFromStars(card);
 
      let ok = true;
      if(fare > priceMax) ok=false;
      if(typesAllowed.size && ![...typesAllowed].some(t=> type.includes(t))) ok=false;
      if(minStars && stars < minStars) ok=false;
      if(wins.length && !wins.some(w=>inWindow(depMin,w))) ok=false;
 
      card.style.display = ok ? '' : 'none';
      if(ok) visible.push(card);
    });
 
    // Sorting
    const sort = rs('bb-sort').value;
    const getDep = c => toMin12h(c.getAttribute('data-dep')||'');
    const getFare = c => Number(c.getAttribute('data-fare')||0);
    const getStars = c => ratingFromStars(c);
    const comp = {
      'fare-asc'   : (a,b)=>getFare(a)-getFare(b),
      'fare-desc'  : (a,b)=>getFare(b)-getFare(a),
      'dep-asc'    : (a,b)=>getDep(a)-getDep(b),
      'dep-desc'   : (a,b)=>getDep(b)-getDep(a),
      'rating-desc': (a,b)=>getStars(b)-getStars(a),
    }[sort] || ((a,b)=>getFare(a)-getFare(b));
 
    visible.sort(comp);
    const parent = rs('bus-results');
    visible.forEach(c=>parent.appendChild(c));
 
    rs('bb-count').textContent = `${visible.length} result${visible.length>1?'s':''}`;
    if(first && visible.length===0){
      rs('bb-note').textContent = 'No exact matches. Try relaxing filters.';
    }
  }
 
  // Seat modal wiring (works with your fetch_seats.php and passengerModal already in your page)
  let BUS_ID=0, BUS_PRICE=0, SELECTED_SEAT=null;
  function openSeatLayout(id, type, name, price, dep, arr){
    BUS_ID = id; BUS_PRICE = price; SELECTED_SEAT = null;
    rs('bm-title').textContent = `${name} • ${type}`;
    rs('bm-sub').innerHTML = `<span class="pill">${dep}</span> → <span class="pill">${arr||''}</span> • Fare from ₹ ${price.toLocaleString('en-IN')}`;
    rs('bb-continue').disabled = true;
 
    rs('seatModal').style.display='flex';
    const body = new URLSearchParams({ bus_id:id, bus_type:type });
    fetch('fetch_seats.php',{ method:'POST', body })
      .then(r=>r.text())
      .then(html=>{
        rs('seat-layout').innerHTML = html;
        // Click handler for available seats coming from your PHP (must have .seat.available and data-seat)
        rs('seat-layout').querySelectorAll('.seat.available').forEach(s=>{
          s.addEventListener('click',()=>{
            rs('seat-layout').querySelectorAll('.seat.selected').forEach(x=>x.classList.remove('selected'));
            s.classList.add('selected');
            SELECTED_SEAT = s.dataset.seat;
            rs('bb-continue').disabled = false;
          });
        });
      })
      .catch(err=>{
        console.error(err);
        rs('seat-layout').innerHTML = `<div class="bb rb-tiny" style="color:#b91c1c">Failed to load seats.</div>`;
      });
  }
  window.openSeatLayout = openSeatLayout; // expose globally for safety
 
  rs('bb-continue').addEventListener('click', ()=>{
  if(!SELECTED_SEAT){
    alert("⚠️ Please select a seat!");
    return;
  }

  const from = document.getElementById('bb-from').value;
  const to   = document.getElementById('bb-to').value;

  // ✅ OPEN PASSENGER FORM HERE
 openFinalBusPassengerForm(
  BUS_ID,
  SELECTED_SEAT,
  document.getElementById('bb-from').value,
  document.getElementById('bb-to').value
);


  document.getElementById('seatModal').style.display = 'none';

 
    const pf = rs('passengerForm');
    ['bus_id','seat_no'].forEach(n=>{
      let el = pf.querySelector(`input[name="${n}"]`);
      if(!el){ el = document.createElement('input'); el.type='hidden'; el.name=n; pf.appendChild(el); }
      el.value = (n==='bus_id') ? BUS_ID : SELECTED_SEAT;
    });
  });
 
  function closeSeatModal(){ rs('seatModal').style.display='none'; }
  window.closeSeatModal = closeSeatModal;
 
  // Try initial search once so UI doesn't look empty
  // fetchBuses();
})();
</script>
 
 </div>
 </div>
  <!-- Profile Upload -->
  <div id="profile" class="section-content">
  <style>
    /* --- प्रोफाइल पेज के लिए नया CSS --- */
    .profile-container {
      max-width: 1000px;
      margin: 30px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.05);
      overflow: hidden;
      display: flex; /* साइडबार और कंटेंट के लिए */
      min-height: 600px; /* कम से कम ऊंचाई */
    }

    /* --- Left Sidebar --- */
    .profile-sidebar {
      width: 280px;
      background: #f4f7f9; /* हल्का ग्रे बैकग्राउंड */
      padding: 30px 0;
      border-right: 1px solid #e0e0e0;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .profile-avatar-wrapper {
      position: relative;
      width: 120px;
      height: 120px;
      margin-bottom: 20px;
      border-radius: 50%;
      background: #e0e0e0;
      overflow: hidden;
      border: 3px solid #0066cc; /* थीम कलर बॉर्डर */
    }
    .profile-avatar-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .avatar-upload-btn {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #0066cc;
      color: #fff;
      border-radius: 50%;
      width: 35px;
      height: 35px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 0.9rem;
      border: 2px solid #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .avatar-upload-btn input {
      display: none;
    }
    .profile-username {
      font-size: 1.5rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 5px;
    }
    .profile-useremail {
      font-size: 0.95rem;
      color: #777;
      margin-bottom: 30px;
    }

    .profile-nav {
      width: 100%;
    }
    .profile-nav ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .profile-nav li {
      margin-bottom: 5px;
    }
    .profile-nav a {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px 30px;
      color: #555;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.2s ease;
      border-left: 4px solid transparent;
    }
    .profile-nav a i {
      font-size: 1.2rem;
    }
    .profile-nav a:hover,
    .profile-nav a.active {
      background: #e0f0ff;
      color: #0066cc;
      border-left: 4px solid #0066cc;
    }

    /* --- Right Content Area --- */
    .profile-content {
      flex-grow: 1;
      padding: 40px;
      display: none; /* डिफ़ॉल्ट रूप से सब छिपा दें */
    }
    .profile-content.active {
      display: block; /* एक्टिव कंटेंट दिखाएं */
    }
    .profile-content h2 {
      font-size: 2rem;
      font-weight: 700;
      color: #1a202c;
      margin-top: 0;
      margin-bottom: 30px;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
    }

    /* Form Styles (पुराने कॉन्टैक्ट फॉर्म से मिलते जुलते) */
    .profile-form-group {
      margin-bottom: 20px;
    }
    .profile-form-group label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .profile-form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      box-sizing: border-box;
    }
    .profile-button {
      padding: 12px 25px;
      font-size: 1rem;
      font-weight: 600;
      color: #fff;
      background: #0066cc; /* आपका थीम कलर */
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
      transition: background 0.2s ease;
    }
    .profile-button:hover {
      background: #0056b3;
    }
    .profile-danger-button {
      background: #dc3545;
      margin-left: 10px;
    }
    .profile-danger-button:hover {
      background: #c82333;
    }
/* --- 3. Booking History Styles --- */
.history-item {
  display: flex;
  align-items: center;
  gap: 15px;
  background: #f9f9f9;
  border: 1px solid #eee;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 15px;
}
.history-icon {
  font-size: 1.8rem;
  color: #0066cc;
  width: 40px;
  text-align: center;
}
.history-details {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}
.history-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #333;
}
.history-date, .history-pnr {
  font-size: 0.9rem;
  color: #777;
}
.history-price-status {
  text-align: right;
  min-width: 100px;
}
.history-price {
  font-size: 1.2rem;
  font-weight: 700;
  color: #111;
}
.history-status {
  font-size: 0.9rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 5px;
}
.history-status.confirmed {
  background: #d4edda;
  color: #155724;
}
.history-status.cancelled {
  background: #f8d7da;
  color: #721c24;
}
.history-action {
  background: #0066cc;
  color: #fff;
  text-decoration: none;
  padding: 8px 15px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  transition: background 0.2s ease;
}
.history-action:hover {
  background: #0056b3;
}
.history-action.cancelled {
  background: #777;
}
    /* Responsive */
    @media (max-width: 768px) {
      .profile-container {
        flex-direction: column; /* मोबाइल पर स्टैक */
        min-height: auto;
      }
      .profile-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 20px;
      }
      .profile-nav ul {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
      }
      .profile-nav li {
        margin-bottom: 0;
      }
      .profile-nav a {
        padding: 10px 15px;
        border-left: none;
        border-bottom: 4px solid transparent;
        flex-direction: row;
        gap: 8px;
      }
      .profile-nav a.active {
        border-left: none;
        border-bottom: 4px solid #0066cc;
      }
      .profile-content {
        padding: 20px;
      }
    }
    @media (max-width: 480px) {
      .profile-username {
        font-size: 1.3rem;
      }
      .profile-useremail {
        font-size: 0.85rem;
      }
      .profile-content h2 {
        font-size: 1.6rem;
      }
    }
  </style>

  <div class="profile-container">
    
    <div class="profile-sidebar">
      <div class="profile-avatar-wrapper">
        <img id="profile-pic" src="https://via.placeholder.com/120/E0E0E0/808080?text=JD" alt="User Avatar">
        <label for="avatar-input" class="avatar-upload-btn">
          <i class="fa-solid fa-camera"></i>
          <input type="file" id="avatar-input" accept="image/*">
        </label>
      </div>
      <p class="profile-username">John Doe</p>
      <p class="profile-useremail">john.doe@example.com</p>

      <nav class="profile-nav">
        <ul>
          <li><a href="#" class="profile-nav-link active" data-target="personal-info"><i class="fa-solid fa-user"></i> Personal Info</a></li>
          <li><a href="#" class="profile-nav-link" data-target="security"><i class="fa-solid fa-lock"></i> Security</a></li>
          <li><a href="#" class="profile-nav-link" data-target="preferences"><i class="fa-solid fa-gear"></i> Preferences</a></li>
          <li><a href="#" class="profile-nav-link" data-target="booking-history"><i class="fa-solid fa-receipt"></i> Booking History</a></li>
        </ul>
      </nav>
    </div>
    
    <div class="profile-content-area">
      <div id="personal-info" class="profile-content active">
        <h2>Personal Information</h2>
        <form>
          <div class="profile-form-group">
            <label for="p-name">Full Name</label>
            <input type="text" id="p-name" value="John Doe">
          </div>
          <div class="profile-form-group">
            <label for="p-email">Email Address</label>
            <input type="email" id="p-email" value="john.doe@example.com" disabled>
          </div>
          <div class="profile-form-group">
            <label for="p-phone">Phone Number</label>
            <input type="tel" id="p-phone" value="+91 98765 43210">
          </div>
          <div class="profile-form-group">
            <label for="p-address">Address</label>
            <input type="text" id="p-address" value="123 Travel Street, Ahmedabad">
          </div>
          <button type="submit" class="profile-button">Save Changes</button>
        </form>
      </div>

      <div id="security" class="profile-content">
        <h2>Security Settings</h2>
        <form>
          <div class="profile-form-group">
            <label for="s-current-password">Current Password</label>
            <input type="password" id="s-current-password">
          </div>
          <div class="profile-form-group">
            <label for="s-new-password">New Password</label>
            <input type="password" id="s-new-password">
          </div>
          <div class="profile-form-group">
            <label for="s-confirm-password">Confirm New Password</label>
            <input type="password" id="s-confirm-password">
          </div>
          <button type="submit" class="profile-button">Update Password</button>
          <button type="button" class="profile-button profile-danger-button">Deactivate Account</button>
        </form>
      </div>

      <div id="preferences" class="profile-content">
        <h2>Preferences</h2>
        <form>
          <div class="profile-form-group">
            <label for="pref-notifications">Email Notifications</label>
            <select id="pref-notifications">
              <option value="all">All Notifications</option>
              <option value="important">Important Only</option>
              <option value="none">None</option>
            </select>
          </div>
          <div class="profile-form-group">
            <label for="pref-currency">Preferred Currency</label>
            <select id="pref-currency">
              <option value="INR">INR - Indian Rupee</option>
              <option value="USD">USD - US Dollar</option>
              <option value="EUR">EUR - Euro</option>
            </select>
          </div>
          <button type="submit" class="profile-button">Save Preferences</button>
        </form>
      </div>

      <div id="booking-history" class="profile-content">
  <h2>Booking History</h2>
  <p>यहाँ आपकी हाल की बुकिंग और टिकट हैं।</p>
  
  <div class="history-item">
    <div class="history-icon">
      <i class="fa-solid fa-person-hiking"></i>
    </div>
    <div class="history-details">
      <span class="history-title">The Golden Triangle (Package)</span>
      <span class="history-date">Date: 2025-11-05</span>
      <span class="history-pnr">PNR: TY-589986</span>
    </div>
    <div class="history-price-status">
      <span class="history-price">₹22,500</span>
      <span class="history-status confirmed">Confirmed</span>
    </div>
    <a href="#" class="history-action" onclick="alert('Viewing Ticket PNR: TY-589986')">View Ticket</a>
  </div>
  
  <div class="history-item">
    <div class="history-icon">
      <i class="fa-solid fa-hotel"></i>
    </div>
    <div class="history-details">
      <span class="history-title">Grand Hyatt (Hotel)</span>
      <span class="history-date">Date: 2025-10-15</span>
      <span class="history-pnr">PNR: HT-129458</span>
    </div>
    <div class="history-price-status">
      <span class="history-price">₹4,500</span>
      <span class="history-status confirmed">Confirmed</span>
    </div>
    <a href="#" class="history-action" onclick="alert('Viewing Booking HT-129458')">View Details</a>
  </div>
  
  <div class="history-item">
    <div class="history-icon">
      <i class="fa-solid fa-bus"></i>
    </div>
    <div class="history-details">
      <span class="history-title">Ahmedabad to Mumbai (Bus)</span>
      <span class="history-date">Date: 2025-09-20</span>
      <span class="history-pnr">PNR: BUS-750123</span>
    </div>
    <div class="history-price-status">
      <span class="history-price">₹800</span>
      <span class="history-status cancelled">Cancelled</span>
    </div>
    <a href="#" class="history-action cancelled">View Details</a>
  </div>
  
</div>
    </div>
  </div>
</div>
 
  <!-- Trip Calculator Section -->
   <div id="trip-calculator" class="section-content">
  <style>
    .calc-container {
      max-width: 1000px;
      margin: 30px auto;
      display: grid;
      grid-template-columns: 1fr 1fr; /* 50/50 लेआउट */
      gap: 30px;
      background: #f4f7f9;
      padding: 0;
    }
    .calc-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.05);
      padding: 30px;
    }
    .calc-container h2 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #1a202c;
      margin-top: 0;
      margin-bottom: 25px;
    }
    
    /* --- 1. Form Styles (Left Side) --- */
    .calc-form-group {
      margin-bottom: 20px;
    }
    .calc-form-group label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .input-with-icon {
      position: relative;
    }
    .input-with-icon i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    /* 🔻🔻 इस नए CSS से बदलें 🔻🔻 */
.calc-form-group input,
.calc-form-group select { /* ◀️ 'select' को यहाँ जोड़ा गया */
  padding: 12px 12px 12px 40px; /* आइकन के लिए जगह */
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 1rem;
  width: 100%;
  box-sizing: border-box; 
  margin: 0;

  /* <select> के डिफ़ॉल्ट तीर को छिपाने और अपना जोड़ने के लिए */
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: #fff;
  background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E');
  background-repeat: no-repeat;
  background-position: right 10px center;
}

/* <input> को तीर (arrow) की ज़रूरत नहीं है */
.calc-form-group input {
    background-image: none;
}
/* 🔺🔺 यहाँ तक 🔺🔺 */
    /* Travel Style (Radio Buttons) */
    .style-selector {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 10px;
    }
    .style-selector input[type="radio"] {
      display: none; /* असली रेडियो बटन छिपा दें */
    }
    .style-selector label {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 15px;
      border: 2px solid #ddd;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.2s ease;
      font-weight: 600;
    }
    .style-selector label i {
      font-size: 1.5rem;
      margin-bottom: 8px;
      color: #555;
    }
    .style-selector input[type="radio"]:checked + label {
      background: #e0f0ff;
      border-color: #0066cc;
      color: #0066cc;
    }
    .style-selector input[type="radio"]:checked + label i {
      color: #0066cc;
    }

    .calc-button {
      width: 100%;
      padding: 15px;
      font-size: 1.1rem;
      font-weight: 700;
      color: #111;
      background: #ffcc00; /* पीला बटन */
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
    }

    /* --- 2. Result Styles (Right Side) --- */
    #calc-placeholder {
      text-align: center;
      padding: 50px 20px;
      color: #777;
    }
    #calc-placeholder i {
      font-size: 4rem;
      color: #ddd;
      margin-bottom: 20px;
    }
    
    #calc-results-content {
      display: none; /* डिफ़ॉल्ट रूप से छिपा हुआ */
    }
    #calc-results-content h2 {
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
    }
    #result-dest {
      color: #0066cc;
    }
    .result-item {
      display: flex;
      justify-content: space-between;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    .result-item .label {
      font-weight: 600;
      color: #555;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .result-item .value {
      font-size: 1.1rem;
      font-weight: 700;
      color: #333;
    }
    .result-total {
      margin-top: 20px;
      padding-top: 20px;
      border-top: 2px solid #1a202c;
    }
    .result-total .total-line {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 10px;
    }
    .result-total .label {
      font-size: 1.2rem;
      font-weight: 600;
    }
    .result-total .value {
      font-size: 1.8rem;
      font-weight: 900;
      color: #0066cc;
    }
    .result-total .per-person {
      font-size: 0.9rem;
      color: #777;
      text-align: right;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .calc-container {
        grid-template-columns: 1fr; /* मोबाइल पर स्टैक */
      }
    }
  </style>

  <div class="calc-container">
    
    <div class="calc-card">
      <h2>Trip Budget Estimator</h2>
      <form id="trip-estimator-form" onsubmit="return false;">
        
        <div class="calc-form-group">
  <label for="calc-dest">Destination</label>
  <div class="input-with-icon">
    <i class="fa-solid fa-map-marker-alt"></i>
    <select id="calc-dest" required>
      <option value="">-- Select a Destination --</option>
      <option value="Mumbai">Mumbai</option>
      <option value="Delhi">Delhi</option>
      <option value="Ahmedabad">Ahmedabad</option>
      <option value="Shimla">Shimla</option>
      <option value="Manali">Manali</option>
      <option value="Ooty">Ooty</option>
      <option value="Goa">Goa</option>
      <option value="Kerala">Kerala</option>
      <option value="Varanasi">Varanasi</option>
      <option value="Amritsar">Amritsar</option>
      <option value="Jaipur">Jaipur</option>
      <option value="Kolkata">Kolkata</option>
      <option value="Bangalore">Bangalore</option>
    </select>
  </div>
</div>
        
        <div class="calc-form-group">
          <label for="calc-days">Number of Days</label>
          <div class="input-with-icon">
            <i class="fa-solid fa-calendar-days"></i>
            <input type="number" id="calc-days" value="3" min="1" required>
          </div>
        </div>
        
        <div class="calc-form-group">
          <label for="calc-people">Number of People</label>
          <div class="input-with-icon">
            <i class="fa-solid fa-users"></i>
            <input type="number" id="calc-people" value="2" min="1" required>
          </div>
        </div>
        
        <div class="calc-form-group">
          <label>Travel Style</label>
          <div class="style-selector">
            <input type="radio" id="style-budget" name="travel_style" value="budget">
            <label for="style-budget">
              <i class="fa-solid fa-seedling"></i>
              Budget
            </label>
            
            <input type="radio" id="style-standard" name="travel_style" value="standard" checked>
            <label for="style-standard">
              <i class="fa-solid fa-person-walking"></i>
              Standard
            </label>
            
            <input type="radio" id="style-luxury" name="travel_style" value="luxury">
            <label for="style-luxury">
              <i class="fa-solid fa-gem"></i>
              Luxury
            </label>
          </div>
        </div>
        
        <button type="button" class="calc-button" onclick="calculateTrip()">Calculate Estimate</button>
      </form>
    </div>
    
    <div class="calc-card">
      
      <div id="calc-placeholder">
        <i class="fa-solid fa-calculator"></i>
        <h2>Your Estimate Appears Here</h2>
        <p>Fill out the details on the left to get an estimated budget for your trip.</p>
      </div>
      
      <div id="calc-results-content">
        <h2>Estimate for <span id="result-dest">...</span></h2>
        
        <div class="result-item">
          <span class="label"><i class="fa-solid fa-hotel"></i> Hotel</span>
          <span class="value" id="result-hotel">₹ 0</span>
        </div>
        <div class="result-item">
          <span class="label"><i class="fa-solid fa-utensils"></i> Food</span>
          <span class="value" id="result-food">₹ 0</span>
        </div>
        <div class="result-item">
          <span class="label"><i class="fa-solid fa-ticket"></i> Activities</span>
          <span class="value" id="result-activities">₹ 0</span>
        </div>
        <div class="result-item">
          <span class="label"><i class="fa-solid fa-plane"></i> Travel (Est.)</span>
          <span class="value" id="result-travel">₹ 0</span>
        </div>
        
        <div class="result-total">
          <p class="per-person">Approx. <span id="result-per-person">₹0</span> / person</p>
          <div class="total-line">
            <span class="label">Grand Total</span>
            <span class="value" id="result-total">₹ 0</span>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</div>
 
  <!-- Destinations Section -->
   <div id="destinations" class="section-content">

  <style>
    .dest-container {
      max-width: 1200px;
      margin: auto;
      text-align: center;
      padding: 40px 20px;
      font-family: Inter, sans-serif;
      background: #f8f9fa; /* हल्का ग्रे बैकग्राउंड */
    }
    .dest-title {
      font-size: 34px;
      font-weight: 900;
      color: #0f172a;
      margin-bottom: 10px;
    }
    .dest-sub {
      color: #64748b;
      margin-bottom: 35px;
      font-size: 1.1rem;
    }

    /* Tabs */
    .dest-tabs { 
      display: flex; 
      gap: 12px; 
      margin-bottom: 30px; 
      justify-content: center; 
      flex-wrap: wrap;
    }
    .dest-tab { 
      background: #fff; 
      border: 1px solid #ddd; 
      padding: 10px 22px; 
      border-radius: 50px; 
      cursor: pointer; 
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .dest-tab:hover {
      background: #e9ecef;
      border-color: #ccc;
    }
    .dest-tab.active { 
      background: #0066cc; 
      color: white; 
      border-color: #0066cc; 
      box-shadow: 0 4px 10px rgba(0,102,204,0.3);
    }
    
    /* Panel Grid */
    .dest-grid-panel { 
      display: none; /* डिफ़ॉल्ट रूप से सभी पैनल छिपाएँ */
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
    }
    .dest-grid-panel.active { 
      display: grid; /* सिर्फ एक्टिव पैनल दिखाएँ */
      animation: fadeIn 0.5s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Card (Improved) */
    .dest-card {
      position: relative;
      overflow: hidden;
      border-radius: 18px;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08);
      transition: transform .35s ease, box-shadow .35s ease;
    }
    .dest-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.12);
    }
    .dest-card img {
      width: 100%;
      height: 360px; /* थोड़ी लंबी इमेज */
      object-fit: cover;
      transition: .4s ease;
    }
    .dest-card:hover img {
      transform: scale(1.05); /* ज़ूम इफ़ेक्ट */
    }
    .dest-info {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 20px;
      color: #fff;
      text-align: left;
      z-index: 2;
      /* स्मूथ ग्रेडिएंट */
      background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.8) 100%);
    }
    .dest-info h3 {
      font-size: 24px;
      margin: 0;
      font-weight: 800;
      text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
    }
    .dest-info p {
      font-size: 15px;
      margin-top: 3px;
      opacity: .9;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.4);
    }
    .dest-info button {
      margin-top: 12px;
      background: #fff;
      color: #111;
      padding: 9px 18px;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    .dest-info button:hover {
      background: #0066cc;
      color: #fff;
    }
    /* ==================================
   CSS FOR DESTINATION UPGRADE
   ( इसे अपने मुख्य <style> टैग में जोड़ें )
   ================================== */

#destinations .dest-container {
  max-width: 1200px;
  margin: auto; /* यह div को केंद्र में रखेगा */
  text-align: center;
  padding: 40px 20px;
  font-family: Inter, sans-serif;
  
  /* === यहाँ बदलाव है === */
  background: #f4f7f9; /* एक बहुत हल्का, शानदार ग्रे-ब्लू बैकग्राउंड */
  border-radius: 16px; /* कोनों को थोड़ा गोल करें */
  box-shadow: 0 8px 30px rgba(0,0,0,0.05); /* हल्का शैडो दें */
  margin-top: 30px; /* ऊपर से थोड़ी जगह */
  margin-bottom: 30px; /* नीचे से थोड़ी जगह */
  /* ================== */
}

/* 2. नए Modal (Popup) के लिए CSS */
.dest-modal {
  display: none; /* डिफ़ॉल्ट रूप से छिपा हुआ */
  position: fixed; /* स्क्रीन पर फिक्स */
  z-index: 2000; /* सबसे ऊपर दिखेगा */
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow-y: auto; /* ज़्यादा कंटेंट होने पर स्क्रॉल */
  background-color: rgba(0,0,0,0.6); /* काला बैकग्राउंड (हल्का) */
  align-items: center;
  justify-content: center;
  animation: fadeInModal 0.3s ease;
}
@keyframes fadeInModal {
  from { opacity: 0; }
  to { opacity: 1; }
}

.dest-modal-content {
  position: relative;
  background-color: #fefefe;
  margin: auto;
  border-radius: 12px;
  width: 90%;
  max-width: 800px; /* modal की चौड़ाई */
  box-shadow: 0 5px 20px rgba(0,0,0,0.2);
  overflow: hidden; /* ताकि इमेज कोनों से बाहर न निकले */
  animation: slideInModal 0.4s ease-out;
}
@keyframes slideInModal {
  from { transform: translateY(-50px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.dest-modal-close {
  color: #333;
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 28px;
  font-weight: bold;
  padding: 0px 8px;
  cursor: pointer;
  background: rgba(255,255,255,0.8);
  border-radius: 50%;
  line-height: 1;
  z-index: 10;
}
.dest-modal-close:hover { color: #000; }

.dest-modal-content img#modal-image {
  width: 100%;
  height: 300px; /* modal इमेज की ऊंचाई */
  object-fit: cover;
}

.dest-modal-body {
  padding: 20px 30px 30px 30px;
}
.dest-modal-body h2 {
  margin-top: 0;
  color: #0066cc; /* आपके थीम का नीला रंग */
  font-size: 2rem;
}
.dest-modal-body h3 {
  border-bottom: 2px solid #eee;
  padding-bottom: 5px;
  margin-top: 25px;
  color: #333;
}
.dest-modal-body ul {
  list-style-type: '✔'; /* चेक-मार्क बुलेट्स */
  padding-left: 20px;
  display: grid;
  grid-template-columns: 1fr 1fr; /* दो कॉलम लेआउट */
  gap: 10px;
}
.dest-modal-body li {
  margin-bottom: 5px;
  padding-left: 10px;
  color: #555;
}

.modal-book-button {
  background-color: #0066cc;
  color: white;
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 100%;
  margin-top: 20px;
}
.modal-book-button:hover {
  background-color: #004999;
  transform: scale(1.02);
}
  </style>

  <div class="dest-container">
    <h2 class="dest-title">🌍 Explore Destinations</h2>
    <p class="dest-sub">Find your next adventure, sorted by what you love.</p>

    <div class="dest-tabs">
      <div class="dest-tab active" onclick="showDestPanel('top', this)">Top Picks</div>
      <div class="dest-tab" onclick="showDestPanel('hills', this)">Hill Stations</div>
      <div class="dest-tab" onclick="showDestPanel('beach', this)">Beaches</div>
      <div class="dest-tab" onclick="showDestPanel('religious', this)">Religious</div>
    </div>

    <div id="dest-panel-top" class="dest-grid-panel active">
      <div class="dest-card">
        <img src="https://images.unsplash.com/photo-1562979314-bee7453e911c?w=600&h=400&fit=crop" alt="Mumbai">
        <div class="dest-info">
          <h3>Mumbai</h3>
          <p>The City of Dreams</p>
          <button onclick="openModal('mumbai')">Explore</button>
        </div>
      </div>
      <div class="dest-card">
        <img src="https://images.unsplash.com/photo-1587474260584-136574528ed5?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8ZGVsaGl8ZW58MHx8MHx8fDA%3D&fm=jpg&q=60&w=3000" alt="Delhi">
        <div class="dest-info">
          <h3>Delhi</h3>
          <p>The Heart of India</p>
          <button onclick="openModal('delhi')">Explore</button>
        </div>
      </div>
      <div class="dest-card">
        <img src="https://www.holidify.com/images/bgImages/AHMEDABAD.jpg" alt="Ahmedabad">
        <div class="dest-info">
          <h3>Ahmedabad</h3>
          <p>Heritage & Modernity</p>
          <button onclick="openModal('Ahmedabad')">Explore</button>
        </div>
      </div>
    </div>

    <div id="dest-panel-hills" class="dest-grid-panel">
      <div class="dest-card">
        <img src="https://www.holidify.com/images/bgImages/KUFRI.jpg" alt="Shimla">
        <div class="dest-info">
          <h3>Shimla</h3>
          <p>Queen of the Hills</p>
          <button onclick="openModal('Shimla')">Explore</button>
        </div>
      </div>
      <div class="dest-card">
        <img src="https://www.holidify.com/images/cmsuploads/compressed/Snowy-mountains-of-Manali-87231-pixahive_20210426123023.jpg" alt="Manali">
        <div class="dest-info">
          <h3>Manali</h3>
          <p>Valley of the Gods</p>
          <button onclick="openModal('Manali')">Explore</button>
        </div>
      </div>
       <div class="dest-card">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcReeiFXYk9MenWnhMYj3rsbmJ_fNRtl6ggo2A&s" alt="Ooty">
        <div class="dest-info">
          <h3>Ooty</h3>
          <p>Lush Green Valleys</p>
          <button onclick="openModal('Ooty')">Explore</button>
        </div>
      </div>
    </div>

    <div id="dest-panel-beach" class="dest-grid-panel">
      <div class="dest-card">
        <img src="https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?w=600&h=400&fit=crop" alt="Goa">
        <div class="dest-info">
          <h3>Goa</h3>
          <p>Sun, Sand and Sea</p>
          <button onclick="openModal('Goa')">Explore</button>
        </div>
      </div>
      <div class="dest-card">
        <img src="https://media.easemytrip.com/media/Blog/India/638230439303406485/638230439303406485c8ffC9.png" alt="Kerala">
        <div class="dest-info">
          <h3>Kerala</h3>
          <p>God's Own Country</p>
          <button onclick="openModal('Kerala')">Explore</button>
        </div>
      </div>
    </div>

    <div id="dest-panel-religious" class="dest-grid-panel">
      <div class="dest-card">
        <img src="https://s7ap1.scene7.com/is/image/incredibleindia/manikarnika-ghat-city-hero?qlt=82&ts=1727959374496" alt="Varanasi">
        <div class="dest-info">
          <h3>Varanasi</h3>
          <p>The Spiritual Capital</p>
          <button onclick="openModal('Varanasi')">Explore</button>
        </div>
      </div>
      <div class="dest-card">
        <img src="https://www.shutterstock.com/image-photo/sikh-gurdwara-golden-temple-harmandir-600nw-2395078835.jpg" alt="Amritsar">
        <div class="dest-info">
          <h3>Amritsar</h3>
          <p>The Golden City</p>
          <button onclick="openModal('Amritsar')">Explore</button>
        </div>
      </div>
    </div>

  </div> </div>
<script>
  // यह फंक्शन टैब बदलने के लिए है
  function showDestPanel(panelId, element) {
      // सभी टैब से 'active' हटाएँ
      document.querySelectorAll('.dest-tab').forEach(tab => tab.classList.remove('active'));
      // सभी पैनल को छिपाएँ
      document.querySelectorAll('.dest-grid-panel').forEach(panel => panel.classList.remove('active'));

      // क्लिक किए गए टैब और पैनल को दिखाएँ
      element.classList.add('active');
      document.getElementById('dest-panel-' + panelId).classList.add('active');
  }

  // यह "स्मार्ट" Explore बटन है
  function openDestination(city) {
      // 1. मुख्य "Booking" टैब पर स्विच करें
      try {
          showSection('booking');
      } catch (e) {
          console.error("showSection function not found", e);
      }

      // 2. बुकिंग के अंदर "Bus" टैब को एक्टिवेट करें
      try {
          // हम सीधे 'bus' ID वाले फॉर्म को 'active' क्लास दे सकते हैं
          document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
          document.getElementById('bus').classList.add('active');

          // और बस वाले बटन को 'active' दिखा सकते हैं
          document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
          document.querySelector('.tab-button[onclick*="showForm(\'bus\'"]').classList.add('active');

      } catch (e) {
          console.error("Could not switch to bus tab", e);
      }

      // 3. बस फॉर्म में "To" (Destination) फील्ड भरें
      const busToField = document.getElementById('bb-to'); // Bus Booking 'To' field
      if (busToField) {
          busToField.value = city;
      }
      
      // 4. (Bonus) होटल फॉर्म में "City" फील्ड भरें
      const hotelCityField = document.getElementById('hy-city'); // Hotel 'City' field
      if (hotelCityField) {
          hotelCityField.value = city;
          // होटल लिस्ट रेंडर करने वाले फंक्शन को कॉल करें
          if (typeof renderList === 'function') {
              renderList();
          }
      }

      // 5. (Bonus) फ्लाइट फॉर्म में "To" फील्ड भरें
      const flightToField = document.getElementById('fx-to'); // Flight 'To' field
      if (flightToField) {
          const cityToIATA = {
              'Mumbai': 'Mumbai (BOM)',
              'Delhi': 'Delhi (DEL)',
              'Ahmedabad': 'Ahmedabad (AMD)',
              'Kolkata': 'Kolkata (CCU)',
              'Shimla': 'Shimla (SLV)',
              'Manali': 'Kullu (KUU)', // Manali ka nearest
              'Goa': 'Goa (GOI)',
              'Varanasi': 'Varanasi (VNS)',
              'Amritsar': 'Amritsar (ATQ)'
          };
          flightToField.value = cityToIATA[city] || city;
      }
  
      // 6. यूज़र को पेज के टॉप पर स्क्रॉल करें
      window.scrollTo({ top: 0, behavior: 'smooth' });
  }

</script>

 
  <!-- ✅ Contact Section -->
  <div id="contact" class="section-content">
  <style>
    /* --- कॉन्टैक्ट पेज के लिए नया CSS --- */
    .contact-page-container {
      max-width: 1200px;
      margin: 30px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.05);
      overflow: hidden; /* मैप के कोनों को गोल करने के लिए */
    }
    .contact-title-header {
      padding: 40px;
      text-align: center;
      background: #f4f7f9;
    }
    .contact-title-header h2 {
      font-size: 2.8rem;
      font-weight: 900;
      color: #1a202c;
      margin: 0 0 10px 0;
    }
    .contact-title-header p {
      font-size: 1.1rem;
      color: #64748b;
      margin: 0;
    }

    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 1.5fr; /* 40% इन्फो, 60% फॉर्म */
      gap: 0;
    }
    
    /* --- 1. Left Side (Info) --- */
    .contact-info-left {
      padding: 40px;
      background: #0066cc; /* आपका मेन थीम कलर */
      color: #fff;
    }
    .contact-info-left h3 {
      font-size: 1.8rem;
      margin-top: 0;
      color: #fff;
      margin-bottom: 20px;
    }
    .contact-info-left p {
      line-height: 1.7;
      opacity: 0.9;
      margin-bottom: 30px;
    }
    .info-item {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
    }
    .info-item i {
      font-size: 1.5rem;
      width: 30px;
      text-align: center;
    }
    .info-item span {
      font-size: 1.05rem;
      font-weight: 500;
    }
    
    .social-links {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,0.3);
    }
    .social-links a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: rgba(255,255,255,0.1);
      color: #fff;
      border-radius: 50%;
      text-decoration: none;
      margin-right: 10px;
      transition: all 0.3s ease;
    }
    .social-links a:hover {
      background: #fff;
      color: #0066cc;
    }

    /* --- 2. Right Side (Form) --- */
    .contact-form-right {
      padding: 40px;
    }
    .contact-form-right h3 {
      font-size: 1.8rem;
      margin-top: 0;
      color: #333;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      box-sizing: border-box; /* ज़रूरी */
      margin: 0; /* ज़रूरी */
    }
    .form-group textarea {
      resize: vertical;
      min-height: 120px;
    }
    .contact-button {
      width: 100%;
      padding: 15px;
      font-size: 1.1rem;
      font-weight: 700;
      color: #111;
      background: #ffcc00; /* पीला बटन */
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
    }
    
    /* --- 3. Map --- */
    .contact-map {
      width: 100%;
      height: 350px;
    }
    .contact-map iframe {
      width: 100%;
      height: 100%;
      border: none;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
      .contact-grid {
        grid-template-columns: 1fr; /* मोबाइल पर स्टैक */
      }
      .contact-info-left {
        border-bottom: 4px solid #004999;
      }
    }
    @media (max-width: 600px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="contact-page-container">
    
    <div class="contact-title-header">
      <h2>Get In Touch</h2>
      <p>We'd love to hear from you! Whether you have a question about features, pricing, or anything else, our team is ready to answer all your questions.</p>
    </div>
    
    <div class="contact-grid">
      
      <div class="contact-info-left">
        <h3>Contact Information</h3>
        <p>Fill up the form and our Team will get back to you within 24 hours.</p>
        
        <div class="info-item">
          <i class="fa-solid fa-phone"></i>
          <span>+91 98765 43210</span>
        </div>
        <div class="info-item">
          <i class="fa-solid fa-envelope"></i>
          <span>support@thrillyari.com</span>
        </div>
        <div class="info-item">
          <i class="fa-solid fa-map-marker-alt"></i>
          <span>123 Travel Street, Ahmedabad, Gujarat</span>
        </div>
        
        <div class="social-links">
          <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#"><i class="fa-brands fa-twitter"></i></a>
          <a href="#"><i class="fa-brands fa-instagram"></i></a>
          <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
      </div>
      
      <div class="contact-form-right">
        <h3>Send us a Message</h3>
        <form action="contact.php" method="POST">
          <div class="form-row">
            <div class="form-group">
              <label for="contact-name">Your Name*</label>
              <input type="text" id="contact-name" name="name" required>
            </div>
            <div class="form-group">
              <label for="contact-email">Your Email*</label>
              <input type="email" id="contact-email" name="email" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="contact-phone">Your Number</label>
              <input type="tel" id="contact-phone" name="phone">
            </div>
            <div class="form-group">
              <label for="contact-subject">Subject</label>
              <select id="contact-subject" name="subject">
                <option value="General Inquiry">General Inquiry</option>
                <option value="Booking Support">Booking Support</option>
                <option value="Feedback">Feedback</option>
                <option value="Partnership">Partnership</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label for="contact-message">Your Message*</label>
            <textarea id="contact-message" name="message" required></textarea>
          </div>
          
          <button type="submit" class="contact-button">Send Message</button>
        </form>
      </div>
      
    </div>
    
    <div class="contact-map">
      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d117502.82223847372!2d72.48860265215752!3d23.02700301986422!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395e848aba5bd449%3A0x4fcedd11614f6516!2sAhmedabad%2C%20Gujarat!5e0!3m2!1sen!2sin!4v1678888888888!5m2!1sen!2sin" 
        width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    
  </div>
</div>
 
  <!-- 🌍 Trip Management Section -->
  <div id="trip-section" class="section-content">
    <h2>➕ Add a Trip</h2>
    <form method="POST">
      Destination: <input type="text" name="destination" required>
      Flight Cost: <input type="number" name="flight" required>
      Hotel Cost: <input type="number" name="hotel" required>
      Transport Cost: <input type="number" name="transport" required>
      Duration (days): <input type="number" name="days" required>
      <button type="submit" name="add_trip">Add Trip</button>
    </form>
 
    <h3>🌴 All Trips</h3>
    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr style="background:#caf0f8">
        <th>ID</th><th>User ID</th><th>Destination</th>
        <th>Flight</th><th>Hotel</th><th>Transport</th>
        <th>Days</th><th>Total</th><th>Final (+5%)</th>
      </tr>
      <?php while ($r = $trips_all->fetch_assoc()) { ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= $r['user_id'] ?></td>
          <td><?= $r['destination'] ?></td>
          <td>₹<?= number_format($r['flight_cost'],2) ?></td>
          <td>₹<?= number_format($r['hotel_cost'],2) ?></td>
          <td>₹<?= number_format($r['transport_cost'],2) ?></td>
          <td><?= $r['duration_days'] ?></td>
          <td>₹<?= number_format($r['total_cost'],2) ?></td>
          <td>₹<?= number_format($r['final_cost'],2) ?></td>
        </tr>
      <?php } ?>
    </table>
 
    <h3>💰 Trips with Total Cost &gt; ₹1,00,000</h3>
    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr style="background:#caf0f8">
        <th>User ID</th><th>Destination</th><th>Total Cost</th>
      </tr>
      <?php while ($r = $trips_costly->fetch_assoc()) { ?>
        <tr>
          <td><?= $r['user_id'] ?></td>
          <td><?= $r['destination'] ?></td>
          <td>₹<?= number_format($r['total_cost'],2) ?></td>
        </tr>
      <?php } ?>
    </table>
 
    <h3>🧾 Trips with Final Cost (+5%)</h3>
    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr style="background:#caf0f8"><th>User ID</th><th>Destination</th><th>Final Cost</th></tr>
      <?php while ($r = $trips_final->fetch_assoc()) { ?>
        <tr>
          <td><?= $r['user_id'] ?></td>
          <td><?= $r['destination'] ?></td>
          <td>₹<?= number_format($r['final_cost'],2) ?></td>
        </tr>
      <?php } ?>
    </table>
 
    <h3>⏱️ Trips Longer than 7 Days</h3>
    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr style="background:#caf0f8"><th>User ID</th><th>Destination</th><th>Days</th></tr>
      <?php while ($r = $trips_long->fetch_assoc()) { ?>
        <tr>
          <td><?= $r['user_id'] ?></td>
          <td><?= $r['destination'] ?></td>
          <td><?= $r['duration_days'] ?></td>
        </tr>
      <?php } ?>
    </table>
 
    <h3>🏆 Top 3 Most Expensive Trips</h3>
    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr style="background:#caf0f8"><th>User ID</th><th>Destination</th><th>Total Cost</th></tr>
      <?php while ($r = $trips_top3->fetch_assoc()) { ?>
        <tr>
          <td><?= $r['user_id'] ?></td>
          <td><?= $r['destination'] ?></td>
          <td>₹<?= number_format($r['total_cost'],2) ?></td>
        </tr>
      <?php } ?>
    </table>
  </div>
</div> <!-- /#main-content -->
  <footer>
    <p>© 2025 Thrill Yari. All rights reserved.</p>
  </footer>
 <div id="passenger-details-page" class="section-content" style="background: #f4f7f9; display: none;">
  <style>
    .passenger-container {
      max-width: 800px;
      margin: 30px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    .passenger-header {
      padding: 20px 30px;
      background: #0066cc;
      color: #fff;
    }
    .passenger-header h2 { margin: 0; font-size: 1.8rem; }
    .passenger-header p { margin: 5px 0 0 0; font-size: 1.1rem; opacity: 0.9; }
    
    .passenger-form { padding: 30px; }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .form-full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label {
      font-size: 0.9rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 6px;
    }
    .form-group input, .form-group select {
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
    }
    .passenger-submit-btn {
      width: 100%;
      padding: 15px;
      font-size: 1.2rem;
      font-weight: 700;
      color: #111;
      background: #ffcc00; /* पीला बटन */
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 20px;
    }
    @media (max-width: 600px) {
      .form-grid { grid-template-columns: 1fr; }
    }
  </style>
  
  <div class="passenger-container">
    <div class="passenger-header">
      <h2 id="pkg-title-summary">Passenger Details</h2>
      <p id="pkg-date-summary">Please fill in the details for all travelers.</p>
    </div>
    
    <form id="detailed-passenger-form" class="passenger-form">
      <div class="form-grid">
        <div class="form-group form-full">
          <label for="lead-name">Lead Passenger Full Name*</label>
          <input type="text" id="lead-name" placeholder="As per ID proof" required>
        </div>
        <div class="form-group">
          <label for="lead-email">Email Address*</label>
          <input type="email" id="lead-email" placeholder="Your booking confirmation will be sent here" required>
        </div>
        <div class="form-group">
          <label for="lead-phone">Mobile Number*</label>
          <input type="tel" id="lead-phone" placeholder="10-digit mobile number" required>
        </div>
        <div class="form-group form-full">
          <label for="lead-address">Address</label>
          <input type="text" id="lead-address" placeholder="Street Address, City">
        </div>
        
        <div class="form-group">
          <label for="lead-age">Age*</label>
          <input type="number" id="lead-age" placeholder="e.g., 28" required>
        </div>
        <div class="form-group">
          <label for="lead-gender">Gender*</label>
          <select id="lead-gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label for="lead-id-type">ID Proof Type*</label>
          <select id="lead-id-type" required>
            <option value="">Select ID</option>
            <option value="Aadhaar Card">Aadhaar Card</option>
            <option value="Passport">Passport</option>
            <option value="Voter ID">Voter ID</option>
            <option value="Driving License">Driving License</option>
          </select>
        </div>
        <div class="form-group">
          <label for="lead-id-number">ID Proof Number*</label>
          <input type="text" id="lead-id-number" placeholder="Enter ID number" required>
        </div>
        <div class="form-group form-full">
          <label for="lead-emergency-contact">Emergency Contact Number</label>
          <input type="tel" id="lead-emergency-contact" placeholder="Someone not traveling with you">
        </div>
      </div>
      
      <button type="button" class="passenger-submit-btn" onclick="showPaymentPage()">Proceed to Payment</button>
    </form>
  </div>
</div>

<div id="payment-summary-page" class="section-content" style="background: #f4f7f9; display: none;">
  <style>
    .payment-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      max-width: 1000px;
      margin: 30px auto;
    }
    .payment-options, .payment-summary {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      padding: 30px;
    }
    .payment-options h2 { margin-top: 0; }
    .payment-method {
      display: block;
      width: 100%;
      padding: 20px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 600;
      text-align: left;
      cursor: pointer;
      background: #f9f9f9;
      transition: all 0.2s ease;
      color: #333; /* ◀️◀️ यह लाइन जोड़ें */
    }
    .payment-method:hover {
      border-color: #0066cc;
      background: #f0f7ff;
    }
    .payment-method i {
      margin-right: 15px;
      color: #0066cc;
      width: 20px;
    }
    .payment-summary h3 { margin-top: 0; }
    .summary-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
      font-size: 1.05rem;
    }
    .summary-item .label { color: #555; }
    .summary-item .value { font-weight: 600; }
    .summary-total {
      border-top: 2px solid #ddd;
      padding-top: 15px;
      font-size: 1.5rem;
      font-weight: 700;
    }
  </style>
  
  <div class="payment-container">
    <div class="payment-options">
      <h2>Choose Payment Method</h2>
      <button class="payment-method" onclick="showTicketPage('UPI')">
        <i class="fa-solid fa-qrcode"></i> UPI (GPay, PhonePe, Paytm)
      </button>
      <button class="payment-method" onclick="showTicketPage('Credit/Debit Card')">
        <i class="fa-solid fa-credit-card"></i> Credit / Debit Card
      </button>
      <button class="payment-method" onclick="showTicketPage('Net Banking')">
        <i class="fa-solid fa-building-columns"></i> Net Banking
      </button>
    </div>
    
    <div class="payment-summary">
      <h3>Booking Summary</h3>
      <div class="summary-item">
        <span class="label">Package</span>
        <span class="value" id="summary-pkg-name">...</span>
      </div>
      <div class="summary-item">
        <span class="label">Date</span>
        <span class="value" id="summary-pkg-date">...</span>
      </div>
      <div class="summary-item">
        <span class="label">Passenger</span>
        <span class="value" id="summary-pkg-pax">...</span>
      </div>
      <div class="summary-item summary-total">
        <span class="label">Total Price</span>
        <span class="value" id="summary-pkg-price">...</span>
      </div>
    </div>
  </div>
</div>

<div id="ticket-confirmation-page" class="section-content" style="background: #f4f7f9; display: none;">
  <style>
    @keyframes checkmark {
      0% { stroke-dashoffset: 50; }
      100% { stroke-dashoffset: 0; }
    }
    .ticket-success-icon {
      width: 100px;
      height: 100px;
      margin: 20px auto;
    }
    .ticket-success-icon circle {
      fill: #28a745;
    }
    .ticket-success-icon path {
      stroke: #fff;
      stroke-width: 3;
      stroke-dasharray: 50;
      stroke-dashoffset: 50;
      animation: checkmark 0.5s ease-out 0.3s forwards;
    }
    .ticket-container {
      max-width: 800px;
      margin: 30px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
      border-top: 8px solid #0066cc;
    }
    .ticket-header { text-align: center; padding: 20px 30px; }
    .ticket-header h2 { margin: 0; font-size: 2rem; color: #28a745; }
    .ticket-header p { font-size: 1.1rem; color: #555; }
    
    .ticket-body { padding: 30px; border-top: 2px dashed #ddd; border-bottom: 2px dashed #ddd;}
    .ticket-pnr {
      text-align: center;
      margin-bottom: 20px;
    }
    .ticket-pnr .label { font-size: 1.1rem; color: #555; }
    .ticket-pnr .pnr-code {
      font-size: 1.8rem;
      font-weight: 700;
      color: #111;
      background: #f4f7f9;
      padding: 5px 15px;
      border-radius: 8px;
      border: 1px solid #ddd;
    }
    .ticket-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 25px;
    }
    .ticket-grid h4 {
      margin: 0 0 10px 0;
      color: #0066cc;
      border-bottom: 1px solid #eee;
      padding-bottom: 5px;
    }
    .ticket-grid p { margin: 5px 0; line-height: 1.6; }
    .ticket-grid .label { font-weight: 600; color: #333; }
    
    .ticket-actions {
      padding: 20px;
      display: flex;
      gap: 15px;
      justify-content: center;
    }
    .ticket-btn {
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .ticket-btn.download { background: #0066cc; color: #fff; }
    .ticket-btn.share { background: #25D366; color: #fff; }
    
    /* Print Styles (for Download) */
    @media print {
      body, html {
        background: #fff;
      }
      header, nav, footer, .ticket-actions, .passenger-container, .payment-container,
      .section-content:not(#ticket-confirmation-page) {
        display: none !important;
      }
      #ticket-confirmation-page {
        display: block !important;
        background: #fff;
      }
      .ticket-container {
        margin: 0;
        box-shadow: none;
        border: 2px solid #000;
      }
    }
  </style>
  
  <div class="ticket-container">
    <div class="ticket-header">
      <svg class="ticket-success-icon" viewBox="0 0 52 52">
        <circle cx="26" cy="26" r="25" />
        <path d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
      </svg>
      <h2>Booking Confirmed!</h2>
      <p>Your ticket is ready. Have a wonderful trip!</p>
    </div>
    
    <div class="ticket-body">
      <div class="ticket-pnr">
        <span class="label">Booking ID / PNR: </span>
        <span class="pnr-code" id="ticket-pnr">...</span>
      </div>
      
      <div class="ticket-grid">
        <div>
          <h4>Trip Details</h4>
          <p><span class="label">Package:</span> <span id="ticket-package-name">...</span></p>
          <p><span class="label">Date:</span> <span id="ticket-travel-date">...</span></p>
          <p><span class="label">Total Price:</span> <span id="ticket-total-price">...</span></p>
          <p><span class="label">Payment via:</span> <span id="ticket-payment-method">...</span></p>
        </div>
        <div>
          <h4>Passenger Details</h4>
          <p><span class="label">Name:</span> <span id="ticket-lead-name">...</span></p>
          <p><span class="label">Phone:</span> <span id="ticket-lead-phone">...</span></p>
          <p><span class="label">Email:</span> <span id="ticket-lead-email">...</span></p>
          <p><span class="label">ID Proof:</span> <span id="ticket-id-proof">...</span></p>
        </div>
      </div>
    </div>
    
    <div class="ticket-actions">
      <button class="ticket-btn download" onclick="downloadTicket()">
        <i class="fa-solid fa-download"></i> Download / Print Ticket
      </button>
      <a href="#" id="whatsapp-share-btn" class="ticket-btn share" target="_blank">
        <i class="fa-brands fa-whatsapp"></i> Share on WhatsApp
      </a>
      <button class="ticket-btn done" onclick="goHome()">
        <i class="fa-solid fa-house"></i> Done (Go Home)
      </button>
    </div>
  </div>
</div>
 <div id="destination-modal" class="dest-modal">
  <div class="dest-modal-content">
    <span class="dest-modal-close" onclick="closeModal()">&times;</span>
    <img src="https://images.unsplash.com/photo-1562979314-bee7453e911c?w=800&h=500&fit=crop" id="modal-image" alt="Destination Image">
    
    <div class="dest-modal-body">
      <h2 id="modal-title">City Name</h2>
      <p id="modal-desc">Description will load here.</p>
      
      <h3>Top Attractions</h3>
      <ul id="modal-attractions">
        </ul>
      
      <h3>Best Time to Visit</h3>
      <p><strong><span id="modal-best-time"></span></strong></p>
      
      <button id="modal-book-btn" class="modal-book-button" onclick="">Book Now</button>
    </div>
  </div>
</div>
<script>
// ------------------ HERO ROTATION ------------------
const hero = document.querySelector('.hero');
if (hero) {
  const images = [
      'https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=1500&q=80',
      'https://images.unsplash.com/photo-1582719478123-8b5465d9482e?auto=format&fit=crop&w=1500&q=80',
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1500&q=80',
      'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1500&q=80',
      'https://images.unsplash.com/photo-1601758123927-950b6b8f4fc0?auto=format&fit=crop&w=1500&q=80'
  ];
  let current = 0;
  function changeImage() {
      current = (current + 1) % images.length;
      hero.style.backgroundImage = `url('${images[current]}')`;
  }
  hero.style.backgroundImage = `url('${images[0]}')`;
  setInterval(changeImage, 3000);
}
 
// ------------------ AUTH LOGIC ------------------
// ------------------ (बदला हुआ) AUTH LOGIC ------------------
window.onload = () => {
  const authModal = document.getElementById('auth-modal');
  const mainContent = document.getElementById('main-content');
  
  <?php if (!isset($_SESSION['user_id'])): ?>
    // (फिक्स) अगर यूज़र लॉग-इन नहीं है, तो पॉप-अप दिखाएँ
    if (authModal) authModal.style.display = 'flex'; 
    if (mainContent) mainContent.classList.add('hidden');
  <?php else: ?>
    // (फिक्स) अगर यूज़र लॉग-इन है, तो पॉप-अप छिपाएँ
    if (authModal) authModal.style.display = 'none';
    if (mainContent) mainContent.classList.remove('hidden');
  <?php endif; ?>
};

function switchAuth(type) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
  if(type === 'login'){
    document.querySelector('.auth-tab:nth-child(1)').classList.add('active');
    document.getElementById('login-form').classList.add('active');
  } else {
    document.querySelector('.auth-tab:nth-child(2)').classList.add('active');
    document.getElementById('signup-form').classList.add('active');
  }
}
// ------------------ NAVIGATION SECTION SWITCH ------------------
function showForm(id, event){
  document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
 
  const target = document.getElementById(id);
  if (target) target.classList.add('active');
 
  document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
  if(event && event.target) event.target.classList.add('active');
}
document.addEventListener("DOMContentLoaded", () => {
  showSection('home'); // default visible section → Home/Booking jo bhi tum chaho
});
 
/* ====================================================== */
/* ✅ START: NEW BOOKING FLOW JAVASCRIPT
/* (इसे अपने मुख्य <script> टैग में पेस्ट करें)
/* ====================================================== */

// यह वैरिएबल आपकी बुकिंग की जानकारी को पेज दर पेज ले जाएगा
let currentBooking = {};

/**
 * 1. पैकेज फॉर्म से डेटा लेता है और पैसेंजर पेज दिखाता है
 */
function handlePackageBooking(button, packageTitle, packagePrice) {
    // फॉर्म को ढूँढें
    const form = button.closest('.package-booking-form');
    const name = form.querySelector('.pkg-name').value;
    const email = form.querySelector('.pkg-email').value;
    const date = form.querySelector('.pkg-date').value;

    // बेसिक वैलिडेशन
    if (!name || !email || !date) {
        alert('Please fill in your name, email, and travel date to continue.');
        return;
    }

    // ग्लोबल वैरिएबल में डेटा सेव करें
    currentBooking = {
        packageName: packageTitle,
        price: packagePrice,
        travelDate: date,
        leadName: name, // पैसेंजर फॉर्म को प्री-फिल करने के लिए
        leadEmail: email // पैसेंजर फॉर्म को प्री-फिल करने के लिए
    };

    // पैसेंजर पेज पर जानकारी दिखाएँ (प्री-फिल)
    document.getElementById('pkg-title-summary').textContent = packageTitle;
    document.getElementById('pkg-date-summary').textContent = `Travel Date: ${date}`;
    document.getElementById('lead-name').value = name;
    document.getElementById('lead-email').value = email;

    // पैसेंजर पेज दिखाएँ
    showSection('passenger-details-page');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * 2. पैसेंजर फॉर्म से डेटा लेता है और पेमेंट पेज दिखाता है
 */
function showPaymentPage() {
    // वैलिडेशन (यहाँ आप और भी वैलिडेशन जोड़ सकते हैं)
    const passengerName = document.getElementById('lead-name').value;
    const passengerPhone = document.getElementById('lead-phone').value;
    const idNumber = document.getElementById('lead-id-number').value;

    if (!passengerName || !passengerPhone || !idNumber) {
        alert('Please fill in all required passenger details.');
        return;
    }

    // ग्लोबल वैरिएबल में और डेटा जोड़ें
    currentBooking.leadName = passengerName;
    currentBooking.leadPhone = passengerPhone;
    currentBooking.leadEmail = document.getElementById('lead-email').value;
    currentBooking.leadID = `${document.getElementById('lead-id-type').value} - ${idNumber}`;

    // पेमेंट पेज पर समरी भरें
    document.getElementById('summary-pkg-name').textContent = currentBooking.packageName;
    document.getElementById('summary-pkg-date').textContent = currentBooking.travelDate;
    document.getElementById('summary-pkg-pax').textContent = currentBooking.leadName;
    document.getElementById('summary-pkg-price').textContent = currentBooking.price;

    // पेमेंट पेज दिखाएँ
    showSection('payment-summary-page');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * 3. पेमेंट को "सिम्युलेट" करता है और फाइनल टिकट दिखाता है
 */
function showTicketPage(paymentMethod) {
    // पेमेंट मेथड को सेव करें
    currentBooking.paymentMethod = paymentMethod;
    
    // एक रैंडम बुकिंग ID बनाएँ
    currentBooking.pnr = "TY-" + Math.floor(100000 + Math.random() * 900000);

    // --- असली प्रोजेक्ट में: इस जगह पर आप डेटाबेस में सब कुछ सेव करेंगे ---
    console.log("Booking to save:", currentBooking);
    
    // टिकट पेज पर फाइनल जानकारी भरें
    document.getElementById('ticket-pnr').textContent = currentBooking.pnr;
    document.getElementById('ticket-package-name').textContent = currentBooking.packageName;
    document.getElementById('ticket-travel-date').textContent = currentBooking.travelDate;
    document.getElementById('ticket-total-price').textContent = currentBooking.price;
    document.getElementById('ticket-payment-method').textContent = currentBooking.paymentMethod;
    
    document.getElementById('ticket-lead-name').textContent = currentBooking.leadName;
    document.getElementById('ticket-lead-phone').textContent = currentBooking.leadPhone;
    document.getElementById('ticket-lead-email').textContent = currentBooking.leadEmail;
    document.getElementById('ticket-id-proof').textContent = currentBooking.leadID;

    // WhatsApp शेयर लिंक बनाएँ
    const ticketText = `*Booking Confirmed!*
*Package:* ${currentBooking.packageName}
*PNR:* ${currentBooking.pnr}
*Name:* ${currentBooking.leadName}
*Date:* ${currentBooking.travelDate}
*Price:* ${currentBooking.price}
Thanks for booking with Thrill Yari!`;
    
    const whatsappLink = `https://api.whatsapp.com/send?text=${encodeURIComponent(ticketText)}`;
    document.getElementById('whatsapp-share-btn').href = whatsappLink;

    // टिकट पेज दिखाएँ
    showSection('ticket-confirmation-page');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * 4. टिकट को प्रिंट/PDF के रूप में डाउनलोड करता है
 */
function downloadTicket() {
    // यह ब्राउज़र का प्रिंट डायलॉग खोलेगा
    window.print();
} 
 
// ------------------ HOTEL DROPDOWN ------------------
const hotels = {
  "Mumbai": ["The Taj Mahal Palace", "Trident Hotel", "Sea Princess Hotel"],
  "Delhi": ["The Leela Palace", "ITC Maurya", "The Imperial Hotel"],
  "Ahmedabad": ["Hyatt Regency", "The House of MG", "Novotel Ahmedabad"],
  "Kolkata": ["The Oberoi Grand", "ITC Sonar", "The Park Hotel"],
  "Punjab": ["The Lalit", "JW Marriott Amritsar", "Radisson Blu"]
};
 
function updateHotels(){
  const city = document.getElementById('hotel-city').value;
  const hotelSelect = document.getElementById('hotel-name');
  hotelSelect.innerHTML = '<option value="">-- Select a Hotel --</option>';
  if(hotels[city]){
    hotels[city].forEach(hotel =>{
      const opt = document.createElement('option');
      opt.value = hotel;
      opt.textContent = hotel;
      hotelSelect.appendChild(opt);
    });
  }
}
function openHotelReview(hotelName, roomNumber){
  const checkIn  = document.getElementById('hy-in').value;
  const checkOut = document.getElementById('hy-out').value;
  const nights = nightsBetween(checkIn, checkOut);
  const hotel = HY_HOTELS.find(h => h.name === hotelName);
  const base = hotel.price * nights;
  const tax = Math.round(base * 0.05);
  const total = base + tax;
 
  document.body.innerHTML = `
  <div style="max-width:460px;margin:40px auto;background:#fff;border-radius:16px;padding:28px;font-family:Inter;box-shadow:0 4px 18px rgba(0,0,0,0.12)">
    
    <h2 style="text-align:center;color:#0d6efd;margin-bottom:15px;">Guest Information</h2>
 
    <label>Full Name*</label>
    <input id="guest-name" required placeholder="Your Full Name">
 
    <label>Mobile Number*</label>
    <input id="guest-phone" required placeholder="10-digit number">
 
    <label>Age*</label>
    <input id="guest-age" type="number" required min="1" max="120">
 
    <label>Email*</label>
    <input id="guest-email" type="email" required placeholder="example@gmail.com">
 
    <label>Aadhar Number (Optional)</label>
    <input id="guest-aadhar" placeholder="XXXX-XXXX-XXXX">
 
    <label>No. of Guests*</label>
    <input id="guest-count" type="number" required min="1" value="1">
 
    <button onclick="reviewHotelBooking('${hotelName}','${roomNumber}',${base},${tax},${total},'${checkIn}','${checkOut}',${nights})"
    style="background:#0d6efd;color:#fff;padding:13px;width:100%;margin-top:18px;border:none;border-radius:10px;font-weight:700;">
      Continue
    </button>
 
    <button onclick="location.reload()" style="margin-top:10px;background:#eee;padding:12px;width:100%;border:none;border-radius:8px;">
      Cancel
    </button>
  </div>`;
}
 
 
function reviewHotelBooking(hotelName, roomNumber, base, tax, total, checkIn, checkOut, nights){
  const name = guest('name');
  const phone = guest('phone');
  const age = guest('age');
  const email = guest('email');
  const aadhar = guest('aadhar') || "Not Provided";
  const count = guest('count');
 
  function guest(id){ return document.getElementById('guest-'+id).value.trim(); }
 
  document.body.innerHTML = `
  <div style="max-width:480px;margin:40px auto;background:#fff;border-radius:14px;padding:25px;font-family:Inter;line-height:1.6;box-shadow:0 4px 18px rgba(0,0,0,0.12)">
    <h2 style="text-align:center;color:#138a36;">Review Booking</h2>
 
<pre style="white-space:pre-line;margin-top:15px">
 
📍 <b>${hotelName}</b>
🛏 Room     : ${roomNumber}
👤 Guest    : ${name} (${age}), ${count} person(s)
📞 Phone    : ${phone}
✉ Email    : ${email}
🪪 Aadhar   : ${aadhar}
 
📅 Check-in : ${checkIn}
📅 Check-out: ${checkOut}
🌙 Nights   : ${nights}
 
💰 Base Price : ₹ ${base}
💰 Taxes (5%) : ₹ ${tax}
💳 Total Pay  : ₹ ${total}
</pre>
 
<button onclick="proceedHotelPaymentFinal('${hotelName}','${roomNumber}','${name}','${phone}','${email}','${age}','${aadhar}',${count},${base},${tax},${total},'${checkIn}','${checkOut}',${nights})"
style="background:#138a36;color:#fff;padding:15px;width:100%;border:none;border-radius:10px;font-weight:700;">
 Proceed to Payment
</button>
 
<button onclick="location.reload()" style="margin-top:10px;background:#eee;padding:12px;width:100%;border:none;border-radius:8px;">
 Back
</button>
</div>`;
}
 
// ------------------ BUS SEARCH ------------------
function fetchBuses(event){
  if(event) event.preventDefault();
  const from = document.getElementById('bus-from').value;
  const to = document.getElementById('bus-to').value;
  const fd = new FormData(); 
  fd.append('from', from); 
  fd.append('to', to);
 
  fetch('fetch_buses.php',{ method:'POST', body:fd })
    .then(r=>r.text())
    .then(html => { 
      document.getElementById('bus-results').innerHTML = html; 
      if(typeof applyBusRefine === "function") applyBusRefine();
    })
    .catch(err => console.error(err));
}
 
 
// ------------------ OPEN SEAT LAYOUT DIRECT → SEAT PAGE ------------------
let CURRENT_BUS_ID = null;
let SELECTED_SEAT = null;
 
function openSeatModal(busId, busType, busName, price, dep, arr){
  CURRENT_BUS_ID = busId;
  document.getElementById('seatModal').classList.remove('hidden');
 
  const fd = new FormData(); 
  fd.append('bus_id', busId); 
  fd.append('bus_type', busType);
 
  fetch('fetch_seats.php',{ method:'POST', body:fd })
    .then(r=>r.text())
    .then(html => {
      const area = document.getElementById('seat-area');
      area.innerHTML = html;
 
      area.querySelectorAll('.seat.available').forEach(btn => {
        btn.addEventListener('click', () => {
 
          area.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
 
          btn.classList.add('selected');
          SELECTED_SEAT = btn.dataset.seat;
 
          const c = document.getElementById('confirmBtn');
          c.disabled = false;
          c.style.cursor = 'pointer';
 
        });
      });
    });   // ✅ yeh missing tha
}         // ✅ yeh bhi missing tha
 
function finalizeSeat(){
  if(!SELECTED_SEAT){
    alert("Please select a seat first.");
    return;
  }
 
  // ✅ Redirect directly to book_seat.php without modal
  const form = document.createElement("form");
  form.method = "POST";
  form.action = "book_seat.php";
  form.innerHTML = `
    <input type="hidden" name="bus_id" value="${CURRENT_BUS_ID}">
    <input type="hidden" name="seat_no" value="${SELECTED_SEAT}">
  `;
  document.body.appendChild(form);
  form.submit();
}
 
 
/* =============================
   UPGRADED JAVASCRIPT (SMART VERSION)
   (पुराने showSection को इससे बदलें)
   ============================= */
/* =================================
   FINAL UPGRADED showSection FUNCTION
   (पुराने showSection को इससे बदलें)
   ================================= */
function showSection(sectionId, element) {
    
    // 1. सभी "पेज" (.section-content) को छिपाएँ
    document.querySelectorAll('.section-content').forEach(sec => {
        sec.style.display = 'none';
        sec.classList.remove('active', 'show'); 
    });
    
    // 2. सभी "होम पेज" (.data-page="home") को छिपाएँ
    document.querySelectorAll('[data-page="home"]').forEach(sec => {
        sec.style.display = 'none';
    });

    // 3. सभी Nav लिंक्स से 'active' क्लास हटाएँ
    document.querySelectorAll('header nav a').forEach(a => a.classList.remove('active'));

    // 4. सही सेक्शन दिखाएँ
    if (sectionId === 'home') {
        // अगर 'home' क्लिक हुआ है, तो 'data-page="home"' वाले सभी सेक्शन दिखाएँ
        document.querySelectorAll('[data-page="home"]').forEach(sec => {
            sec.style.display = 'block';
        });
        const homeHero = document.getElementById('home');
        if(homeHero) {
            homeHero.style.display = 'block'; // सुनिश्चित करें कि यह भी दिखे
            homeHero.classList.add('active'); 
        }

    } else {
        // किसी और टैब (Booking, Profile, Packages, या नया पैसेंजर/पेमेंट/टिकट पेज) के लिए
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.style.display = 'block';
            targetSection.classList.add('active');
        }
    }

    // 5. क्लिक किए गए Nav लिंक को 'active' करें (अगर वह एक टैब था)
    if (element && element.tagName === 'A') {
        element.classList.add('active');
    } else {
        // अगर यह टैब नहीं था (जैसे 'packages' या 'passenger-details-page')
        // तो data-section के आधार पर सही टैब ढूँढें
        const matchingTab = document.querySelector(`header nav a[data-section="${sectionId}"]`);
        if (matchingTab) {
            matchingTab.classList.add('active');
        }
    }
}
 /* =============================
   4. NEW JAVASCRIPT FUNCTION
   (इसे अपने <script> टैग में जोड़ें)
   ============================= */
function showPackage(packageId) {
    // 1. "Packages" टैब पर स्विच करें
    // (यह 'packages' टैब को 'active' भी कर देगा)
    showSection('packages'); 

    // 2. उस पैकेज तक स्क्रॉल करें
    const targetPackage = document.getElementById('package-detail-' + packageId);
    if (targetPackage) {
        // उसे थोड़ी देर बाद स्क्रॉल करें ताकि पेज दिखने का समय मिले
        setTimeout(() => {
            targetPackage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // (Bonus) ध्यान खींचने के लिए पैकेज को फ्लैश (flash) करें
            targetPackage.style.transition = 'box-shadow 0.2s ease-out';
            targetPackage.style.boxShadow = '0 0 0 4px #ffcc00'; // पीला बॉर्डर
            setTimeout(() => {
                targetPackage.style.boxShadow = '0 10px 40px rgba(0,0,0,0.1)'; // वापस नार्मल
            }, 1500);
        }, 300); // 300ms का छोटा सा डिले
    }
}

// यह सुनिश्चित करें कि पेज लोड होने पर 'home' डिफ़ॉल्ट रूप से सही दिखे
document.addEventListener("DOMContentLoaded", () => {
    // पुराने showSection('home') की जगह इसे चलाएँ
    showSection('home', document.querySelector('header nav a[data-section="home"]'));
});
 
</script>
 
 <!-- ===================== PASSENGER FORM MODAL (DROP-IN) ===================== -->
<style>
  .psg-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:10000}
  .psg-modal.show{display:flex}
  .psg-card{background:#fff;width:min(760px,94%);border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 10px 30px rgba(15,23,42,.1);font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .psg-hd{background:#0d6efd;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
  .psg-bd{padding:16px}
  .psg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .psg-row{display:flex;flex-direction:column;gap:6px}
  .psg-row label{font-size:12px;color:#475569;font-weight:700}
  .psg-inp,.psg-sel{height:44px;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;font-size:14px;background:#fff}
  .psg-note{font-size:12px;color:#64748b}
  .psg-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}
  .psg-btn{border:1px solid #cfe0ff;background:#eef6ff;border-radius:10px;padding:10px 14px;font-weight:800;color:#1d4ed8;cursor:pointer}
  .psg-btn.p{background:#0d6efd;border-color:#0d6efd;color:#fff}
  .pill{background:#eef2ff;border:1px solid #c7d2fe;color:#1d4ed8;border-radius:999px;padding:4px 10px;font-weight:800;font-size:12px}
  @media (max-width:720px){ .psg-grid{grid-template-columns:1fr} }
</style>
<!-- ✅ FINAL BUS PASSENGER FORM (Direct Show After Seat Select) -->
<div id="bus-final-passenger-form" style="display:none; max-width:800px; margin:25px auto; padding:25px; background:#fff; border-radius:14px; box-shadow:0 4px 18px rgba(0,0,0,0.12); font-family:Inter;">
  
  <h2 style="text-align:center; margin-bottom:20px;">Passenger Details</h2>

  <div style="display:flex; justify-content:space-between; margin-bottom:18px; font-size:14px; font-weight:600;">
    <span>Route: <span id="final-route"></span></span>
    <span>Seat: <span id="final-seat"></span></span>
  </div>

  <form id="bus-final-form">
    
    <input type="hidden" id="final-bus-id">
    <input type="hidden" id="final-seat-no">

    <label>Full Name *</label>
    <input type="text" placeholder="Your full name" required>

    <label>Mobile Number *</label>
    <input type="text" placeholder="10-digit number" required>

    <label>Email *</label>
    <input type="email" placeholder="example@gmail.com" required>

    <label>Age *</label>
    <input type="number" placeholder="Age" required>

    <label>Gender *</label>
    <select required>
      <option value="">Select…</option>
      <option>Male</option>
      <option>Female</option>
      <option>Other</option>
    </select>

    <label>City *</label>
    <input type="text" placeholder="Your city" required>

    <label>ID Proof Type *</label>
    <select required>
      <option value="">Select…</option>
      <option>Aadhar</option>
      <option>PAN</option>
      <option>Driving License</option>
    </select>

    <label>ID Number *</label>
    <input type="text" placeholder="Document number" required>

    <label>Emergency Contact (optional)</label>
    <input type="text" placeholder="Emergency phone (optional)">

    <button type="button" onclick="proceedBusPayment()" style="background:#0066ff;color:#fff;padding:12px;border:none;border-radius:10px;font-size:16px;width:100%;margin-top:15px;cursor:pointer;">
      Proceed to Payment
    </button>
  </form>

</div>


<script>
  // ===== Modal API =====
  function openPassengerModal(ctx){
    // ctx: { bus_id, seat_no, from, to, name?, phone?, email?, city? }
    const M = document.getElementById('passengerModal');
    // context pills
    document.getElementById('psg-route-from').textContent = ctx?.from || '—';
    document.getElementById('psg-route-to').textContent = ctx?.to   || '—';
    document.getElementById('selected-seat-display').textContent = ctx?.seat_no || '—';

    // Hidden fields for your PHP
    document.getElementById('psg-bus-id').value = ctx?.bus_id || '';
    document.getElementById('psg-seat-no').value = ctx?.seat_no || '';

    // Optional prefill if you have user data
    if(ctx?.name)  document.getElementById('psg-name').value  = ctx.name;
    if(ctx?.phone) document.getElementById('psg-phone').value = ctx.phone;
    if(ctx?.email) document.getElementById('psg-email').value = ctx.email;
    if(ctx?.city)  document.getElementById('psg-city').value  = ctx.city;

    M.classList.add('show');
  }
  function closePassengerModal(){
    document.getElementById('passengerModal').classList.remove('show');
  }

  // Basic validation + submit hook
  function submitPassengerForm(){
    const phone = document.getElementById('psg-phone').value.trim();
    if(!/^\d{10}$/.test(phone)){
      alert('Please enter a valid 10-digit phone number.');
      return false;
    }
    // You can add more checks for ID formats if needed
    return true; // allow form POST to book_seat.php
  }

  // ===== Wiring to your existing Bus flow =====
  // In your Bus code you already set these when user picks a seat:
  // rs('bb-continue').addEventListener('click', ()=>{ ... open passenger modal ... });
  // We just ensure that click opens THIS modal with the right context.

  (function wireToBusFlow(){
    // If your existing code already sets passengerModal visibility, it's fine.
    // We expose a global helper used by your bus flow after seat selection.
    window.showPassengerForBus = function(busId, seatNo, fromTxt, toTxt){
      openPassengerModal({ bus_id: busId, seat_no: seatNo, from: fromTxt, to: toTxt });
    };

  

    const cont = document.getElementById('bb-continue');
    if(cont && !cont._wired){
      cont._wired = true;
      cont.addEventListener('click', function(e){
        // If your bus script set globals, try to read them:
        const from = (document.getElementById('bb-from')?.value)||'';
        const to   = (document.getElementById('bb-to')?.value)||'';
        try{
          if(window.SELECTED_SEAT && window.BUS_ID){
            // Open our modal when Continue is pressed
            openPassengerModal({ bus_id: window.BUS_ID, seat_no: window.SELECTED_SEAT, from, to });
            e.preventDefault();
            e.stopPropagation();
          }
        }catch(_){}
      }, {capture:true});
    }
  })();
  function openFinalBusPassengerForm(BUS_ID, SELECTED_SEAT) {

  let from = document.getElementById('bb-from').value;
  let to   = document.getElementById('bb-to').value;

  const form = document.createElement("form");
  form.method = "POST";
  form.action = "bus_passenger_form.php";
  form.innerHTML = `
    <input type="hidden" name="bus_id" value="${BUS_ID}">
    <input type="hidden" name="seat_no" value="${SELECTED_SEAT}">
    <input type="hidden" name="from" value="${from}">
    <input type="hidden" name="to" value="${to}">
  `;
  document.body.appendChild(form);
  form.submit();
}

/* ==================================
   JAVASCRIPT FOR DESTINATION UPGRADE
   ( पुराने openDestination को इससे बदलें )
   ================================== */

// 1. सभी शहरों का डेटा (आप इसे बढ़ा सकते हैं)
const destinationData = {
    'mumbai': {
        title: 'Mumbai',
        image: 'https://images.unsplash.com/photo-1562979314-bee7453e911c?w=800&h=500&fit=crop',
        description: 'मुंबई, सपनों का शहर, एक हलचल भरा महानगर है जो कभी नहीं सोता। यह संस्कृतियों, वित्त और सिनेमाई ग्लैमर का एक पिघलने वाला बर्तन (melting pot) है।',
        attractions: ['Gateway of India', 'Marine Drive', 'Elephanta Caves', 'Juhu Beach'],
        bestTime: 'October to March'
    },
    'delhi': {
        title: 'Delhi',
        image: 'https://s7ap1.scene7.com/is/image/incredibleindia/80-Places-to-Visit-Near-Delhi-To-Gain-Some-Unforgettable-Experience4-about?qlt=82&ts=1742170431408',
        description: 'भारत की राजधानी दिल्ली एक जीवंत शहर है जहाँ प्राचीन इतिहास और आधुनिक जीवन सह-अस्तित्व में हैं। इसके स्मारकों और बाज़ारों में सदियों के इतिहास का अन्वेषण करें।',
        attractions: ['India Gate', 'Qutub Minar', 'Humayun\'s Tomb', 'Chandni Chowk'],
        bestTime: 'September to November & February to March'
    },
    'Ahmedabad': {
        title: 'Ahmedabad',
        image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT-1K6WoagR-mk_R9_Kl23zVfArgyFnbJ0eHQ&s',
        description: 'साबरमती नदी के तट पर स्थित, अहमदाबाद विरासत और आधुनिकता का एक अनूठा मिश्रण पेश करता है। यह अपनी वास्तुकला, भोजन और वस्त्रों के लिए जाना जाता है।',
        attractions: ['Sabarmati Ashram', 'Adalaj Stepwell', 'Kankaria Lake', 'Jama Masjid'],
        bestTime: 'November to February'
    },
    'Shimla': {
        title: 'Shimla',
        image: 'https://s7ap1.scene7.com/is/image/incredibleindia/cityscape-of-shimla-himachal-pradesh-city-1-hero?qlt=82&ts=1742171983523',
        description: 'पहाड़ियों की रानी, शिमला अपनी औपनिवेशिक वास्तुकला, मॉल रोड और सुंदर घाटियों के लिए प्रसिद्ध है। यह एक आदर्श ग्रीष्मकालीन अवकाश स्थल है।',
        attractions: ['The Ridge', 'Mall Road', 'Jakhoo Temple', 'Kufri'],
        bestTime: 'March to June'
    },
    'Manali': {
        title: 'Manali',
        image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqm91tRbkXAJ5Ly1yJI1bCaUHB05m-VROhUA&s',
        description: 'देवताओं की घाटी, मनाली साहसिक प्रेमियों और शांति चाहने वालों के लिए एक स्वर्ग है। बर्फ से ढके पहाड़ और ब्यास नदी इसकी सुंदरता को बढ़ाते हैं।',
        attractions: ['Solang Valley', 'Rohtang Pass', 'Hadimba Temple', 'Old Manali'],
        bestTime: 'October to June'
    },
    'Ooty': {
        title: 'Ooty',
        image: 'https://www.clubmahindra.com/blog/media/section_images/ultimate-o-8ac88a2da056a3d.jpg',
        description: 'नीलगिरि पहाड़ियों में बसा ऊटी अपने चाय के बागानों, शांत झीलों और टॉय ट्रेन के लिए जाना जाता है। यह एक हरा-भरा और तरोताजा कर देने वाला हिल स्टेशन है।',
        attractions: ['Ooty Lake', 'Botanical Gardens', 'Doddabetta Peak', 'Toy Train'],
        bestTime: 'Throughout the year'
    },
    'Goa': {
        title: 'Goa',
        image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQlH8cldG7pDhSvEkFq6kJLUmEAw_RSleLMbw&s',
        description: 'भारत का समुद्र तट स्वर्ग, गोवा अपने जीवंत नाइटलाइफ़, पुर्तगाली वास्तुकला और रेतीले समुद्र तटों के लिए प्रसिद्ध है।',
        attractions: ['Baga Beach', 'Calangute Beach', 'Fort Aguada', 'Old Goa Churches'],
        bestTime: 'November to February'
    },
    'Kerala': {
        title: 'Kerala',
        image: 'https://media.easemytrip.com/media/Blog/India/637033873695687971/637033873695687971exGTcR.jpg',
        description: 'ईश्वर का अपना देश, केरल अपने शांत बैकवॉटर्स, नारियल के पेड़ों, मसालों के बागानों और आयुर्वेद के लिए जाना जाता है।',
        attractions: ['Alleppey Backwaters', 'Munnar Tea Gardens', 'Kovalam Beach', 'Thekkady'],
        bestTime: 'September to March'
    },
    'Varanasi': {
        title: 'Varanasi',
        image: 'https://s7ap1.scene7.com/is/image/incredibleindia/manikarnika-ghat-city-hero?qlt=82&ts=1727959374496',
        description: 'दुनिया के सबसे पुराने शहरों में से एक, वाराणसी भारत की आध्यात्मिक राजधानी है। गंगा नदी के किनारे इसके घाट एक अविस्मरणीय अनुभव प्रदान करते हैं।',
        attractions: ['Kashi Vishwanath Temple', 'Dashashwamedh Ghat', 'Ganga Aarti', 'Sarnath'],
        bestTime: 'October to March'
    },
    'Amritsar': {
        title: 'Amritsar',
        image: 'https://s7ap1.scene7.com/is/image/incredibleindia/1-gurdwara-sri-tarn-taran-sahib-or-gurdwara-sri-darbar-sahib-amritsar-punjab-city-hero?qlt=82&ts=1726662408793',
        description: 'स्वर्ण मंदिर का घर, अमृतसर सिख धर्म का एक प्रमुख केंद्र है। यह शहर अपने समृद्ध इतिहास, स्वादिष्ट भोजन और वाघा बॉर्डर समारोह के लिए भी जाना जाता है।',
        attractions: ['Golden Temple', 'Jallianwala Bagh', 'Wagah Border', 'Partition Museum'],
        bestTime: 'November to March'
    }
};

// 2. नया Modal खोलने का फंक्शन
function openModal(cityKey) {
    const data = destinationData[cityKey];
    if (!data) return; // अगर डेटा नहीं मिला तो कुछ न करें

    // Modal में जानकारी भरें
    document.getElementById('modal-title').textContent = data.title;
    document.getElementById('modal-image').src = data.image;
    document.getElementById('modal-desc').textContent = data.description;
    document.getElementById('modal-best-time').textContent = data.bestTime;
    
    const attractionsList = document.getElementById('modal-attractions');
    attractionsList.innerHTML = ''; // पुरानी लिस्ट साफ़ करें
    data.attractions.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        attractionsList.appendChild(li);
    });

    // "Book Now" बटन को सही शहर के लिए सेट करें
    document.getElementById('modal-book-btn').setAttribute('onclick', `redirectToBooking('${data.title}')`);

    // Modal को दिखाएँ
    document.getElementById('destination-modal').style.display = 'flex';
}

// 3. Modal बंद करने का फंक्शन
function closeModal() {
    document.getElementById('destination-modal').style.display = 'none';
}

// 4. आपका पुराना openDestination फंक्शन, नए नाम के साथ
// यह फंक्शन अब modal के अंदर वाला "Book Now" बटन कॉल करेगा
function redirectToBooking(city) {
    closeModal(); // पहले modal बंद करें

    // 1. मुख्य "Booking" टैब पर स्विच करें
    try {
        showSection('booking');
    } catch (e) {
        console.error("showSection function not found", e);
    }

    // 2. बुकिंग के अंदर "Bus" टैब को एक्टिवेट करें
    try {
        document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
        document.getElementById('bus').classList.add('active');
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.tab-button[onclick*="showForm(\'bus\'"]').classList.add('active');
    } catch (e) {
        console.error("Could not switch to bus tab", e);
    }

    // 3. बस फॉर्म में "To" (Destination) फील्ड भरें
    const busToField = document.getElementById('bb-to'); // Bus Booking 'To' field
    if (busToField) {
        busToField.value = city;
    }
    
    // 4. (Bonus) होटल फॉर्म में "City" फील्ड भरें
    const hotelCityField = document.getElementById('hy-city'); // Hotel 'City' field
    if (hotelCityField) {
        hotelCityField.value = city;
        if (typeof renderList === 'function') {
            renderList();
        }
    }

    // 5. (Bonus) फ्लाइट फॉर्म में "To" फील्ड भरें
    const flightToField = document.getElementById('fx-to'); // Flight 'To' field
    if (flightToField) {
        const cityToIATA = {
            'Mumbai': 'Mumbai (BOM)', 'Delhi': 'Delhi (DEL)', 'Ahmedabad': 'Ahmedabad (AMD)',
            'Shimla': 'Shimla (SLV)', 'Manali': 'Kullu (KUU)', 'Goa': 'Goa (GOI)',
            'Varanasi': 'Varanasi (VNS)', 'Amritsar': 'Amritsar (ATQ)', 'Ooty': 'Coimbatore (CJB)',
            'Kerala': 'Cochin (COK)'
        };
        flightToField.value = cityToIATA[city] || city;
    }
  
    // 6. यूज़र को पेज के टॉप पर स्क्रॉल करें
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
/* =============================
   HERO SEARCH BAR FUNCTION
   (इसे अपने मुख्य <script> में जोड़ें)
   ============================= */
function searchFromHero() {
    const city = document.getElementById('hero-search-city').value;

    if (!city) {
        alert("Please enter a destination to search.");
        return;
    }

    // यह फंक्शन आपके 'Destinations' वाले 'Explore' बटन की तरह ही काम करेगा
    // यह 'redirectToBooking' फंक्शन आपके कोड में पहले से होना चाहिए
    if (typeof redirectToBooking === 'function') {
        redirectToBooking(city);
    } else {
        // फॉलबैक, अगर वह फंक्शन मौजूद न हो
        alert("Loading booking for: " + city);
        showSection('booking');
        
        // फॉर्म को भरने की कोशिश
        const busToField = document.getElementById('bb-to');
        if (busToField) busToField.value = city;
        
        const hotelCityField = document.getElementById('hy-city');
        if (hotelCityField) hotelCityField.value = city;
    }
}
/* =============================
   NEW 'showBooking' FUNCTION
   (इसे अपने <script> टैग में जोड़ें)
   ============================= */
function showBooking(type) {
    // 'Booking' टैब पर जाएँ
    showSection('booking', document.querySelector('header nav a[data-section="booking"]'));

    // 'Booking' के अंदर 'Hotel' टैब को एक्टिवेट करें
    if (typeof showForm === 'function') {
        // हम इवेंट 'null' पास कर सकते हैं क्योंकि हमें बटन क्लिक की ज़रूरत नहीं है
        showForm('hotel', null); 
        // होटल टैब बटन को मैन्युअली एक्टिवेट करें
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        const hotelTabButton = document.querySelector('.tab-button[onclick*="showForm(\'hotel\'"]');
        if(hotelTabButton) hotelTabButton.classList.add('active');
    }
    
    // (Bonus) अगर होटल फ़िल्टर है, तो उसे यहाँ सेट कर सकते हैं
    // उदा: document.getElementById('hotel-type-filter').value = type;
}
/* =============================
   DONE (GO HOME) BUTTON FUNCTION
   (इसे अपने मुख्य <script> टैग में जोड़ें)
   ============================= */
function goHome() {
    // 'home' टैब पर वापस जाएँ
    // यह 'showSection' फ़ंक्शन टिकट पेज को छिपा देगा और होम पेज के सभी हिस्सों को दिखा देगा
    showSection('home', document.querySelector('header nav a[data-section="home"]'));
    
    // पेज को सबसे ऊपर स्क्रॉल करें
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
/* ==============================================
   ✅ START: "TRIP COST ESTIMATOR" JAVASCRIPT
   (इसे अपने मुख्य <script> टैग में पेस्ट करें)
   ============================================== */

function calculateTrip() {
    // 1. यूज़र से इनपुट लें
    const destination = document.getElementById('calc-dest').value;
    const days = parseInt(document.getElementById('calc-days').value) || 1;
    const people = parseInt(document.getElementById('calc-people').value) || 1;
    const style = document.querySelector('input[name="travel_style"]:checked').value;

    if (!destination) {
        alert("Please enter a destination.");
        return;
    }

    // 2. ट्रैवल स्टाइल के आधार पर खर्च का अनुमान (आप इन्हें बदल सकते हैं)
    const costs = {
        budget: {
            hotel_per_night: 1500, // प्रति रात का खर्च
            food_per_day: 800,     // प्रति व्यक्ति, प्रति दिन
            activity_per_day: 400, // प्रति व्यक्ति, प्रति दिन
            travel_base: 4000      // प्रति व्यक्ति (फ्लाइट/ट्रेन)
        },
        standard: {
            hotel_per_night: 4000,
            food_per_day: 1500,
            activity_per_day: 1000,
            travel_base: 8000
        },
        luxury: {
            hotel_per_night: 10000,
            food_per_day: 4000,
            activity_per_day: 3000,
            travel_base: 15000
        }
    };

    // 3. कैलकुलेशन करें
    const selectedStyle = costs[style];
    
    // होटल का खर्च (यह प्रति व्यक्ति नहीं है, प्रति रूम/रात है)
    const hotelTotal = selectedStyle.hotel_per_night * days;
    
    // भोजन और एक्टिविटी (यह प्रति व्यक्ति, प्रति दिन है)
    const foodTotal = selectedStyle.food_per_day * days * people;
    const activityTotal = selectedStyle.activity_per_day * days * people;
    
    // यात्रा (यह प्रति व्यक्ति है)
    const travelTotal = selectedStyle.travel_base * people;

    const grandTotal = hotelTotal + foodTotal + activityTotal + travelTotal;
    const perPersonTotal = grandTotal / people;

    // 4. रिजल्ट को फॉर्मेट करें (रुपये में)
    const formatINR = (num) => '₹ ' + num.toLocaleString('en-IN');

    // 5. रिजल्ट को HTML में दिखाएँ
    document.getElementById('result-dest').innerText = destination;
    document.getElementById('result-hotel').innerText = formatINR(hotelTotal);
    document.getElementById('result-food').innerText = formatINR(foodTotal);
    document.getElementById('result-activities').innerText = formatINR(activityTotal);
    document.getElementById('result-travel').innerText = formatINR(travelTotal);
    
    document.getElementById('result-per-person').innerText = formatINR(Math.round(perPersonTotal));
    document.getElementById('result-total').innerText = formatINR(grandTotal);

    // 6. रिजल्ट दिखाएँ और प्लेसहोल्डर छिपाएँ
    document.getElementById('calc-placeholder').style.display = 'none';
    document.getElementById('calc-results-content').style.display = 'block';
}
/* ==============================================
   ✅ START: "USER PROFILE" JAVASCRIPT
   (इसे goHome() के बाद पेस्ट करें)
   ============================================== */

// --- यह प्रोफाइल पेज के टैब को चलाएगा ---
function setupProfileTabs() {
    const profileNavLinks = document.querySelectorAll('.profile-nav-link');
    const profileContents = document.querySelectorAll('.profile-content');

    if (profileNavLinks.length === 0) return; 

    profileNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            profileNavLinks.forEach(nav => nav.classList.remove('active'));
            profileContents.forEach(content => content.classList.remove('active'));
            this.classList.add('active');
            const targetId = this.dataset.target;
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // (बदलाव) डिफ़ॉल्ट टैब सेट न करें, इसे URL पैरामीटर पर छोड़ने दें
    // if (!document.querySelector('.profile-nav-link.active')) {
    //   profileNavLinks[0].classList.add('active');
    //   profileContents[0].classList.add('active');
    // }
}

// --- यह प्रोफाइल पिक्चर अपलोड को चलाएगा ---
function setupAvatarUpload() {
    // यह अब <form> द्वारा खुद ही हैंडल किया जाता है (onchange="...submit()")
    // इसलिए जावास्क्रिप्ट की ज़रूरत नहीं है।
    // const profilePic = document.getElementById('profile-pic');
    // const avatarInput = document.getElementById('avatar-input');
    // ... (पुराना JS हटा दिया गया) ...
}

// --- यह "Save" बटनों को चलाएगा ---
function setupProfileForms() {
    // यह अब असली <form> हैं और खुद ही सबमिट होते हैं।
    // इसलिए, 'alert' वाले फेक सबमिट JS की ज़रूरत नहीं है।
    // ... (पुराना JS हटा दिया गया) ...
}

// --- (बदला हुआ) DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", () => {
    
    // होम पेज को डिफ़ॉल्ट रूप से दिखाएँ
    // (इसे URL पैरामीटर चेक करने के बाद चलाएँ)

    // ... (आपके अन्य DOMContentLoaded कोड जैसे होटल सर्च, फ़िल्टर) ...

    // --- नया प्रोफाइल JS यहाँ कॉल करें ---
    setupProfileTabs();
    // setupAvatarUpload(); // ज़रूरत नहीं
    // setupProfileForms(); // ज़रूरत नहीं

    // --- (नया) URL पैरामीटर के आधार पर सही टैब दिखाएँ ---
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    const section = urlParams.get('section'); // ?section=profile
 
    let defaultTabSet = false;

    if (tab === 'profile') {
        // 'personal-info' टैब दिखाएँ
        const link = document.querySelector('.profile-nav-link[data-target="personal-info"]');
        if(link) link.click();
        showSection('profile'); // प्रोफाइल पेज को भी दिखाएँ
        defaultTabSet = true;
    } else if (tab === 'profile_security') {
        // 'security' टैब दिखाएँ
        const link = document.querySelector('.profile-nav-link[data-target="security"]');
        if(link) link.click();
        showSection('profile'); // प्रोफाइल पेज को भी दिखाएँ
        defaultTabSet = true;
    } else if (section === 'profile') {
        // अगर URL ?section=profile है
        const link = document.querySelector('.profile-nav-link[data-target="personal-info"]');
        if(link) link.click();
        showSection('profile');
        defaultTabSet = true;
    }

    // अगर URL में कोई टैब नहीं है, तो डिफ़ॉल्ट 'home' दिखाएँ
    if (!defaultTabSet) {
        showSection('home', document.querySelector('header nav a[data-section="home"]'));
    }
});


</script>


 
</body>
</html>
 