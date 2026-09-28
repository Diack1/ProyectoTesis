<?php

return [
    'access_mail_ready' => env('SECURITY_ACCESS_MAIL_READY', false),
    'require_staff_mfa' => env('SECURITY_REQUIRE_STAFF_MFA', true),
    // Alerts remain local until an explicitly configured destination and mailer exist.
    'alert_email' => env('SECURITY_ALERT_EMAIL'),
];
