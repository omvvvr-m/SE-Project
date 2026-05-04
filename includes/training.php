<?php

function training_set_requirement(mysqli $conn, int $eqID, bool $required, ?string $title, ?int $updatedBy = null): bool
{
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
