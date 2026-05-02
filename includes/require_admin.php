<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['vlms_role'] ?? '';
if ($role !== 'admin') {
    header('Location: login.html');
    exit;
}
