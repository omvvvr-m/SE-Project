<?php
$conn = new mysqli("localhost", "root", "", "virtual_lab");

if ($conn->connect_error) {
    die("DB failed: " . $conn->connect_error);
}

require_once __DIR__ . "/../includes/grant_status_sync.php";
vl_sync_grant_expiry_statuses($conn);

// Auto-clean expired guest accounts (24h lifetime).
$hasGuestExpires = $conn->query("SHOW COLUMNS FROM users LIKE 'guest_expires_at'");
if ($hasGuestExpires && $hasGuestExpires->num_rows > 0) {
    $conn->query("DELETE FROM users
                  WHERE role = 'guest'
                    AND guest_expires_at IS NOT NULL
                    AND guest_expires_at <= NOW()");
}
