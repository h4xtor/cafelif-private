<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
admin_layout_start('Bordbooking er fjernet', 'dashboard');
?>
<div class="panel"><div class="panel__header"><h2>Bordbooking er fjernet</h2></div><div class="panel__body"><p>Bordbooking er fjernet fra adminområdet, så der ikke vises funktioner, som ikke bruges på hjemmesiden.</p><p><a class="button button--primary" href="<?= h(base_url('/admin/index.php')) ?>">Tilbage til start</a></p></div></div>
<?php admin_layout_end(); ?>
