<?php
require_once 'includes/auth.php';
requireLogin();
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle cancel
if (isset($_POST['cancel_booking'])) {
    $bid = (int)$_POST['booking_id'];

    // Fetch booking + item info before cancelling (for email)
    $brow = $conn->query(
        "SELECT b.*, u.name AS user_name, u.email AS user_email,
         f.origin, f.destination,
         h.hotel_name
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         LEFT JOIN flights f ON b.booking_type='flight' AND b.item_id=f.id
         LEFT JOIN hotels  h ON b.booking_type='hotel'  AND b.item_id=h.id
         WHERE b.id=$bid AND b.user_id=$user_id"
    )->fetch_assoc();

    $conn->query("UPDATE bookings SET status='cancelled' WHERE id=$bid AND user_id=$user_id");

    // Send cancellation email silently
    if ($brow) {
        require_once 'includes/mailer.php';
        SkyMailer::sendCancellation($brow, [
            'name'  => $brow['user_name'],
            'email' => $brow['user_email'],
        ]);
    }
}

// Handle profile update
$profile_msg = '';
if (isset($_POST['update_profile'])) {
    $name  = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $stmt  = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $email, $user_id);
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $profile_msg = 'success';
    }
}

// Fetch user
$user = getCurrentUser();

// Fetch bookings
$bookings = $conn->query(
    "SELECT b.*, 
     CASE WHEN b.booking_type='flight' THEN f.airline ELSE h.hotel_name END AS item_name,
     CASE WHEN b.booking_type='flight' THEN CONCAT(f.origin, ' → ', f.destination) ELSE h.location END AS item_detail,
     CASE WHEN b.booking_type='flight' THEN f.departure_time ELSE b.check_in END AS main_date
     FROM bookings b
     LEFT JOIN flights f ON b.booking_type='flight' AND b.item_id=f.id
     LEFT JOIN hotels h ON b.booking_type='hotel' AND b.item_id=h.id
     WHERE b.user_id=$user_id ORDER BY b.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$total_spent = array_sum(array_column(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled'), 'total_price'));
$active = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
$tab = $_GET['tab'] ?? 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Dashboard — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
  <a href="index.php" class="brand">✈ <?= SITE_NAME ?></a>
  <div class="nav-links">
    <a href="index.php">Explore</a>
    <a href="dashboard.php" class="active">Dashboard</a>
    <?php if (isAdmin()): ?><a href="admin/index.php">Admin</a><?php endif; ?>
  </div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="logout.php" class="btn btn-outline btn-sm">Sign Out</a>
  </div>
</nav>

<div class="layout-with-sidebar">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-brand"><div class="brand">✈ <?= SITE_NAME ?></div></div>
    <nav class="sidebar-menu">
      <a href="?tab=bookings" class="<?= $tab === 'bookings' ? 'active' : '' ?>"><span class="menu-icon">📋</span> My Bookings</a>
      <a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>"><span class="menu-icon">👤</span> My Profile</a>
      <a href="index.php"><span class="menu-icon">🔍</span> Search Flights</a>
      <a href="index.php?type=hotels"><span class="menu-icon">🏨</span> Search Hotels</a>
      <a href="logout.php"><span class="menu-icon">🚪</span> Sign Out</a>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="sidebar-content">

    <?php if ($tab === 'bookings'): ?>
      <h2 class="section-title">My Bookings</h2>
      <p class="section-sub">All your travel reservations</p>

      <!-- STATS -->
      <div class="dash-grid mb-2">
        <div class="stat-card">
          <div><div class="stat-value"><?= count($bookings) ?></div><div class="stat-label">Total Bookings</div></div>
          <div class="stat-icon">📋</div>
        </div>
        <div class="stat-card">
          <div><div class="stat-value"><?= $active ?></div><div class="stat-label">Active</div></div>
          <div class="stat-icon">✅</div>
        </div>
        <div class="stat-card">
          <div><div class="stat-value" style="font-size:1.3rem"><?= formatPrice($total_spent) ?></div><div class="stat-label">Total Spent</div></div>
          <div class="stat-icon">💰</div>
        </div>
        <div class="stat-card">
          <div><div class="stat-value"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')) ?></div><div class="stat-label">Cancelled</div></div>
          <div class="stat-icon">❌</div>
        </div>
      </div>

      <!-- BOOKINGS TABLE -->
      <div class="card">
        <?php if (empty($bookings)): ?>
          <div class="text-center" style="padding:3rem">
            <div style="font-size:3rem; margin-bottom:1rem">✈</div>
            <p class="text-muted">No bookings yet. <a href="index.php">Start exploring!</a></p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Name</th>
                  <th>Details</th>
                  <th>Date</th>
                  <th>Guests</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                  <td><span style="font-size:1.2rem"><?= $b['booking_type'] === 'flight' ? '✈' : '🏨' ?></span></td>
                  <td class="fw-600"><?= htmlspecialchars($b['item_name']) ?></td>
                  <td class="text-muted fs-sm"><?= htmlspecialchars($b['item_detail']) ?></td>
                  <td class="fs-sm"><?= $b['main_date'] ? date('M d, Y', strtotime($b['main_date'])) : '—' ?></td>
                  <td class="text-center"><?= $b['guests'] ?></td>
                  <td class="text-gold fw-600"><?= formatPrice($b['total_price']) ?></td>
                  <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                  <td>
                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap">
                      <a href="receipt.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm" title="Download PDF Receipt">⬇ PDF</a>
                      <?php if ($b['status'] === 'confirmed'): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this booking?')">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">Cancel</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'profile'): ?>
      <h2 class="section-title">My Profile</h2>
      <p class="section-sub">Manage your account information</p>

      <?php if ($profile_msg === 'success'): ?>
        <div class="alert alert-success">✓ Profile updated successfully.</div>
      <?php endif; ?>

      <div class="card" style="max-width:500px">
        <div class="card-header"><span class="card-title">👤 Account Details</span></div>
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Account Role</label>
            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled style="opacity:0.6">
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    <?php endif; ?>

  </div>
</div>

<footer class="footer">© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>
