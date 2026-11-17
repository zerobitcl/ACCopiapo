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
        'name' => 'Refrigeración San Agustín',
        'tagline' => 'Tu equipo en buenas manos',
        'footer' => '¡Tu refrigeración, nuestra prioridad!',
        'phone' => '+56 9 2365 0596',
        'whatsapp' => '56923650596',
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
