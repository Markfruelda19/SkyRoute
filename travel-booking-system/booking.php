<?php
require_once 'includes/auth.php';
requireLogin();

$conn = getDBConnection();
$type = sanitize($_GET['type'] ?? '');
$id   = (int)($_GET['id'] ?? 0);
$item = null;
$error = '';
$success = '';

if ($type === 'flight') {
    $stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
} elseif ($type === 'hotel') {
    $stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
}

if (!$item) {
    echo "<p>Item not found.</p>"; exit();
}

// Process booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guests    = max(1, (int)($_POST['guests'] ?? 1));
    $check_in  = sanitize($_POST['check_in'] ?? '');
    $check_out = sanitize($_POST['check_out'] ?? '');

    // Calculate total
    if ($type === 'hotel' && $check_in && $check_out) {
        $nights = max(1, (strtotime($check_out) - strtotime($check_in)) / 86400);
        $total = $item['price_per_night'] * $nights * $guests;
    } else {
        $total = $item['price'] * $guests;
        $nights = 0;
    }

    // Fake payment processing
    $txn_id = generateTransactionID();

    $stmt = $conn->prepare("INSERT INTO bookings (user_id, booking_type, item_id, check_in, check_out, guests, total_price, status, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)");
    $ci = $check_in ?: null;
    $co = $check_out ?: null;
    $stmt->bind_param("isissiis", $_SESSION['user_id'], $type, $id, $ci, $co, $guests, $total, $txn_id);

    if ($stmt->execute()) {
        $booking_id = $conn->insert_id;

        // Decrement availability
        if ($type === 'flight') {
            $conn->query("UPDATE flights SET seats_available = seats_available - $guests WHERE id = $id");
        } else {
            $conn->query("UPDATE hotels SET rooms_available = rooms_available - 1 WHERE id = $id");
        }

        $success = $booking_id;
    } else {
        $error = "Booking failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book <?= ucfirst($type) ?> — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .booking-summary { background: rgba(201,168,76,0.06); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1.5rem; }
    .summary-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; }
    .summary-row.total { border-top: 1px solid var(--border); margin-top: 0.5rem; padding-top: 0.75rem; font-weight: 600; font-size: 1rem; color: var(--gold); }
    .payment-card { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1rem; }
    .fake-card { background: linear-gradient(135deg, #1a3a5c, #0a1628); border-radius: 12px; padding: 1.5rem; color: white; font-family: monospace; margin-bottom: 1rem; }
    .confirmation-box { text-align: center; padding: 2rem; }
    .confirmation-box .checkmark { font-size: 4rem; margin-bottom: 1rem; }
    .txn-badge { background: rgba(201,168,76,0.1); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.5rem 1rem; font-family: monospace; color: var(--gold); font-size: 0.9rem; }
  </style>
</head>
<body>
<nav class="navbar">
  <a href="index.php" class="brand">✈ <?= SITE_NAME ?></a>
  <div class="nav-links">
    <a href="index.php">← Back to Search</a>
    <a href="dashboard.php">My Bookings</a>
  </div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="logout.php" class="btn btn-outline btn-sm">Sign Out</a>
  </div>
</nav>

<div class="container" style="max-width:720px">
  <div class="page-content">

    <?php if ($success): ?>
    <!-- CONFIRMATION PAGE -->
    <div class="card">
      <div class="confirmation-box">
        <div class="checkmark">✅</div>
        <h2 class="section-title" style="margin-bottom:0.5rem">Booking Confirmed!</h2>
        <p class="text-muted mb-2">Your <?= $type ?> has been successfully booked.</p>
        <div class="txn-badge">Transaction ID: <?= $txn_id ?></div>
        <div class="booking-summary mt-2" style="text-align:left">
          <?php if ($type === 'flight'): ?>
            <div class="summary-row"><span>Flight</span><span><?= htmlspecialchars($item['airline']) ?></span></div>
            <div class="summary-row"><span>Route</span><span><?= htmlspecialchars($item['origin']) ?> → <?= htmlspecialchars($item['destination']) ?></span></div>
            <div class="summary-row"><span>Departure</span><span><?= date('M d, Y h:i A', strtotime($item['departure_time'])) ?></span></div>
            <div class="summary-row"><span>Passengers</span><span><?= $_POST['guests'] ?></span></div>
          <?php else: ?>
            <div class="summary-row"><span>Hotel</span><span><?= htmlspecialchars($item['hotel_name']) ?></span></div>
            <div class="summary-row"><span>Location</span><span><?= htmlspecialchars($item['location']) ?></span></div>
            <div class="summary-row"><span>Check-in</span><span><?= $_POST['check_in'] ?></span></div>
            <div class="summary-row"><span>Check-out</span><span><?= $_POST['check_out'] ?></span></div>
          <?php endif; ?>
        </div>
        <div style="display:flex; gap:1rem; justify-content:center; margin-top:1.5rem">
          <a href="dashboard.php" class="btn btn-primary">View My Bookings</a>
          <a href="index.php" class="btn btn-outline">Book More</a>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- BOOKING FORM -->
    <h2 class="section-title">Complete Your Booking</h2>
    <p class="section-sub">Review details and confirm your reservation</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- ITEM SUMMARY -->
    <div class="card mb-2">
      <div class="card-header">
        <span class="card-title"><?= $type === 'flight' ? '✈ Flight Details' : '🏨 Hotel Details' ?></span>
        <span class="badge badge-confirmed">Available</span>
      </div>
      <?php if ($type === 'flight'): ?>
        <div class="flight-card" style="background:transparent; border:none; padding:0; box-shadow:none">
          <div style="text-align:center; min-width:60px"><div style="font-size:2rem">✈</div><div class="fs-sm text-muted"><?= htmlspecialchars($item['airline']) ?></div></div>
          <div class="flight-route">
            <div><div class="flight-city"><?= htmlspecialchars($item['origin']) ?></div><div class="flight-time"><?= date('h:i A', strtotime($item['departure_time'])) ?></div></div>
            <div class="flight-arrow">────→</div>
            <div><div class="flight-city"><?= htmlspecialchars($item['destination']) ?></div><div class="flight-time"><?= date('h:i A', strtotime($item['arrival_time'])) ?></div></div>
          </div>
          <div><div class="listing-price"><?= formatPrice($item['price']) ?></div><div class="fs-sm text-muted">per person</div></div>
        </div>
      <?php else: ?>
        <div class="flex-between">
          <div>
            <h3 style="font-family:'Playfair Display',serif; margin-bottom:0.3rem"><?= htmlspecialchars($item['hotel_name']) ?></h3>
            <div class="text-muted fs-sm">📍 <?= htmlspecialchars($item['location']) ?></div>
            <div class="stars mt-1"><?= str_repeat('★', round($item['rating'])) ?> <?= $item['rating'] ?></div>
          </div>
          <div style="text-align:right">
            <div class="listing-price"><?= formatPrice($item['price_per_night']) ?></div>
            <div class="fs-sm text-muted">per night</div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <form method="POST" id="bookingForm">
      <div class="card mb-2">
        <div class="card-header"><span class="card-title">🧾 Reservation Details</span></div>

        <?php if ($type === 'hotel'): ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem">
          <div class="form-group">
            <label class="form-label">Check-in Date</label>
            <input type="date" name="check_in" id="check_in" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="calcTotal()">
          </div>
          <div class="form-group">
            <label class="form-label">Check-out Date</label>
            <input type="date" name="check_out" id="check_out" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required onchange="calcTotal()">
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label"><?= $type === 'flight' ? 'Number of Passengers' : 'Number of Guests' ?></label>
          <select name="guests" id="guests" class="form-control" onchange="calcTotal()">
            <?php for ($i = 1; $i <= 8; $i++): ?>
              <option value="<?= $i ?>"><?= $i ?> <?= $type === 'flight' ? 'Passenger' : 'Guest' ?><?= $i > 1 ? 's' : '' ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div class="card mb-2">
        <div class="card-header"><span class="card-title">💳 Payment Details</span></div>
        <div class="fake-card">
          <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem">
            <span style="font-size:0.8rem; opacity:0.7">SKYROUTE TRAVEL CARD</span>
            <span>💳</span>
          </div>
          <div style="letter-spacing:3px; font-size:1.1rem; margin-bottom:1rem">•••• •••• •••• ••••</div>
          <div style="display:flex; justify-content:space-between; font-size:0.8rem; opacity:0.8">
            <span>CARDHOLDER NAME</span><span>MM/YY</span>
          </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem">
          <div class="form-group">
            <label class="form-label">Card Number</label>
            <input type="text" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)">
          </div>
          <div class="form-group">
            <label class="form-label">Cardholder Name</label>
            <input type="text" class="form-control" placeholder="Juan dela Cruz">
          </div>
          <div class="form-group">
            <label class="form-label">Expiry Date</label>
            <input type="text" class="form-control" placeholder="MM/YY" maxlength="5">
          </div>
          <div class="form-group">
            <label class="form-label">CVV</label>
            <input type="text" class="form-control" placeholder="•••" maxlength="3">
          </div>
        </div>
        <div class="alert alert-info" style="margin:0; font-size:0.8rem">
          🔒 This is a <strong>demo simulation</strong>. No real payment is processed.
        </div>
      </div>

      <!-- PRICE SUMMARY -->
      <div class="card mb-2">
        <div class="card-header"><span class="card-title">💰 Price Summary</span></div>
        <div class="booking-summary" style="margin:0">
          <?php if ($type === 'flight'): ?>
            <div class="summary-row"><span>Base price</span><span><?= formatPrice($item['price']) ?> × <span id="qty_display">1</span></span></div>
            <div class="summary-row text-muted fs-sm"><span>Taxes & fees</span><span>Included</span></div>
            <div class="summary-row total"><span>Total</span><span id="total_display"><?= formatPrice($item['price']) ?></span></div>
          <?php else: ?>
            <div class="summary-row"><span>Rate per night</span><span><?= formatPrice($item['price_per_night']) ?></span></div>
            <div class="summary-row"><span>Nights</span><span id="nights_display">—</span></div>
            <div class="summary-row text-muted fs-sm"><span>Taxes & fees</span><span>Included</span></div>
            <div class="summary-row total"><span>Total</span><span id="total_display">—</span></div>
          <?php endif; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg btn-block" id="submitBtn">
        ✈ Confirm Booking
      </button>
    </form>
    <?php endif; ?>

  </div>
</div>

<footer class="footer">© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</footer>

<script>
const pricePerUnit = <?= $type === 'flight' ? $item['price'] : $item['price_per_night'] ?>;
const type = '<?= $type ?>';

function formatPrice(n) {
  return '₱' + n.toLocaleString('en-PH', {minimumFractionDigits:2});
}

function calcTotal() {
  const guests = parseInt(document.getElementById('guests').value) || 1;
  if (document.getElementById('qty_display')) document.getElementById('qty_display').textContent = guests;

  if (type === 'hotel') {
    const ci = document.getElementById('check_in')?.value;
    const co = document.getElementById('check_out')?.value;
    if (ci && co) {
      const nights = Math.max(1, (new Date(co) - new Date(ci)) / 86400000);
      document.getElementById('nights_display').textContent = nights + ' night' + (nights > 1 ? 's' : '');
      document.getElementById('total_display').textContent = formatPrice(pricePerUnit * nights * guests);
    }
  } else {
    document.getElementById('total_display').textContent = formatPrice(pricePerUnit * guests);
  }
}

function formatCard(el) {
  let v = el.value.replace(/\D/g,'').substring(0,16);
  el.value = v.replace(/(.{4})/g,'$1 ').trim();
}

document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
  document.getElementById('submitBtn').textContent = '⏳ Processing...';
  document.getElementById('submitBtn').disabled = true;
});
</script>
</body>
</html>
<?php $conn->close(); ?>
