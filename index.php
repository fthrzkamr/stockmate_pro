<?php
session_start();
$sistem = 'http://'.$_SERVER['HTTP_HOST'].'/project_work';
if (isset($_SESSION['user_id'])) { header("Location: $sistem/home"); }
else { header("Location: $sistem/signin"); }
exit;
