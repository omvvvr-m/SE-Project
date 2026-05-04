<?php

declare(strict_types=1);

function vl_sync_grant_expiry_statuses(mysqli $conn): void
{
    $conn->query("UPDATE grants SET status = 'expired' WHERE expiryDate < CURDATE()");
    $conn->query("UPDATE grants SET status = 'active' WHERE expiryDate >= CURDATE()");
}
