<?php require_once __DIR__ . '/../clinic-info.php'; ?>
</main>
<footer class="mephys-footer text-white pt-5 pb-4" aria-label="Clinic footer">
  <div class="container">
    <div class="row gy-4 justify-content-between">
      <div class="col-12 col-md-5 col-lg-4">
        <h2 class="h5 text-uppercase fw-bold mb-3" style="color:#a4eff0">Mephys Clinic</h2>
        <p>Physiotherapy, rehabilitation and recovery products to support your wellbeing.</p>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <h2 class="h6 text-uppercase fw-bold mb-3">Quick Links</h2>
        <ul class="list-unstyled mephys-footer-links">
          <li><a href="../index.php">Home</a></li>
          <li><a href="../about.php#about">About Us</a></li>
          <li><a href="../services.php#services">Services</a></li>
          <li><a href="index.php">Products</a></li>
          <li><a href="../services.php#appointmentFormContainer">Book Appointment</a></li>
          <li><a href="../location.php#map">Find Us</a></li>
        </ul>
      </div>
      <div class="col-12 col-md-4 col-lg-5">
        <h2 class="h6 text-uppercase fw-bold mb-3">Contact &amp; Locations</h2>
        <p class="mb-2"><i class="fas fa-envelope me-2" aria-hidden="true"></i><a class="mephys-footer-contact" href="mailto:info@mephysclinic.com">info@mephysclinic.com</a></p>
        <p class="mb-3"><i class="fas fa-phone me-2" aria-hidden="true"></i><a class="mephys-footer-contact" href="tel:+254720095236">+254 720 095 236</a></p>
        <?php foreach (mephysClinicLocations() as $key => $facility): ?>
          <p class="d-flex align-items-start gap-2 mb-3">
            <i class="fas fa-location-dot mt-1" aria-hidden="true"></i>
            <span><strong><?= htmlspecialchars($facility['short'], ENT_QUOTES, 'UTF-8') ?></strong><br>
              <?= htmlspecialchars($facility['address'], ENT_QUOTES, 'UTF-8') ?><br>
              <a class="mephys-footer-contact small" href="<?= htmlspecialchars(mephysClinicMapUrl($key), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Directions on Google Maps</a>
            </span>
          </p>
        <?php endforeach; ?>
      </div>
    </div>
    <hr class="my-4" style="border-color:rgba(255,255,255,.25)">
    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center">
      <p class="mb-0 small">&copy; <?= (int) date('Y') ?> Mephys Clinic. All rights reserved.</p>
      <div class="mephys-footer-social" aria-label="Social media">
        <a href="https://wa.me/254725917485" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp Mephys"><i class="fab fa-whatsapp" aria-hidden="true"></i></a>
        <a href="<?= htmlspecialchars(mephysClinicSocialUrl('instagram'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="Find Mephys on Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
        <a href="<?= htmlspecialchars(mephysClinicSocialUrl('tiktok'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="Find Mephys on TikTok"><i class="fab fa-tiktok" aria-hidden="true"></i></a>
      </div>
    </div>
  </div>
</footer>
<?php $assistantAssetPrefix = '../'; require __DIR__ . '/../assistant/widget.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
