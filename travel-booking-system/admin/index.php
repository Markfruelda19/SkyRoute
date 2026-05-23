<?php
require_once '../includes/auth.php';
requireLogin();
requireAdmin();
$conn = getDBConnection();

// Fetch stats
$total_users    = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$total_bookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0];
$total_revenue  = $conn->query("SELECT SUM(total_price) FROM bookings WHERE status='confirmed'")->fetch_row()[0] ?? 0;
$total_flights  = $conn->query("SELECT COUNT(*) FROM flights")->fetch_row()[0];

// Recent bookings
$recent = $conn->query(
    "SELECT b.*, u.name as user_name, u.email,
     CASE WHEN b.booking_type='flight' THEN CONCAT(f.airline, ': ', f.origin, ' → ', f.destination) 
          ELSE h.hotel_name END AS item_name
     FROM bookings b
     JOIN users u ON b.user_id=u.id
     LEFT JOIN flights f ON b.booking_type='flight' AND b.item_id=f.id
     LEFT JOIN hotels h ON b.booking_type='hotel' AND b.item_id=h.id
     ORDER BY b.created_at DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// Revenue by type
$flight_rev = $conn->query("SELECT SUM(total_price) FROM bookings WHERE booking_type='flight' AND status='confirmed'")->fetch_row()[0] ?? 0;
$hotel_rev  = $conn->query("SELECT SUM(total_price) FROM bookings WHERE booking_type='hotel' AND status='confirmed'")->fetch_row()[0] ?? 0;

$tab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Panel — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
<nav class="navbar">
  <a href="../index.php" class="brand">✈ <?= SITE_NAME ?></a>
  <div class="nav-links">
    <a href="../index.php">← Front End</a>
    <span class="badge badge-confirmed" style="font-size:0.75rem">Admin Panel</span>
  </div>
  <div class="nav-user">
    <div class="nav-avatar">A</div>
    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="../logout.php" class="btn btn-outline btn-sm">Sign Out</a>
  </div>
</nav>

<div class="layout-with-sidebar">
  <div class="sidebar">
    <div class="sidebar-brand"><div class="brand">⚙ Admin</div></div>
    <nav class="sidebar-menu">
      <a href="?tab=overview" class="<?= $tab==='overview'?'active':'' ?>"><span class="menu-icon">📊</span> Overview</a>
      <a href="?tab=bookings" class="<?= $tab==='bookings'?'active':'' ?>"><span class="menu-icon">📋</span> All Bookings</a>
      <a href="?tab=users" class="<?= $tab==='users'?'active':'' ?>"><span class="menu-icon">👥</span> Users</a>
      <a href="?tab=flights" class="<?= $tab==='flights'?'active':'' ?>"><span class="menu-icon">✈</span> Flights</a>
      <a href="?tab=hotels" class="<?= $tab==='hotels'?'active':'' ?>"><span class="menu-icon">🏨</span> Hotels</a>
    </nav>
  </div>

  <div class="sidebar-content">

    <?php if ($tab === 'overview'): ?>
      <h2 class="section-title">Admin Overview</h2>
      <p class="section-sub">Platform performance at a glance</p>

      <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem">
        <div class="stat-card"><div><div class="stat-value"><?= $total_users ?></div><div class="stat-label">Total Users</div></div><div class="stat-icon">👥</div></div>
        <div class="stat-card"><div><div class="stat-value"><?= $total_bookings ?></div><div class="stat-label">Bookings</div></div><div class="stat-icon">📋</div></div>
        <div class="stat-card"><div><div class="stat-value" style="font-size:1.1rem"><?= formatPrice($total_revenue) ?></div><div class="stat-label">Revenue</div></div><div class="stat-icon">💰</div></div>
        <div class="stat-card"><div><div class="stat-value"><?= $total_flights ?></div><div class="stat-label">Flights Listed</div></div><div class="stat-icon">✈</div></div>
      </div>

      <!-- CHARTS ROW -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.5rem">
        <div class="card">
          <div class="card-header"><span class="card-title">📊 Revenue by Type</span></div>
          <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">📈 Booking Status</span></div>
          <canvas id="statusChart" height="200"></canvas>
        </div>
      </div>

      <!-- RECENT BOOKINGS -->
      <div class="card">
        <div class="card-header"><span class="card-title">🕐 Recent Bookings</span><a href="?tab=bookings" class="btn btn-outline btn-sm">View All</a></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>User</th><th>Item</th><th>Type</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $b): ?>
              <tr>
                <td class="text-muted fs-sm">#<?= $b['id'] ?></td>
                <td><div class="fw-600"><?= htmlspecialchars($b['user_name']) ?></div><div class="text-muted fs-sm"><?= htmlspecialchars($b['email']) ?></div></td>
                <td class="fs-sm"><?= htmlspecialchars(substr($b['item_name'], 0, 40)) ?></td>
                <td><span style="font-size:1rem"><?= $b['booking_type']==='flight'?'✈':'🏨' ?></span></td>
                <td class="text-gold fw-600"><?= formatPrice($b['total_price']) ?></td>
                <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                <td class="fs-sm text-muted"><?= date('M d', strtotime($b['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($tab === 'bookings'): ?>
      <?php
      // Admin can update booking status
      if (isset($_POST['update_status'])) {
          $bid = (int)$_POST['booking_id'];
          $st  = sanitize($_POST['status']);
          $conn->query("UPDATE bookings SET status='$st' WHERE id=$bid");
      }
      $all_bookings = $conn->query(
          "SELECT b.*, u.name as user_name,
           CASE WHEN b.booking_type='flight' THEN CONCAT(f.airline, ': ', f.origin, ' → ', f.destination) 
                ELSE h.hotel_name END AS item_name
           FROM bookings b JOIN users u ON b.user_id=u.id
           LEFT JOIN flights f ON b.booking_type='flight' AND b.item_id=f.id
           LEFT JOIN hotels h ON b.booking_type='hotel' AND b.item_id=h.id
           ORDER BY b.created_at DESC"
      )->fetch_all(MYSQLI_ASSOC);
      ?>
      <h2 class="section-title">All Bookings</h2>
      <p class="section-sub"><?= count($all_bookings) ?> total bookings</p>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>User</th><th>Item</th><th>Type</th><th>Guests</th><th>Total</th><th>TXN</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
              <?php foreach ($all_bookings as $b): ?>
              <tr>
                <td class="text-muted fs-sm">#<?= $b['id'] ?></td>
                <td><?= htmlspecialchars($b['user_name']) ?></td>
                <td class="fs-sm"><?= htmlspecialchars(substr($b['item_name'], 0, 35)) ?></td>
                <td><?= $b['booking_type']==='flight'?'✈':'🏨' ?></td>
                <td class="text-center"><?= $b['guests'] ?></td>
                <td class="text-gold fw-600"><?= formatPrice($b['total_price']) ?></td>
                <td class="fs-sm text-muted" style="font-family:monospace"><?= $b['transaction_id'] ?></td>
                <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                <td>
                  <form method="POST" style="display:flex; gap:0.3rem">
                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                    <select name="status" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.8rem; width:auto">
                      <option value="confirmed" <?= $b['status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                      <option value="pending" <?= $b['status']==='pending'?'selected':'' ?>>Pending</option>
                      <option value="cancelled" <?= $b['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-outline btn-sm">✓</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($tab === 'users'): ?>
      <?php
      $users = $conn->query("SELECT u.*, COUNT(b.id) as total_bookings FROM users u LEFT JOIN bookings b ON u.id=b.user_id GROUP BY u.id ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);
      ?>
      <h2 class="section-title">All Users</h2>
      <p class="section-sub"><?= count($users) ?> registered users</p>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Bookings</th><th>Joined</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td class="text-muted fs-sm">#<?= $u['id'] ?></td>
                <td class="fw-600">
                  <div style="display:flex; align-items:center; gap:0.5rem">
                    <div class="nav-avatar" style="width:28px; height:28px; font-size:0.75rem"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                    <?= htmlspecialchars($u['name']) ?>
                  </div>
                </td>
                <td class="text-muted fs-sm"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge <?= $u['role']==='admin' ? 'badge-pending' : 'badge-confirmed' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td class="text-center"><?= $u['total_bookings'] ?></td>
                <td class="text-muted fs-sm"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($tab === 'flights'): ?>
      <?php
      if (isset($_POST['delete_flight'])) $conn->query("DELETE FROM flights WHERE id=" . (int)$_POST['fid']);
      if (isset($_POST['add_flight'])) {
          $a = sanitize($_POST['airline']); $o = sanitize($_POST['origin']); $d = sanitize($_POST['destination']);
          $dep = sanitize($_POST['departure_time']); $arr = sanitize($_POST['arrival_time']);
          $p = (float)$_POST['price']; $s = (int)$_POST['seats'];
          $conn->prepare("INSERT INTO flights (airline,origin,destination,departure_time,arrival_time,price,seats_available) VALUES(?,?,?,?,?,?,?)")->bind_param("sssssdi",$a,$o,$d,$dep,$arr,$p,$s) && $conn->prepare("INSERT INTO flights (airline,origin,destination,departure_time,arrival_time,price,seats_available) VALUES(?,?,?,?,?,?,?)")->execute();
          // Simple insert
          $stmt = $conn->prepare("INSERT INTO flights (airline,origin,destination,departure_time,arrival_time,price,seats_available) VALUES(?,?,?,?,?,?,?)");
          $stmt->bind_param("sssssdi",$a,$o,$d,$dep,$arr,$p,$s);
          $stmt->execute();
      }
      $all_flights = $conn->query("SELECT * FROM flights ORDER BY departure_time")->fetch_all(MYSQLI_ASSOC);
      ?>
      <div class="flex-between mb-2">
        <div><h2 class="section-title">Manage Flights</h2><p class="section-sub"><?= count($all_flights) ?> flights listed</p></div>
        <button class="btn btn-primary" onclick="document.getElementById('addFlightModal').classList.add('show')">+ Add Flight</button>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Airline</th><th>Origin</th><th>Destination</th><th>Departure</th><th>Price</th><th>Seats</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($all_flights as $f): ?>
              <tr>
                <td class="fw-600"><?= htmlspecialchars($f['airline']) ?></td>
                <td><?= htmlspecialchars($f['origin']) ?></td>
                <td><?= htmlspecialchars($f['destination']) ?></td>
                <td class="fs-sm"><?= date('M d, Y h:i A', strtotime($f['departure_time'])) ?></td>
                <td class="text-gold"><?= formatPrice($f['price']) ?></td>
                <td><?= $f['seats_available'] ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Delete this flight?')">
                    <input type="hidden" name="fid" value="<?= $f['id'] ?>">
                    <button type="submit" name="delete_flight" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ADD FLIGHT MODAL -->
      <div class="modal-backdrop" id="addFlightModal" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="modal">
          <div class="modal-header"><span class="modal-title">Add New Flight</span><button class="modal-close" onclick="document.getElementById('addFlightModal').classList.remove('show')">×</button></div>
          <form method="POST">
            <div class="form-group"><label class="form-label">Airline</label><input type="text" name="airline" class="form-control" required></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div class="form-group"><label class="form-label">Origin</label><input type="text" name="origin" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Destination</label><input type="text" name="destination" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Departure</label><input type="datetime-local" name="departure_time" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Arrival</label><input type="datetime-local" name="arrival_time" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Price (₱)</label><input type="number" name="price" class="form-control" step="0.01" required></div>
              <div class="form-group"><label class="form-label">Seats Available</label><input type="number" name="seats" class="form-control" value="100" required></div>
            </div>
            <button type="submit" name="add_flight" class="btn btn-primary btn-block">Add Flight</button>
          </form>
        </div>
      </div>

    <?php elseif ($tab === 'hotels'): ?>
      <?php
      if (isset($_POST['delete_hotel'])) $conn->query("DELETE FROM hotels WHERE id=" . (int)$_POST['hid']);
      if (isset($_POST['add_hotel'])) {
          $hn = sanitize($_POST['hotel_name']); $loc = sanitize($_POST['location']); $desc = sanitize($_POST['description']);
          $ppn = (float)$_POST['price_per_night']; $rat = (float)$_POST['rating']; $rooms = (int)$_POST['rooms'];
          $stmt = $conn->prepare("INSERT INTO hotels (hotel_name,location,description,price_per_night,rating,rooms_available) VALUES(?,?,?,?,?,?)");
          $stmt->bind_param("sssddi",$hn,$loc,$desc,$ppn,$rat,$rooms);
          $stmt->execute();
      }
      $all_hotels = $conn->query("SELECT * FROM hotels ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
      ?>
      <div class="flex-between mb-2">
        <div><h2 class="section-title">Manage Hotels</h2><p class="section-sub"><?= count($all_hotels) ?> hotels listed</p></div>
        <button class="btn btn-primary" onclick="document.getElementById('addHotelModal').classList.add('show')">+ Add Hotel</button>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Hotel</th><th>Location</th><th>Price/Night</th><th>Rating</th><th>Rooms</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($all_hotels as $h): ?>
              <tr>
                <td class="fw-600"><?= htmlspecialchars($h['hotel_name']) ?></td>
                <td class="text-muted fs-sm">📍 <?= htmlspecialchars($h['location']) ?></td>
                <td class="text-gold"><?= formatPrice($h['price_per_night']) ?></td>
                <td><span class="stars"><?= str_repeat('★', round($h['rating'])) ?></span> <?= $h['rating'] ?></td>
                <td><?= $h['rooms_available'] ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Delete this hotel?')">
                    <input type="hidden" name="hid" value="<?= $h['id'] ?>">
                    <button type="submit" name="delete_hotel" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ADD HOTEL MODAL -->
      <div class="modal-backdrop" id="addHotelModal" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="modal">
          <div class="modal-header"><span class="modal-title">Add New Hotel</span><button class="modal-close" onclick="document.getElementById('addHotelModal').classList.remove('show')">×</button></div>
          <form method="POST">
            <div class="form-group"><label class="form-label">Hotel Name</label><input type="text" name="hotel_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
              <div class="form-group"><label class="form-label">Price/Night (₱)</label><input type="number" name="price_per_night" class="form-control" step="0.01" required></div>
              <div class="form-group"><label class="form-label">Rating (1-5)</label><input type="number" name="rating" class="form-control" min="1" max="5" step="0.1" value="4.0" required></div>
              <div class="form-group"><label class="form-label">Rooms</label><input type="number" name="rooms" class="form-control" value="20" required></div>
            </div>
            <button type="submit" name="add_hotel" class="btn btn-primary btn-block">Add Hotel</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<footer class="footer">© <?= date('Y') ?> <?= SITE_NAME ?> Admin Panel</footer>

<script>
<?php if ($tab === 'overview'): ?>
// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
  type: 'doughnut',
  data: {
    labels: ['Flights', 'Hotels'],
    datasets: [{
      data: [<?= $flight_rev ?>, <?= $hotel_rev ?>],
      backgroundColor: ['rgba(201,168,76,0.8)', 'rgba(26,58,92,0.9)'],
      borderColor: ['#c9a84c', '#1a3a5c'],
      borderWidth: 2
    }]
  },
  options: {
    plugins: { legend: { labels: { color: '#8892a4' } } },
    cutout: '65%'
  }
});

// Status Chart
<?php
$confirmed  = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetch_row()[0];
$pending    = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetch_row()[0];
$cancelled2 = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetch_row()[0];
?>
new Chart(document.getElementById('statusChart'), {
  type: 'bar',
  data: {
    labels: ['Confirmed', 'Pending', 'Cancelled'],
    datasets: [{
      data: [<?= $confirmed ?>, <?= $pending ?>, <?= $cancelled2 ?>],
      backgroundColor: ['rgba(76,175,130,0.6)', 'rgba(201,168,76,0.6)', 'rgba(224,92,92,0.6)'],
      borderRadius: 6
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#8892a4' }, grid: { color: 'rgba(255,255,255,0.05)' } },
      y: { ticks: { color: '#8892a4' }, grid: { color: 'rgba(255,255,255,0.05)' } }
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
