<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find Mephys Clinic at The Veteran Mission Hospitals in Syokimau, along Katani Road.">
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
</head>
<body>
 <?php include __DIR__ . '/nav.php'; ?>

<!--Map Location of the website--->
<!--section id="map" class="map-section py-5">
  <div class="container" data-aos="fade-up">
    <div class="text-center mb-4">
      <h2 class="section-title">Find <span class="highlight">Us</span></h2>
      <p class="section-subtitle">Our clinic is located in the heart of Calgary</p>
    </div>
    <div class="map-container">
      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.7675865751676!2d36.83735147397421!3d-1.314973335656062!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f11a416c3cab7%3A0x2193788a9fb879ab!2sGolden%20Gate%20Dr%2C%20Nairobi!5e0!3m2!1sen!2ske!4v1751632474605!5m2!1sen!2ske" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section-->
<section id="map" class="map-section py-5">
  <div class="map-header text-center mb-4" data-aos="fade-up">
    <h2 class="section-title" style="color: #007bff; font-weight: bold;">Locate <span class="highlight">Us</span></h2>
    <!--p class="section-subtitle">Our clinic is located at South B the heart of Calgary</p-->
  </div>
  
  <div class="map-full-width">
    <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.6738186272737!2d36.93271147495362!3d-1.3723967357262574!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f0da705a1832f%3A0xf3e1d0f7f90e02a5!2sThe%20Veteran%20Mission%20Hospitals!5e0!3m2!1sen!2ske!4v1794224935688!5m2!1sen!2ske"
        width="100%"
        height="500"
        style="border:0;"
        allowfullscreen
        loading="lazy"
        referrerpolicy="strict-origin-when-cross-origin">
    </iframe>
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