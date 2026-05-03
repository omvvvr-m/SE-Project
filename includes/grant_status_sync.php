<?php

declare(strict_types=1);

/**
 * Ensures grants.status exists, then sets active/expired from expiryDate (server date).
 */
function vl_sync_grant_status_schema(mysqli $conn): void
{
    $tbl = $conn->query("SHOW TABLES LIKE 'grants'");
    if (!$tbl || $tbl->num_rows === 0) {
        return;
    }
    $col = $conn->query("SHOW COLUMNS FROM grants LIKE 'status'");
    if ($col && $col->num_rows === 0) {
        $conn->query(
            "ALTER TABLE grants ADD COLUMN status ENUM('active','expired') NOT NULL DEFAULT 'active'"
        );
    }
}

function vl_sync_grant_expiry_statuses(mysqli $conn): void
{
    $col = $conn->query("SHOW COLUMNS FROM grants LIKE 'status'");
    if (!$col || $col->num_rows === 0) {
        return;
    }
    $conn->query("UPDATE grants SET status = 'expired' WHERE expiryDate < CURDATE()");
    $conn->query("UPDATE grants SET status = 'active' WHERE expiryDate >= CURDATE()");
}
