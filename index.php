<?php
session_start();
$sistem = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/stockmate_pro';
if (isset($_SESSION['user_id'])) { header("Location: $sistem/home"); }
else { header("Location: $sistem/signin"); }
exit;
