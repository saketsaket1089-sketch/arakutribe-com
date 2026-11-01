<?php
session_start();
session_unset();
session_destroy();
//header("Location: dashboard.php");
header("Location: adminlogin.php");
exit;
