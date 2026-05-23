<?php
require_once 'includes/auth.php';
$conn = getDBConnection();

// Search flights
$flights = [];
$hotels = [];
$search_type = $_GET['type'] ?? 'flights';
$search_done = false;

if (isset($_GET['search'])) {
    $search_done = true;
    if ($search_type === 'flights') {
        $origin = '%' . sanitize($_GET['origin'] ?? '') . '%';
        $dest   = '%' . sanitize($_GET['destination'] ?? '') . '%';
        $stmt = $conn->prepare("SELECT * FROM flights WHERE origin LIKE ? AND destination LIKE ? AND seats_available > 0 ORDER BY departure_time");
        $stmt->bind_param("ss", $origin, $dest);
        $stmt->execute();
        $flights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $location = '%' . sanitize($_GET['location'] ?? '') . '%';
        $stmt = $conn->prepare("SELECT * FROM hotels WHERE location LIKE ? AND rooms_available > 0 ORDER BY rating DESC");
        $stmt->bind_param("s", $location);
        $stmt->execute();
        $hotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Default listings (featured)
if (!$search_done) {
    $flights = $conn->query("SELECT * FROM flights WHERE seats_available > 0 ORDER BY RAND() LIMIT 6")->fetch_all(MYSQLI_ASSOC);
    $hotels  = $conn->query("SELECT * FROM hotels WHERE rooms_available > 0 ORDER BY rating DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= SITE_NAME ?> — Book Flights & Hotels</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="brand">✈ <?= SITE_NAME ?></div>
  <div class="nav-links">
    <a href="index.php" class="active">Explore</a>
    <?php if (isLoggedIn()): ?>
      <a href="dashboard.php">My Bookings</a>
      <?php if (isAdmin()): ?><a href="admin/index.php">Admin</a><?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="nav-user">
    <?php if (isLoggedIn()): ?>
      <div class="nav-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
      <span><?= htmlspecialchars($_SESSION['name']) ?></span>
      <a href="logout.php" class="btn btn-outline btn-sm">Sign Out</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
      <a href="register.php" class="btn btn-primary btn-sm">Register</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<div class="hero">
  <h1 class="hero-title">Your Journey Starts<br>with <span>One Click</span></h1>
  <p class="hero-subtitle">Search and book flights and hotels across the Philippines and beyond.</p>
</div>

<!-- SEARCH BOX -->
<div class="container">
  <div class="search-box">
    <form method="GET" action="index.php">
      <div class="search-tabs">
        <button type="button" class="search-tab <?= $search_type === 'flights' ? 'active' : '' ?>" onclick="setType('flights')">✈ Flights</button>
        <button type="button" class="search-tab <?= $search_type === 'hotels' ? 'active' : '' ?>" onclick="setType('hotels')">🏨 Hotels</button>
      </div>
      <input type="hidden" name="type" id="search_type" value="<?= $search_type ?>">
      <input type="hidden" name="search" value="1">

      <!-- FLIGHT SEARCH -->
      <div id="flight-fields" style="<?= $search_type !== 'flights' ? 'display:none' : '' ?>">
        <div class="search-row">
          <div class="form-group" style="margin:0">
            <label class="form-label">From</label>
            <input type="text" name="origin" class="form-control" placeholder="e.g. Manila" value="<?= htmlspecialchars($_GET['origin'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">To</label>
            <input type="text" name="destination" class="form-control" placeholder="e.g. Cebu" value="<?= htmlspecialchars($_GET['destination'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= $_GET['date'] ?? '' ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-lg">Search</button>
        </div>
      </div>

      <!-- HOTEL SEARCH -->
      <div id="hotel-fields" style="<?= $search_type !== 'hotels' ? 'display:none' : '' ?>">
        <div class="search-row">
          <div class="form-group" style="margin:0; grid-column: span 2">
            <label class="form-label">Destination / Location</label>
            <input type="text" name="location" class="form-control" placeholder="e.g. Cebu, Boracay, Manila" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Check-in</label>
            <input type="date" name="checkin" class="form-control" value="<?= $_GET['checkin'] ?? '' ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-lg">Search</button>
        </div>
      </div>
    </form>
  </div>

  <!-- RESULTS / FEATURED -->
  <div class="page-content">

    <?php if ($search_type === 'flights'): ?>
      <h2 class="section-title"><?= $search_done ? 'Flight Results' : 'Featured Flights' ?></h2>
      <p class="section-sub"><?= $search_done ? count($flights) . ' flights found' : 'Popular routes this season' ?></p>

      <?php if (empty($flights)): ?>
        <div class="alert alert-info">No flights found. Try a different route.</div>
      <?php else: ?>
        <?php foreach ($flights as $f): ?>
        <div class="flight-card">
          <div style="min-width:80px; text-align:center">
            <div style="font-size:1.5rem">✈</div>
            <div class="fs-sm text-muted"><?= htmlspecialchars($f['airline']) ?></div>
          </div>
          <div class="flight-route">
            <div>
              <div class="flight-city"><?= htmlspecialchars(explode('(', $f['origin'])[0]) ?></div>
              <div class="flight-time"><?= date('h:i A', strtotime($f['departure_time'])) ?></div>
            </div>
            <div class="flight-arrow">──────→</div>
            <div>
              <div class="flight-city"><?= htmlspecialchars(explode('(', $f['destination'])[0]) ?></div>
              <div class="flight-time"><?= date('h:i A', strtotime($f['arrival_time'])) ?></div>
            </div>
          </div>
          <div class="flight-meta text-center">
            <div><?= date('M d, Y', strtotime($f['departure_time'])) ?></div>
            <div class="mt-1"><?= $f['seats_available'] ?> seats left</div>
          </div>
          <div class="flight-price-wrap">
            <div class="listing-price"><?= formatPrice($f['price']) ?></div>
            <div class="fs-sm text-muted">per person</div>
            <a href="booking.php?type=flight&id=<?= $f['id'] ?>" class="btn btn-primary btn-sm mt-1">Book Now</a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php else: ?>
      <h2 class="section-title"><?= $search_done ? 'Hotel Results' : 'Featured Hotels' ?></h2>
      <p class="section-sub"><?= $search_done ? count($hotels) . ' hotels found' : 'Top-rated stays in the Philippines' ?></p>

      <?php if (empty($hotels)): ?>
        <div class="alert alert-info">No hotels found in that location.</div>
      <?php else: ?>
        <div class="listing-grid">
          <?php
          $emojis = ['🏖','🌴','🏝','🗻','🌆','🏔','🌅','🌊'];
          foreach ($hotels as $i => $h): ?>
          <div class="listing-card" onclick="window.location='booking.php?type=hotel&id=<?= $h['id'] ?>'">
            <div class="listing-img"><?= $emojis[$i % count($emojis)] ?></div>
            <div class="listing-body">
              <div class="listing-title"><?= htmlspecialchars($h['hotel_name']) ?></div>
              <div class="listing-location">📍 <?= htmlspecialchars($h['location']) ?></div>
              <div style="color:var(--text-muted); font-size:0.82rem; margin-bottom:0.5rem; line-height:1.5">
                <?= htmlspecialchars(substr($h['description'], 0, 80)) ?>...
              </div>
              <div class="listing-footer">
                <div>
                  <div class="listing-price"><?= formatPrice($h['price_per_night']) ?> <span>/ night</span></div>
                  <div class="stars mt-1">
                    <?= str_repeat('★', round($h['rating'])) ?><?= str_repeat('☆', 5 - round($h['rating'])) ?>
                    <span class="text-muted"><?= $h['rating'] ?></span>
                  </div>
                </div>
                <a href="booking.php?type=hotel&id=<?= $h['id'] ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">Book</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<footer class="footer">
  © <?= date('Y') ?> <?= SITE_NAME ?>. Built with PHP & MySQL.
</footer>

<script>
function setType(type) {
  document.getElementById('search_type').value = type;
  document.getElementById('flight-fields').style.display = type === 'flights' ? '' : 'none';
  document.getElementById('hotel-fields').style.display = type === 'hotels' ? '' : 'none';
  document.querySelectorAll('.search-tab').forEach((t, i) => {
    t.classList.toggle('active', (type === 'flights' && i === 0) || (type === 'hotels' && i === 1));
  });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
