<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
flash('success', 'Bordbooking er ikke aktiv på hjemmesiden. Brug telefon eller e-mail.');
redirect('/#kontakt');
