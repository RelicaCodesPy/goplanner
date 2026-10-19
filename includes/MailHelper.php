<?php
// includes/MailHelper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Load PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Composer autoload
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Manual installation (also loaded when PHPMailer is not registered in Composer)
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/PHPMailer/src/Exception.php')) {
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
}

require_once __DIR__ . '/../config/mail.php';

class MailHelper {

    /**
     * Create a configured PHPMailer instance
     */
    private static function createMailer() {
        $mail = new PHPMailer(true);

        if (!MAIL_ENABLED) {
            return $mail;
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        return $mail;
    }

    /**
     * Generate Google Calendar "Add Event" URL
     */
    public static function getGoogleCalendarUrl($title, $date, $time = null, $location = '', $description = '') {
        $title = urlencode($title);

        if ($time) {
            $startDateTime = date('Ymd\THis', strtotime($date . ' ' . $time));
            $endDateTime = date('Ymd\THis', strtotime($date . ' ' . $time . ' +1 hour'));
        } else {
            $startDateTime = date('Ymd', strtotime($date));
            $endDateTime = date('Ymd', strtotime($date . ' +1 day'));
        }

        $location = urlencode($location);
        $description = urlencode($description);

        return "https://calendar.google.com/calendar/render?action=TEMPLATE"
            . "&text=" . $title
            . "&dates=" . $startDateTime . "/" . $endDateTime
            . "&location=" . $location
            . "&details=" . $description
            . "&sf=true&output=xml";
    }

    /**
     * Generate ICS content for a single event
     */
    public static function generateICS($title, $date, $time = null, $location = '', $description = '', $eventId = 0) {
        $dateStr = date('Ymd', strtotime($date));
        $uid = 'goplanner-' . $eventId . '-' . $dateStr . '-' . bin2hex(random_bytes(4)) . '@debesmscat.edu.ph';
        $prodId = '-//GoPlanner//EN';
        $calName = CALENDAR_NAME;
        $tz = CALENDAR_TIMEZONE;

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:{$prodId}\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= "X-WR-CALNAME:{$calName}\r\n";
        $ics .= "X-WR-TIMEZONE:{$tz}\r\n";

        // VTIMEZONE component for proper timezone handling
        $ics .= "BEGIN:VTIMEZONE\r\n";
        $ics .= "TZID:{$tz}\r\n";
        $ics .= "BEGIN:STANDARD\r\n";
        $ics .= "DTSTART:19700101T000000\r\n";
        $ics .= "TZOFFSETFROM:+0800\r\n";
        $ics .= "TZOFFSETTO:+0800\r\n";
        $ics .= "TZNAME:PHT\r\n";
        $ics .= "END:STANDARD\r\n";
        $ics .= "END:VTIMEZONE\r\n";

        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics .= "CREATED:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics .= "LAST-MODIFIED:" . gmdate('Ymd\THis\Z') . "\r\n";

        if ($time) {
            $timeClean = str_replace(':', '', $time);
            // Ensure time has seconds
            if (strlen($timeClean) === 4) $timeClean .= '00';
            $ics .= "DTSTART;TZID={$tz}:{$dateStr}T{$timeClean}\r\n";
            // End time = 1 hour after start
            $endTime = date('His', strtotime($date . ' ' . $time . ' +1 hour'));
            $ics .= "DTEND;TZID={$tz}:{$dateStr}T{$endTime}\r\n";
        } else {
            $ics .= "DTSTART;VALUE=DATE:{$dateStr}\r\n";
            $nextDay = date('Ymd', strtotime($date . ' +1 day'));
            $ics .= "DTEND;VALUE=DATE:{$nextDay}\r\n";
        }

        $safeTitle = str_replace(["\r", "\n", ","], ["", "", "\\,"], $title);
        $ics .= "SUMMARY:{$safeTitle}\r\n";
        if ($location) {
            $safeLocation = str_replace(["\r", "\n", ","], ["", "", "\\,"], $location);
            $ics .= "LOCATION:{$safeLocation}\r\n";
        }
        if ($description) {
            $safeDesc = str_replace(["\r", "\n", ","], ["", "", "\\,"], $description);
            $ics .= "DESCRIPTION:{$safeDesc}\r\n";
        }
        $viewUrl = (defined('APP_URL') ? APP_URL : 'http://localhost/goplanner') . '/pages/announcements/view.php?id=' . $eventId;
        $ics .= "URL:{$viewUrl}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "TRANSP:OPAQUE\r\n";

        // 30-minute reminder alarm
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT30M\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Reminder: {$safeTitle}\r\n";
        $ics .= "END:VALARM\r\n";

        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build email HTML template for event announcement
     */
    public static function buildEventEmailHtml($announcement, $recipientName, $gcalUrl) {
        $title = htmlspecialchars($announcement['title']);
        $body = $announcement['content'] ?? ($announcement['body'] ?? '');
        $eventDate = $announcement['event_date'];
        $eventTime = $announcement['event_time'] ?? null;
        $location = $announcement['map_address'] ?? '';
        $collegeName = htmlspecialchars($announcement['college_name'] ?? 'DEBESMSCAT');
        $collegeColor = $announcement['college_color'] ?? '#3b82f6';
        $viewUrl = (defined('APP_URL') ? APP_URL : 'http://localhost/goplanner') . '/pages/announcements/view.php?id=' . $announcement['id'];

        $formattedDate = date('l, F j, Y', strtotime($eventDate));
        $formattedTime = $eventTime ? date('g:i A', strtotime($eventTime)) : null;
        $dayNum = date('d', strtotime($eventDate));
        $monthShort = date('M', strtotime($eventDate));

        // Truncate body for email
        if (strlen($body) > 300) {
            $body = substr($body, 0, 300) . '...';
        }

        $locationBlock = '';
        if ($location) {
            $locationBlock = '
            <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:12px;">
            <tr>
                <td width="28" valign="top" style="padding-top:2px;">
                    <div style="width:28px;height:28px;background:rgba(255,255,255,0.08);border-radius:6px;text-align:center;line-height:28px;font-size:13px;">&#x1F4CD;</div>
                </td>
                <td style="padding-left:10px;color:#94a3b8;font-size:14px;line-height:1.5;">' . htmlspecialchars($location) . '</td>
            </tr>
            </table>';
        }

        $descBlock = '';
        if ($body) {
            $descBlock = '
            <div style="color:#94a3b8;font-size:14px;line-height:1.7;margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.06);">'
                . nl2br(htmlspecialchars($body)) . '</div>';
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . $title . '</title></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;">

    <!-- Header Banner -->
    <tr><td style="background:linear-gradient(135deg, ' . $collegeColor . ' 0%, ' . $collegeColor . 'dd 100%);padding:32px 36px;border-radius:14px 14px 0 0;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td>
                <div style="color:rgba(255,255,255,0.7);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px;">New Event Announcement</div>
                <div style="color:#ffffff;font-size:13px;font-weight:500;">' . $collegeName . '</div>
            </td>
            <td align="right" valign="top">
                <div style="background:rgba(255,255,255,0.15);border-radius:10px;padding:10px 14px;text-align:center;">
                    <div style="color:#ffffff;font-size:22px;font-weight:800;line-height:1;">' . $dayNum . '</div>
                    <div style="color:rgba(255,255,255,0.8);font-size:11px;font-weight:600;text-transform:uppercase;margin-top:2px;">' . $monthShort . '</div>
                </div>
            </td>
        </tr>
        </table>
    </td></tr>

    <!-- Body -->
    <tr><td style="background:#1e293b;padding:32px 36px;">
        <p style="color:#94a3b8;font-size:14px;margin:0 0 6px;">Hi <strong style="color:#e2e8f0;">' . htmlspecialchars($recipientName) . '</strong>,</p>
        <p style="color:#64748b;font-size:13px;margin:0 0 28px;line-height:1.5;">A new event has been scheduled. Here are the details:</p>

        <!-- Event Card -->
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;border:1px solid #334155;border-radius:12px;overflow:hidden;margin-bottom:28px;">
        <tr><td style="padding:28px;">
            <h2 style="color:#f1f5f9;font-size:22px;font-weight:700;margin:0 0 20px;line-height:1.3;">' . $title . '</h2>

            <!-- Date & Time Row -->
            <table cellpadding="0" cellspacing="0" width="100%" role="presentation">
            <tr>
                <td width="48" valign="top">
                    <div style="width:44px;height:44px;background:' . $collegeColor . ';border-radius:10px;text-align:center;line-height:44px;color:#fff;font-size:18px;font-weight:700;">' . $dayNum . '</div>
                </td>
                <td style="padding-left:14px;" valign="top">
                    <div style="color:#f1f5f9;font-size:15px;font-weight:600;line-height:1.4;">' . $formattedDate . '</div>' .
                    ($formattedTime ? '<div style="color:#64748b;font-size:13px;margin-top:4px;">&#x1F550; ' . $formattedTime . ' (Philippine Time)</div>' : '<div style="color:#64748b;font-size:13px;margin-top:4px;">All Day Event</div>') . '
                </td>
            </tr>
            </table>';

        $html .= $locationBlock;
        $html .= $descBlock;

        // Attachment types block
        $files = $announcement['files'] ?? [];
        if (!empty($files)) {
            $attachmentIcons = [
                'pdf'  => ['icon' => '&#x1F4C4;', 'color' => '#ef4444', 'label' => 'PDF'],
                'png'  => ['icon' => '&#x1F5BC;', 'color' => '#8b5cf6', 'label' => 'PNG'],
                'jpg'  => ['icon' => '&#x1F5BC;', 'color' => '#8b5cf6', 'label' => 'JPG'],
                'jpeg' => ['icon' => '&#x1F5BC;', 'color' => '#8b5cf6', 'label' => 'JPEG'],
                'gif'  => ['icon' => '&#x1F5BC;', 'color' => '#8b5cf6', 'label' => 'GIF'],
                'doc'  => ['icon' => '&#x1F4C3;', 'color' => '#3b82f6', 'label' => 'DOC'],
                'docx' => ['icon' => '&#x1F4C3;', 'color' => '#3b82f6', 'label' => 'DOCX'],
                'xls'  => ['icon' => '&#x1F4CA;', 'color' => '#10b981', 'label' => 'XLS'],
                'xlsx' => ['icon' => '&#x1F4CA;', 'color' => '#10b981', 'label' => 'XLSX'],
                'ppt'  => ['icon' => '&#x1F4CA;', 'color' => '#f59e0b', 'label' => 'PPT'],
                'pptx' => ['icon' => '&#x1F4CA;', 'color' => '#f59e0b', 'label' => 'PPTX'],
                'zip'  => ['icon' => '&#x1F4E6;', 'color' => '#64748b', 'label' => 'ZIP'],
            ];

            $html .= '
            <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:20px;">
            <tr>
                <td>
                    <div style="background:#0f172a;border:1px solid #334155;border-radius:10px;padding:20px;">
                        <div style="color:#94a3b8;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:12px;">&#x1F4CE; Attachments (' . count($files) . ')</div>';

            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                $icon = $attachmentIcons[$ext] ?? ['icon' => '&#x1F4C4;', 'color' => '#64748b', 'label' => strtoupper($ext)];
                $safeName = htmlspecialchars($file['file_name']);
                $html .= '
                        <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:6px;">
                        <tr>
                            <td width="36" valign="middle">
                                <div style="width:32px;height:32px;background:' . $icon['color'] . '22;border-radius:8px;text-align:center;line-height:32px;font-size:15px;">' . $icon['icon'] . '</div>
                            </td>
                            <td style="padding-left:10px;" valign="middle">
                                <div style="color:#e2e8f0;font-size:13px;font-weight:500;">' . $safeName . '</div>
                                <div style="color:#64748b;font-size:11px;margin-top:2px;">' . $icon['label'] . ' file</div>
                            </td>
                        </tr>
                        </table>';
            }

            $html .= '
                    </div>
                </td>
            </tr>
            </table>';
        }

        $html .= '
        </td></tr>
        </table>

        <!-- Action Buttons -->
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
        <tr>
            <td width="50%" style="padding-right:6px;">
                <a href="' . $gcalUrl . '" target="_blank" style="display:block;background:#4285f4;color:#ffffff;text-decoration:none;padding:14px 0;border-radius:10px;font-size:14px;font-weight:600;text-align:center;border:0;mso-padding-alt:0;">
                    <!--[if mso]><i style="letter-spacing:24px;mso-font-width:-100%;mso-text-raise:20pt">&nbsp;</i><![endif]-->
                    <span style="mso-text-raise:10pt;">&#x1F4C5; Add to Google Calendar</span>
                    <!--[if mso]><i style="letter-spacing:24px;mso-font-width:-100%">&nbsp;</i><![endif]-->
                </a>
            </td>
            <td width="50%" style="padding-left:6px;">
                <a href="' . $viewUrl . '" style="display:block;background:' . $collegeColor . ';color:#ffffff;text-decoration:none;padding:14px 0;border-radius:10px;font-size:14px;font-weight:600;text-align:center;border:0;">
                    &#x1F441; View Details
                </a>
            </td>
        </tr>
        </table>

        <p style="color:#475569;font-size:12px;margin:0;text-align:center;line-height:1.5;">
            &#x1F4CE; An .ics calendar file is attached &#x2014; open it to add this event to Apple Calendar, Outlook, or any calendar app.
        </p>
    </td></tr>

    <!-- Footer -->
    <tr><td style="background:#1e293b;padding:20px 36px;border-top:1px solid #334155;border-radius:0 0 14px 14px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="color:#475569;font-size:12px;line-height:1.5;">
                GoPlanner<br>
                <span style="color:#334155;font-size:11px;">This is an automated notification &#x2014; do not reply to this email.</span>
            </td>
            <td align="right" valign="top">
                <a href="' . (defined('APP_URL') ? APP_URL : 'http://localhost/goplanner') . '/pages/settings/profile.php" style="color:#64748b;font-size:12px;text-decoration:none;white-space:nowrap;">Notification Settings</a>
            </td>
        </tr>
        </table>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

        return $html;
    }

    /**
     * Send event notification email to a single recipient
     */
    public static function sendEventNotification($recipientEmail, $recipientName, $announcement) {
        if (!MAIL_ENABLED) {
            error_log('Mail disabled — skipping email to ' . $recipientEmail);
            return false;
        }

        try {
            $mail = self::createMailer();
            $mail->CharSet = 'UTF-8';

            // Recipient
            $mail->addAddress($recipientEmail, $recipientName);

            $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost/goplanner';

            // Fetch attached files for this announcement
            if (!isset($announcement['files'])) {
                try {
                    $fileStmt = db()->prepare('SELECT file_name, mime_type, file_size FROM announcement_files WHERE announcement_id = ?');
                    $fileStmt->execute([$announcement['id']]);
                    $announcement['files'] = $fileStmt->fetchAll();
                } catch (\Throwable $e) {
                    $announcement['files'] = [];
                }
            }

            // Generate Google Calendar URL
            $gcalUrl = self::getGoogleCalendarUrl(
                $announcement['title'],
                $announcement['event_date'],
                $announcement['event_time'] ?? null,
                $announcement['map_address'] ?? '',
                'Event from GoPlanner: ' . $appUrl . '/pages/announcements/view.php?id=' . $announcement['id']
            );

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'New Event: ' . $announcement['title'] . ' — ' . date('M j, Y', strtotime($announcement['event_date']));
            $mail->Body    = self::buildEventEmailHtml($announcement, $recipientName, $gcalUrl);
            $mail->AltBody = "New Event: " . $announcement['title'] . "\n"
                . "Date: " . date('l, F j, Y', strtotime($announcement['event_date'])) . "\n"
                . (!empty($announcement['event_time']) ? "Time: " . date('g:i A', strtotime($announcement['event_time'])) . "\n" : "")
                . (!empty($announcement['map_address']) ? "Location: " . $announcement['map_address'] . "\n" : "")
                . "\nView: " . $appUrl . "/pages/announcements/view.php?id=" . $announcement['id']
                . "\nAdd to Google Calendar: " . $gcalUrl;

            // Attach .ics file with correct content type
            $icsContent = self::generateICS(
                $announcement['title'],
                $announcement['event_date'],
                $announcement['event_time'] ?? null,
                $announcement['map_address'] ?? '',
                $announcement['content'] ?? ($announcement['body'] ?? ''),
                $announcement['id']
            );

            $icsFilename = 'event-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($announcement['title'])) . '.ics';
            $mail->addStringAttachment(
                $icsContent,
                $icsFilename,
                'base64',
                'text/calendar; charset=UTF-8; method=REQUEST',
                'inline'
            );

            $mail->send();
            return true;

        } catch (\Throwable $e) {
            error_log('Mail error for ' . $recipientEmail . ': ' . $e->getMessage());
            return false;
        }
    }
/**
 * Build email HTML template for an announcement without an event date
 */
public static function buildAnnouncementEmailHtml($announcement, $recipientName) {
    $title = htmlspecialchars($announcement['title']);
    $body = $announcement['content'] ?? ($announcement['body'] ?? '');
    $collegeName = htmlspecialchars($announcement['college_name'] ?? 'DEBESMSCAT');
    $collegeColor = $announcement['college_color'] ?? '#3b82f6';
    $viewUrl = (defined('APP_URL') ? APP_URL : 'http://localhost/goplanner%201') . '/pages/announcements/view.php?id=' . $announcement['id'];

    if (strlen($body) > 600) {
        $body = substr($body, 0, 600) . '...';
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . $title . '</title></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;">

    <tr><td style="background:linear-gradient(135deg, ' . $collegeColor . ' 0%, ' . $collegeColor . 'dd 100%);padding:32px 36px;border-radius:14px 14px 0 0;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td>
                <div style="color:rgba(255,255,255,0.7);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px;">New Announcement</div>
                <div style="color:#ffffff;font-size:13px;font-weight:500;">' . $collegeName . '</div>
            </td>
            <td align="right" valign="middle" style="color:#ffffff;font-size:26px;">&#x1F4E2;</td>
        </tr>
        </table>
    </td></tr>

    <tr><td style="background:#1e293b;padding:32px 36px;">
        <p style="color:#94a3b8;font-size:14px;margin:0 0 6px;">Hi <strong style="color:#e2e8f0;">' . htmlspecialchars($recipientName) . '</strong>,</p>
        <p style="color:#64748b;font-size:13px;margin:0 0 28px;line-height:1.5;">A new announcement has been posted. Here are the details:</p>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;border:1px solid #334155;border-radius:12px;overflow:hidden;margin-bottom:28px;">
        <tr><td style="padding:28px;">
            <h2 style="color:#f1f5f9;font-size:20px;font-weight:700;margin:0 0 16px;line-height:1.3;">' . $title . '</h2>';

    if ($body) {
        $html .= '<div style="color:#94a3b8;font-size:14px;line-height:1.7;">' . nl2br(htmlspecialchars($body)) . '</div>';
    }

    $html .= '
        </td></tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:8px;">
        <tr>
            <td>
                <a href="' . $viewUrl . '" style="display:block;background:#3b82f6;color:#ffffff;text-decoration:none;padding:15px 24px;border-radius:10px;font-size:14px;font-weight:700;text-align:center;">
                    View Details
                </a>
            </td>
        </tr>
        </table>
    </td></tr>

    <tr><td style="background:#1e293b;padding:18px 36px;border-top:1px solid #334155;border-radius:0 0 14px 14px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="color:#475569;font-size:12px;">GoPlanner DEBESMSCAT &middot; This is an automated notification</td>
            <td align="right"><a href="' . (defined('APP_URL') ? APP_URL : '') . '/pages/settings/profile.php" style="color:#3b82f6;font-size:12px;text-decoration:none;">Notification Settings</a></td>
        </tr>
        </table>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

    return $html;
}

/**
 * Send announcement email (no event date) to a single recipient
 */
public static function sendAnnouncementEmail($recipientEmail, $recipientName, $announcement) {
    if (!MAIL_ENABLED) {
        error_log('Mail disabled — skipping email to ' . $recipientEmail);
        return false;
    }

    try {
        $mail = self::createMailer();

        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = '📣 ' . $announcement['title'];
        $mail->Body    = self::buildAnnouncementEmailHtml($announcement, $recipientName);
        $mail->AltBody = $announcement['title'] . "\n\n"
            . trim(strip_tags($announcement['content'] ?? ($announcement['body'] ?? ''))) . "\n\n"
            . 'View: ' . (defined('APP_URL') ? APP_URL : '') . '/pages/announcements/view.php?id=' . $announcement['id'];

        $mail->send();
        return true;

    } catch (\Throwable $e) {
        error_log('Mail error for ' . $recipientEmail . ': ' . $e->getMessage());
        return false;
    }
}

public static function sendEventNotifications($db, $announcement) {
    if (!MAIL_ENABLED) return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'skipped' => 0];

    try {
        $enabled = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'email_notifications_enabled'")->fetchColumn();
        if ($enabled !== null && (int)$enabled === 0) {
            error_log('GoPlanner Mail: Email notifications disabled via system settings.');
            return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'skipped' => 0, 'disabled' => true];
        }
    } catch (\Throwable $e) {}

    $sent = 0;
    $failed = 0;
    $queued = 0;
    $skipped = 0;

    // Get target users
    $users = self::getTargetUsers($db, $announcement);
    $totalUsers = count($users);

    // Skip users already notified for this announcement (idempotency)
    $alreadySent = [];
    if (!empty($announcement['id'])) {
        try {
            $sStmt = $db->prepare('SELECT email FROM email_log WHERE announcement_id = ?');
            $sStmt->execute([$announcement['id']]);
            $alreadySent = $sStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {}
    }

    error_log("GoPlanner Mail: Event '{$announcement['title']}' -> $totalUsers target user(s)");

    if ($totalUsers === 0) {
        return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'skipped' => 0];
    }

    // Check how many emails sent today
    $todayCount = self::getEmailsSentToday($db);
    $dailyLimit = defined('MAIL_DAILY_LIMIT') ? MAIL_DAILY_LIMIT : 450;
    $remaining = max(0, $dailyLimit - $todayCount);

    if ($remaining <= 0) {
        // Daily limit reached — queue all for tomorrow
        error_log("GoPlanner Mail: Daily limit ($dailyLimit) reached. Queueing all $totalUsers emails.");
        foreach ($users as $u) {
            self::queueEmail($db, $u['email'], $u['first_name'], $u['last_name'], $announcement);
            $queued++;
        }
        return ['sent' => 0, 'failed' => 0, 'queued' => $queued, 'skipped' => 0, 'daily_limit' => true];
    }

    // Send up to remaining limit, queue the rest
    $batchDelay = defined('MAIL_BATCH_DELAY') ? MAIL_BATCH_DELAY : 1000000;
    $canSendNow = min($totalUsers, $remaining);

    $counter = 0;
    $isEvent = !empty($announcement['event_date']);
    foreach ($users as $u) {
        $fullName = trim($u['first_name'] . ' ' . $u['last_name']);

        if (empty($u['email'])) {
            $skipped++;
            continue;
        }

        if (in_array($u['email'], $alreadySent, true)) {
            $skipped++;
            continue;
        }

        $counter++;

        if ($counter > $canSendNow) {
            // Over daily limit — queue remaining
            self::queueEmail($db, $u['email'], $u['first_name'], $u['last_name'], $announcement);
            $queued++;
            continue;
        }

        $result = $isEvent
            ? self::sendEventNotification($u['email'], $fullName, $announcement)
            : self::sendAnnouncementEmail($u['email'], $fullName, $announcement);

        if ($result) {
            $sent++;
            self::logEmailSent($db, $u['email'], $announcement['id']);
        } else {
            $failed++;
        }

        // Rate limiting delay
        if ($counter < $canSendNow) {
            usleep($batchDelay);
        }
    }

    $msg = "GoPlanner Mail: Done — $sent sent, $failed failed, $queued queued";
    if ($queued > 0) $msg .= " (daily limit: $dailyLimit, used: " . ($todayCount + $sent) . "/$dailyLimit)";
    error_log($msg);

    return [
        'sent' => $sent,
        'failed' => $failed,
        'queued' => $queued,
        'skipped' => $skipped,
        'daily_limit' => $dailyLimit,
        'remaining_after' => max(0, $remaining - $sent)
    ];
}

/**
 * Get how many emails were sent today
 */
private static function getEmailsSentToday($db) {
    try {
        // Check if log table exists
        $check = $db->prepare("SHOW TABLES LIKE 'email_log'");
        $check->execute();
        if (!$check->fetch()) return 0;

        $stmt = $db->prepare("SELECT COUNT(*) FROM email_log WHERE sent_date = CURDATE()");
        $stmt->execute();
        return intval($stmt->fetchColumn());
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * Log that an email was sent
 */
private static function logEmailSent($db, $email, $announcementId) {
    try {
        $db->prepare("INSERT INTO email_log (email, announcement_id, sent_date, sent_at) VALUES (?, ?, CURDATE(), NOW())")
           ->execute([$email, $announcementId]);
    } catch (\Throwable $e) {
        // Table might not exist — that's ok
    }
}

/**
 * Queue an email for later delivery
 */
private static function queueEmail($db, $email, $firstName, $lastName, $announcement) {
    try {
        $body = ($announcement['content'] ?? ($announcement['body'] ?? ''));
        if (!empty($announcement['event_date'])) {
            $body .= "\n\nDate: " . date('l, F j, Y', strtotime($announcement['event_date']));
            if (!empty($announcement['event_time'])) $body .= "\nTime: " . date('g:i A', strtotime($announcement['event_time']));
        }
        if (!empty($announcement['map_address'])) $body .= "\nLocation: " . $announcement['map_address'];
        $db->prepare("INSERT INTO email_queue (email, first_name, last_name, announcement_id, event_date, event_time, map_address, title, body, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())")
           ->execute([
               $email,
               $firstName,
               $lastName,
               $announcement['id'] ?? 0,
               $announcement['event_date'] ?? null,
               $announcement['event_time'] ?? null,
               $announcement['map_address'] ?? null,
               $announcement['title'],
               $body
           ]);
    } catch (\Throwable $e) {
        error_log('Queue email error: ' . $e->getMessage());
    }
}

/**
 * Get target users based on announcement targeting criteria
 */
private static function getTargetUsers($db, $announcement) {
    $where = ['u.status = "active"', 'u.is_archived = 0', 'u.email IS NOT NULL', 'u.email != ""'];
    $params = [];
    $joins = 'LEFT JOIN user_preferences up ON up.user_id = u.id';
    $where[] = '(up.email_notifications IS NULL OR up.email_notifications = 1)';

    if (!empty($announcement['college_id'])) {
        $where[] = 'u.college_id = ?';
        $params[] = $announcement['college_id'];
    }
    if (!empty($announcement['program_id'])) {
        $where[] = 'u.program_id = ?';
        $params[] = $announcement['program_id'];
    }
    if (!empty($announcement['target_year'])) {
        $where[] = 'u.year_level = ?';
        $params[] = $announcement['target_year'];
    }
    if (!empty($announcement['target_section'])) {
        $where[] = 'u.section = ?';
        $params[] = $announcement['target_section'];
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("SELECT u.id, u.email, u.first_name, u.last_name FROM users u $joins WHERE $whereSQL");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Process email queue (call via cron job daily)
 */
public static function processQueue($db) {
    if (!MAIL_ENABLED) return ['sent' => 0, 'failed' => 0];

    $dailyLimit = defined('MAIL_DAILY_LIMIT') ? MAIL_DAILY_LIMIT : 450;
    $todayCount = self::getEmailsSentToday($db);
    $remaining = max(0, $dailyLimit - $todayCount);

    if ($remaining <= 0) {
        return ['sent' => 0, 'failed' => 0, 'reason' => 'daily_limit'];
    }

    // Get queued emails: pending, or failed ones that still have retries left
    $maxAttempts = 3;
    $stmt = $db->prepare("
        SELECT * FROM email_queue
        WHERE status = 'pending' OR (status = 'failed' AND attempts < ?)
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $maxAttempts, PDO::PARAM_INT);
    $stmt->bindValue(2, $remaining, PDO::PARAM_INT);
    $stmt->execute();
    $queued = $stmt->fetchAll();

    $sent = 0;
    $failed = 0;
    $batchDelay = defined('MAIL_BATCH_DELAY') ? MAIL_BATCH_DELAY : 1000000;

    foreach ($queued as $q) {
        $fullName = trim($q['first_name'] . ' ' . $q['last_name']);

        $announcement = [
            'id' => $q['announcement_id'],
            'title' => $q['title'],
            'body' => $q['body'],
            'event_date' => $q['event_date'],
            'event_time' => $q['event_time'],
            'map_address' => $q['map_address']
        ];

        $result = !empty($q['event_date'])
            ? self::sendEventNotification($q['email'], $fullName, $announcement)
            : self::sendAnnouncementEmail($q['email'], $fullName, $announcement);

        if ($result) {
            $sent++;
            self::logEmailSent($db, $q['email'], $q['announcement_id']);
            // Mark as sent
            $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")
               ->execute([$q['id']]);
        } else {
            $failed++;
            // Mark as failed (retry later)
            $db->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, last_attempt = NOW() WHERE id = ?")
               ->execute([$q['id']]);
        }

        usleep($batchDelay);
    }

    return ['sent' => $sent, 'failed' => $failed, 'processed' => count($queued)];
}

    /**
     * Send account rejection notification email
     */
    public static function sendRejectionEmail(string $email, string $fullName): bool {
        if (!MAIL_ENABLED) return false;

        try {
            $db = db();
            $enabled = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'email_notifications_enabled'")->fetchColumn();
            if ($enabled !== null && (int)$enabled === 0) {
                error_log('GoPlanner Mail: Rejection email skipped — notifications disabled via system settings.');
                return false;
            }
        } catch (\Throwable $e) {}

        $mail = self::createMailer();

        $siteName = defined('APP_NAME') ? APP_NAME : 'GoPlanner';
        $institutionShort = 'DEBESMSCAT';
        try {
            $db = db();
            $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $siteName = $settings['site_name'] ?? $siteName;
            $institutionShort = $settings['institution_short'] ?? $institutionShort;
        } catch (\Throwable $e) {}

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Account Not Approved</title></head>';
        $html .= '<body style="margin:0;padding:0;background:#0f172a;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;padding:40px 20px;">';
        $html .= '<tr><td align="center"><table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;">';

        // Header
        $html .= '<tr><td style="background:linear-gradient(135deg,#64748b 0%,#475569 100%);padding:32px 36px;border-radius:14px 14px 0 0;">';
        $html .= '<h1 style="color:#ffffff;font-size:22px;margin:0;font-weight:700;">&#9888; Account Not Approved</h1>';
        $html .= '<p style="color:#cbd5e1;font-size:14px;margin:8px 0 0;">' . htmlspecialchars($siteName) . '</p>';
        $html .= '</td></tr>';

        // Body
        $html .= '<tr><td style="background:#1e293b;padding:32px 36px;">';
        $html .= '<p style="color:#e2e8f0;font-size:15px;margin:0 0 20px;">Dear ' . htmlspecialchars($fullName) . ',</p>';
        $html .= '<p style="color:#cbd5e1;font-size:14px;line-height:1.7;margin:0 0 20px;">Thank you for your interest in joining <strong>' . htmlspecialchars($siteName) . '</strong>. After reviewing your registration, we regret to inform you that your account has not been approved at this time.</p>';
        $html .= '<p style="color:#cbd5e1;font-size:14px;line-height:1.7;margin:0 0 20px;">If you believe this is an error or would like to appeal this decision, please contact the ' . htmlspecialchars($institutionShort) . ' administration.</p>';

        // Info box
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0f172a;border:1px solid #334155;border-radius:12px;margin-bottom:24px;">';
        $html .= '<tr><td style="padding:20px 24px;">';
        $html .= '<p style="color:#94a3b8;font-size:13px;margin:0;line-height:1.6;">If you have questions, please reach out to your administrator or reply to this email.</p>';
        $html .= '</td></tr></table>';

        $html .= '</td></tr>';

        // Footer
        $html .= '<tr><td style="background:#1e293b;padding:20px 36px;border-top:1px solid #334155;border-radius:0 0 14px 14px;text-align:center;">';
        $html .= '<p style="color:#64748b;font-size:12px;margin:0;">This is an automated message from ' . htmlspecialchars($siteName) . '. Please do not reply directly.</p>';
        $html .= '</td></tr></table></td></tr></table></body></html>';

        try {
            $mail->addAddress($email, $fullName);
            $mail->isHTML(true);
            $mail->Subject = 'Account Not Approved — ' . $siteName;
            $mail->Body = $html;
            $mail->AltBody = "Dear $fullName,\n\nThank you for your interest in joining $siteName. After reviewing your registration, we regret to inform you that your account has not been approved at this time.\n\nIf you believe this is an error, please contact the $institutionShort administration.";
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('Rejection email failed: ' . $e->getMessage());
            return false;
        }
    }

}