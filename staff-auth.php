
<?php

/**
 * MEPhys Staff Authentication Core
 * PHP 8.4+
 */

if (defined('MEPHYS_AUTH_LOADED')) {
    return;
}

define('MEPHYS_AUTH_LOADED', true);
define('MEPHYS_AUTH_BOOTSTRAPPED', true);

require_once __DIR__ . '/db.php';

// Load private security configuration.
$securityConfig = require __DIR__ . '/staff-security-config.php';

if (
    empty($securityConfig['security_key']) ||
    strlen($securityConfig['security_key']) < 64 ||
    $securityConfig['security_key'] === 'PASTE_YOUR_GENERATED_KEY_HERE'
) {
    error_log('Mephys security configuration is missing or invalid.');
    http_response_code(500);
    exit('Security configuration error.');
}

define('MEPHYS_SECURITY_KEY', $securityConfig['security_key']);

// Session security settings.
$isHttps = (
    isset($_SERVER['HTTPS']) &&
    strtolower((string) $_SERVER['HTTPS']) !== 'off' &&
    $_SERVER['HTTPS'] !== ''
);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

session_name('MEPHYSSESSID');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Security headers for administration pages.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Session duration settings.
define('MEPHYS_IDLE_TIMEOUT', 1800);      // 30 minutes
define('MEPHYS_ABSOLUTE_TIMEOUT', 28800); // 8 hours


/**
 * Generate a CSRF token.
 */
function mephysCsrfToken(): string
{
    if (empty($_SESSION['mephys_csrf_token'])) {
        $_SESSION['mephys_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['mephys_csrf_token'];
}


/**
 * Validate a submitted CSRF token.
 */
function mephysVerifyCsrfToken(?string $token): bool
{
    if (
        !is_string($token) ||
        empty($_SESSION['mephys_csrf_token'])
    ) {
        return false;
    }

    return hash_equals($_SESSION['mephys_csrf_token'], $token);
}


/**
 * Check whether a staff member is logged in.
 */
function mephysIsStaffLoggedIn(): bool
{
    return isset($_SESSION['staff_user_id'])
        && is_int($_SESSION['staff_user_id'])
        && $_SESSION['staff_user_id'] > 0;
}


/**
 * Clear the current staff session.
 */
function mephysDestroyStaffSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


/**
 * Require an authenticated and active staff account.
 *
 * Every protected administration page must call this.
 */

function mephysRequireStaffLogin(): array
{
    global $pdo;

    if (!mephysIsStaffLoggedIn()) {
        header('Location: staff-login.php');
        exit;
    }

    $now = time();

    $lastActivity = $_SESSION['mephys_last_activity'] ?? 0;
    $authenticatedAt = $_SESSION['mephys_authenticated_at'] ?? 0;

    if (
        !is_int($lastActivity) ||
        !is_int($authenticatedAt) ||
        $lastActivity <= 0 ||
        $authenticatedAt <= 0
    ) {
        mephysDestroyStaffSession();
        header('Location: staff-login.php');
        exit;
    }

    if (($now - $lastActivity) > MEPHYS_IDLE_TIMEOUT) {
        mephysDestroyStaffSession();
        header('Location: staff-login.php?expired=1');
        exit;
    }

    if (($now - $authenticatedAt) > MEPHYS_ABSOLUTE_TIMEOUT) {
        mephysDestroyStaffSession();
        header('Location: staff-login.php?expired=1');
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, username, email, role, is_active,
                    must_change_password
             FROM staff_users
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $_SESSION['staff_user_id']
        ]);

        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff || (int) $staff['is_active'] !== 1) {
            mephysDestroyStaffSession();
            header('Location: staff-login.php');
            exit;
        }

        // Keep the current role synchronized with the database.
        $_SESSION['staff_role'] = $staff['role'];

        // Enforce the first-login password change.
        $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

        if (
            (int) $staff['must_change_password'] === 1 &&
            $currentPage !== 'staff-change-password.php'
        ) {
            header('Location: staff-change-password.php');
            exit;
        }

        $_SESSION['mephys_last_activity'] = $now;

        return $staff;

    } catch (PDOException $e) {
        error_log('Mephys staff authentication error: ' . $e->getMessage());

        http_response_code(503);
        exit('Unable to verify your access at this time. Please try again later.');
    }
}



/**
 * Create a protected hash of the visitor IP.
 * Raw IP addresses are not stored in the security logs.
 */
function mephysHashIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    return hash_hmac(
        'sha256',
        $ip,
        MEPHYS_SECURITY_KEY
    );
}


/**
 * Record administrative activity.
 */
function mephysAuditLog(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    array $details = []
): void {
    global $pdo;

    try {
        $staffId = $_SESSION['staff_user_id'] ?? null;

        $detailsJson = !empty($details)
            ? json_encode($details, JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = $pdo->prepare(
            "INSERT INTO staff_audit_logs
                (staff_user_id, action, entity_type, entity_id, details, ip_hash)
             VALUES
                (:staff_id, :action, :entity_type, :entity_id, :details, :ip_hash)"
        );

        $stmt->execute([
            ':staff_id' => $staffId,
            ':action' => substr($action, 0, 100),
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':details' => $detailsJson,
            ':ip_hash' => mephysHashIp(),
        ]);

    } catch (Throwable $e) {
        // Logging failure must not expose sensitive information.
        error_log('Mephys audit logging failed: ' . $e->getMessage());
    }
}
