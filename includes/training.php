<?php

function training_ensure_tables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS equipment_training_requirements (
            eqID INT NOT NULL PRIMARY KEY,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            training_title VARCHAR(150) NULL,
            updated_by INT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_training_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            userID INT NOT NULL,
            eqID INT NOT NULL,
            training_title VARCHAR(150) NULL,
            passed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            approved_by INT NULL,
            UNIQUE KEY uq_user_equipment (userID, eqID),
            KEY idx_user (userID),
            KEY idx_equipment (eqID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $hasExpires = $conn->query("SHOW COLUMNS FROM user_training_records LIKE 'expires_at'");
    if (!$hasExpires || $hasExpires->num_rows === 0) {
        $conn->query("ALTER TABLE user_training_records ADD COLUMN expires_at DATETIME NULL AFTER passed_at");
    }
    $conn->query("UPDATE user_training_records
                  SET expires_at = DATE_ADD(passed_at, INTERVAL 3 MONTH)
                  WHERE expires_at IS NULL");
}

function training_set_requirement(mysqli $conn, int $eqID, bool $required, ?string $title, ?int $updatedBy = null): bool
{
    training_ensure_tables($conn);
    $eqID = (int)$eqID;
    $requiredInt = $required ? 1 : 0;
    $safeTitle = $title !== null ? trim($title) : "";
    $safeTitleSql = $safeTitle === "" ? "NULL" : ("'" . $conn->real_escape_string($safeTitle) . "'");
    $updatedBySql = $updatedBy !== null ? (string)(int)$updatedBy : "NULL";

    return (bool)$conn->query(
        "INSERT INTO equipment_training_requirements (eqID, is_required, training_title, updated_by)
         VALUES ($eqID, $requiredInt, $safeTitleSql, $updatedBySql)
         ON DUPLICATE KEY UPDATE
            is_required = VALUES(is_required),
            training_title = VALUES(training_title),
            updated_by = VALUES(updated_by)"
    );
}

function training_mark_user_passed(mysqli $conn, int $userID, int $eqID, ?string $title = null, ?int $approvedBy = null): bool
{
    training_ensure_tables($conn);
    $userID = (int)$userID;
    $eqID = (int)$eqID;
    $safeTitle = $title !== null ? trim($title) : "";
    $safeTitleSql = $safeTitle === "" ? "NULL" : ("'" . $conn->real_escape_string($safeTitle) . "'");
    $approvedBySql = $approvedBy !== null ? (string)(int)$approvedBy : "NULL";

    return (bool)$conn->query(
        "INSERT INTO user_training_records (userID, eqID, training_title, approved_by, expires_at)
         VALUES ($userID, $eqID, $safeTitleSql, $approvedBySql, DATE_ADD(NOW(), INTERVAL 3 MONTH))
         ON DUPLICATE KEY UPDATE
            training_title = VALUES(training_title),
            approved_by = VALUES(approved_by),
            passed_at = CURRENT_TIMESTAMP,
            expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 3 MONTH)"
    );
}

function training_get_required_map(mysqli $conn): array
{
    training_ensure_tables($conn);
    $map = [];
    $res = $conn->query("SELECT eqID, is_required, training_title FROM equipment_training_requirements");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ((int)($row["is_required"] ?? 0) === 1) {
                $map[(int)$row["eqID"]] = (string)($row["training_title"] ?? "");
            }
        }
    }
    return $map;
}

function training_get_user_passed_map(mysqli $conn, int $userID): array
{
    training_ensure_tables($conn);
    $userID = (int)$userID;
    $map = [];
    $res = $conn->query("SELECT eqID
                         FROM user_training_records
                         WHERE userID = $userID
                           AND expires_at > NOW()");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row["eqID"]] = true;
        }
    }
    return $map;
}

function training_user_has_access(mysqli $conn, int $userID, int $eqID): bool
{
    training_ensure_tables($conn);
    $eqID = (int)$eqID;
    $requiredRes = $conn->query("SELECT is_required FROM equipment_training_requirements WHERE eqID = $eqID LIMIT 1");
    $isRequired = false;
    if ($requiredRes && $row = $requiredRes->fetch_assoc()) {
        $isRequired = (int)($row["is_required"] ?? 0) === 1;
    }
    if (!$isRequired) return true;

    $userID = (int)$userID;
    $passRes = $conn->query("SELECT id
                             FROM user_training_records
                             WHERE userID = $userID
                               AND eqID = $eqID
                               AND expires_at > NOW()
                             LIMIT 1");
    return $passRes && $passRes->num_rows > 0;
}
