<?php
// ============================================================
//  SkyRoute — Mailer
//  Wraps PHPMailer. Call SkyMailer::send() anywhere.
// ============================================================

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/db.php';    // for SITE_NAME / CURRENCY / formatPrice

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class SkyMailer
{
    // ── Core send ────────────────────────────────────────────
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $mail = new PHPMailer(true);
        try {
            // Server
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            // From / To
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log("SkyMailer error: " . $mail->ErrorInfo);
            return false;
        }
    }

    // ── Email: Booking Confirmation ──────────────────────────
    public static function sendBookingConfirmation(array $booking, array $user): bool
    {
        $subject = "✈ Your booking is confirmed — " . SITE_NAME;
        $body    = self::templateBookingConfirm($booking, $user);
        return self::send($user['email'], $user['name'], $subject, $body);
    }

    // ── Email: Welcome / Registration ────────────────────────
    public static function sendWelcome(array $user): bool
    {
        $subject = "Welcome to " . SITE_NAME . " 🌏";
        $body    = self::templateWelcome($user);
        return self::send($user['email'], $user['name'], $subject, $body);
    }

    // ── Email: Booking Cancellation ──────────────────────────
    public static function sendCancellation(array $booking, array $user): bool
    {
        $subject = "Booking Cancelled — " . SITE_NAME;
        $body    = self::templateCancellation($booking, $user);
        return self::send($user['email'], $user['name'], $subject, $body);
    }

    // ════════════════════════════════════════════════════════
    //  TEMPLATES
    // ════════════════════════════════════════════════════════

    private static function baseLayout(string $preheader, string $content): string
    {
        $year    = date('Y');
        $siteName = SITE_NAME;
        $siteUrl  = SITE_URL;
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title>{$siteName}</title>
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
</head>
<body style="margin:0;padding:0;background:#0a1628;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

  <!-- Preheader (hidden) -->
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{$preheader}&nbsp;&zwnj;&zwnj;&zwnj;</div>

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a1628;min-height:100vh;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

          <!-- HEADER -->
          <tr>
            <td style="background:#112240;border-radius:12px 12px 0 0;padding:28px 36px;border-bottom:3px solid #c9a84c;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td>
                    <div style="font-family:Georgia,serif;font-size:24px;color:#c9a84c;letter-spacing:1px;">&#9992; {$siteName}</div>
                    <div style="font-size:12px;color:#8892a4;margin-top:3px;">Your Travel Companion</div>
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <a href="{$siteUrl}" style="background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);color:#c9a84c;text-decoration:none;font-size:12px;padding:6px 14px;border-radius:20px;">Visit Site</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- BODY -->
          <tr>
            <td style="background:#112240;padding:36px 36px 28px;">
              {$content}
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="background:#0d1e38;border-radius:0 0 12px 12px;padding:20px 36px;border-top:1px solid rgba(201,168,76,0.12);">
              <p style="margin:0;font-size:11px;color:#8892a4;text-align:center;line-height:1.6;">
                © {$year} {$siteName} · All rights reserved<br>
                <a href="{$siteUrl}/dashboard.php" style="color:#c9a84c;text-decoration:none;">My Bookings</a> &nbsp;·&nbsp;
                <a href="{$siteUrl}" style="color:#c9a84c;text-decoration:none;">Explore</a><br><br>
                <span style="color:#4a5568;font-size:10px;">This email was sent because you have an account at {$siteName}.<br>
                This is a portfolio demo — no real booking or payment was made.</span>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    // ── Template: Booking Confirmation ───────────────────────
    private static function templateBookingConfirm(array $b, array $u): string
    {
        $name    = htmlspecialchars($u['name']);
        $txn     = htmlspecialchars($b['transaction_id'] ?? 'N/A');
        $btype   = $b['booking_type'];
        $price   = formatPrice($b['total_price']);
        $guests  = $b['guests'];
        $status  = strtoupper($b['status']);
        $bid     = $b['id'];
        $siteUrl = SITE_URL;

        // Type-specific details
        if ($btype === 'flight') {
            $icon    = '&#9992;';
            $title   = 'Flight Booking Confirmed';
            $detail1 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Airline</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;font-weight:600;" align="right">' . htmlspecialchars($b['airline']) . '</td>';
            $detail2 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Route</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;font-weight:600;" align="right">' . htmlspecialchars($b['origin']) . ' &rarr; ' . htmlspecialchars($b['destination']) . '</td>';
            $detail3 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Departure</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;" align="right">' . date('D, M d Y · h:i A', strtotime($b['departure_time'])) . '</td>';
            $detail4 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Passengers</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;" align="right">' . $guests . '</td>';
        } else {
            $icon    = '&#127968;';
            $title   = 'Hotel Booking Confirmed';
            $nights  = 1;
            if (!empty($b['check_in']) && !empty($b['check_out'])) {
                $nights = max(1, (strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);
            }
            $detail1 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Hotel</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;font-weight:600;" align="right">' . htmlspecialchars($b['hotel_name']) . '</td>';
            $detail2 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Location</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;" align="right">' . htmlspecialchars($b['location']) . '</td>';
            $detail3 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Check-in / Out</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;" align="right">' . date('M d', strtotime($b['check_in'])) . ' &ndash; ' . date('M d, Y', strtotime($b['check_out'])) . ' (' . $nights . ' nights)</td>';
            $detail4 = '<td style="color:#8892a4;font-size:13px;padding:7px 0;">Guests</td><td style="color:#e8eaf0;font-size:13px;padding:7px 0;" align="right">' . $guests . '</td>';
        }

        $preheader = "Your {$btype} booking #{$bid} is confirmed. Transaction: {$txn}";

        $content = <<<HTML
          <!-- Hero -->
          <div style="text-align:center;margin-bottom:28px;">
            <div style="font-size:48px;margin-bottom:12px;">{$icon}</div>
            <h1 style="margin:0 0 6px;font-family:Georgia,serif;font-size:22px;color:#f8f9fc;">{$title}</h1>
            <p style="margin:0;font-size:14px;color:#8892a4;">Hi {$name}, your reservation is all set.</p>
          </div>

          <!-- Status pill -->
          <div style="text-align:center;margin-bottom:24px;">
            <span style="background:rgba(76,175,130,0.15);border:1px solid rgba(76,175,130,0.35);color:#7ecfaa;font-size:11px;font-weight:700;padding:5px 16px;border-radius:20px;letter-spacing:1px;">{$status}</span>
          </div>

          <!-- Booking details card -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"
            style="background:rgba(255,255,255,0.03);border:1px solid rgba(201,168,76,0.15);border-radius:10px;padding:0;margin-bottom:20px;">
            <tr><td style="padding:20px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">{$detail1}</tr>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">{$detail2}</tr>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">{$detail3}</tr>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">{$detail4}</tr>
                <tr>
                  <td style="color:#8892a4;font-size:13px;padding:7px 0;">Transaction ID</td>
                  <td style="color:#c9a84c;font-size:12px;padding:7px 0;font-family:monospace;" align="right">{$txn}</td>
                </tr>
              </table>
            </td></tr>
          </table>

          <!-- Total price -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"
            style="background:#0a1628;border-radius:8px;margin-bottom:28px;">
            <tr>
              <td style="padding:16px 24px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="font-size:13px;color:#8892a4;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Total Paid</td>
                    <td align="right" style="font-family:Georgia,serif;font-size:22px;color:#c9a84c;font-weight:700;">{$price}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- CTA Buttons -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
            <tr>
              <td align="center" style="padding:0 6px;">
                <a href="{$siteUrl}/receipt.php?id={$bid}"
                   style="display:inline-block;background:#c9a84c;color:#0a1628;font-weight:700;font-size:14px;padding:13px 28px;border-radius:8px;text-decoration:none;">
                  &#11015; Download Receipt (PDF)
                </a>
              </td>
              <td align="center" style="padding:0 6px;">
                <a href="{$siteUrl}/dashboard.php"
                   style="display:inline-block;background:transparent;color:#c9a84c;border:1px solid rgba(201,168,76,0.4);font-size:14px;padding:12px 28px;border-radius:8px;text-decoration:none;">
                  View My Bookings
                </a>
              </td>
            </tr>
          </table>

          <!-- Note -->
          <p style="margin:0;font-size:12px;color:#8892a4;text-align:center;line-height:1.7;border-top:1px solid rgba(255,255,255,0.06);padding-top:20px;">
            Present this confirmation at check-in. For changes or questions,<br>reply to this email or visit your dashboard.
          </p>
HTML;

        return self::baseLayout($preheader, $content);
    }

    // ── Template: Welcome ─────────────────────────────────────
    private static function templateWelcome(array $u): string
    {
        $name    = htmlspecialchars($u['name']);
        $siteUrl = SITE_URL;
        $siteName = SITE_NAME;
        $preheader = "Welcome to {$siteName}, {$name}! Start exploring flights and hotels.";

        $content = <<<HTML
          <div style="text-align:center;margin-bottom:32px;">
            <div style="font-size:52px;margin-bottom:14px;">🌏</div>
            <h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:22px;color:#f8f9fc;">Welcome aboard, {$name}!</h1>
            <p style="margin:0;font-size:14px;color:#8892a4;max-width:400px;margin:0 auto;line-height:1.6;">
              Your {$siteName} account is ready. Discover flights and hotels across the Philippines and beyond.
            </p>
          </div>

          <!-- Feature highlights -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
            <tr>
              <td style="padding:0 6px 12px;" width="33%" valign="top">
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(201,168,76,0.12);border-radius:8px;padding:16px;text-align:center;">
                  <div style="font-size:28px;margin-bottom:8px;">✈</div>
                  <div style="font-size:12px;font-weight:700;color:#c9a84c;margin-bottom:4px;">FLIGHTS</div>
                  <div style="font-size:11px;color:#8892a4;line-height:1.5;">Domestic &amp; international routes</div>
                </div>
              </td>
              <td style="padding:0 6px 12px;" width="33%" valign="top">
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(201,168,76,0.12);border-radius:8px;padding:16px;text-align:center;">
                  <div style="font-size:28px;margin-bottom:8px;">🏨</div>
                  <div style="font-size:12px;font-weight:700;color:#c9a84c;margin-bottom:4px;">HOTELS</div>
                  <div style="font-size:11px;color:#8892a4;line-height:1.5;">Top-rated stays nationwide</div>
                </div>
              </td>
              <td style="padding:0 6px 12px;" width="33%" valign="top">
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(201,168,76,0.12);border-radius:8px;padding:16px;text-align:center;">
                  <div style="font-size:28px;margin-bottom:8px;">📋</div>
                  <div style="font-size:12px;font-weight:700;color:#c9a84c;margin-bottom:4px;">DASHBOARD</div>
                  <div style="font-size:11px;color:#8892a4;line-height:1.5;">Manage all your bookings</div>
                </div>
              </td>
            </tr>
          </table>

          <!-- CTA -->
          <div style="text-align:center;margin-bottom:24px;">
            <a href="{$siteUrl}"
               style="display:inline-block;background:#c9a84c;color:#0a1628;font-weight:700;font-size:14px;padding:14px 36px;border-radius:8px;text-decoration:none;">
              Start Exploring &#8594;
            </a>
          </div>

          <p style="margin:0;font-size:12px;color:#8892a4;text-align:center;border-top:1px solid rgba(255,255,255,0.06);padding-top:20px;line-height:1.7;">
            Need help? Just reply to this email — we're always happy to assist.
          </p>
HTML;

        return self::baseLayout($preheader, $content);
    }

    // ── Template: Cancellation ────────────────────────────────
    private static function templateCancellation(array $b, array $u): string
    {
        $name   = htmlspecialchars($u['name']);
        $bid    = $b['id'];
        $btype  = ucfirst($b['booking_type']);
        $price  = formatPrice($b['total_price']);
        $siteUrl = SITE_URL;

        $itemName = $b['booking_type'] === 'flight'
            ? htmlspecialchars($b['origin'] . ' → ' . $b['destination'])
            : htmlspecialchars($b['hotel_name']);

        $preheader = "Your booking #{$bid} has been cancelled.";

        $content = <<<HTML
          <div style="text-align:center;margin-bottom:28px;">
            <div style="font-size:48px;margin-bottom:12px;">❌</div>
            <h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:21px;color:#f8f9fc;">Booking Cancelled</h1>
            <p style="margin:0;font-size:14px;color:#8892a4;">Hi {$name}, your reservation has been cancelled.</p>
          </div>

          <table width="100%" cellpadding="0" cellspacing="0" border="0"
            style="background:rgba(224,92,92,0.06);border:1px solid rgba(224,92,92,0.2);border-radius:10px;margin-bottom:24px;">
            <tr><td style="padding:20px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#8892a4;font-size:13px;padding:6px 0;">Booking ID</td>
                  <td style="color:#e8eaf0;font-size:13px;padding:6px 0;font-weight:600;" align="right">#</td>
                </tr>
                <tr>
                  <td style="color:#8892a4;font-size:13px;padding:6px 0;">Type</td>
                  <td style="color:#e8eaf0;font-size:13px;padding:6px 0;" align="right">{$btype}</td>
                </tr>
                <tr>
                  <td style="color:#8892a4;font-size:13px;padding:6px 0;">Details</td>
                  <td style="color:#e8eaf0;font-size:13px;padding:6px 0;" align="right">{$itemName}</td>
                </tr>
                <tr>
                  <td style="color:#8892a4;font-size:13px;padding:6px 0;">Amount</td>
                  <td style="color:#c9a84c;font-size:13px;padding:6px 0;font-weight:600;" align="right">{$price}</td>
                </tr>
              </table>
            </td></tr>
          </table>

          <div style="text-align:center;margin-bottom:24px;">
            <a href="{$siteUrl}"
               style="display:inline-block;background:#c9a84c;color:#0a1628;font-weight:700;font-size:14px;padding:13px 28px;border-radius:8px;text-decoration:none;">
              Book Another Trip &#8594;
            </a>
          </div>

          <p style="margin:0;font-size:12px;color:#8892a4;text-align:center;border-top:1px solid rgba(255,255,255,0.06);padding-top:18px;line-height:1.7;">
            If this cancellation was unexpected, please reply to this email immediately.
          </p>
HTML;

        return self::baseLayout($preheader, $content);
    }
}
?>
