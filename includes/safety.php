<?php

function safety_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS equipment_safety_requirements (
            eqID INT NOT NULL PRIMARY KEY,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            reason TEXT NULL,
            updated_by INT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function safety_set_requirement(mysqli $conn, int $eqID, bool $isRequired, ?string $reason, ?int $updatedBy = null): bool
{
    safety_ensure_table($conn);
    $eqID = (int)$eqID;
    $requiredInt = $isRequired ? 1 : 0;
    $safeReason = trim((string)$reason);
    $reasonSql = $safeReason === "" ? "NULL" : ("'" . $conn->real_escape_string($safeReason) . "'");
    $updatedBySql = $updatedBy !== null ? (string)(int)$updatedBy : "NULL";

    return (bool)$conn->query(
        "INSERT INTO equipment_safety_requirements (eqID, is_required, reason, updated_by)
         VALUES ($eqID, $requiredInt, $reasonSql, $updatedBySql)
         ON DUPLICATE KEY UPDATE
            is_required = VALUES(is_required),
            reason = VALUES(reason),
            updated_by = VALUES(updated_by)"
    );
}

function safety_get_for_equipment(mysqli $conn, int $eqID): array
{
    safety_ensure_table($conn);
    $eqID = (int)$eqID;
    $res = $conn->query("SELECT is_required, reason FROM equipment_safety_requirements WHERE eqID = $eqID LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return [
            "is_required" => (int)($row["is_required"] ?? 0) === 1,
            "reason" => (string)($row["reason"] ?? "")
        ];
    }
    return ["is_required" => false, "reason" => ""];
}

function safety_get_map(mysqli $conn): array
{
    safety_ensure_table($conn);
    $map = [];
    $res = $conn->query("SELECT eqID, is_required, reason FROM equipment_safety_requirements");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row["eqID"]] = [
                "is_required" => (int)($row["is_required"] ?? 0) === 1,
                "reason" => (string)($row["reason"] ?? "")
            ];
        }
    }
    return $map;
}
