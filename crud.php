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
$allowedPages = ['dashboard', 'appointments', 'messages', 'orders', 'payments', 'customers', 'account'];
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
    'orders' => [
        'title' => 'Online Shop Orders',
        'subtitle' => 'Track customer orders, payments and delivery fulfilment.'
    ],
    'payments' => [
        'title' => 'Payment Tracking',
        'subtitle' => 'Review M-Pesa payment attempts, receipts and failed requests.'
    ],
    'customers' => [
        'title' => 'Shop Customers',
        'subtitle' => 'View registered customers and their purchase history.'
    ],
    'account' => [
        'title' => 'My Account',
        'subtitle' => 'View your staff account information and security options.'
    ]
];

$currentPageTitle = $pageDetails[$page]['title'];
$currentPageSubtitle = $pageDetails[$page]['subtitle'];

// Handle staff order updates before any HTML is sent to the browser.
require_once __DIR__ . '/shop-management-actions.php';

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title><?= mephysEscape($currentPageTitle) ?> | Mephys</title>

    <!-- Apply saved theme early to avoid a light-mode flash. -->
    <script>
        try {
            if (localStorage.getItem('mephys-admin-theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (e) {}
    </script>

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

        /* Theme toggle button */
        .theme-toggle {
            cursor: pointer;
            font-family: inherit;
        }

        .theme-toggle i {
            font-size: 14px;
        }

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
        .commerce-stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }

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


        /* =====================================================
           DARK MODE
           These styles only apply when dark mode is selected.
        ===================================================== */

        html.dark-mode {
            color-scheme: dark;
            --background: #101b25;
            --text: #e1eaf0;
            --muted: #a7b7c2;
            --border: #304451;
        }

        html.dark-mode body {
            background: #101b25 !important;
            color: #e1eaf0 !important;
        }

        /* Main layout surfaces */
        html.dark-mode .sidebar,
        html.dark-mode .topbar,
        html.dark-mode .panel,
        html.dark-mode .stat-card,
        html.dark-mode .quick-action,
        html.dark-mode .details-content,
        html.dark-mode .card,
        html.dark-mode .modal-content,
        html.dark-mode .dropdown-menu,
        html.dark-mode .list-group-item {
            background: #192a36 !important;
            color: #e1eaf0 !important;
            border-color: #304653 !important;
        }

        html.dark-mode .topbar {
            background: rgba(25,42,54,.97) !important;
        }

        html.dark-mode .filters {
            background: #152630 !important;
            border-color: #304653 !important;
        }

        /* Headings and important text */
        html.dark-mode .brand,
        html.dark-mode .brand-name,
        html.dark-mode .account-name,
        html.dark-mode .topbar-title,
        html.dark-mode .page-heading,
        html.dark-mode .section-heading,
        html.dark-mode .stat-number,
        html.dark-mode .patient-name,
        html.dark-mode .appointment-date {
            color: #e6f0f5 !important;
        }

        /* Secondary text */
        html.dark-mode .brand-subtitle,
        html.dark-mode .sidebar-label,
        html.dark-mode .account-role,
        html.dark-mode .topbar-subtitle,
        html.dark-mode .page-subtitle,
        html.dark-mode .stat-label,
        html.dark-mode .results-count,
        html.dark-mode .filter-label,
        html.dark-mode .patient-email,
        html.dark-mode .appointment-time,
        html.dark-mode .table-footer {
            color: #a9bbc6 !important;
        }

        /* Navigation */
        html.dark-mode .nav-link {
            color: #c1d0d9 !important;
        }

        html.dark-mode .nav-link i,
        html.dark-mode .quick-action i {
            color: #9aafbd !important;
        }

        html.dark-mode .nav-link:hover,
        html.dark-mode .quick-action:hover {
            background: #243b49 !important;
            color: #75d2e5 !important;
        }

        html.dark-mode .nav-link.active {
            background: linear-gradient(105deg, #087eaa, #075985) !important;
            color: #fff !important;
        }

        html.dark-mode .nav-link.active i {
            color: #fff !important;
        }

        /* Topbar buttons */
        html.dark-mode .topbar-btn,
        html.dark-mode .export-btn {
            background: #203440 !important;
            color: #d0dee6 !important;
            border-color: #3b5361 !important;
        }

        html.dark-mode .topbar-btn:hover,
        html.dark-mode .export-btn:hover {
            background: #294452 !important;
            color: #80d7e8 !important;
        }

        html.dark-mode .topbar-btn.logout {
            color: #ff9ba4 !important;
        }

        /* Form fields */
        html.dark-mode .form-control,
        html.dark-mode .form-select,
        html.dark-mode .input-group-text,
        html.dark-mode textarea,
        html.dark-mode input,
        html.dark-mode select {
            background-color: #12232e !important;
            color: #e1eaf0 !important;
            border-color: #405765 !important;
        }

        html.dark-mode .form-control::placeholder,
        html.dark-mode textarea::placeholder {
            color: #91a5b2 !important;
        }

        html.dark-mode .form-control:focus,
        html.dark-mode .form-select:focus {
            border-color: #42a9c1 !important;
            box-shadow: 0 0 0 3px rgba(66,169,193,.15) !important;
        }

        /* Tables */
        html.dark-mode .appointments-table,
        html.dark-mode .table {
            --bs-table-color: #d1dfe6;
            --bs-table-bg: transparent;
            --bs-table-border-color: #304653;
            --bs-table-striped-bg: #1d303c;
            --bs-table-hover-bg: #243c49;
            --bs-table-hover-color: #ffffff;
            color: #d1dfe6 !important;
            border-color: #304653 !important;
        }

        html.dark-mode .appointments-table thead th,
        html.dark-mode .table thead th {
            background: #172833 !important;
            color: #b3c5cf !important;
            border-color: #304653 !important;
        }

        html.dark-mode .appointments-table tbody td,
        html.dark-mode .table tbody td,
        html.dark-mode .table tbody th {
            background-color: transparent;
            color: #d1dfe6 !important;
            border-color: #304653 !important;
        }

        html.dark-mode .appointments-table tbody tr:hover,
        html.dark-mode .table tbody tr:hover td {
            background: #243c49 !important;
        }

        /* General Bootstrap text and backgrounds */
        html.dark-mode .text-muted,
        html.dark-mode .text-secondary,
        html.dark-mode .text-body-secondary {
            color: #a9bbc6 !important;
        }

        html.dark-mode .bg-white,
        html.dark-mode .bg-light {
            background: #192a36 !important;
            color: #e1eaf0 !important;
        }

        html.dark-mode .border,
        html.dark-mode hr {
            border-color: #304653 !important;
        }

        html.dark-mode .icon-action {
            color: #a9c1ce !important;
        }

        html.dark-mode .icon-action:hover {
            background: #294452 !important;
            color: #80d7e8 !important;
        }

        html.dark-mode .empty-state {
            color: #a9bbc6 !important;
        }

        /* Keep status labels readable in both themes */
        html.dark-mode .status-pending {
            background: #493a1c !important;
            color: #ffd77b !important;
        }

        html.dark-mode .status-confirmed {
            background: #193b5a !important;
            color: #9bcaff !important;
        }

        html.dark-mode .status-completed {
            background: #164333 !important;
            color: #8be0bd !important;
        }

        html.dark-mode .status-cancelled {
            background: #4b252d !important;
            color: #ffabb3 !important;
        }

        /* Additional text and links inside admin content */
        html.dark-mode .page-content,
        html.dark-mode .page-content p,
        html.dark-mode .page-content label,
        html.dark-mode .page-content strong,
        html.dark-mode .page-content small,
        html.dark-mode .page-content td,
        html.dark-mode .page-content th,
        html.dark-mode .page-content li {
            color: #d1dfe6;
        }

        html.dark-mode .page-content a:not(.btn):not(.nav-link):not(.quick-action):not(.export-btn):not(.icon-action) {
            color: #75c9df;
        }

        html.dark-mode .page-content .btn-primary {
            color: #fff !important;
            background: #176b91 !important;
            border-color: #176b91 !important;
        }


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
            <a href="crud.php?page=orders" class="quick-action">
                <i class="bi bi-bag-check"></i> Manage Shop Orders
            </a>
            <a href="shop/admin/product-form.php" class="quick-action">
                <i class="bi bi-plus-square"></i> Add Shop Product
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

            <div class="sidebar-label mt-3">Online Shop</div>
            <a href="crud.php?page=orders" class="nav-link <?= $page === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-bag-check"></i> Orders
            </a>
            <a href="crud.php?page=payments" class="nav-link <?= $page === 'payments' ? 'active' : '' ?>">
                <i class="bi bi-credit-card"></i> Payments
            </a>
            <a href="crud.php?page=customers" class="nav-link <?= $page === 'customers' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="shop/admin/products.php" class="nav-link">
                <i class="bi bi-box-seam"></i> Products & Inventory
            </a>
            <a href="shop/admin/categories.php" class="nav-link">
                <i class="bi bi-tags"></i> Product Categories
            </a>
            <a href="shop/admin/attributes.php" class="nav-link">
                <i class="bi bi-sliders"></i> Product Specifications
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

                <!-- Light/Dark mode switch -->
                <button
                    type="button"
                    class="topbar-btn theme-toggle"
                    id="themeToggle"
                    aria-label="Switch to dark mode"
                    title="Switch theme"
                >
                    <i class="bi bi-moon-stars" id="themeToggleIcon"></i>
                    <span id="themeToggleText">Dark mode</span>
                </button>

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

            <?php elseif ($page === 'orders'): ?>

                <?php require __DIR__ . '/orders_content.php'; ?>

            <?php elseif ($page === 'payments'): ?>

                <?php require __DIR__ . '/payments_content.php'; ?>

            <?php elseif ($page === 'customers'): ?>

                <?php require __DIR__ . '/customers_content.php'; ?>

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
    // Mobile sidebar menu
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
            mobileMenuButton &&
            !mobileMenuButton.contains(event.target)
        ) {
            document.body.classList.remove('sidebar-open');
        }
    });


    // Light/Dark mode switch
    (function () {
        const storageKey = 'mephys-admin-theme';

        const themeToggle = document.getElementById('themeToggle');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const themeToggleText = document.getElementById('themeToggleText');

        function applyTheme(theme) {
            const isDark = theme === 'dark';

            document.documentElement.classList.toggle('dark-mode', isDark);

            if (themeToggle) {
                themeToggle.setAttribute(
                    'aria-label',
                    isDark ? 'Switch to light mode' : 'Switch to dark mode'
                );

                themeToggle.title = isDark
                    ? 'Switch to light mode'
                    : 'Switch to dark mode';
            }

            if (themeToggleIcon) {
                themeToggleIcon.className = isDark
                    ? 'bi bi-sun'
                    : 'bi bi-moon-stars';
            }

            if (themeToggleText) {
                themeToggleText.textContent = isDark
                    ? 'Light mode'
                    : 'Dark mode';
            }
        }

        let savedTheme = 'light';

        try {
            savedTheme = localStorage.getItem(storageKey) || 'light';
        } catch (error) {
            savedTheme = 'light';
        }

        applyTheme(savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                const currentlyDark = document.documentElement.classList.contains('dark-mode');
                const newTheme = currentlyDark ? 'light' : 'dark';

                try {
                    localStorage.setItem(storageKey, newTheme);
                } catch (error) {
                    // Theme still changes for the current page if storage is unavailable.
                }

                applyTheme(newTheme);
            });
        }
    })();
</script>

</body>
</html>
