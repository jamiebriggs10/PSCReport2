<?php
/**
 * Mail configuration template.
 * Copy this file to mail.php and fill in the credentials.
 * This file is intentionally committed; the real mail.php is git-ignored.
 */
return [
    'driver' => 'smtp',          // smtp | mail (native)
    'host' => 'smtp.gmail.com',  // Gmail SMTP host
    'port' => 587,               // 587 (TLS) or 465 (SSL)
    'encryption' => 'tls',       // tls | ssl
    'username' => 'ee273ecom@gmail.com', // Full Gmail address
    'password' => 'tply zpog furr waqi',     // 16-char Gmail App Password (NOT your normal password)
    'from_email' => 'ee273ecom@gmail.com',
    'from_name' => 'PSC Issues',
    'timeout' => 15,             // seconds
    'debug' => false             // set true to log SMTP conversation
];
