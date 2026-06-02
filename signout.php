<?php
session_start();
session_destroy();
$sistem = 'http://'.$_SERVER['HTTP_HOST'].'/project_work';
header("Location: $sistem/signin?success=logout");
exit;
