<?php
/**
 * receipt.php — Generate & download PDF booking receipt
 * Called with: receipt.php?id=BOOKING_ID
 */
require_once 'includes/auth.php';
requireLogin();

$conn      = getDBConnection();
$user_id   = $_SESSION['user_id'];
$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    http_response_code(400);
    die("Invalid booking ID.");
}

// Fetch booking — users can only download their own; admins can download any
if (isAdmin()) {
    $stmt = $conn->prepare(
        "SELECT b.*, u.name AS user_name, u.email AS user_email,
         f.airline, f.origin, f.destination, f.departure_time, f.arrival_time,
         h.hotel_name, h.location, h.rating
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         LEFT JOIN flights f ON b.booking_type = 'flight' AND b.item_id = f.id
         LEFT JOIN hotels  h ON b.booking_type = 'hotel'  AND b.item_id = h.id
         WHERE b.id = ?"
    );
    $stmt->bind_param("i", $booking_id);
} else {
    $stmt = $conn->prepare(
        "SELECT b.*, u.name AS user_name, u.email AS user_email,
         f.airline, f.origin, f.destination, f.departure_time, f.arrival_time,
         h.hotel_name, h.location, h.rating
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         LEFT JOIN flights f ON b.booking_type = 'flight' AND b.item_id = f.id
         LEFT JOIN hotels  h ON b.booking_type = 'hotel'  AND b.item_id = h.id
         WHERE b.id = ? AND b.user_id = ?"
    );
    $stmt->bind_param("ii", $booking_id, $user_id);
}

$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$conn->close();

if (!$booking) {
    http_response_code(404);
    die("Booking not found.");
}

// Build payload for Python script
$payload = [
    'booking_id'     => $booking['id'],
    'transaction_id' => $booking['transaction_id'] ?? 'N/A',
    'created_at'     => $booking['created_at'],
    'status'         => $booking['status'],
    'booking_type'   => $booking['booking_type'],
    'user_name'      => $booking['user_name'],
    'user_email'     => $booking['user_email'],
    'guests'         => $booking['guests'],
    'total_price'    => $booking['total_price'],
    'check_in'       => $booking['check_in'],
    'check_out'      => $booking['check_out'],
    // Flight fields
    'airline'        => $booking['airline'],
    'origin'         => $booking['origin'],
    'destination'    => $booking['destination'],
    'departure_time' => $booking['departure_time'],
    'arrival_time'   => $booking['arrival_time'],
    // Hotel fields
    'hotel_name'     => $booking['hotel_name'],
    'location'       => $booking['location'],
    'rating'         => $booking['rating'],
    // Output
    'output_path'    => sys_get_temp_dir() . "/skyroute_receipt_{$booking_id}.pdf",
];

$json = json_encode($payload);

// Path to Python script — update if needed
$script = __DIR__ . '/includes/generate_receipt.py';

// Try python3 first, fall back to python
$python = trim(shell_exec('which python3 2>/dev/null') ?: shell_exec('which python 2>/dev/null'));
if (!$python) {
    http_response_code(500);
    die("Python is not available on this server. Please install Python 3.");
}

// Run generator
$cmd    = escapeshellcmd($python) . ' ' . escapeshellarg($script);
$proc   = proc_open($cmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes);

if (!is_resource($proc)) {
    http_response_code(500);
    die("Could not start PDF generator.");
}

fwrite($pipes[0], $json);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($proc);

if ($exit !== 0) {
    http_response_code(500);
    echo "<pre>PDF generation failed:\n$stderr</pre>";
    exit();
}

$pdf_path = trim($stdout);

if (!file_exists($pdf_path)) {
    http_response_code(500);
    die("PDF file was not created.");
}

// Stream PDF to browser
$filename = "SkyRoute-Receipt-{$booking_id}.pdf";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($pdf_path));
header('Cache-Control: no-cache, no-store');
readfile($pdf_path);

// Clean up temp file
@unlink($pdf_path);
exit();
?>
