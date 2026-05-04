<?php
/**
 * Central Audit Logging helper.
 *
 * Usage:
 *   require_once __DIR__ . "/audit.php";
 *   audit_init($conn);
 *   audit_event($conn, "equipment.create", ["eqName" => "..."]);
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function audit_actor_snapshot(): array
{
    $userId = 0;
    if (isset($_SESSION["vlms_user_id"])) {
        $userId = (int)$_SESSION["vlms_user_id"];
    } elseif (isset($_SESSION["user_id"])) {
        $userId = (int)$_SESSION["user_id"];
    }

    $role = $_SESSION["vlms_role"] ?? ($_SESSION["role"] ?? null);
    $role = is_string($role) && $role !== "" ? $role : null;

    $actorType = $userId > 0 ? "user" : "guest";
    return [
        "actor_type" => $actorType,
        "actor_user_id" => $userId > 0 ? $userId : null,
        "actor_role" => $role,
    ];
}

function audit_safe_json($value): ?string
{
    if ($value === null) return null;
    try {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return null;
        return $json;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Initialize audit system (table + session snapshot).
 * Note: we intentionally do NOT log every request.
 */
function audit_init(mysqli $conn): void
{
    // Schema is provisioned separately; no runtime table creation.
}

/**
 * Write a specific event into audit log.
 */
function audit_event(mysqli $conn, string $action, array $payload = []): void
{
    $actor = audit_actor_snapshot();

    $method = $_SERVER["REQUEST_METHOD"] ?? null;
    $uri = $_SERVER["REQUEST_URI"] ?? null;
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? null;
    $ref = $_SERVER["HTTP_REFERER"] ?? null;

    // Basic redaction.
    foreach (["password", "pass", "pwd"] as $k) {
        if (array_key_exists($k, $payload)) {
            $payload[$k] = "***";
        }
    }

    $payload = array_merge(["type" => "event"], $payload);
    $payloadJson = audit_safe_json($payload);

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
        (actor_type, actor_user_id, actor_role, action, request_method, request_uri, ip, user_agent, referrer, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;

    $actorUserIdParam = $actor["actor_user_id"];
    $actorRole = $actor["actor_role"];

    $stmt->bind_param(
        "sissssssss",
        $actor["actor_type"],
        $actorUserIdParam,
        $actorRole,
        $action,
        $method,
        $uri,
        $ip,
        $ua,
        $ref,
        $payloadJson
    );
    $stmt->execute();
    $stmt->close();
}

