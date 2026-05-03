<?php

function booking_rate_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS booking_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 15.00,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $conn->query("INSERT IGNORE INTO booking_settings (id, hourly_rate) VALUES (1, 15.00)");
    $conn->query(
        "CREATE TABLE IF NOT EXISTS booking_user_rates (
            user_id INT NOT NULL PRIMARY KEY,
            hourly_rate DECIMAL(10,2) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function booking_get_hourly_rate(mysqli $conn): float
{
    booking_rate_ensure_table($conn);
    $res = $conn->query("SELECT hourly_rate FROM booking_settings WHERE id = 1 LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (float)$row["hourly_rate"];
    }
    return 15.0;
}

function booking_set_hourly_rate(mysqli $conn, float $rate): bool
{
    booking_rate_ensure_table($conn);
    $safeRate = number_format(max(0, $rate), 2, ".", "");
    return (bool)$conn->query("UPDATE booking_settings SET hourly_rate = $safeRate WHERE id = 1");
}

function booking_get_user_hourly_rate(mysqli $conn, int $userID): ?float
{
    booking_rate_ensure_table($conn);
    $userID = (int)$userID;
    $res = $conn->query("SELECT hourly_rate FROM booking_user_rates WHERE user_id = $userID LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (float)$row["hourly_rate"];
    }
    return null;
}

function booking_set_user_hourly_rate(mysqli $conn, int $userID, float $rate): bool
{
    booking_rate_ensure_table($conn);
    $userID = (int)$userID;
    $safeRate = number_format(max(0, $rate), 2, ".", "");
    return (bool)$conn->query(
        "INSERT INTO booking_user_rates (user_id, hourly_rate)
         VALUES ($userID, $safeRate)
         ON DUPLICATE KEY UPDATE hourly_rate = VALUES(hourly_rate)"
    );
}

function booking_clear_user_hourly_rate(mysqli $conn, int $userID): bool
{
    booking_rate_ensure_table($conn);
    $userID = (int)$userID;
    return (bool)$conn->query("DELETE FROM booking_user_rates WHERE user_id = $userID");
}

function booking_get_effective_hourly_rate(mysqli $conn, int $userID): float
{
    $userRate = booking_get_user_hourly_rate($conn, $userID);
    if ($userRate !== null) {
        return (float)$userRate;
    }
    return booking_get_hourly_rate($conn);
}
