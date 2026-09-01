<?php
require __DIR__ . '/_bootstrap.php';
$auth->logout();
header('Location: login.php');
