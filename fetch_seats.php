<?php
// fetch_seats.php
include 'db.php';

$bus_id  = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
$busType = isset($_POST['bus_type']) ? trim($_POST['bus_type']) : 'Seater';

if ($bus_id <= 0) {
    echo '<div class="error">Invalid bus selection.</div>';
    exit;
}

$booked = [];
$stmt = $conn->prepare("SELECT seat_no, status FROM bus_seats WHERE bus_id = ? AND status = 'booked'");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$r = $stmt->get_result();
while($x = $r->fetch_assoc()) {
    $booked[$x['seat_no']] = true;
}
$stmt->close();

/*
  Layout: 2+2 with aisle -> columns index: 0,1, (aisle) ,3,4
  13 rows * 4 seats = 52 seats (A,B | C,D)
  Labels: 1A 1B 1C 1D ... 13D
*/
$rows = 13;
$cols = ['A','B','C','D'];

$style = 'seater';
if (stripos($busType,'sleeper')!==false) $style='sleeper';
elseif (stripos($busType,'volvo')!==false) $style='volvo';

echo '<div class="legend" style="display:flex;gap:14px;margin-bottom:8px;font-size:13px">';
echo '<span><i style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#16a34a;margin-right:6px;border:1px solid #a7f3d0"></i> Available</span>';
echo '<span><i style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#ef4444;margin-right:6px;border:1px solid #fecaca"></i> Booked</span>';
echo '<span><i style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#2563eb;margin-right:6px;border:1px solid #bfdbfe"></i> Selected</span>';
echo '</div>';

echo "<div class='seat-grid $style'>";

for($r=1;$r<=$rows;$r++){
  // left 2
  foreach([0,1] as $i){
    $code = $r.$cols[$i];
    $isBooked = isset($booked[$code]);
    $cls = $isBooked ? 'seat booked' : 'seat available';
    echo "<button class='$cls' data-seat='$code' ".($isBooked?'disabled':'').">$code</button>";
  }
  // aisle gap
  echo "<span class='aisle'></span>";
  // right 2
  foreach([2,3] as $i){
    $code = $r.$cols[$i];
    $isBooked = isset($booked[$code]);
    $cls = $isBooked ? 'seat booked' : 'seat available';
    echo "<button class='$cls' data-seat='$code' ".($isBooked?'disabled':'').">$code</button>";
  }
}
echo "</div>";

?>
<style>
/* base */
.seat-grid{
  display:grid;grid-template-columns:repeat(5,56px);gap:10px;justify-content:center
}
.seat-grid .aisle{width:26px}
.seat{height:42px;border:1px solid #cbd5e1;border-radius:10px;font-weight:800;cursor:pointer}
.seat.available{background:#16a34a;color:#fff;border-color:#a7f3d0}
.seat.booked{background:#ef4444;color:#fff;border-color:#fecaca;cursor:not-allowed;opacity:.9}
.seat.selected{background:#2563eb;color:#fff;border-color:#bfdbfe;outline:2px solid #93c5fd}

/* style by bus type */
.seat-grid.sleeper .seat{border-radius:20px}
.seat-grid.volvo .seat{border-radius:12px;box-shadow:inset 0 4px 0 rgba(255,255,255,.15)}
</style>
<script>
// single select
document.querySelectorAll('.seat.available').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.seat.selected').forEach(b=>b.classList.remove('selected'));
    btn.classList.add('selected');
    window.SELECTED_SEAT = btn.dataset.seat;
    const c = document.getElementById('confirmBtn');
    if(c){ c.disabled=false; c.style.cursor='pointer'; }
  });
});
</script>
