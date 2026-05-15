<?php
require_once 'config.php';
require_once 'includes/auth.php';

logout();
header('Location: login.php?message=logged_out');
exit;