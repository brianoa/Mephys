<?php
require __DIR__ . '/shop/_mpesa.php';

foreach (mephysMpesaConfig() as $key => $value) {
    echo $key . ': ' . ($value === '' ? 'MISSING' : 'SET') . PHP_EOL;
}
