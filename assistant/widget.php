<?php
// Include once from the public clinic or shop footer; never include in staff pages.
$assistantAssetPrefix = isset($assistantAssetPrefix) ? (string) $assistantAssetPrefix : '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($assistantAssetPrefix, ENT_QUOTES, 'UTF-8') ?>assistant/widget.css?v=20261111b">
<div class="mephys-assistant" data-chat-endpoint="<?= htmlspecialchars($assistantAssetPrefix, ENT_QUOTES, 'UTF-8') ?>assistant/chat.php" data-token-endpoint="<?= htmlspecialchars($assistantAssetPrefix, ENT_QUOTES, 'UTF-8') ?>assistant/token.php">
  <button type="button" class="mephys-assistant-launch" aria-expanded="false" aria-controls="mephys-assistant-panel"><i class="fa-solid fa-comments" aria-hidden="true"></i><span>Ask Mephys</span></button>
  <section id="mephys-assistant-panel" class="mephys-assistant-panel" role="dialog" aria-label="Mephys virtual assistant" hidden>
    <div class="mephys-assistant-header"><div><strong>Mephys virtual assistant</strong><small>General information · Not a medical diagnosis</small></div><button type="button" class="mephys-assistant-close" aria-label="Close assistant">&times;</button></div>
    <div class="mephys-assistant-messages" aria-live="polite" aria-relevant="additions text"><div class="mephys-assistant-bubble assistant">Hello! I can help with Mephys appointments, locations and general physiotherapy questions. For symptoms, I can share general information, but I cannot diagnose you. If symptoms are sudden or severe, seek urgent medical care.</div></div>
    <div class="mephys-assistant-examples"><button type="button" data-question="Where are the Mephys clinics?">Clinic locations</button><button type="button" data-question="Can I book home-based care?">Home visits</button><button type="button" data-question="What should I expect at my first physiotherapy session?">First visit</button></div>
    <form class="mephys-assistant-form">
      <label class="mephys-assistant-consent"><input type="checkbox" name="ai_consent"> I agree to send my message to an external AI provider when AI is configured. Do not include names, phone numbers or other identifying health details.</label>
      <div class="mephys-assistant-input-row"><input name="message" type="text" placeholder="Ask a question…" maxlength="800" autocomplete="off" aria-label="Ask the Mephys assistant" required><button type="submit" aria-label="Send question"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button></div>
      <p class="mephys-assistant-note">Without consent or AI configuration, general predefined answers are provided. For emergencies, contact local emergency services.</p>
    </form>
  </section>
</div>
<script src="<?= htmlspecialchars($assistantAssetPrefix, ENT_QUOTES, 'UTF-8') ?>assistant/widget.js?v=20261111b" defer></script>
