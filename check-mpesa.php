<?php
// CLI-only; never expose configuration or diagnostic details to website visitors.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/shop/_mpesa.php';
$config = mephysMpesaConfig();
foreach ($config as $key => $value) {
    echo $key . ': ' . ($value === '' ? 'MISSING' : 'SET') . PHP_EOL;
}
try {
    mephysMpesaAssertConfigured($config);
    echo 'M-Pesa configuration fields: READY (connection not tested).' . PHP_EOL;
} catch (Throwable $error) {
    echo 'M-Pesa configuration fields: INCOMPLETE - ' . $error->getMessage() . PHP_EOL;
}
