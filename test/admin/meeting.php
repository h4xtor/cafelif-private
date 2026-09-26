<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/front.php';
require_admin();

$meetingItem = front_meeting_item();
$legacyParts = front_menu_item_text_parts($meetingItem);

$defaults = [
    'meeting_enabled' => '1',
    'meeting_eyebrow' => 'Til virksomheder, foreninger & hold',
    'meeting_title' => trim((string)($meetingItem['title'] ?? 'Mødeforplejning')) ?: 'Mødeforplejning',
    'meeting_intro' => (string)$legacyParts['intro'],
    'meeting_points' => front_meeting_points_setting_value(),
    'meeting_price_text' => trim((string)($meetingItem['price_suffix'] ?? '')) ?: 'Pris efter aftale',
    'meeting_card_kicker' => 'Fleksibel løsning',
    'meeting_badge_room' => 'I vores mødelokaler',
    'meeting_badge_delivery' => 'Leveret ud af huset',
    'meeting_card_text' => 'Fortæl os antal personer, tidspunkt og ønsker. Så sammensætter Eva en løsning, der passer til mødet.',
    'meeting_offer_button' => 'Få et tilbud',
    'meeting_call_button' => 'Ring til Eva',
    'meeting_mail_subject' => 'Forespørgsel om mødeforplejning',
    'meeting_mail_intro' => 'Jeg vil gerne høre mere om mødeforplejning hos Café LIF.',
    'meeting_order_enabled' => '1',
    'meeting_order_price' => (string)($meetingItem['price'] ?? '0'),
    'meeting_order_button' => 'Tilføj til bestilling',
    'meeting_order_help' => 'Antallet af personer kan justeres i bestillingen.',
];

$values = [];
foreach ($defaults as $key => $default) {
    // front_meeting_points_setting_value() har allerede håndteret manglende
    // eller ældre standardindhold. Undgå et ekstra setting()-kald, da
    // setting-cachen ellers kan genbruge den tomme databaseværdi.
    $values[$key] = $key === 'meeting_points' ? (string)$default : (string)setting($key, $default);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $values = [
            'meeting_enabled' => isset($_POST['meeting_enabled']) ? '1' : '0',
            'meeting_eyebrow' => trim((string)($_POST['meeting_eyebrow'] ?? '')),
            'meeting_title' => trim((string)($_POST['meeting_title'] ?? '')),
            'meeting_intro' => trim((string)($_POST['meeting_intro'] ?? '')),
            'meeting_points' => trim((string)($_POST['meeting_points'] ?? '')),
            'meeting_price_text' => trim((string)($_POST['meeting_price_text'] ?? '')),
            'meeting_card_kicker' => trim((string)($_POST['meeting_card_kicker'] ?? '')),
            'meeting_badge_room' => trim((string)($_POST['meeting_badge_room'] ?? '')),
            'meeting_badge_delivery' => trim((string)($_POST['meeting_badge_delivery'] ?? '')),
            'meeting_card_text' => trim((string)($_POST['meeting_card_text'] ?? '')),
            'meeting_offer_button' => trim((string)($_POST['meeting_offer_button'] ?? '')),
            'meeting_call_button' => trim((string)($_POST['meeting_call_button'] ?? '')),
            'meeting_mail_subject' => trim((string)($_POST['meeting_mail_subject'] ?? '')),
            'meeting_mail_intro' => trim((string)($_POST['meeting_mail_intro'] ?? '')),
            'meeting_order_enabled' => isset($_POST['meeting_order_enabled']) ? '1' : '0',
            'meeting_order_price' => trim((string)($_POST['meeting_order_price'] ?? '0')),
            'meeting_order_button' => trim((string)($_POST['meeting_order_button'] ?? '')),
            'meeting_order_help' => trim((string)($_POST['meeting_order_help'] ?? '')),
        ];

        foreach (['meeting_title', 'meeting_intro', 'meeting_points', 'meeting_price_text'] as $requiredKey) {
            if ($values[$requiredKey] === '') {
                throw new RuntimeException('Titel, introduktion, muligheder og pris/tekst er nødvendige, før siden kan gemmes.');
            }
        }

        $points = front_meeting_points($values['meeting_points'], []);
        if (!$points) throw new RuntimeException('Mindst én mulighed er nødvendig, f.eks. Morgenmad.');
        $values['meeting_points'] = implode("\n", $points);

        $orderPriceNormalized = str_replace(',', '.', $values['meeting_order_price']);
        if ($orderPriceNormalized === '' || !is_numeric($orderPriceNormalized) || (float)$orderPriceNormalized < 0) {
            throw new RuntimeException('Prisen i bestillingen skal være 0 eller et positivt beløb.');
        }
        $values['meeting_order_price'] = number_format((float)$orderPriceNormalized, 2, '.', '');
        if ($values['meeting_order_button'] === '') $values['meeting_order_button'] = 'Tilføj til bestilling';

        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) set_setting($key, $value);

            // Den separate ordrepost holdes synkroniseret med fanen. Den bliver
            // fortsat filtreret væk fra den almindelige madmenu på kundesiden.
            $description = $values['meeting_intro'];
            foreach ($points as $point) $description .= "\n*" . $point;
            $meetingItemId = (int)($meetingItem['id'] ?? 0);
            if ($meetingItemId <= 0) {
                $categoryId = (int)$pdo->query("SELECT id FROM menu_categories WHERE slug='catering-fest' ORDER BY is_active DESC,sort_order,id LIMIT 1")->fetchColumn();
                if ($categoryId <= 0) $categoryId = (int)$pdo->query('SELECT id FROM menu_categories ORDER BY is_active DESC,sort_order,id LIMIT 1')->fetchColumn();
                if ($categoryId <= 0) throw new RuntimeException('Der findes ingen menukategori, som Mødeforplejning kan kobles til.');
                $nextSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM menu_items WHERE category_id=?');
                $nextSort->execute([$categoryId]);
                $insert = $pdo->prepare('INSERT INTO menu_items(category_id,title,slug,description,price,xl_price,price_suffix,image_path,badge,allergens,is_featured,is_available,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $insert->execute([$categoryId,$values['meeting_title'],'moedeforplejning',$description,(float)$values['meeting_order_price'],null,$values['meeting_price_text'],null,'', '',0,$values['meeting_order_enabled']==='1'?1:0,(int)$nextSort->fetchColumn()]);
                $meetingItemId = (int)$pdo->lastInsertId();
            } else {
                $sync = $pdo->prepare('UPDATE menu_items SET title=?, description=?, price=?, xl_price=NULL, price_suffix=?, is_available=? WHERE id=?');
                $sync->execute([
                    $values['meeting_title'],
                    $description,
                    (float)$values['meeting_order_price'],
                    $values['meeting_price_text'],
                    $values['meeting_order_enabled'] === '1' ? 1 : 0,
                    $meetingItemId,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        flash('success', 'Mødeforplejning er gemt og opdateret på hjemmesiden.');
        redirect('/admin/meeting.php');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException
            ? 'Mødeforplejning kunne ikke gemmes i databasen. Prøv igen.'
            : $e->getMessage();
    }
}

admin_layout_start('Mødeforplejning', 'meeting');
?>
<?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
<div class="page-intro">
  <p class="admin-kicker">Mødeforplejning</p>
  <h2>Mødeforplejning på hjemmesiden</h2>
  <p>Hej Eva. Her kan du tilpasse indhold, pris og bestillingsmuligheder til Mødeforplejning. Ændringerne bliver vist på hjemmesiden, når de gemmes.</p>
  <p>Mulighederne kan stå én pr. linje, f.eks. Morgenmad, Frokost og Buffet. Punkttegn bliver tilføjet automatisk på hjemmesiden.</p>
</div>
<form method="post" class="form-card"><?= csrf_field() ?>
  <div class="form-grid">
    <div class="field field--full">
      <div class="check-row">
        <label class="check"><input type="checkbox" name="meeting_enabled" <?= $values['meeting_enabled'] !== '0' ? 'checked' : '' ?>> Fanen og sektionen Mødeforplejning vises på hjemmesiden</label>
      </div>
    </div>

    <div class="field"><label>Lille tekst over overskriften</label><input name="meeting_eyebrow" value="<?= h($values['meeting_eyebrow']) ?>" placeholder="Til virksomheder, foreninger & hold"><small>Vises med små bogstaver over hovedoverskriften.</small></div>
    <div class="field"><label>Overskrift *</label><input name="meeting_title" value="<?= h($values['meeting_title']) ?>" required placeholder="Mødeforplejning"><small>Navnet på sektionen og den synkroniserede menupost.</small></div>

    <div class="field field--full"><label>Introduktion *</label><textarea name="meeting_intro" required placeholder="Kort beskrivelse af jeres mødeforplejning"><?= h($values['meeting_intro']) ?></textarea><small>Teksten står direkte under overskriften.</small></div>
    <div class="field field--full"><label>Muligheder – én pr. linje *</label><textarea name="meeting_points" required placeholder="Morgenmad&#10;Frokost&#10;Snacks&#10;Aftensmad eller buffet"><?= h($values['meeting_points']) ?></textarea><small>Hver linje bliver vist med et flueben i boksen.</small></div>

    <div class="field"><label>Pris eller prisniveau *</label><input name="meeting_price_text" value="<?= h($values['meeting_price_text']) ?>" required placeholder="Pris efter aftale"><small>Eksempel: “Fra 85 kr. pr. person” eller “Pris efter aftale”.</small></div>
    <div class="field"><label>Lille tekst over prisen</label><input name="meeting_card_kicker" value="<?= h($values['meeting_card_kicker']) ?>" placeholder="Fleksibel løsning"><small>Vises med små bogstaver lige over prisen.</small></div>
    <div class="field field--full"><label>Tekst i informationsboksen</label><input name="meeting_card_text" value="<?= h($values['meeting_card_text']) ?>" placeholder="Fortæl os antal personer..."><small>Vises under listen med muligheder.</small></div>

    <div class="field"><label>Første leveringsmulighed</label><input name="meeting_badge_room" value="<?= h($values['meeting_badge_room']) ?>" placeholder="I vores mødelokaler"><small>Feltet kan stå tomt, hvis mærkatet ikke skal vises.</small></div>
    <div class="field"><label>Anden leveringsmulighed</label><input name="meeting_badge_delivery" value="<?= h($values['meeting_badge_delivery']) ?>" placeholder="Leveret ud af huset"><small>Feltet kan stå tomt, hvis mærkatet ikke skal vises.</small></div>

    <div class="field"><label>Tekst på tilbudsknappen</label><input name="meeting_offer_button" value="<?= h($values['meeting_offer_button']) ?>" placeholder="Få et tilbud"></div>
    <div class="field"><label>Tekst på ring-knappen</label><input name="meeting_call_button" value="<?= h($values['meeting_call_button']) ?>" placeholder="Ring til Eva"></div>

    <div class="field"><label>Emne i kundens e-mail</label><input name="meeting_mail_subject" value="<?= h($values['meeting_mail_subject']) ?>" placeholder="Forespørgsel om mødeforplejning"></div>
    <div class="field"><label>Første linje i kundens e-mail</label><input name="meeting_mail_intro" value="<?= h($values['meeting_mail_intro']) ?>" placeholder="Jeg vil gerne høre mere..."></div>

    <div class="field field--full"><div class="alert alert--info"><strong>Bestilling og ordrestatus:</strong> Mødeforplejning kan kobles til den samme bestillingskurv som de øvrige retter. Kunden får et ordrenummer, og status kan efterfølgende ses via mobilnummeret på hjemmesiden. Mødeforplejning bliver fortsat ikke vist i den almindelige madmenu.</div></div>
    <div class="field field--full"><div class="check-row"><label class="check"><input type="checkbox" name="meeting_order_enabled" <?= $values['meeting_order_enabled'] !== '0' ? 'checked' : '' ?>> Mødeforplejning kan bestilles gennem kurven</label></div></div>
    <div class="field"><label>Pris i bestillingen pr. person</label><input type="number" step="0.01" min="0" name="meeting_order_price" value="<?= h($values['meeting_order_price']) ?>" placeholder="0"><small>0 kan bruges, når den endelige pris aftales efter bestillingen.</small></div>
    <div class="field"><label>Tekst på bestillingsknappen</label><input name="meeting_order_button" value="<?= h($values['meeting_order_button']) ?>" placeholder="Tilføj til bestilling"></div>
    <div class="field field--full"><label>Lille forklaring ved bestillingen</label><input name="meeting_order_help" value="<?= h($values['meeting_order_help']) ?>" placeholder="Antallet af personer kan justeres i bestillingen."></div>

    <div class="field field--full"><div class="alert alert--info"><strong>Automatisk synkronisering:</strong> Når ændringerne gemmes her, bliver titel, introduktion, muligheder, pris og bestillingsstatus også opdateret på den skjulte ordrepost “Mødeforplejning”. Oplysningerne behøver derfor kun at blive rettet ét sted.</div></div>
  </div>
  <div class="form-actions sticky-save"><a class="button button--soft" href="<?= h(base_url('/')) ?>#moedeforplejning" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Se fanen</a><button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Gem mødeforplejning</button></div>
</form>
<?php admin_layout_end(); ?>
