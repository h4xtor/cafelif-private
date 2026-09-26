<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_logout();
redirect('/admin/login.php');
