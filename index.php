<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- SEO: Page title and description -->
    <title>Mephys Clinic | Physiotherapy Services in Nairobi</title>
    <meta name="description" content="Mephys Clinic provides professional physiotherapy services in Nairobi, including musculoskeletal, neurological, sports and home-based physiotherapy.">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- AOS Animation CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" type="text/css" href="style.css?v=20261110intro2">
    <link rel="stylesheet" href="nav.css?v=20261110b">
    <script src="nav.js?v=20261110b" defer></script>

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<link rel="stylesheet" href="mephys-theme.css?v=20261110c">
<link rel="stylesheet" href="mephys-readability.css?v=20261111a">
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>
<section class="home mephys-hero" id="home" aria-label="Mephys physiotherapy clinic home">
  <div class="mephys-hero-banner">
    <picture class="mephys-hero-image">
      <source media="(max-width: 767.98px)" srcset="images/mephys-reception-mobile.webp?v=20261110hero" type="image/webp">
      <img src="images/mephys-reception-wide.webp?v=20261110hero"
           width="2160" height="1305"
           alt="Bright Mephys physiotherapy reception with the clinic emblem on the desk"
           decoding="async" fetchpriority="high">
    </picture>
    <!-- These words are HTML, not part of the photo: they animate once and stay sharp. -->
    <div class="mephys-hero-wall">
      <h1 class="mephys-wall-name">MEPHYS</h1>
      <p class="mephys-wall-specialty">PHYSIOTHERAPY CLINIC</p>
      <p class="mephys-wall-tagline">Healing Touch, Care That Moves</p>
    </div>
  </div>
  <div class="mephys-hero-actions" aria-label="Explore the clinic">
    <a class="mephys-hero-cta mephys-hero-cta-primary" href="#contact">Contact Us</a>
    <a class="mephys-hero-cta mephys-hero-cta-secondary" href="#projects">Our Projects</a>
  </div>
</section>

<!-- Introduction: added without changing your existing projects or contact sections -->
<section class="mephys-introduction" id="introduction" aria-labelledby="introduction-title">
  <div class="container">
    <div class="mephys-intro-layout">
      <div>
        <p class="intro-label">Welcome to Mephys</p>
        <h2 id="introduction-title">Compassionate care. <span>Better movement.</span></h2>
        <p class="intro-description">At Mephys Physiotherapy Clinic, we help people manage pain, recover from injuries and improve their mobility through attentive, goal-focused physiotherapy. We believe care should be practical, supportive and tailored to each person's needs.</p>
        <p class="intro-description">Our work extends beyond individual appointments through experience supporting healthcare facilities and workplaces with physiotherapy services, staff treatment and ergonomic guidance.</p>
        <a class="intro-link" href="services.php">Explore Our Services</a>
      </div>
      <aside class="partnership-panel" aria-labelledby="partnership-title">
        <p class="intro-label">B2B Partnerships</p>
        <h3 id="partnership-title">Stronger care through collaboration</h3>
        <p>Mephys has worked with healthcare providers and employers on physiotherapy department support, employee treatment and ergonomic training. We welcome opportunities to collaborate with clinics, hospitals, businesses and other organizations seeking physiotherapy and workplace-wellness support.</p>
        <a href="#contact">Discuss a partnership &rarr;</a>
      </aside>
    </div>
  </div>
</section>

<!-- Past Projects -->
<section class="past-projects" id="projects">
  <div class="container py-1">
    <h2 class="text-center mb-4">Our Past Projects</h2>

    <div class="row g-4">

      <!-- Project Card 1 -->
      <div class="col-md-6 col-lg-4" data-aos="fade-right">
        <div class="project-card shadow-sm">
          <img src="images/Medplus.png" alt="Medplus Healthcare Solutions Limited logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2023 - Present</h5>
          <p>Established and managing the physiotherapy department clinic at Medplus Healthcare Solutions Limited, Parklands.</p>
        </div>
      </div>

      <!-- Project Card 2 -->
      <div class="col-md-6 col-lg-4" data-aos="fade-left">
        <div class="project-card shadow-sm">
          <img src="images/panesa.png" alt="Panesser Furniture logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2020 - Present</h5>
          <p>Providing physiotherapy treatment for staff at Panesser Furniture.</p>
        </div>
      </div>

      <!-- Project Card 3 -->
      <div class="col-md-6 col-lg-4" data-aos="fade-right">
        <div class="project-card shadow-sm">
          <img src="images/EASTCELMA.jpg" alt="East Celma Medical Center logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2017 - Present</h5>
          <p>Set up and managing Physiotherapy department at East Celma Medical Center.</p>
        </div>
      </div>

      <!-- Project Card 4 -->
      <div class="col-md-6 col-lg-4" data-aos="fade-left">
        <div class="project-card shadow-sm">
          <img src="images/UAP.jpg" alt="UAP logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2017 - Present</h5>
          <p>Providing corporate ergonomic training and talks for UAP.</p>
        </div>
      </div>

      <!-- Project Card 5 -->
      <div class="col-md-6 col-lg-4">
        <div class="project-card shadow-sm" data-aos="fade-right">
          <img src="images/SINGH.jpg" alt="Singh Retread Limited logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2010 - Present</h5>
          <p>Offering ergonomic training and physiotherapy treatment for staff at Singh Retread Limited.</p>
        </div>
      </div>

      <!-- Project Card 6 -->
      <div class="col-md-6 col-lg-4">
        <div class="project-card shadow-sm" data-aos="fade-left">
          <img src="images/AMURT.jpg" alt="Amurt Hospital logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2016 - 2023</h5>
          <p>Established and managing physiotherapy department at Amurt Hospital MountainView Waiyaki Way.</p>
        </div>
      </div>

      <!-- Project Card 7 -->
      <div class="col-md-6 col-lg-4">
        <div class="project-card shadow-sm" data-aos="fade-right">
          <img src="images/VITALITY.jpg" alt="Vitality Fountain Clinic logo" class="project-logo" style="width: 120px; height: 100px;">
          <h5>2015 - 2019</h5>
          <p>Lymphatic drainage provider at Vitality Fountain Clinic, 14 Riverside.</p>
        </div>
      </div>

      <!-- Project Card 8 -->
      <div class="col-md-6 col-lg-4">
        <div class="project-card shadow-sm" data-aos="fade-left">
          <img src="images/SIRI.jpg" alt="Siri-Gurdwara Clinic logo" class="project-logo" style="width: 100px; height: 100px;">
          <h5>2011 - 2016</h5>
          <p>Set up the physiotherapy department at Siri-Gurdwara Clinic, South C.</p>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Contact Us -->
<section id="contact" class="contact-section py-5">
  <div class="container" data-aos="fade-up">

    <div class="text-center mb-5">
      <h2 class="section-title"><span class="highlight">Get in Touch</span></h2>
      <p class="section-subtitle">We'd love to hear from you. Let's talk!</p>
    </div>

    <div class="row">

      <div class="col-md-6 mb-4" data-aos="fade-right">
        <h4 class="mb-3">Contact Information</h4>

        <p><i class="fa-solid fa-envelope"></i><strong> Email:</strong> info@mephysclinic.com</p>
        <p><i class="fa-solid fa-phone"></i><strong> Phone:</strong> +254 720-095-236</p>

        <!-- Confirm that the corrected location is accurate -->
        <p class="location-info mb-2"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="location-address"><strong>Syokimau Katani:</strong> Veteran Hospital Doctors Plaza, Ground Floor.</span></p>
        <p class="location-info mb-3"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="location-address"><strong>Komarocks Sector 2:</strong> Dawasmile Medical Centre.</span></p>
        <a href="location.php#map" class="btn btn-outline-light btn-sm mb-3">View both locations and directions</a>

        <p><i class="fa-solid fa-clock-rotate-left"></i> We are open from <strong>9:00am - 6:00pm</strong>, Monday to Friday.</p>
      </div>

      <div class="col-md-6" data-aos="fade-left">

        <!-- Contact form submits to contact.php through JavaScript -->
        <form id="contact-form" method="POST">
          <div class="form-group mb-3">
            <input type="text" class="form-control" name="name" placeholder="Your Name" required>
          </div>

          <div class="form-group mb-3">
            <input type="email" class="form-control" name="email" placeholder="Your Email" required>
          </div>

          <div class="form-group mb-3">
            <textarea class="form-control" name="message" rows="5" placeholder="Your Message" required></textarea>
          </div>

          <button type="submit" class="btn btn-primary w-100">Send Message</button>

          <!-- Message output -->
          <div id="form-message" class="mt-3"></div>
        </form>

      </div>
    </div>
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
      <!-- Opens WhatsApp in a new tab -->
      <a href="https://wa.me/254725917485" target="_blank" rel="noopener noreferrer" class="btn btn-light btn-sm rounded-pill">Chat on WhatsApp</a>
    </div>

  </div>
</div>

<!-- AOS Animate on Scroll JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

<script>
  // Initialize AOS animations
  if (window.AOS) AOS.init({
    duration: 1000,
    once: true,
  });
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Contact form submission using Fetch API and SweetAlert2
document.addEventListener("DOMContentLoaded", () => {

  const form = document.getElementById("contact-form");
  const alertAvailable = !!(window.Swal && typeof Swal.fire === 'function');

  // Check that the contact form exists before attaching the event
  if (form) {

    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(form);

      // Show loading dialog
      if (alertAvailable) Swal.fire({
        title: 'Sending...',
        text: 'Please wait while we submit your message.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      fetch("contact.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => {
          // Detect unsuccessful HTTP responses
          if (!res.ok) {
            throw new Error("Server returned an error. Please try again.");
          }

          return res.text();
        })
        .then((response) => {

          if (alertAvailable) Swal.close();

          if (response.trim() === "success") {

            if (alertAvailable) Swal.fire({
              icon: 'success',
              title: 'Message Received!',
              text: 'Thank you for contacting us. We’ll respond shortly.',
            });

            if (!alertAvailable) alert("Message sent successfully.");
            form.reset();

          } else {

            if (alertAvailable) Swal.fire({
              icon: 'error',
              title: 'Oops!',
              text: response || 'Unable to send your message. Please try again.',
            });

          }
        })
        .catch((err) => {

          if (alertAvailable) Swal.close();

          if (alertAvailable) Swal.fire({
            icon: 'error',
            title: 'Unexpected Error',
            text: 'Please try again later.',
          });

          if (!alertAvailable) alert('Unable to send your message. Please try again.');
          console.error("Fetch error:", err);

        });

    });

  }
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>

<!-- Include website footer -->
<?php include 'shop/_public-footer.php'; ?>

</body>
</html>
