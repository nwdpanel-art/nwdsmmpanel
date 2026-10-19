<?php

/*
|--------------------------------------------------------------------------
| ADMIN NOTIFICATIONS (EMAIL + WHATSAPP)
|--------------------------------------------------------------------------
|
| Sends a WhatsApp message (via CallMeBot - free, personal-use API) and
| an email (via Gmail SMTP, using a Gmail "App Password") to the admin
| whenever something happens that needs attention: a new order, or a
| new deposit request.
|
| No external libraries/composer needed - plain sockets for SMTP, plain
| cURL for CallMeBot.
|
| SETUP REQUIRED (see config.php):
|
| 1. WhatsApp (CallMeBot):
|    - Save +34 684 72 39 62 in your phone as a contact.
|    - From YOUR WhatsApp (the number you want notifications on), send
|      this exact message to that contact: "I allow callmebot to send
|      me messages"
|    - You'll get a reply with your personal API key. Put your phone
|      number (with country code, no + or spaces, e.g. 923001234567)
|      and that API key into config.php.
|
| 2. Email (Gmail):
|    - Go to https://myaccount.google.com/apppasswords
|    - Create an "App Password" for Mail (needs 2-Step Verification
|      enabled on the Gmail account first).
|    - Put your Gmail address and that 16-character App Password into
|      config.php. Do NOT use your normal Gmail password - it will not
|      work and Google will block the login.
|
*/


/*
|--------------------------------------------------------------------------
| SEND WHATSAPP (CALLMEBOT)
|--------------------------------------------------------------------------
*/

function notify_whatsapp($message)
{
    if (
        !defined('WHATSAPP_NOTIFY_ENABLED') ||
        WHATSAPP_NOTIFY_ENABLED !== true
    ) {
        return false;
    }

    if (
        ADMIN_WHATSAPP_NUMBER === '' ||
        CALLMEBOT_API_KEY === ''
    ) {
        return false;
    }

    $url =
        'https://api.callmebot.com/whatsapp.php?phone=' .
        urlencode(ADMIN_WHATSAPP_NUMBER) .
        '&text=' .
        urlencode($message) .
        '&apikey=' .
        urlencode(CALLMEBOT_API_KEY);

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $ok       = ($response !== false);

    curl_close($ch);

    return $ok;
}


/*
|--------------------------------------------------------------------------
| SEND EMAIL (RAW SMTP OVER GMAIL - NO LIBRARY NEEDED)
|--------------------------------------------------------------------------
*/

function notify_email($subject, $body)
{
    if (
        !defined('EMAIL_NOTIFY_ENABLED') ||
        EMAIL_NOTIFY_ENABLED !== true
    ) {
        return false;
    }

    if (
        GMAIL_ADDRESS === '' ||
        GMAIL_APP_PASSWORD === '' ||
        ADMIN_NOTIFY_EMAIL === ''
    ) {
        return false;
    }

    $host = 'smtp.gmail.com';
    $port = 587;

    $smtp = @fsockopen($host, $port, $errno, $errstr, 15);

    if (!$smtp) {
        return false;
    }

    stream_set_timeout($smtp, 15);


    $expect = function ($socket, $code) {

        $response = '';

        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        return substr($response, 0, 3) === (string) $code;
    };


    if (!$expect($smtp, 220)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "EHLO localhost\r\n");
    if (!$expect($smtp, 250)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "STARTTLS\r\n");
    if (!$expect($smtp, 220)) {
        fclose($smtp);
        return false;
    }

    if (
        !stream_socket_enable_crypto(
            $smtp,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        )
    ) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "EHLO localhost\r\n");
    if (!$expect($smtp, 250)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "AUTH LOGIN\r\n");
    if (!$expect($smtp, 334)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, base64_encode(GMAIL_ADDRESS) . "\r\n");
    if (!$expect($smtp, 334)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, base64_encode(GMAIL_APP_PASSWORD) . "\r\n");
    if (!$expect($smtp, 235)) {
        // Auth failed - usually wrong App Password or 2-Step Verification
        // not enabled on the Gmail account.
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "MAIL FROM:<" . GMAIL_ADDRESS . ">\r\n");
    if (!$expect($smtp, 250)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "RCPT TO:<" . ADMIN_NOTIFY_EMAIL . ">\r\n");
    if (!$expect($smtp, 250)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "DATA\r\n");
    if (!$expect($smtp, 354)) {
        fclose($smtp);
        return false;
    }

    $headers =
        "From: " . SITE_NAME . " <" . GMAIL_ADDRESS . ">\r\n" .
        "To: <" . ADMIN_NOTIFY_EMAIL . ">\r\n" .
        "Subject: " . $subject . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";

    // Per SMTP rules, a lone "." on a line must be escaped as ".."
    $escaped_body = str_replace("\n.", "\n..", $body);

    fwrite(
        $smtp,
        $headers . "\r\n" . $escaped_body . "\r\n.\r\n"
    );

    if (!$expect($smtp, 250)) {
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);

    return true;
}


/*
|--------------------------------------------------------------------------
| SEND BOTH (CONVENIENCE WRAPPER)
|--------------------------------------------------------------------------
|
| Fire-and-forget - never throws, so a failed notification never breaks
| the order/deposit flow that triggered it.
|
*/

function notify_admin($subject, $message)
{
    try {
        notify_whatsapp($subject . "\n" . $message);
    } catch (Exception $e) {
        // Ignore - notifications are best-effort.
    }

    try {
        notify_email($subject, $message);
    } catch (Exception $e) {
        // Ignore - notifications are best-effort.
    }
}