<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find the two Mephys clinic locations in Syokimau Katani and Komarocks Sector 2.">
  <!---Font Awesome--->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!--Bootstrap link-->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <!--AOS--->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet" />
  <!--Stylesheet link-->
	<link rel="stylesheet" type="text/css" href="location.css?v=20261110b">
    <link rel="stylesheet" href="nav.css?v=20261110b">
    <script src="nav.js?v=20261110b" defer></script>
  
  <!--Animate-->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
	<title>Clinic Location | Mephys Clinic</title>
<link rel="stylesheet" href="mephys-theme.css?v=20261110c">
<link rel="stylesheet" href="mephys-readability.css?v=20261111a">
</head>
<body>
 <?php include __DIR__ . '/nav.php'; ?>

<?php require_once __DIR__ . '/clinic-info.php'; ?>
<section id="map" class="map-section py-5">
  <div class="container">
    <div class="text-center mb-4">
      <p class="text-uppercase fw-bold mb-2" style="color:#087b88;letter-spacing:.1em">Find us</p>
      <h1 class="fw-bold" style="color:#173c4a">Visit Mephys at either facility</h1>
      <p class="text-secondary mx-auto" style="max-width:680px">Select the location that works best for you. These map searches are based on the facility names provided; verify the exact entrance with our team when booking.</p>
    </div>
    <div class="row g-4">
      <?php foreach (mephysClinicLocations() as $key => $facility): ?>
      <div class="col-12 col-lg-6">
        <article class="mephys-location-card h-100">
          <div class="mephys-location-card-top">
            <span class="mephys-location-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
            <div><h2 class="h5 fw-bold mb-1"><?= htmlspecialchars($facility['short'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p class="mb-0"><?= htmlspecialchars($facility['address'], ENT_QUOTES, 'UTF-8') ?></p></div>
          </div>
          <iframe title="Map search for <?= htmlspecialchars($facility['short'], ENT_QUOTES, 'UTF-8') ?>" src="<?= htmlspecialchars(mephysClinicMapUrl($key, true), ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="mephys-location-map"></iframe>
          <div class="p-3"><a class="btn btn-primary w-100" href="<?= htmlspecialchars(mephysClinicMapUrl($key), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-route me-2" aria-hidden="true"></i>Open Google Maps / Directions</a></div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="small text-secondary mt-4 mb-0">We can also discuss home-based physiotherapy. <a href="services.php#appointmentFormContainer">Request an appointment</a> and specify the visit type and full address.</p>
  </div>
</section>

<!-- WhatsApp Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="whatsappToast" class="toast align-items-center text-white bg-primary border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        💬 Have questions? Chat with us on WhatsApp!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="px-3 pb-3">
      <a href="https://wa.me/254725917485" target="_blank" class="btn btn-light btn-sm rounded-pill">Chat on WhatsApp</a>
    </div>
  </div>
</div>
<!--AOS Animate on Scroll JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    if (window.AOS) AOS.init({
      duration: 1000,
      once: true,
    });
  </script>

<!--Bootstrap Js---->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>


<?php include 'footer.php'; ?>
</body>
</html>