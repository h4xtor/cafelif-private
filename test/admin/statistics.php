<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
admin_layout_start('Statistik er skjult', 'dashboard');
?>
<div class="panel"><div class="panel__header"><h2>Statistik er skjult</h2></div><div class="panel__body"><p>Statistik er skjult for at holde adminområdet enkelt og overskueligt.</p><p><a class="button button--primary" href="<?= h(base_url('/admin/index.php')) ?>">Tilbage til start</a></p></div></div>
<?php admin_layout_end(); ?>
