<?php
/**
 * analytics.php — Booking Analytics Dashboard
 * Admin-only. Provides revenue, booking trends, top routes/hotels, and more.
 */
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();

// ── KPI CARDS ────────────────────────────────────────────────────────────────
$kpi = [];

$kpi['total_revenue']   = (float)($conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='confirmed'")->fetch_row()[0]);
$kpi['total_bookings']  = (int)($conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0]);
$kpi['confirmed']       = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetch_row()[0]);
$kpi['cancelled']       = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetch_row()[0]);
$kpi['total_users']     = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0]);
$kpi['flight_bookings'] = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE booking_type='flight'")->fetch_row()[0]);
$kpi['hotel_bookings']  = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE booking_type='hotel'")->fetch_row()[0]);
$kpi['avg_booking']     = $kpi['total_bookings'] > 0
    ? round($kpi['total_revenue'] / max($kpi['confirmed'], 1), 2)
    : 0;
$kpi['conversion_rate'] = $kpi['total_bookings'] > 0
    ? round(($kpi['confirmed'] / $kpi['total_bookings']) * 100, 1)
    : 0;

// ── REVENUE BY MONTH (last 12 months) ────────────────────────────────────────
$monthly = $conn->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
            DATE_FORMAT(created_at, '%b %Y')  AS label,
            SUM(total_price)                   AS revenue,
            COUNT(*)                           AS bookings
     FROM bookings
     WHERE status='confirmed'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY month, label
     ORDER BY month ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── BOOKINGS BY TYPE PER MONTH ────────────────────────────────────────────────
$by_type_month = $conn->query(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
            DATE_FORMAT(created_at,'%b %Y')  AS label,
            booking_type,
            COUNT(*) AS cnt
     FROM bookings
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month, label, booking_type
     ORDER BY month ASC"
)->fetch_all(MYSQLI_ASSOC);

// Build aligned month arrays for stacked bar
$month_keys = array_unique(array_column($by_type_month, 'month'));
sort($month_keys);
$month_labels_type = [];
$flight_counts = [];
$hotel_counts  = [];
foreach ($month_keys as $mk) {
    $rows = array_filter($by_type_month, fn($r) => $r['month'] === $mk);
    $label = '';
    $f = 0; $h = 0;
    foreach ($rows as $r) {
        $label = $r['label'];
        if ($r['booking_type'] === 'flight') $f = (int)$r['cnt'];
        else                                  $h = (int)$r['cnt'];
    }
    $month_labels_type[] = $label;
    $flight_counts[]     = $f;
    $hotel_counts[]      = $h;
}

// ── STATUS BREAKDOWN ─────────────────────────────────────────────────────────
$status_data = $conn->query(
    "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status"
)->fetch_all(MYSQLI_ASSOC);

// ── TOP FLIGHT ROUTES ─────────────────────────────────────────────────────────
$top_routes = $conn->query(
    "SELECT CONCAT(f.origin, ' → ', f.destination) AS route,
            COUNT(b.id)        AS bookings,
            SUM(b.total_price) AS revenue
     FROM bookings b
     JOIN flights f ON b.item_id = f.id AND b.booking_type = 'flight'
     WHERE b.status = 'confirmed'
     GROUP BY route
     ORDER BY bookings DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// ── TOP HOTELS ────────────────────────────────────────────────────────────────
$top_hotels = $conn->query(
    "SELECT h.hotel_name, h.location,
            COUNT(b.id)        AS bookings,
            SUM(b.total_price) AS revenue,
            h.rating
     FROM bookings b
     JOIN hotels h ON b.item_id = h.id AND b.booking_type = 'hotel'
     WHERE b.status = 'confirmed'
     GROUP BY h.id, h.hotel_name, h.location, h.rating
     ORDER BY bookings DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// ── REVENUE BY BOOKING TYPE ───────────────────────────────────────────────────
$flight_rev = (float)($conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE booking_type='flight' AND status='confirmed'")->fetch_row()[0]);
$hotel_rev  = (float)($conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE booking_type='hotel'  AND status='confirmed'")->fetch_row()[0]);

// ── BOOKINGS PER DAY (last 30 days) ──────────────────────────────────────────
$daily = $conn->query(
    "SELECT DATE(created_at) AS day,
            DATE_FORMAT(created_at, '%b %d') AS label,
            COUNT(*) AS cnt
     FROM bookings
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY day, label
     ORDER BY day ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── RECENT HIGH-VALUE BOOKINGS ────────────────────────────────────────────────
$high_value = $conn->query(
    "SELECT b.id, b.total_price, b.booking_type, b.created_at,
            u.name AS user_name,
            CASE WHEN b.booking_type='flight'
                 THEN CONCAT(f.origin, ' → ', f.destination)
                 ELSE h.hotel_name END AS item_name
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     LEFT JOIN flights f ON b.booking_type='flight' AND b.item_id=f.id
     LEFT JOIN hotels  h ON b.booking_type='hotel'  AND b.item_id=h.id
     WHERE b.status = 'confirmed'
     ORDER BY b.total_price DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// ── ENCODE DATA FOR CHARTS ────────────────────────────────────────────────────
$monthly_labels  = json_encode(array_column($monthly, 'label'));
$monthly_revenue = json_encode(array_map(fn($r) => (float)$r['revenue'], $monthly));
$monthly_counts  = json_encode(array_map(fn($r) => (int)$r['bookings'],  $monthly));

$daily_labels = json_encode(array_column($daily, 'label'));
$daily_counts = json_encode(array_map(fn($r) => (int)$r['cnt'], $daily));

$status_labels = json_encode(array_column($status_data, 'status'));
$status_counts = json_encode(array_map(fn($r) => (int)$r['cnt'], $status_data));

$type_labels    = json_encode($month_labels_type);
$type_flights   = json_encode($flight_counts);
$type_hotels    = json_encode($hotel_counts);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <style>
    /* ── ANALYTICS-SPECIFIC STYLES ───────────────────────────────────── */
    :root {
      --chart-gold:   rgba(201,168,76,1);
      --chart-gold-t: rgba(201,168,76,0.15);
      --chart-blue:   rgba(26,120,200,1);
      --chart-blue-t: rgba(26,120,200,0.15);
      --chart-green:  rgba(76,175,130,1);
      --chart-red:    rgba(224,92,92,1);
    }

    /* Page header */
    .analytics-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-bottom: 1.75rem;
    }
    .analytics-header h1 { font-size: 1.75rem; color: var(--white); margin-bottom: 0.25rem; }
    .analytics-header p  { color: var(--text-muted); font-size: 0.88rem; }
    .date-badge {
      background: rgba(201,168,76,0.1);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 0.35rem 0.9rem;
      font-size: 0.8rem;
      color: var(--gold);
    }

    /* KPI grid */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .kpi-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.1rem 1.25rem;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 36px rgba(0,0,0,0.45); }
    .kpi-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 3px; height: 100%;
      background: var(--kpi-accent, var(--gold));
    }
    .kpi-icon {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      opacity: 0.8;
    }
    .kpi-value {
      font-family: 'Playfair Display', serif;
      font-size: 1.65rem;
      font-weight: 700;
      color: var(--white);
      line-height: 1;
      margin-bottom: 0.3rem;
    }
    .kpi-label {
      font-size: 0.78rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.6px;
    }
    .kpi-sub {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-top: 0.4rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .kpi-sub .up   { color: var(--success); }
    .kpi-sub .down { color: var(--danger); }

    /* Charts grid */
    .charts-row { display: grid; gap: 1.25rem; margin-bottom: 1.25rem; }
    .charts-row-2 { grid-template-columns: 1fr 1fr; }
    .charts-row-3 { grid-template-columns: 2fr 1fr; }

    .chart-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
    }
    .chart-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      color: var(--gold);
      margin-bottom: 0.2rem;
    }
    .chart-sub {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-bottom: 1.25rem;
    }
    .chart-wrap { position: relative; }

    /* Tables inside analytics */
    .rank-table { width: 100%; border-collapse: collapse; }
    .rank-table td { padding: 0.65rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.88rem; }
    .rank-table tr:last-child td { border-bottom: none; }
    .rank-num {
      width: 24px; height: 24px;
      border-radius: 50%;
      background: rgba(201,168,76,0.12);
      color: var(--gold);
      font-size: 0.72rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 0.5rem;
    }
    .rank-bar-wrap { height: 4px; background: rgba(255,255,255,0.06); border-radius: 2px; margin-top: 4px; }
    .rank-bar      { height: 4px; border-radius: 2px; background: linear-gradient(90deg, var(--gold), var(--gold-light)); }

    /* Inline mini-stat pill */
    .mini-pill {
      display: inline-block;
      padding: 0.18rem 0.55rem;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 600;
    }
    .pill-flight { background: rgba(26,120,200,0.15); color: #5ab4ff; }
    .pill-hotel  { background: rgba(201,168,76,0.15);  color: var(--gold); }

    /* Conversion meter */
    .meter-wrap { margin-top: 1rem; }
    .meter-label { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.4rem; }
    .meter-track { height: 8px; background: rgba(255,255,255,0.07); border-radius: 4px; overflow: hidden; }
    .meter-fill  { height: 100%; border-radius: 4px; transition: width 1s ease; }
    .meter-fill.green { background: linear-gradient(90deg, var(--success), #6ee0aa); }
    .meter-fill.gold  { background: linear-gradient(90deg, var(--gold), var(--gold-light)); }
    .meter-fill.red   { background: linear-gradient(90deg, var(--danger), #f08080); }

    @media (max-width: 900px) {
      .kpi-grid       { grid-template-columns: repeat(2, 1fr); }
      .charts-row-2   { grid-template-columns: 1fr; }
      .charts-row-3   { grid-template-columns: 1fr; }
    }
    @media (max-width: 540px) {
      .kpi-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="../index.php" class="brand">✈ <?= SITE_NAME ?></a>
  <div class="nav-links">
    <a href="../index.php">Front End</a>
    <a href="index.php">Admin</a>
    <a href="analytics.php" class="active">Analytics</a>
  </div>
  <div class="nav-user">
    <div class="nav-avatar">A</div>
    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="../logout.php" class="btn btn-outline btn-sm">Sign Out</a>
  </div>
</nav>

<div class="layout-with-sidebar">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-brand"><div class="brand">⚙ Admin</div></div>
    <nav class="sidebar-menu">
      <a href="index.php?tab=overview"><span class="menu-icon">📊</span> Overview</a>
      <a href="analytics.php" class="active"><span class="menu-icon">📈</span> Analytics</a>
      <a href="index.php?tab=bookings"><span class="menu-icon">📋</span> All Bookings</a>
      <a href="index.php?tab=users"><span class="menu-icon">👥</span> Users</a>
      <a href="index.php?tab=flights"><span class="menu-icon">✈</span> Flights</a>
      <a href="index.php?tab=hotels"><span class="menu-icon">🏨</span> Hotels</a>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="sidebar-content">

    <!-- HEADER -->
    <div class="analytics-header">
      <div>
        <h1>Booking Analytics</h1>
        <p>Revenue, trends, and performance insights</p>
      </div>
      <span class="date-badge">📅 <?= date('F Y') ?></span>
    </div>

    <!-- ── ROW 1: KPI CARDS ───────────────────────────────────────────── -->
    <div class="kpi-grid">

      <div class="kpi-card" style="--kpi-accent: var(--gold)">
        <div class="kpi-icon">💰</div>
        <div class="kpi-value"><?= CURRENCY . number_format($kpi['total_revenue'], 0) ?></div>
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-sub">Confirmed bookings only</div>
      </div>

      <div class="kpi-card" style="--kpi-accent: #4caf82">
        <div class="kpi-icon">📋</div>
        <div class="kpi-value"><?= number_format($kpi['total_bookings']) ?></div>
        <div class="kpi-label">Total Bookings</div>
        <div class="kpi-sub">
          <span class="up">✓ <?= $kpi['confirmed'] ?> confirmed</span>
          · <?= $kpi['cancelled'] ?> cancelled
        </div>
      </div>

      <div class="kpi-card" style="--kpi-accent: #5ab4ff">
        <div class="kpi-icon">📊</div>
        <div class="kpi-value"><?= CURRENCY . number_format($kpi['avg_booking'], 0) ?></div>
        <div class="kpi-label">Avg. Booking Value</div>
        <div class="kpi-sub">Per confirmed booking</div>
      </div>

      <div class="kpi-card" style="--kpi-accent: #c97ae0">
        <div class="kpi-icon">👥</div>
        <div class="kpi-value"><?= $kpi['total_users'] ?></div>
        <div class="kpi-label">Registered Users</div>
        <div class="kpi-sub"><?= $kpi['conversion_rate'] ?>% conversion rate</div>
      </div>

    </div>

    <!-- ── ROW 2: REVENUE OVER TIME + DONUT ──────────────────────────── -->
    <div class="charts-row charts-row-3">

      <div class="chart-card">
        <div class="chart-title">Monthly Revenue</div>
        <div class="chart-sub">Confirmed bookings · last 12 months</div>
        <div class="chart-wrap"><canvas id="revenueLineChart" height="200"></canvas></div>
      </div>

      <div class="chart-card">
        <div class="chart-title">Revenue Mix</div>
        <div class="chart-sub">Flights vs Hotels</div>
        <div class="chart-wrap"><canvas id="revenueMixChart" height="200"></canvas></div>
        <div style="margin-top:1rem">
          <div class="meter-label"><span>✈ Flights</span><span><?= CURRENCY . number_format($flight_rev, 0) ?></span></div>
          <div class="meter-track"><div class="meter-fill gold" style="width:<?= $kpi['total_revenue'] > 0 ? round(($flight_rev/$kpi['total_revenue'])*100) : 0 ?>%"></div></div>
          <div class="meter-label" style="margin-top:0.6rem"><span>🏨 Hotels</span><span><?= CURRENCY . number_format($hotel_rev, 0) ?></span></div>
          <div class="meter-track"><div class="meter-fill green" style="width:<?= $kpi['total_revenue'] > 0 ? round(($hotel_rev/$kpi['total_revenue'])*100) : 0 ?>%"></div></div>
        </div>
      </div>

    </div>

    <!-- ── ROW 3: STACKED BAR + STATUS DONUT ─────────────────────────── -->
    <div class="charts-row charts-row-3">

      <div class="chart-card">
        <div class="chart-title">Bookings by Type</div>
        <div class="chart-sub">Flights vs Hotels per month · last 6 months</div>
        <div class="chart-wrap"><canvas id="stackedBarChart" height="200"></canvas></div>
      </div>

      <div class="chart-card">
        <div class="chart-title">Booking Status</div>
        <div class="chart-sub">Breakdown across all bookings</div>
        <div class="chart-wrap"><canvas id="statusChart" height="160"></canvas></div>
        <div style="margin-top:1rem">
          <?php foreach ($status_data as $s): ?>
          <?php
            $colors_map = ['confirmed'=>['label'=>'✓ Confirmed','cls'=>'green'], 'pending'=>['label'=>'⏳ Pending','cls'=>'gold'], 'cancelled'=>['label'=>'✕ Cancelled','cls'=>'red']];
            $info = $colors_map[$s['status']] ?? ['label'=>ucfirst($s['status']),'cls'=>'gold'];
            $pct = $kpi['total_bookings'] > 0 ? round(($s['cnt']/$kpi['total_bookings'])*100) : 0;
          ?>
          <div class="meter-label"><span><?= $info['label'] ?></span><span><?= $s['cnt'] ?> (<?= $pct ?>%)</span></div>
          <div class="meter-track" style="margin-bottom:0.5rem"><div class="meter-fill <?= $info['cls'] ?>" style="width:<?= $pct ?>%"></div></div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- ── ROW 4: DAILY TREND ─────────────────────────────────────────── -->
    <div class="charts-row" style="grid-template-columns:1fr; margin-bottom:1.25rem">
      <div class="chart-card">
        <div class="chart-title">Daily Booking Volume</div>
        <div class="chart-sub">All bookings · last 30 days</div>
        <div class="chart-wrap"><canvas id="dailyChart" height="100"></canvas></div>
      </div>
    </div>

    <!-- ── ROW 5: TOP ROUTES + TOP HOTELS ───────────────────────────── -->
    <div class="charts-row charts-row-2">

      <!-- TOP ROUTES -->
      <div class="chart-card">
        <div class="chart-title">✈ Top Flight Routes</div>
        <div class="chart-sub">By number of confirmed bookings</div>
        <?php if (empty($top_routes)): ?>
          <p class="text-muted fs-sm" style="padding:1rem 0">No flight bookings yet.</p>
        <?php else: ?>
          <?php $max_r = max(array_column($top_routes, 'bookings')); ?>
          <table class="rank-table">
            <?php foreach ($top_routes as $i => $r): ?>
            <tr>
              <td style="width:32px; vertical-align:top; padding-top:0.8rem">
                <span class="rank-num"><?= $i+1 ?></span>
              </td>
              <td>
                <div class="fw-600 fs-sm"><?= htmlspecialchars($r['route']) ?></div>
                <div class="rank-bar-wrap">
                  <div class="rank-bar" style="width:<?= round(($r['bookings']/$max_r)*100) ?>%"></div>
                </div>
              </td>
              <td style="text-align:right; white-space:nowrap; padding-left:1rem">
                <div class="text-gold fw-600"><?= $r['bookings'] ?> <span class="text-muted fw-400">bkgs</span></div>
                <div class="fs-sm text-muted"><?= formatPrice($r['revenue']) ?></div>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- TOP HOTELS -->
      <div class="chart-card">
        <div class="chart-title">🏨 Top Hotels</div>
        <div class="chart-sub">By number of confirmed bookings</div>
        <?php if (empty($top_hotels)): ?>
          <p class="text-muted fs-sm" style="padding:1rem 0">No hotel bookings yet.</p>
        <?php else: ?>
          <?php $max_h = max(array_column($top_hotels, 'bookings')); ?>
          <table class="rank-table">
            <?php foreach ($top_hotels as $i => $h): ?>
            <tr>
              <td style="width:32px; vertical-align:top; padding-top:0.8rem">
                <span class="rank-num"><?= $i+1 ?></span>
              </td>
              <td>
                <div class="fw-600 fs-sm"><?= htmlspecialchars($h['hotel_name']) ?></div>
                <div class="text-muted" style="font-size:0.75rem">📍 <?= htmlspecialchars($h['location']) ?></div>
                <div class="rank-bar-wrap">
                  <div class="rank-bar" style="width:<?= round(($h['bookings']/$max_h)*100) ?>%; background:linear-gradient(90deg,#4caf82,#6ee0aa)"></div>
                </div>
              </td>
              <td style="text-align:right; white-space:nowrap; padding-left:1rem">
                <div class="text-gold fw-600"><?= $h['bookings'] ?> <span class="text-muted fw-400">bkgs</span></div>
                <div class="fs-sm text-muted"><?= formatPrice($h['revenue']) ?></div>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

    </div>

    <!-- ── ROW 6: HIGH-VALUE BOOKINGS ─────────────────────────────────── -->
    <div class="chart-card" style="margin-bottom:1.25rem">
      <div class="chart-title">💎 Highest Value Bookings</div>
      <div class="chart-sub">Top 5 confirmed bookings by total price</div>
      <?php if (empty($high_value)): ?>
        <p class="text-muted fs-sm">No confirmed bookings yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Booking</th>
                <th>Type</th>
                <th>Total</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($high_value as $i => $b): ?>
              <tr>
                <td><span class="rank-num"><?= $i+1 ?></span></td>
                <td class="fw-600"><?= htmlspecialchars($b['user_name']) ?></td>
                <td class="fs-sm"><?= htmlspecialchars($b['item_name']) ?></td>
                <td>
                  <span class="mini-pill pill-<?= $b['booking_type'] ?>">
                    <?= $b['booking_type'] === 'flight' ? '✈ Flight' : '🏨 Hotel' ?>
                  </span>
                </td>
                <td class="text-gold fw-600" style="font-size:1rem"><?= formatPrice($b['total_price']) ?></td>
                <td class="text-muted fs-sm"><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── ROW 7: CONVERSION + SUMMARY ───────────────────────────────── -->
    <div class="charts-row charts-row-2" style="margin-bottom:2rem">

      <div class="chart-card">
        <div class="chart-title">🎯 Conversion Metrics</div>
        <div class="chart-sub">Booking funnel performance</div>
        <div class="meter-wrap">
          <div class="meter-label"><span>Confirmation Rate</span><span class="text-gold"><?= $kpi['conversion_rate'] ?>%</span></div>
          <div class="meter-track"><div class="meter-fill green" style="width:<?= $kpi['conversion_rate'] ?>%"></div></div>

          <div class="meter-label" style="margin-top:1rem">
            <span>Cancellation Rate</span>
            <span style="color:var(--danger)"><?= $kpi['total_bookings'] > 0 ? round(($kpi['cancelled']/$kpi['total_bookings'])*100,1) : 0 ?>%</span>
          </div>
          <div class="meter-track"><div class="meter-fill red" style="width:<?= $kpi['total_bookings'] > 0 ? round(($kpi['cancelled']/$kpi['total_bookings'])*100) : 0 ?>%"></div></div>

          <div class="meter-label" style="margin-top:1rem"><span>Flight Share</span><span class="text-gold"><?= $kpi['total_bookings'] > 0 ? round(($kpi['flight_bookings']/$kpi['total_bookings'])*100,1) : 0 ?>%</span></div>
          <div class="meter-track"><div class="meter-fill gold" style="width:<?= $kpi['total_bookings'] > 0 ? round(($kpi['flight_bookings']/$kpi['total_bookings'])*100) : 0 ?>%"></div></div>

          <div class="meter-label" style="margin-top:1rem"><span>Hotel Share</span><span style="color:#4caf82"><?= $kpi['total_bookings'] > 0 ? round(($kpi['hotel_bookings']/$kpi['total_bookings'])*100,1) : 0 ?>%</span></div>
          <div class="meter-track"><div class="meter-fill green" style="width:<?= $kpi['total_bookings'] > 0 ? round(($kpi['hotel_bookings']/$kpi['total_bookings'])*100) : 0 ?>%"></div></div>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-title">📌 Platform Summary</div>
        <div class="chart-sub">Key numbers at a glance</div>
        <table class="rank-table" style="margin-top:0.5rem">
          <tr><td class="text-muted fs-sm">Total Revenue</td><td style="text-align:right" class="text-gold fw-600"><?= formatPrice($kpi['total_revenue']) ?></td></tr>
          <tr><td class="text-muted fs-sm">Flight Revenue</td><td style="text-align:right" class="fw-600"><?= formatPrice($flight_rev) ?></td></tr>
          <tr><td class="text-muted fs-sm">Hotel Revenue</td><td style="text-align:right" class="fw-600"><?= formatPrice($hotel_rev) ?></td></tr>
          <tr><td class="text-muted fs-sm">Total Bookings</td><td style="text-align:right" class="fw-600"><?= $kpi['total_bookings'] ?></td></tr>
          <tr><td class="text-muted fs-sm">Confirmed</td><td style="text-align:right" style="color:var(--success)" class="fw-600"><?= $kpi['confirmed'] ?></td></tr>
          <tr><td class="text-muted fs-sm">Cancelled</td><td style="text-align:right" class="fw-600" style="color:var(--danger)"><?= $kpi['cancelled'] ?></td></tr>
          <tr><td class="text-muted fs-sm">Avg. Booking Value</td><td style="text-align:right" class="fw-600"><?= formatPrice($kpi['avg_booking']) ?></td></tr>
          <tr><td class="text-muted fs-sm">Registered Users</td><td style="text-align:right" class="fw-600"><?= $kpi['total_users'] ?></td></tr>
        </table>
      </div>

    </div>

  </div><!-- /sidebar-content -->
</div><!-- /layout-with-sidebar -->

<footer class="footer">© <?= date('Y') ?> <?= SITE_NAME ?> Admin · Analytics</footer>

<!-- ── CHART.JS SETUP ──────────────────────────────────────────────────────── -->
<script>
Chart.defaults.color = '#8892a4';
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 11;

const GOLD      = 'rgba(201,168,76,1)';
const GOLD_T    = 'rgba(201,168,76,0.15)';
const GOLD_T2   = 'rgba(201,168,76,0.08)';
const BLUE      = 'rgba(26,120,200,1)';
const BLUE_T    = 'rgba(26,120,200,0.15)';
const GREEN     = 'rgba(76,175,130,1)';
const GREEN_T   = 'rgba(76,175,130,0.15)';
const RED       = 'rgba(224,92,92,1)';
const RED_T     = 'rgba(224,92,92,0.15)';
const GRID      = 'rgba(255,255,255,0.05)';

const sharedScales = {
  x: { grid: { color: GRID }, ticks: { color: '#8892a4' } },
  y: { grid: { color: GRID }, ticks: { color: '#8892a4' } }
};

// ── 1. Monthly Revenue Line ──────────────────────────────────────────────────
const revCtx = document.getElementById('revenueLineChart').getContext('2d');
const revGrad = revCtx.createLinearGradient(0, 0, 0, 280);
revGrad.addColorStop(0, 'rgba(201,168,76,0.35)');
revGrad.addColorStop(1, 'rgba(201,168,76,0)');

new Chart(revCtx, {
  type: 'line',
  data: {
    labels: <?= $monthly_labels ?>,
    datasets: [
      {
        label: 'Revenue (₱)',
        data: <?= $monthly_revenue ?>,
        borderColor: GOLD,
        backgroundColor: revGrad,
        borderWidth: 2.5,
        pointBackgroundColor: GOLD,
        pointRadius: 4,
        pointHoverRadius: 7,
        tension: 0.4,
        fill: true,
        yAxisID: 'y',
      },
      {
        label: 'Bookings',
        data: <?= $monthly_counts ?>,
        borderColor: BLUE,
        backgroundColor: 'transparent',
        borderWidth: 2,
        borderDash: [5, 3],
        pointBackgroundColor: BLUE,
        pointRadius: 3,
        tension: 0.4,
        fill: false,
        yAxisID: 'y1',
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { labels: { color: '#8892a4', usePointStyle: true, pointStyleWidth: 10 } },
      tooltip: {
        backgroundColor: '#112240',
        borderColor: 'rgba(201,168,76,0.3)',
        borderWidth: 1,
        callbacks: {
          label: ctx => ctx.datasetIndex === 0
            ? ` Revenue: ₱${ctx.raw.toLocaleString('en-PH', {minimumFractionDigits:2})}`
            : ` Bookings: ${ctx.raw}`
        }
      }
    },
    scales: {
      x:  { grid: { color: GRID }, ticks: { color: '#8892a4' } },
      y:  { grid: { color: GRID }, ticks: { color: '#8892a4', callback: v => '₱' + (v>=1000 ? (v/1000).toFixed(0)+'k' : v) }, position: 'left' },
      y1: { grid: { drawOnChartArea: false }, ticks: { color: BLUE }, position: 'right' }
    }
  }
});

// ── 2. Revenue Mix Doughnut ──────────────────────────────────────────────────
new Chart(document.getElementById('revenueMixChart'), {
  type: 'doughnut',
  data: {
    labels: ['Flights', 'Hotels'],
    datasets: [{
      data: [<?= $flight_rev ?>, <?= $hotel_rev ?>],
      backgroundColor: ['rgba(201,168,76,0.85)', 'rgba(76,175,130,0.85)'],
      borderColor: ['#c9a84c', '#4caf82'],
      borderWidth: 2,
      hoverOffset: 8,
    }]
  },
  options: {
    cutout: '68%',
    plugins: {
      legend: { labels: { color: '#8892a4', usePointStyle: true } },
      tooltip: {
        callbacks: { label: ctx => ` ₱${ctx.raw.toLocaleString('en-PH', {minimumFractionDigits:2})}` }
      }
    }
  }
});

// ── 3. Stacked Bar (Flights vs Hotels) ──────────────────────────────────────
new Chart(document.getElementById('stackedBarChart'), {
  type: 'bar',
  data: {
    labels: <?= $type_labels ?>,
    datasets: [
      {
        label: 'Flights',
        data: <?= $type_flights ?>,
        backgroundColor: 'rgba(201,168,76,0.75)',
        borderRadius: 4,
        borderSkipped: false,
      },
      {
        label: 'Hotels',
        data: <?= $type_hotels ?>,
        backgroundColor: 'rgba(76,175,130,0.75)',
        borderRadius: 4,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { labels: { color: '#8892a4', usePointStyle: true } },
      tooltip: { backgroundColor: '#112240', borderColor: 'rgba(201,168,76,0.3)', borderWidth: 1 }
    },
    scales: {
      ...sharedScales,
      x: { ...sharedScales.x, stacked: true },
      y: { ...sharedScales.y, stacked: true, ticks: { stepSize: 1, color: '#8892a4' } }
    }
  }
});

// ── 4. Status Doughnut ───────────────────────────────────────────────────────
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= $status_labels ?>,
    datasets: [{
      data: <?= $status_counts ?>,
      backgroundColor: [
        'rgba(76,175,130,0.8)',
        'rgba(201,168,76,0.8)',
        'rgba(224,92,92,0.8)',
      ],
      borderColor: ['#4caf82','#c9a84c','#e05c5c'],
      borderWidth: 2,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '60%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} bookings` } }
    }
  }
});

// ── 5. Daily Booking Sparkline ───────────────────────────────────────────────
const dailyCtx  = document.getElementById('dailyChart').getContext('2d');
const dailyGrad = dailyCtx.createLinearGradient(0, 0, 0, 120);
dailyGrad.addColorStop(0, 'rgba(26,120,200,0.3)');
dailyGrad.addColorStop(1, 'rgba(26,120,200,0)');

new Chart(dailyCtx, {
  type: 'bar',
  data: {
    labels: <?= $daily_labels ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= $daily_counts ?>,
      backgroundColor: ctx => {
        const v = ctx.raw;
        if (!v) return 'rgba(255,255,255,0.04)';
        return `rgba(26,120,200,${Math.min(0.3 + v * 0.15, 0.9)})`;
      },
      borderRadius: 4,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#112240',
        borderColor: 'rgba(26,120,200,0.4)',
        borderWidth: 1,
        callbacks: { label: ctx => ` ${ctx.raw} booking${ctx.raw !== 1 ? 's' : ''}` }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#8892a4', maxTicksLimit: 10 } },
      y: { grid: { color: GRID }, ticks: { stepSize: 1, color: '#8892a4' } }
    }
  }
});
</script>

</body>
</html>
