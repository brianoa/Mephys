
<?php
date_default_timezone_set('Africa/Nairobi');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

if (empty($_SESSION['booking_csrf_token'])) {
    $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
}

$earliestBookingDate = (new DateTimeImmutable(
    'tomorrow',
    new DateTimeZone('Africa/Nairobi')
))->format('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Mephys physiotherapy services and request an appointment in Kenya.">
  <!---Font Awesome--->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!--Bootstrap link-->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <!--AOS--->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet" />
  <!--Stylesheet link-->
	<link rel="stylesheet" type="text/css" href="services.css?v=20261110b">
    <link rel="stylesheet" href="nav.css?v=20261110b">
    <script src="nav.js?v=20261110b" defer></script>
  
  <!--Animate-->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
	<title>Physiotherapy Services | Mephys Clinic</title>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>


<!--Our Services------>

<section class="services" id="services">
<div class="container">
  <h1>Our Services</h1>
  <div class="row">
    <!-- Musculoskeletal Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="musculoskeletal" id="musculoskeletal">
        <div class="card-body">
          <h5 class="card-title">Musculoskeletal Physiotherapy</h5>
          <p class="card-text">Treatment for muscles, bones, and joints to reduce pain and improve mobility.</p>
        </div>
      </div>
    </div>
    <!-- Neurological Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="neurological" id="neurological">
        <div class="card-body">
          <h5 class="card-title">Neurological Physiotherapy</h5>
          <p class="card-text">Helping patients with nervous system conditions like stroke or Parkinson's regain control and function.</p>
        </div>
      </div>
    </div>

    <!-- Cardiorespiratory Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="cardiorespiratory" id="cardiorespiratory">
        <div class="card-body">
          <h5 class="card-title">Cardiorespiratory Physiotherapy</h5>
          <p class="card-text">Treatment for heart and lung conditions with personalized rehabilitation programs and breathing exercises.</p>
        </div>
      </div>
    </div>
   </div>


    <div class="row">
    <!-- Geriatric Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="geriatric" id="geriatric">
        <div class="card-body">
          <h5 class="card-title">Geriatric Physiotherapy</h5>
          <p class="card-text">Empowering seniors with mobility, independence, and fall prevention strategies.</p>
        </div>
      </div>
    </div>

    <!-- Sports Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="sports" id="sports">
        <div class="card-body">
          <h5 class="card-title">Sports Physiotherapy</h5>
          <p class="card-text">Injury rehab and performance care designed for athletes and active individuals.</p>
        </div>
      </div>
    </div>

    <!-- Orthopedic Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="orthopedic" id="orthopedic">
        <div class="card-body">
          <h5 class="card-title">Orthopedic Physiotherapy</h5>
          <p class="card-text">Support for bone, joint, and muscle recovery — now includes Lamaze for mothers.</p>
        </div>
      </div>
    </div>
   </div>

   <div class="row">
    <!-- Corporate Ergonomic Training Card --> 
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="ergonomictraining" id="corporate">
        <div class="card-body">
          <h5 class="card-title">Corporate Ergonomic Training</h5>
          <p class="card-text">Customized workplace ergonomics for better health and productivity.</p>
        </div>
      </div>
    </div>

    <!-- Pediatric Physiotherapy -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="pediatric" id="pediatric">
        <div class="card-body">
          <h5 class="card-title">Pediatric Physiotherapy</h5>
          <p class="card-text">Helping infants and children grow stronger, move better, and thrive with early personalized care.</p>
        </div>
      </div>
    </div>
    <!-- Lamaze for Expectant Mothers card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="lamaze" id="lamaze">
        <div class="card-body">
          <h5 class="card-title">Lamaze for Expectant Mothers</h5>
          <p class="card-text">Supporting moms-to-be with informed, confident, and natural birthing techniques.</p>
        </div>
      </div>
    </div>
   </div>

   
   <div class="row">

    <!-- Home based care card -->   
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="homebasedcare" id="homebased">
        <div class="card-body">
          <h5 class="card-title">Home-Based Physiotherapy</h5>
          <p class="card-text">Personalized healing in the comfort of your home for better recovery and convenience.</p>
        </div>
      </div>
    </div>

    <!-- Lymphatic drainage post surgery Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="lymphatic" id="lymphatic">
        <div class="card-body">
          <h5 class="card-title">Lymphatic drainage post surgery</h5>
          <p class="card-text">...</p>
        </div>
      </div>
    </div>

    <!-- Physical Therapy Consultancy card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="physical" id="physical">
        <div class="card-body">
          <h5 class="card-title">Physical Therapy Consultancy </h5>
          <p class="card-text">...</p>
        </div>
      </div>
    </div>
   </div>

   <div class="row">
    <!-- Sexual Health and wellness Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="sexualhealth" id="sexual">
        <div class="card-body">
          <h5 class="card-title">Sexual Health and wellness</h5>
          <p class="card-text">...</p>
        </div>
      </div>
    </div>

    <!-- Physical Therapy Consultancy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="supply" id="physicaltherapy">
        <div class="card-body">
          <h5 class="card-title">Physical Therapy Consultancy</h5>
          <p class="card-text">...</p>
        </div>
      </div>
    </div>  

    <!-- Pain Management Physiotherapy Card -->
    <div class="col-md-4 mb-4">
      <div class="card service-card" role="button" tabindex="0" aria-label="View service details and book an appointment" data-service="painmanagement" id="pain">
        <div class="card-body">
          <h5 class="card-title">Pain Management</h5>
          <p class="card-text">...</p>
        </div>
      </div>
    </div>     
   </div>   
  </div>  
</section>

<!-- Modal -->
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="modal-content" tabindex="-1">
    <button id="closeModal" type="button" aria-label="Close service details">&times;</button>
    <h2 id="modalTitle"></h2>
    <div id="modalDesc"></div>
    <div class="text-end mt-4">
      <!--a href="#appointment" class="btn btn-primary">Book Appointment</a-->
      <button id="openAppointmentFormBtn" class="btn btn-primary">Book Appointment</button>
    </div>
  </div>
</div>



<!-- Booking Form Section -->
<section id="appointmentFormContainer" class="container my-5" style="display: none;" data-aos="fade-up">
  <div class="card shadow-lg p-4 rounded-4 border-0 bg-light">
    <h3 class="mb-4 text-center text-primary">Book Your Appointment</h3>    
    <form id="appointmentForm" method="POST" action="book_appointment.php">
        <input type="hidden" name="service" id="selectedService" value="">

        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['booking_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-3">
        <label for="fullName" class="form-label">Full Name</label>
        <input type="text" class="form-control form-control-lg rounded-3" id="fullName" name="fullName" required>
        <div class="error-message text-danger mt-1" id="nameError"></div>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control form-control-lg rounded-3" id="email" name="email" required>
        <div class="error-message text-danger mt-1" id="emailError"></div>
      </div>

      <div class="mb-3">
        <label for="phone" class="form-label">Phone Number</label>
        <input type="tel" class="form-control form-control-lg rounded-3" id="phone" name="phone" required>
        <div class="error-message text-danger mt-1" id="phoneError"></div>
      </div>
      <div class="mb-3">
        <label for="appointmentDate" class="form-label">Select Date</label>        
        <input type="date"
              class="form-control form-control-lg rounded-3"
              id="appointmentDate"
              name="date"
              min="<?= htmlspecialchars($earliestBookingDate, ENT_QUOTES, 'UTF-8') ?>"
              required>

      </div>
      <div class="mb-3">
      <label for="appointmentTime" class="form-label">Select Time</label>
      <input type="time" class="form-control form-control-lg rounded-3"
       id="appointmentTime" name="time"
       min="09:00" max="16:00" required>
      <div class="error-message text-danger mt-1" id="timeError"></div>
      </div>

       <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" class="form-control form-control-lg rounded-3" id="location" name="location" required>
        <div class="error-message text-danger mt-1" id="locationError"></div>
      </div>

       <div class="mb-3">
        <label for="referral" class="form-label">Referral</label>
        <select class="form-select form-select-sm rounded-3" id="referral" name="referral" required>
          <option value="">-- Select Referral Type --</option>
          <option value="Self Referral">Self Referral</option>
          <option value="Hospital Referral">Hospital Referral</option>
          <option value="Doctor Referral">Doctor Referral</option>
        </select>
        <div class="error-message text-danger mt-1" id="referralError"></div>
      </div>

      <div class="mb-3">
        <label for="notes" class="form-label">Additional Notes (Optional)</label>
        <textarea class="form-control rounded-3" id="notes" name="notes" rows="3"></textarea>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg rounded-pill">Confirm Appointment</button>
      </div>
    </form>
  </div>
</section>


<!--CONTACT US--->

<section id="contact" class="contact-section py-5">
  <div class="container" data-aos="fade-up">
    <div class="text-center mb-5">
      <h2 class="section-title"><span class="highlight">Get in Touch</span></h2>
      <p class="section-subtitle">We'd love to hear from you. Let's talk!</p>
    </div>

    <div class="row">
      <div class="col-md-6 mb-4" data-aos="fade-right">
        <h4 class="mb-3">Contact Information</h4>
        <p><i class="fa-solid fa-envelope"></i><strong>  Email:</strong> info@mephysclinic.com</p>
        <p><i class="fa-solid fa-phone"></i><strong> Phone:</strong> +254 720-095-236</p>
        <p><i class="fa-solid fa-location-dot"></i><strong> Location:</strong> Soubth B, Golden Gates Drive</p>
        <p><i class="fa-solid fa-clock-rotate-left"></i>  We are open from <strong>9:00am - 6:00pm</strong>, Monday to Friday.</p>
      </div>

      <div class="col-md-6" data-aos="fade-left">
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

 <!---js--->
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("contact-form");
  if (!form) return;
  const contactAlert = window.Swal || {
    fire(options) {
      if (options && options.title === 'Sending...') return;
      window.alert([options && options.title, options && options.text].filter(Boolean).join('\n'));
    },
    close() {},
    showLoading() {}
  };

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    // Show loading dialog
    contactAlert.fire({
      title: 'Sending...',
      text: 'Please wait while we submit your message.',
      allowOutsideClick: false,
      didOpen: () => {
        contactAlert.showLoading(); // shows spinner
      }
    });

    fetch("contact.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then((response) => {
        contactAlert.close(); // close loading

        if (response.trim() === "success") {
          contactAlert.fire({
            icon: 'success',
            title: 'Message Sent!',
            text: 'Thank you for contacting us. We’ll respond shortly.',
          });
          form.reset();
        } else {
          contactAlert.fire({
            icon: 'error',
            title: 'Oops!',
            text: response,
          });
        }
      })
      .catch((err) => {
        contactAlert.close(); // close loading

        contactAlert.fire({
          icon: 'error',
          title: 'Unexpected Error',
          text: 'Please try again later.',
        });
        console.error("Fetch error:", err);
      });
  });
});
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!--Bootstrap Js---->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
<script src="script.js"></script>

<?php include 'footer.php'; ?>
</body>
</html>
