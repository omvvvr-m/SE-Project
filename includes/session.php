<?php

class session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function requireUserOrRedirectToLogin(): int
    {
        self::start();
        if (!isset($_SESSION["user_id"])) {
            header("Location: login.html");
            exit;
        }

        return (int)$_SESSION["user_id"];
    }

    public static function pullFlash(string $key)
    {
        self::start();
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function setFlash(string $key, string $message): void
    {
        self::start();
        $_SESSION[$key] = $message;
    }

    /** @return DateTime|null */
    public static function parseSessionEndDateTime($resDate, $endTime)
    {
        $endTime = trim((string)$endTime);
        if ($endTime === '') {
            return null;
        }
        $end = null;
        $resDate = trim((string)$resDate);
        if ($resDate !== '') {
            $end = DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $endTime)
                ?: DateTime::createFromFormat('Y-m-d H:i:s', $resDate . ' ' . $endTime);
        }
        if (!$end && preg_match('/^\d{4}-\d{2}-\d{2}/', $endTime)) {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', $endTime)
                ?: DateTime::createFromFormat('Y-m-d H:i', $endTime);
        }
        if (!$end) {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' ' . $endTime)
                ?: DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d') . ' ' . $endTime);
        }
        return $end ?: null;
    }

    public static function formatRemainingTime($resDate, $endTime): string
    {
        $end = self::parseSessionEndDateTime($resDate, $endTime);
        if (!$end) {
            return "00:00:00";
        }
        $now = new DateTime();
        if ($now >= $end) {
            return "00:00:00";
        }
        $seconds = $end->getTimestamp() - $now->getTimestamp();
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
    }

    public static function formatHmsFromSeconds($seconds): string
    {
        $seconds = max(0, (int)$seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
    }

    public static function sqlNowAndEndTs(mysqli $conn, array $activeSession): array
    {
        $nowRes = $conn->query("SELECT UNIX_TIMESTAMP(NOW()) AS ts");
        $nowTs = 0;
        if ($nowRes && $row = $nowRes->fetch_assoc()) {
            $nowTs = (int)$row['ts'];
        }

        $endTs = null;
        $resDate = trim((string)($activeSession['resDate'] ?? ''));
        $endTime = trim((string)($activeSession['endTime'] ?? ''));

        if ($endTime !== '') {
            $er = null;
            if ($resDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $resDate)) {
                $d = $conn->real_escape_string($resDate);
                $t = $conn->real_escape_string($endTime);
                $er = $conn->query("SELECT UNIX_TIMESTAMP(CONCAT('$d', ' ', '$t')) AS ts");
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $endTime)) {
                $t = $conn->real_escape_string($endTime);
                $er = $conn->query("SELECT UNIX_TIMESTAMP('$t') AS ts");
            } else {
                $t = $conn->real_escape_string($endTime);
                $er = $conn->query("SELECT UNIX_TIMESTAMP(CONCAT(CURDATE(), ' ', '$t')) AS ts");
            }
            if ($er) {
                $row = $er->fetch_assoc();
                if (is_array($row) && isset($row['ts']) && is_numeric($row['ts'])) {
                    $endTs = (int)$row['ts'];
                }
            }
        }

        return ['now_ts' => $nowTs, 'end_ts' => $endTs];
    }

    public static function handleDashboardUserSessionAction(
        mysqli $conn,
        Reservation $reservation,
        int $currentUserId,
        array $postData
    ): bool {
        if (!isset($postData['session_action'])) {
            return false;
        }

        $action = $postData['session_action'];
        $resID = isset($postData['res_id']) ? (int)$postData['res_id'] : 0;

        if ($action === 'terminate' && $resID > 0) {
            audit_init($conn);
            audit_event($conn, "session.terminate", [
                "resID" => (int)$resID,
                "userID" => (int)$currentUserId
            ]);
            $reservation->terminateSession($resID, (int)$currentUserId);
            self::setFlash('session_action_msg', 'Session terminated successfully.');
        } elseif ($action === 'emergency' && $resID > 0) {
            $message = trim($postData['emergency_message'] ?? '');
            $startTime = $postData['start_time'] ?? '';
            $endTime = $postData['end_time'] ?? '';
            if ($message === '') {
                self::setFlash('session_action_msg', 'Please write the emergency issue details.');
            } else {
                audit_init($conn);
                audit_event($conn, "session.emergency_report", [
                    "resID" => (int)$resID,
                    "userID" => (int)$currentUserId,
                    "message" => mb_substr((string)$message, 0, 200)
                ]);
                $reservation->createEmergencyReport($resID, (int)$currentUserId, $message, $startTime, $endTime);
                $reservation->terminateSession($resID, (int)$currentUserId);
                self::setFlash('session_action_msg', 'Emergency report sent to admin and session terminated.');
            }
        } elseif ($action === 'need_help' && $resID > 0) {
            $message = trim($postData['help_message'] ?? '');
            if ($message === '') {
                self::setFlash('session_action_msg', 'Please write what help you need.');
            } else {
                audit_init($conn);
                audit_event($conn, "session.supervision_request", [
                    "resID" => (int)$resID,
                    "userID" => (int)$currentUserId,
                    "message" => mb_substr((string)$message, 0, 200)
                ]);
                $reservation->createSessionSupportRequest($resID, (int)$currentUserId, $message);
                self::setFlash('session_action_msg', 'Support request sent to admin successfully.');
            }
        } elseif ($action === 'heartbeat_timeout' && $resID > 0) {
            audit_init($conn);
            audit_event($conn, "session.auto_terminate_inactive", [
                "resID" => (int)$resID,
                "userID" => (int)$currentUserId
            ]);
            $reservation->terminateSession($resID, (int)$currentUserId);
            self::setFlash('session_action_msg', 'Session terminated due to inactivity (no response to presence check).');
        }

        return true;
    }
}
