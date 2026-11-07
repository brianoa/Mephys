
<?php

require_once __DIR__ . '/staff-auth.php';

if (!mephysIsStaffLoggedIn()) {
    header('Location: staff-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token. Please refresh and try again.');
}

// Record the logout before destroying the session.
mephysAuditLog('staff_logout', 'staff_user', $_SESSION['staff_user_id']);

mephysDestroyStaffSession();

header('Location: staff-login.php?logged_out=1');
exit;
