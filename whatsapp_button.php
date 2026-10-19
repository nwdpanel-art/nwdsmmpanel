<?php

/*
|--------------------------------------------------------------------------
| FLOATING WHATSAPP CHAT BUTTONS
|--------------------------------------------------------------------------
|
| Included near the end of every customer-facing page (before </body>).
| Shows up to 2 floating buttons, stacked bottom-right, each opening a
| WhatsApp chat with a different number via the wa.me link (no API
| setup needed - works instantly).
|
| Set numbers in config.php:
|   SITE_WHATSAPP_NUMBER_1 / SITE_WHATSAPP_LABEL_1
|   SITE_WHATSAPP_NUMBER_2 / SITE_WHATSAPP_LABEL_2
| (country code, no + or spaces, e.g. 923001234567)
| Leave a number blank to hide just that button.
|
*/

function render_whatsapp_button($number, $label, $bottom_offset)
{
    if ($number === '') {
        return;
    }

    $whatsapp_link =
        'https://wa.me/' .
        $number .
        '?text=' .
        urlencode('Hi, I need help with my account.');

?>

<a
    href="<?= e($whatsapp_link) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="<?= e($label) ?>"
    style="
        position: fixed;
        bottom: <?= (int) $bottom_offset ?>px;
        right: 24px;
        width: 58px;
        height: 58px;
        background: #25D366;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(0,0,0,0.35);
        z-index: 9998;
        text-decoration: none;
        transition: transform 0.15s ease;
    "
    onmouseover="this.style.transform='scale(1.08)';"
    onmouseout="this.style.transform='scale(1)';"
>

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 32 32"
    width="30"
    height="30"
    fill="#ffffff"
>
<path d="M16.004 3C9.377 3 3.999 8.377 3.999 15.003c0 2.65.86 5.1 2.317 7.09L4.999 29l7.083-2.267c1.9 1.037 4.08 1.627 6.398 1.627h.005c6.627 0 12.004-5.377 12.004-12.003C30.489 8.377 25.11 3 16.004 3zm0 21.929h-.004a9.9 9.9 0 0 1-5.05-1.386l-.362-.216-3.75 1.2 1.226-3.654-.236-.375a9.9 9.9 0 0 1-1.52-5.296c0-5.478 4.457-9.933 9.937-9.933 2.654 0 5.148 1.034 7.024 2.912a9.87 9.87 0 0 1 2.91 7.026c-.001 5.478-4.457 9.722-9.175 9.722zm5.446-7.393c-.298-.15-1.764-.87-2.038-.97-.273-.1-.472-.15-.671.15-.198.298-.771.97-.945 1.169-.174.199-.347.224-.645.075-.298-.15-1.259-.464-2.397-1.48-.886-.79-1.484-1.767-1.658-2.065-.174-.298-.019-.46.13-.608.134-.133.298-.348.446-.522.15-.174.199-.298.298-.497.1-.199.05-.373-.025-.522-.075-.15-.671-1.618-.92-2.216-.242-.582-.487-.503-.671-.512l-.571-.01c-.199 0-.522.075-.796.373s-1.045 1.02-1.045 2.489 1.07 2.887 1.219 3.086c.15.199 2.105 3.213 5.1 4.505.712.307 1.268.49 1.701.627.715.227 1.365.195 1.879.118.573-.086 1.764-.72 2.013-1.417.248-.696.248-1.293.174-1.417-.075-.124-.273-.199-.571-.348z"/>
</svg>

</a>

<?php

}


render_whatsapp_button(
    defined('SITE_WHATSAPP_NUMBER_1') ? SITE_WHATSAPP_NUMBER_1 : '',
    defined('SITE_WHATSAPP_LABEL_1') ? SITE_WHATSAPP_LABEL_1 : 'Chat with us on WhatsApp',
    24
);

render_whatsapp_button(
    defined('SITE_WHATSAPP_NUMBER_2') ? SITE_WHATSAPP_NUMBER_2 : '',
    defined('SITE_WHATSAPP_LABEL_2') ? SITE_WHATSAPP_LABEL_2 : 'Chat with us on WhatsApp',
    92
);