<?php
/**
 * Copia este archivo como config.php y completa api2pdf_key.
 * El PIN por defecto es 8210 (hash bcrypt abajo). Cámbialo regenerando el hash:
 *   php -r "echo password_hash('NUEVO_PIN', PASSWORD_DEFAULT);"
 */
return [
    'api2pdf_key' => 'TU_API2PDF_KEY_AQUI',
    'pin_hash' => '$2y$12$xFQ8OvfS.WxewE9aTbHeSePukysLPhWkMVdQ//4Yj24IYra61x6O.',
    'company' => [
        'name' => 'Servicio Técnico San Agustín',
        'tagline' => 'Climatización y refrigeración',
        'footer' => 'Técnico oficial de Aire Copiapó · Climatización y refrigeración',
        'phone' => '+56 9 9428 4048',
        'whatsapp' => '56994284048',
        'rut' => '',
        'email' => '',
        'address' => 'Copiapó, Región de Atacama',
    ],
    'quote_prefix' => 'SA',
    'next_number' => 1,
    'session_ttl' => 28800,
    'max_pin_attempts' => 5,
    'pin_lock_seconds' => 600,
];
