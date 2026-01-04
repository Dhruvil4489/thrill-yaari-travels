<?php
// fetch_buses.php
include 'db.php';

$from = isset($_POST['from']) ? trim($_POST['from']) : '';
$to   = isset($_POST['to']) ? trim($_POST['to']) : '';

if (empty($from) || empty($to)) {
    echo '<div class="rb empty">Please select both origin and destination.</div>';
    exit;
}

$stmt = $conn->prepare("
  SELECT id,from_city,to_city,bus_name,bus_type,departure_time,arrival_time,price,rating
  FROM buses
  WHERE from_city = ? AND to_city = ?
  ORDER BY price ASC, rating DESC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$q = $stmt->get_result();

if(!$q || $q->num_rows==0){
  echo '<div class="rb empty">No buses found for this route.</div>';
  $stmt->close();
  exit;
}
$stmt->close();
?>
<style>
  .rb-card{border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;display:grid;grid-template-columns:1fr 160px;background:#fff}
  .rb-left{padding:14px;display:grid;grid-template-columns:180px 1fr 140px;gap:12px;align-items:center}
  .rb-brand{font-weight:900}
  .rb-tiny{font-size:12px;color:#64748b}
  .rb-time{font-size:18px;font-weight:900}
  .rb-tag{background:#f1f5f9;border:1px solid #e2e8f0;padding:2px 8px;border-radius:8px;font-size:12px}
  .rb-right{border-left:1px dashed #e5e7eb;padding:12px;display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;background:#fff7ed}
  .rb-fare{font-size:22px;font-weight:900;color:#9a3412}
  .rb-view{border:1px solid #fed7aa;background:#fff7ed;border-radius:10px;padding:8px 10px;font-weight:800;color:#9a3412;cursor:pointer}
  .stars{color:#f59e0b;font-weight:800}
</style>
<div class="list">
<?php while($b = $q->fetch_assoc()): ?>
  <article class="rb-card" data-fare="<?= (int)$b['price'] ?>" data-dep="<?= htmlspecialchars($b['departure_time']) ?>" data-type="<?= htmlspecialchars($b['bus_type']) ?>">
    <div class="rb-left">
      <div>
        <div class="rb-brand"><?= htmlspecialchars($b['bus_name']) ?></div>
        <div class="rb-tiny"><?= htmlspecialchars($b['bus_type']) ?></div>
        <div class="rb-tiny stars"><?= str_repeat('★', (int)$b['rating']) ?></div>
      </div>
      <div>
        <div class="rb-time"><?= htmlspecialchars($b['departure_time']) ?> → <?= htmlspecialchars($b['arrival_time']) ?></div>
        <div class="rb-tiny"><?= htmlspecialchars($b['from_city']) ?> → <?= htmlspecialchars($b['to_city']) ?></div>
        <div style="display:flex;gap:6px;margin-top:4px">
          <span class="rb-tag">Live tracking</span>
          <span class="rb-tag">Charging</span>
          <span class="rb-tag">Water</span>
        </div>
      </div>
      <div style="text-align:right">
        <div class="rb-tiny">Seats left: 52</div>
        <button class="rb-view" onclick="openSeatModal(<?= (int)$b['id'] ?>,'<?= htmlspecialchars($b['bus_type']) ?>','<?= htmlspecialchars($b['bus_name']) ?>',<?= (int)$b['price'] ?>,'<?= htmlspecialchars($b['departure_time']) ?>','<?= htmlspecialchars($b['arrival_time']) ?>')">View Seats</button>
      </div>
    </div>
    <div class="rb-right">
      <div class="rb-fare">₹ <?= number_format($b['price']) ?></div>
      <div class="rb-tiny">incl. taxes</div>
    </div>
  </article>
<?php endwhile; ?>
</div>
