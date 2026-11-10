<?php
// Shared navigation for all public clinic pages; links are relative to the project root.
$currentNavPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$serviceLinks = [
    'musculoskeletal' => 'Musculoskeletal Physiotherapy',
    'neurological' => 'Neurological Physiotherapy',
    'cardiorespiratory' => 'Cardiorespiratory Physiotherapy',
    'geriatric' => 'Geriatric Physiotherapy',
    'sports' => 'Sports Physiotherapy',
    'orthopedic' => 'Orthopedic Physiotherapy',
    'corporate' => 'Corporate Ergonomic Training',
    'pediatric' => 'Pediatric Physiotherapy',
    'lamaze' => 'Lamaze for Expectant Mothers',
    'homebased' => 'Home-Based Physiotherapy',
    'lymphatic' => 'Lymphatic Drainage After Surgery',
    'physical' => 'Physical Therapy Consultancy',
    'sexual' => 'Sexual Health and Wellness',
    'physicaltherapy' => 'Physical Therapy Products and Support',
    'pain' => 'Pain Management',
];
?>
<nav class="navbar navbar-expand-lg fixed-top bg-white shadow-sm mephys-site-nav" aria-label="Main navigation" data-mobile-nav>
  <div class="container">
    <a class="navbar-brand" href="index.php#home">Mephys</a>
    <button class="navbar-toggler mephys-menu-toggle" type="button" aria-controls="mephysSiteMenu" aria-expanded="false" aria-label="Open navigation menu">
      <span class="navbar-toggler-icon" aria-hidden="true"></span>
    </button>
    <div class="navbar-collapse mephys-nav-panel" id="mephysSiteMenu">
      <div class="mephys-nav-panel-header">
        <span>Menu</span>
        <button class="mephys-menu-close" type="button" aria-label="Close navigation menu">&times;</button>
      </div>
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link<?= $currentNavPage === 'index.php' ? ' active' : '' ?>" href="index.php#home"<?= $currentNavPage === 'index.php' ? ' aria-current="page"' : '' ?>>Home</a></li>
        <li class="nav-item mephys-has-submenu">
          <button class="nav-link mephys-services-toggle<?= $currentNavPage === 'services.php' ? ' active' : '' ?>" type="button" aria-controls="mephysServicesList" aria-expanded="false">Services <span class="mephys-caret" aria-hidden="true"></span></button>
          <ul class="dropdown-menu scrollable-dropdown" id="mephysServicesList">
            <li><a class="dropdown-item fw-semibold" href="services.php">View all services</a></li>
            <?php foreach ($serviceLinks as $anchor => $label): ?>
              <li><a class="dropdown-item" href="services.php#<?= htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="shop/index.php">Products</a></li>
        <li class="nav-item"><a class="nav-link<?= $currentNavPage === 'about.php' ? ' active' : '' ?>" href="about.php#about"<?= $currentNavPage === 'about.php' ? ' aria-current="page"' : '' ?>>About Us</a></li>
        <li class="nav-item"><a class="nav-link<?= $currentNavPage === 'location.php' ? ' active' : '' ?>" href="location.php#map"<?= $currentNavPage === 'location.php' ? ' aria-current="page"' : '' ?>>Location</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="mephys-nav-backdrop" hidden aria-hidden="true"></div>
