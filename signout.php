<?php
session_start();
session_destroy();
$sistem = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/stockmate_pro';
header("Location: $sistem/signin?success=logout");
exit;
