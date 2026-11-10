<?php
// Set MEPHYS_STAFF_SECURITY_KEY to a new randomly generated 64+ character key.
if (!defined('MEPHYS_AUTH_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
return ['security_key' => getenv('MEPHYS_STAFF_SECURITY_KEY') ?: '0b00a4e4b36707a4209866d7b2c89ef3a4b2b87cad435b3f5cee815e58e239b9'];
