<?php

require_once __DIR__ . '/staff-auth.php';

global $pdo;

// Require an authenticated and active staff account.
$staff = mephysRequireStaffLogin();

// Safely display database values.
function mephysEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Validate dates received through the appointment filter form.
function mephysValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

// Only allow known dashboard sections.
$allowedPages = ['dashboard', 'appointments', 'messages', 'account'];
$page = trim((string) ($_GET['page'] ?? 'dashboard'));

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pageDetails = [
    'dashboard' => [
        'title' => 'Dashboard',
        'subtitle' => 'Clinic overview and recent activity.'
    ],
    'appointments' => [
        'title' => 'Appointments',
        'subtitle' => 'View and manage client appointment requests.'
    ],
    'messages' => [
        'title' => 'Contact Messages',
        'subtitle' => 'Review messages submitted through the clinic website.'
    ],
    'account' => [
        'title' => 'My Account',
        'subtitle' => 'View your staff account information and security options.'
    ]
];

$currentPageTitle = $pageDetails[$page]['title'];
$currentPageSubtitle = $pageDetails[$page]['subtitle'];

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title><?= mephysEscape($currentPageTitle) ?> | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --sidebar-width: 218px;
            --navy: #18344d;
            --blue: #2868d7;
            --blue-dark: #2058bd;
            --background: #f5f7fb;
            --text: #303b4b;
            --muted: #7c8797;
            --border: #e8edf3;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            font-size: 13px;
        }

        .app-shell { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            width: var(--sidebar-width);
            background: #fff;
            border-right: 1px solid #e9edf3;
            display: flex;
            flex-direction: column;
            padding: 18px 12px 14px;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 9px 25px;
            color: var(--navy);
            text-decoration: none;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #fff;
            font-size: 19px;
            font-weight: 800;
            background: linear-gradient(145deg, #3b83ee, #245bc4);
            box-shadow: 0 5px 12px rgba(40,104,215,.2);
        }

        .brand-name { font-size: 15px; font-weight: 800; letter-spacing: .2px; }
        .brand-subtitle { display: block; margin-top: 2px; color: #97a1af; font-size: 9px; letter-spacing: 1.1px; }

        .sidebar-label {
            padding: 8px 10px 9px;
            color: #8b95a3;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .quick-actions { display: grid; gap: 7px; margin-bottom: 23px; }

        .quick-action {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 37px;
            padding: 8px 10px;
            border: 1px solid #e8edf4;
            border-radius: 8px;
            color: #526174;
            background: #fff;
            text-decoration: none;
            font-size: 11px;
            font-weight: 650;
            transition: .18s ease;
        }

        .quick-action:hover { background: #f6f9ff; border-color: #d7e4fb; color: var(--blue); }
        .quick-action i { font-size: 14px; color: #6d83a2; }

        .nav-menu { display: grid; gap: 5px; }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 39px;
            padding: 9px 11px;
            border-radius: 9px;
            color: #566273;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: .18s ease;
        }

        .nav-link i { width: 16px; font-size: 15px; text-align: center; color: #8490a0; }
        .nav-link:hover { color: var(--blue); background: #f2f6ff; }
        .nav-link.active {
            color: #fff;
            background: linear-gradient(105deg, #397ce5, #245bc5);
            box-shadow: 0 5px 12px rgba(42,101,204,.2);
        }
        .nav-link.active i { color: #fff; }

        .sidebar-spacer { flex: 1; min-height: 20px; }

        .sidebar-account {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 13px 7px 3px;
            border-top: 1px solid #edf0f4;
        }

        .account-avatar {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 50%;
            color: #fff;
            background: #3976d9;
            font-weight: 800;
        }

        .account-name { color: #3e4a5b; font-size: 11px; font-weight: 750; overflow-wrap: anywhere; }
        .account-role { color: #98a1ad; font-size: 10px; text-transform: capitalize; }

        .main-area {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 27px;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid #e9edf3;
            backdrop-filter: blur(10px);
        }

        .topbar-title { color: #34445a; font-size: 13px; font-weight: 750; }
        .topbar-subtitle { margin-top: 2px; color: #9aa3af; font-size: 10px; }
        .topbar-actions { display: flex; align-items: center; gap: 9px; }

        .topbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 11px;
            border: 1px solid #e6ebf2;
            border-radius: 8px;
            background: #fff;
            color: #647184;
            text-decoration: none;
            font-size: 11px;
            font-weight: 650;
        }

        .topbar-btn:hover { background: #f7f9fc; color: var(--blue); }
        .topbar-btn.logout { color: #b94a56; }

        .page-content {
            width: min(1600px, 100%);
            margin: 0 auto;
            padding: 25px 27px 42px;
        }

        .page-heading { margin: 0; color: #34445a; font-size: 21px; font-weight: 780; letter-spacing: -.45px; }
        .page-subtitle { margin-top: 5px; color: #929cab; font-size: 11px; }

        .welcome-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 12px; margin-bottom: 19px; }

        .stat-card {
            min-height: 94px;
            padding: 15px 16px;
            border: 1px solid #e9edf3;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(34,55,85,.025);
        }

        .stat-label { color: #8994a3; font-size: 10px; font-weight: 700; }
        .stat-number { margin-top: 9px; color: #34445a; font-size: 25px; line-height: 1; font-weight: 800; }
        .stat-indicator { width: 30px; height: 3px; margin-top: 12px; border-radius: 5px; }
        .indicator-total { background: #355f9b; }
        .indicator-pending { background: #e4a52a; }
        .indicator-confirmed { background: #4387db; }
        .indicator-completed { background: #28a17c; }
        .indicator-cancelled { background: #d95d69; }

        .panel {
            overflow: hidden;
            border: 1px solid #e8edf3;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 9px rgba(34,55,85,.025);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .section-heading { margin: 0; color: #39485b; font-size: 14px; font-weight: 750; }
        .results-count { margin-top: 4px; color: #9aa3af; font-size: 10px; }

        .export-actions { display: flex; flex-wrap: wrap; gap: 7px; }

        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 9px;
            border: 1px solid #e5eaf0;
            border-radius: 7px;
            background: #fff;
            color: #667386;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
        }

        .export-btn:hover { background: #f7f9fc; color: var(--blue); }

        .filters {
            display: grid;
            grid-template-columns: minmax(190px,1.5fr) repeat(4,minmax(120px,1fr)) auto;
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid #edf0f4;
            background: #fcfdff;
        }

        .filter-label { display: block; margin-bottom: 5px; color: #7d8998; font-size: 9px; font-weight: 800; letter-spacing: .35px; text-transform: uppercase; }
        .form-control, .form-select { min-height: 35px; border-color: #e2e8f0; border-radius: 6px; color: #4b5869; font-size: 11px; }
        .form-control:focus, .form-select:focus { border-color: #8db4ef; box-shadow: 0 0 0 3px rgba(55,119,218,.09); }

        .filter-buttons { display: flex; align-items: flex-end; gap: 6px; }
        .btn { border-radius: 6px; font-size: 10px; font-weight: 700; }
        .btn-primary { background: #2d68ce; border-color: #2d68ce; }
        .btn-primary:hover { background: #2459b7; border-color: #2459b7; }

        .table-wrap { overflow-x: auto; }
        .appointments-table { width: 100%; min-width: 1050px; margin: 0; border-collapse: separate; border-spacing: 0; font-size: 11px; }
        .appointments-table thead th {
            padding: 12px 13px;
            border-bottom: 1px solid #e9edf2;
            background: #fbfcfe;
            color: #8792a0;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .45px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .appointments-table tbody td {
            padding: 13px;
            border-bottom: 1px solid #edf0f3;
            color: #566273;
            vertical-align: middle;
        }

        .appointments-table tbody tr:hover { background: #f7faff; }
        .appointments-table tbody tr:last-child td { border-bottom: 0; }
        .patient-name { color: #3d4858; font-size: 11px; font-weight: 750; }
        .patient-email { margin-top: 4px; color: #9aa3af; font-size: 9px; overflow-wrap: anywhere; }
        .appointment-date { color: #536174; font-weight: 700; white-space: nowrap; }
        .appointment-time { margin-top: 4px; color: #929cab; font-size: 10px; white-space: nowrap; }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
            text-transform: lowercase;
        }
        .status-pending { background: #fff3d7; color: #a27413; }
        .status-confirmed { background: #e5f1ff; color: #2869b1; }
        .status-completed { background: #e1f5ed; color: #21815f; }
        .status-cancelled { background: #fde9eb; color: #b64a56; }

        .row-actions { display: flex; align-items: center; gap: 7px; white-space: nowrap; }
        .icon-action {
            width: 27px;
            height: 27px;
            display: inline-grid;
            place-items: center;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #71839b;
            text-decoration: none;
            font-size: 13px;
        }
        .icon-action:hover { background: #edf4ff; color: #2869c7; }
        .icon-action.edit { color: #4e78b5; }
        .details-box { position: relative; }
        .details-box summary { list-style: none; cursor: pointer; }
        .details-box summary::-webkit-details-marker { display: none; }
        .details-content {
            position: absolute;
            z-index: 5;
            top: 31px;
            right: -8px;
            width: 230px;
            padding: 12px;
            border: 1px solid #e5eaf0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(34,55,85,.13);
            color: #667386;
            font-size: 10px;
            line-height: 1.7;
            white-space: normal;
        }

        .status-form { display: flex; align-items: center; gap: 5px; min-width: 145px; }
        .status-form .form-select { min-width: 105px; min-height: 30px; padding-top: 4px; padding-bottom: 4px; font-size: 10px; }
        .status-form .btn { min-height: 30px; padding: 5px 8px; white-space: nowrap; }
        .empty-state { padding: 48px 15px !important; text-align: center; color: #98a2af !important; }
        .table-footer { padding: 12px 19px; border-top: 1px solid #edf0f4; color: #9aa3af; font-size: 10px; }

        .alert { border-radius: 8px; font-size: 11px; }
        .mobile-menu-btn { display: none; border: 0; background: transparent; color: #536174; font-size: 20px; }

        @media (max-width: 1100px) {
            .filters { grid-template-columns: repeat(3,minmax(0,1fr)); }
            .stats-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        }

        @media (max-width: 767px) {
            :root { --sidebar-width: 0px; }
            .sidebar { width: 245px; transform: translateX(-100%); transition: transform .22s ease; box-shadow: 8px 0 25px rgba(30,50,80,.08); }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .main-area { margin-left: 0; }
            .topbar { min-height: 58px; padding: 9px 14px; }
            .mobile-menu-btn { display: inline-flex; }
            .page-content { padding: 19px 13px 30px; }
            .welcome-row { align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 8px; }
            .stat-card { min-height: 82px; padding: 12px; }
            .stat-number { font-size: 22px; }
            .panel-header { align-items: flex-start; flex-direction: column; padding: 15px; }
            .filters { grid-template-columns: repeat(2,minmax(0,1fr)); padding: 13px; }
            .filters .search-filter { grid-column: 1 / -1; }
            .filter-buttons { grid-column: 1 / -1; }
            .filter-buttons .btn { flex: 1; }
        }

        @media (max-width: 420px) {
            .stats-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .filters { grid-template-columns: 1fr; }
            .filters .search-filter, .filter-buttons { grid-column: auto; }
            .topbar-subtitle { display: none; }
            .topbar-btn span { display: none; }
        }
    </style>

</head>

<body>

<div class="app-shell">

    <!-- Sidebar navigation -->
    <aside class="sidebar" id="sidebar">
        <a href="crud.php?page=dashboard" class="brand">
            <span class="brand-mark">M</span>
            <span>
                <span class="brand-name">MEPHYS</span>
                <span class="brand-subtitle">PHYSIOTHERAPY CLINIC</span>
            </span>
        </a>

        <div class="sidebar-label">Quick Actions</div>
        <div class="quick-actions">
            <a href="crud.php?page=appointments" class="quick-action">
                <i class="bi bi-calendar-plus"></i> Appointment Records
            </a>
            <a href="crud.php?page=messages" class="quick-action">
                <i class="bi bi-chat-left-text"></i> Contact Messages
            </a>
            <a href="crud.php?page=account" class="quick-action">
                <i class="bi bi-shield-lock"></i> Change Password
            </a>
        </div>

        <nav class="nav-menu" aria-label="Main navigation">
            <div class="sidebar-label">Main Menu</div>
            <a href="crud.php?page=dashboard" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="crud.php?page=appointments" class="nav-link <?= $page === 'appointments' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Appointments
            </a>
            <a href="crud.php?page=messages" class="nav-link <?= $page === 'messages' ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i> Contact Messages
            </a>

            <div class="sidebar-label mt-3">Account</div>
            <a href="crud.php?page=account" class="nav-link <?= $page === 'account' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> My Account
            </a>
        </nav>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-account">
            <div class="account-avatar">
                <?= mephysEscape(strtoupper(substr((string) $staff['username'], 0, 1))) ?>
            </div>
            <div>
                <div class="account-name"><?= mephysEscape($staff['username']) ?></div>
                <div class="account-role"><?= mephysEscape(str_replace('_', ' ', $staff['role'])) ?></div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-area">

        <header class="topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="mobile-menu-btn" id="mobileMenuButton" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <div class="topbar-title">Mephys Clinic Administration</div>
                    <div class="topbar-subtitle"><?= mephysEscape($currentPageSubtitle) ?></div>
                </div>
            </div>

            <div class="topbar-actions">
                <a href="crud.php?page=account" class="topbar-btn">
                    <i class="bi bi-key"></i><span>Change Password</span>
                </a>
                <form method="POST" action="staff-logout.php" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= mephysEscape(mephysCsrfToken()) ?>">
                    <button type="submit" class="topbar-btn logout" onclick="return confirm('Are you sure you want to log out?');">
                        <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </header>
        <div class="page-content">

            <?php if ($page === 'appointments'): ?>

                <?php require __DIR__ . '/appointments_content.php'; ?>

            <?php elseif ($page === 'dashboard'): ?>

                <?php require __DIR__ . '/dashboard_overview.php'; ?>

            <?php elseif ($page === 'messages'): ?>

                <div id="contact-messages-content">
                    <?php require_once __DIR__ . '/contact_embed.php'; ?>
                </div>

            <?php elseif ($page === 'account'): ?>

                <div class="welcome-row">
                    <div>
                        <h1 class="page-heading">My Account</h1>
                        <div class="page-subtitle">
                            Your staff account details and security options.
                        </div>
                    </div>
                </div>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="section-heading">Account Information</h2>
                            <div class="results-count">
                                Signed-in staff account
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="mb-3">
                            <strong>Username:</strong>
                            <?= mephysEscape($staff['username']) ?>
                        </div>

                        <div class="mb-3">
                            <strong>Email:</strong>
                            <?= mephysEscape($staff['email'] ?? 'Not available') ?>
                        </div>

                        <div class="mb-4">
                            <strong>Role:</strong>
                            <?= mephysEscape(str_replace('_', ' ', $staff['role'])) ?>
                        </div>

                        <a href="staff-change-password.php" class="btn btn-primary">
                            <i class="bi bi-key me-1"></i>
                            Change Password
                        </a>
                    </div>
                </section>

            <?php endif; ?>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
    const mobileMenuButton = document.getElementById('mobileMenuButton');

    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    document.addEventListener('click', function (event) {
        const sidebar = document.getElementById('sidebar');

        if (
            window.innerWidth <= 767 &&
            document.body.classList.contains('sidebar-open') &&
            sidebar &&
            !sidebar.contains(event.target) &&
            !mobileMenuButton.contains(event.target)
        ) {
            document.body.classList.remove('sidebar-open');
        }
    });
</script>

</body>
</html>
