<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/front.php';
log_page_visit();
$menuGroups = front_menu_data();
$primaryMenuGroups = [];
$cateringMenuGroup = null;
foreach ($menuGroups as $group) {
    if (($group['key'] ?? '') === 'catering') {
        $cateringMenuGroup = $group;
        continue;
    }
    $primaryMenuGroups[] = $group;
}
$galleryImages = front_gallery_images();
$phone = (string)setting('phone', '+45 61 65 71 08');
$email = (string)setting('email', 'cafelif@lystrup-if.dk');
$address = (string)setting('address', 'Lystrup Centervej 102, 8520 Lystrup');
$mapsUrl = 'https://www.google.com/maps/place/Lystrup+Idr%C3%A6tscenter/@56.236661,10.2347388,19.25z/data=!4m10!1m2!2m1!1sLystrup+Centervej+1028520+Lystrup!3m6!1s0x464c3ddcf434e12f:0xac7db1093c3f2ca0!8m2!3d56.2367177!4d10.235622!15sCiFMeXN0cnVwIENlbnRlcnZlaiAxMDI4NTIwIEx5c3RydXBaIyIhbHlzdHJ1cCBjZW50ZXJ2ZWogMTAyODUyMCBseXN0cnVwkgEOc3BvcnRzX2NvbXBsZXiaASRDaGREU1VoTk1HOW5TMFZKUTBGblNVUlliV1ZFU25GQlJSQULgAQD6AQQIABAN!16s%2Fg%2F11x9q3f1y?entry=ttu&g_ep=EgoyMDI2MDcwNi4wIKXMDSoASAFQAw%3D%3D';
$openingHours = (string)setting('opening_hours', 'I forbindelse med kampe, træning og efter aftale for selskaber.');
$defaultMarquee = 'Mad er vores produkt – glæde er vores brand ❤️|Dagens ret tir–tor|Takeaway afhentes kl. 17–19|Glæder os til at se jer';
$marqueeRaw = (string)setting('marquee_text', $defaultMarquee);
$marqueeItems = array_values(array_filter(array_map('trim', preg_split('/[|\r\n]+/', $marqueeRaw) ?: [])));
if (!$marqueeItems) { $marqueeItems = array_values(array_filter(array_map('trim', explode('|', $defaultMarquee)))); }
$heroKicker = (string)setting('hero_kicker', 'Sportscafé · Lystrup Idrætscenter');
$heroTitle = (string)setting('hero_title', "Mad der\nsamler\nmennesker.");
$heroIntro = (string)setting('hero_intro', 'Mad er vores produkt – men glæde er vores brand ❤️ Hjemmelavede retter, varmt værtsskab og et sted hvor sportsfolk, familier og venner altid føler sig velkomne.');
$heroFact1Number = (string)setting('hero_fact_1_number', '3');
$heroFact1Text = (string)setting('hero_fact_1_text', 'dage med dagens ret');
$heroFact2Number = (string)setting('hero_fact_2_number', '65 kr');
$heroFact2Text = (string)setting('hero_fact_2_text', 'hjemmelavet mad');
$heroFact3Number = (string)setting('hero_fact_3_number', '17–19');
$heroFact3Text = (string)setting('hero_fact_3_text', 'takeaway efter aftale');
$heroCardText = (string)setting('hero_card_text', 'Mad er vores produkt – glæde er vores brand ❤️');
$galleryHeading = (string)setting('gallery_heading', 'Smag med øjnene');
$galleryIntro = (string)setting('gallery_intro', 'Hjemmelavet mad fra Café LIF — fotos fra vores køkken.');
$menuHeading = (string)setting('menu_heading', "Vælg. Tilføj.\nVi ringer og bekræfter.");
$menuIntroText = (string)setting('menu_intro_text', 'Tryk på plus ved de retter, du vil bestille. Udfyld navn og telefonnummer i boksen, så lander bestillingen direkte hos Café LIF.');
if (trim(str_replace("\n", ' ', $menuHeading)) === 'Se menuen. Kontakt os for bestilling.') {
    $menuHeading = "Vælg. Tilføj.\nVi ringer og bekræfter.";
}
if (str_contains($menuIntroText, 'Ring eller skriv')) {
    $menuIntroText = 'Tryk på plus ved de retter, du vil bestille. Udfyld navn og telefonnummer i boksen, så lander bestillingen direkte hos Café LIF.';
}
$contactHeading = (string)setting('contact_heading', "Glæder os til\nat se jer");
$contactIntro = (string)setting('contact_intro', 'Har du spørgsmål om dagens ret, tapas, mad ud af huset eller selskaber, så kontakt os direkte. Så får du hurtigt svar.');
$footerText = (string)setting('footer_text', 'Mad er vores produkt – men glæde er vores brand ❤️ Et hyggeligt samlingspunkt i Lystrup Idrætscenter.');
$meetingItem = front_meeting_item();
$meetingLegacyParts = front_menu_item_text_parts($meetingItem);
$meetingEnabled = (string)setting('meeting_enabled', '1') !== '0';
$meetingEyebrow = trim((string)setting('meeting_eyebrow', 'Til virksomheder, foreninger & hold'));
$meetingTitle = trim((string)setting('meeting_title', (string)($meetingItem['title'] ?? 'Mødeforplejning'))) ?: 'Mødeforplejning';
$meetingIntro = trim((string)setting('meeting_intro', (string)$meetingLegacyParts['intro'])) ?: (string)$meetingLegacyParts['intro'];
$meetingDefaultPoints = front_meeting_default_points();
$meetingPointsRaw = front_meeting_points_setting_value();
$meetingPoints = front_meeting_points($meetingPointsRaw, $meetingDefaultPoints);
$meetingPriceText = trim((string)setting('meeting_price_text', (string)($meetingItem['price_suffix'] ?? '')));
if ($meetingPriceText === '') $meetingPriceText = 'Pris efter aftale';
$meetingPriceText = str_replace(' - ', ' – ', $meetingPriceText);
$meetingCardKicker = trim((string)setting('meeting_card_kicker', 'Fleksibel løsning'));
$meetingBadgeRoom = trim((string)setting('meeting_badge_room', 'I vores mødelokaler'));
$meetingBadgeDelivery = trim((string)setting('meeting_badge_delivery', 'Leveret ud af huset'));
$meetingCardText = trim((string)setting('meeting_card_text', 'Fortæl os antal personer, tidspunkt og ønsker. Så sammensætter Eva en løsning, der passer til mødet.'));
$meetingOfferButton = trim((string)setting('meeting_offer_button', 'Få et tilbud')) ?: 'Få et tilbud';
$meetingCallButton = trim((string)setting('meeting_call_button', 'Ring til Eva')) ?: 'Ring til Eva';
$meetingMailSubject = trim((string)setting('meeting_mail_subject', 'Forespørgsel om mødeforplejning')) ?: 'Forespørgsel om mødeforplejning';
$meetingMailIntro = trim((string)setting('meeting_mail_intro', 'Jeg vil gerne høre mere om mødeforplejning hos Café LIF.'));
$meetingOrderEnabled = $meetingEnabled && (string)setting('meeting_order_enabled', '1') !== '0' && $meetingItem && !empty($meetingItem['id']);
$meetingOrderPrice = max(0.0, (float)setting('meeting_order_price', (string)($meetingItem['price'] ?? 0)));
$meetingOrderButton = trim((string)setting('meeting_order_button', 'Tilføj til bestilling')) ?: 'Tilføj til bestilling';
$meetingOrderHelp = trim((string)setting('meeting_order_help', 'Antallet af personer kan justeres i bestillingen.'));
$meetingNavDescription = implode(', ', array_slice($meetingPoints, 0, 4));
$meetingMailHref = 'mailto:' . $email . '?subject=' . rawurlencode($meetingMailSubject) . '&body=' . rawurlencode("Hej Eva,

" . $meetingMailIntro . "

Antal personer:
Ønsket dato og tidspunkt:
Ønsker til forplejning:

Venlig hilsen
");
?>
<!DOCTYPE html>
<html lang="da">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PBD598DN');</script>
<!-- End Google Tag Manager -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0c0b0a">
  <meta name="color-scheme" content="light">

  <title>Café LIF · Sportscafé & hjemmelavet mad i Lystrup</title>
  <meta name="description" content="Café LIF i Lystrup Idrætscenter — mad er vores produkt, glæde er vores brand. Dagens ret tir–tor, tapas, mad ud af huset og selskaber. Kontakt os Maibritt Jexen Larsen.">
  <meta name="keywords" content="Café LIF, Lystrup, sportscafé, dagens ret, tapas, mødeforplejning, mad ud af huset, Eva Larsen, catering">
  <link rel="canonical" href="https://cafelif.dk/">

  <meta property="og:type" content="website">
  <meta property="og:locale" content="da_DK">
  <meta property="og:title" content="Café LIF · Din lokale sportscafé i Lystrup">
  <meta property="og:description" content="Mad er vores produkt – men glæde er vores brand ❤️ Hjemmelavet mad og varmt værtsskab i Lystrup Idrætscenter.">
  <meta property="og:image" content="assets/img/Evaogkollega.jpg">
  <meta property="og:url" content="https://cafelif.dk/">

  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.svg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..800;1,300..800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=28">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FoodEstablishment",
    "name": "Café LIF",
    "image": "https://cafelif.dk/assets/img/Evaogkollega.jpg",
    "url": "https://cafelif.dk/",
    "telephone": "+4561657108",
    "email": "cafelif@lystrup-if.dk",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Lystrup Centervej 102",
      "postalCode": "8520",
      "addressLocality": "Lystrup",
      "addressCountry": "DK"
    },
    "servesCuisine": ["Dansk", "Tapas", "Café"],
    "priceRange": "$$"
  }
  </script>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PBD598DN"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

  <a class="skip-link" href="#main">Spring til indhold</a>

  <!-- Marquee -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee__track">
      <?php for ($loop = 0; $loop < 2; $loop++): ?>
      <?php foreach ($marqueeItems as $i => $text): ?>
      <span><?= h($text) ?></span>
      <span class="marquee__dot">◆</span>
      <?php endforeach; ?>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Header -->
  <header class="header" id="header">
    <div class="header__inner">
      <a href="#" class="logo logo--brand" data-scroll-top aria-label="Café LIF forside">
        <img src="assets/img/cafeliflogo.jpg" alt="" class="logo__img" width="68" height="68">
        <span class="logo__text">
          <span class="logo__small">Café</span>
          <span class="logo__big">LIF</span>
        </span>
      </a>

      <nav class="nav-desktop" aria-label="Hovednavigation">
        <div class="nav-dropdown" data-nav-dropdown>
          <button class="nav-dropdown__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            Caféen <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown__panel" role="menu">
            <a href="#oplevelse" class="nav-dropdown__link" role="menuitem" data-nav-section="oplevelse">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-store"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Oplevelsen</span>
                <span class="nav-dropdown__link-desc">Filosofi, stemning &amp; værdier</span>
              </span>
            </a>
            <a href="#eva" class="nav-dropdown__link" role="menuitem" data-nav-section="eva">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Mød Eva</span>
                <span class="nav-dropdown__link-desc">Din vært i Lystrup Idrætscenter</span>
              </span>
            </a>
            <a href="#sport" class="nav-dropdown__link" role="menuitem" data-nav-section="sport">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-futbol"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Sportscafé</span>
                <span class="nav-dropdown__link-desc">Før, under &amp; efter kamp</span>
              </span>
            </a>
            <a href="#anmeldelser" class="nav-dropdown__link" role="menuitem" data-nav-section="anmeldelser">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-star"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Anmeldelser</span>
                <span class="nav-dropdown__link-desc">Ægte anbefalinger fra Facebook</span>
              </span>
            </a>
          </div>
        </div>
        <div class="nav-dropdown" data-nav-dropdown>
          <button class="nav-dropdown__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            Menu <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown__panel" role="menu">
            <a href="#menu" class="nav-dropdown__link" role="menuitem" data-nav-section="menu">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-utensils"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Menu &amp; priser</span>
                <span class="nav-dropdown__link-desc">Se retter og priser</span>
              </span>
            </a>
            <?php foreach ($primaryMenuGroups as $group): $navCat = $group['category']; $navKey = $group['key']; ?>
            <a href="#menu" class="nav-dropdown__link" role="menuitem" data-nav-section="menu" data-menu-filter="<?= h($navKey) ?>">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid <?= h(front_category_icon($navKey)) ?>"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title"><?= h($navCat['name'] ?? 'Menu') ?></span>
                <span class="nav-dropdown__link-desc"><?= h(front_group_nav_description($group)) ?></span>
              </span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="nav-dropdown" data-nav-dropdown>
          <button class="nav-dropdown__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            Galleri <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown__panel" role="menu">
            <a href="#galleri" class="nav-dropdown__link" role="menuitem" data-nav-section="galleri">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Madgalleri</span>
                <span class="nav-dropdown__link-desc">Se vores hjemmelavede retter</span>
              </span>
            </a>
          </div>
        </div>
        <div class="nav-dropdown" data-nav-dropdown>
          <button class="nav-dropdown__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            Fest <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown__panel" role="menu">
            <?php if ($meetingEnabled): ?>
            <a href="#moedeforplejning" class="nav-dropdown__link" role="menuitem" data-nav-section="moedeforplejning">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title"><?= h($meetingTitle) ?></span>
                <span class="nav-dropdown__link-desc"><?= h($meetingNavDescription) ?></span>
              </span>
            </a>
            <?php endif; ?>
            <a href="#selskaber" class="nav-dropdown__link" role="menuitem" data-nav-section="selskaber">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-champagne-glasses"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Selskaber</span>
                <span class="nav-dropdown__link-desc">Konfirmation, fødselsdag &amp; firma</span>
              </span>
            </a>
            <?php if ($cateringMenuGroup): $cateringCat = $cateringMenuGroup['category']; ?>
            <a href="#menu" class="nav-dropdown__link" role="menuitem" data-nav-section="menu" data-menu-filter="catering">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid <?= h(front_category_icon('catering')) ?>"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title"><?= h($cateringCat['name'] ?? 'Catering') ?></span>
                <span class="nav-dropdown__link-desc"><?= h(front_group_nav_description($cateringMenuGroup)) ?></span>
              </span>
            </a>
            <?php endif; ?>
          </div>
        </div>
        <div class="nav-dropdown" data-nav-dropdown>
          <button class="nav-dropdown__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            Kontakt <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown__panel" role="menu">
            <a href="#kontakt" class="nav-dropdown__link" role="menuitem" data-nav-section="kontakt">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Kontakt os</span>
                <span class="nav-dropdown__link-desc">Ring, mail eller besøg os</span>
              </span>
            </a>
            <a href="#faq" class="nav-dropdown__link" role="menuitem" data-nav-section="faq">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">Spørgsmål</span>
                <span class="nav-dropdown__link-desc">Mad, kontakt &amp; åbning</span>
              </span>
            </a>
            <a href="<?= h(front_tel_href($phone)) ?>" class="nav-dropdown__link nav-dropdown__link--cta" role="menuitem">
              <span class="nav-dropdown__link-icon" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>
              <span class="nav-dropdown__link-body">
                <span class="nav-dropdown__link-title">+45 61 65 71 08</span>
                <span class="nav-dropdown__link-desc">Ring direkte til Eva</span>
              </span>
            </a>
          </div>
        </div>
      </nav>

      <div class="header__start">
        <a href="<?= h(front_tel_href($phone)) ?>" class="btn btn--clay btn--sm header__cta">
          <i class="fa-solid fa-phone" aria-hidden="true"></i>
          <span>Ring til os</span>
        </a>
        <button class="nav-toggle" id="nav-toggle" aria-label="Åbn menu" aria-expanded="false" aria-controls="mobile-nav">
          <span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Nav -->
  <div class="mobile-nav" id="mobile-nav" aria-hidden="true">
    <div class="mobile-nav__backdrop" data-nav-close></div>
    <nav class="mobile-nav__panel" aria-label="Mobilnavigation">
      <div class="mobile-nav__head">
        <span class="logo logo--compact">
          <img src="assets/img/cafeliflogo.jpg" alt="" class="logo__img" width="36" height="36">
          <span class="logo__text"><span class="logo__small">Café</span><span class="logo__big">LIF</span></span>
        </span>
        <button class="mobile-nav__close" data-nav-close aria-label="Luk menu">&times;</button>
      </div>
      <div class="mobile-nav__group" data-mobile-accordion>
        <button class="mobile-nav__group-trigger" type="button" aria-expanded="false">Caféen</button>
        <div class="mobile-nav__group-panel">
          <a href="#oplevelse" data-nav-close data-nav-section="oplevelse">Oplevelsen</a>
          <a href="#eva" data-nav-close data-nav-section="eva">Mød Eva</a>
          <a href="#sport" data-nav-close data-nav-section="sport">Sportscafé</a>
          <a href="#anmeldelser" data-nav-close data-nav-section="anmeldelser">Anmeldelser</a>
        </div>
      </div>
      <div class="mobile-nav__group" data-mobile-accordion>
        <button class="mobile-nav__group-trigger" type="button" aria-expanded="false">Menu</button>
        <div class="mobile-nav__group-panel">
          <a href="#menu" data-nav-close data-nav-section="menu">Menu &amp; priser</a>
          <?php foreach ($primaryMenuGroups as $group): $mobileCat = $group['category']; ?>
          <a href="#menu" data-nav-close data-nav-section="menu" data-menu-filter="<?= h($group['key']) ?>"><?= h($mobileCat['name'] ?? 'Menu') ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mobile-nav__group" data-mobile-accordion>
        <button class="mobile-nav__group-trigger" type="button" aria-expanded="false">Galleri</button>
        <div class="mobile-nav__group-panel">
          <a href="#galleri" data-nav-close data-nav-section="galleri">Madgalleri</a>
        </div>
      </div>
      <div class="mobile-nav__group" data-mobile-accordion>
        <button class="mobile-nav__group-trigger" type="button" aria-expanded="false">Fest &amp; kontakt</button>
        <div class="mobile-nav__group-panel">
          <?php if ($meetingEnabled): ?><a href="#moedeforplejning" data-nav-close data-nav-section="moedeforplejning"><?= h($meetingTitle) ?></a><?php endif; ?>
          <a href="#selskaber" data-nav-close data-nav-section="selskaber">Selskaber</a>
          <?php if ($cateringMenuGroup): $mobileCateringCat = $cateringMenuGroup['category']; ?><a href="#menu" data-nav-close data-nav-section="menu" data-menu-filter="catering"><?= h($mobileCateringCat['name'] ?? 'Catering') ?></a><?php endif; ?>
          <a href="#kontakt" data-nav-close data-nav-section="kontakt">Kontakt</a>
          <a href="#faq" data-nav-close data-nav-section="faq">Spørgsmål</a>
        </div>
      </div>
      <div class="mobile-nav__ctas">
        <button class="btn btn--clay btn--full" type="button" data-order-status-open data-nav-close><i class="fa-solid fa-receipt" aria-hidden="true"></i> Tjek din ordre</button>
        <a href="<?= h(front_tel_href($phone)) ?>" class="btn btn--outline btn--full">Ring til os</a>
        <a href="mailto:<?= h($email) ?>" class="btn btn--outline btn--full">Send mail</a>
      </div>
    </nav>
  </div>

  <!-- Ordrestatus -->
  <div class="order-status-modal" id="order-status-modal" aria-hidden="true">
    <button class="order-status-modal__backdrop" type="button" data-order-status-close aria-label="Luk ordrestatus"></button>
    <section class="order-status-box" role="dialog" aria-modal="true" aria-labelledby="order-status-title">
      <button class="order-status-box__close" type="button" data-order-status-close aria-label="Luk"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      <div class="order-status-box__icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
      <p class="eyebrow">Ordrestatus</p>
      <h2 id="order-status-title">Tjek din bestilling</h2>
      <p>Indtast det mobilnummer du brugte ved bestillingen. Du kan se dine seneste bestillinger og følge status på hver ordre her.</p>
      <form class="order-status-form" action="actions/order-status.php" method="post" data-order-status-form>
        <label>
          <span>Mobilnummer</span>
          <input type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="fx 12 34 56 78" required>
        </label>
        <button class="btn btn--clay btn--full" type="submit">Se ordrestatus</button>
      </form>
      <div class="order-status-result" data-order-status-result aria-live="polite"></div>
    </section>
  </div>

  <main id="main" data-order-app>
    <!-- HERO -->
    <section class="hero hero--split" id="top">
      <div class="container hero__split">
        <div class="hero__panel" data-reveal>
          <p class="hero__badge"><span class="status-dot" aria-hidden="true"></span><?= h($heroKicker) ?></p>
          <h1 class="hero__title"><?= nl2br(h($heroTitle)) ?></h1>
          <p class="hero__intro"><?= h($heroIntro) ?></p>
          <div class="hero__actions">
            <a href="#menu" class="btn btn--clay btn--lg">Se menuen</a>
            <a href="#galleri" class="btn btn--outline btn--lg"><i class="fa-solid fa-images" aria-hidden="true"></i> Se madgalleri</a>
            <button class="btn btn--status btn--lg" type="button" data-order-status-open><i class="fa-solid fa-receipt" aria-hidden="true"></i> Tjek ordre</button>
          </div>
          <div class="hero__facts">
            <div class="hero__fact">
              <strong><?= h($heroFact1Number) ?></strong>
              <span><?= h($heroFact1Text) ?></span>
            </div>
            <div class="hero__fact">
              <strong><?= h($heroFact2Number) ?></strong>
              <span><?= h($heroFact2Text) ?></span>
            </div>
            <div class="hero__fact hero__fact--highlight">
              <strong><?= h($heroFact3Number) ?></strong>
              <span><?= h($heroFact3Text) ?></span>
            </div>
          </div>
        </div>
        <div class="hero__photo" data-reveal data-reveal-delay="120">
          <div class="hero__photo-frame">
            <img src="assets/img/Evaogkollega.jpg" alt="Eva og kollega i Café LIF" width="720" height="900" fetchpriority="high">
          </div>
          <div class="hero__photo-card">
            <i class="fa-solid fa-heart" aria-hidden="true"></i>
            <p><?= h($heroCardText) ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- Madgalleri -->
    <section class="section section--gallery" id="galleri">
      <div class="container">
        <header class="gallery-head" id="galleri-head" data-reveal>
          <span class="eyebrow">Madgalleri</span>
          <h2 class="section-title"><?= h($galleryHeading) ?></h2>
          <p class="section-desc"><?= h($galleryIntro) ?></p>
        </header>
        <div class="food-gallery" data-food-slider data-reveal>
          <div class="food-gallery__card">
            <button class="food-gallery__nav food-gallery__nav--prev" type="button" data-slider-prev aria-label="Forrige billede">
              <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <div class="food-gallery__viewport">
              <div class="food-gallery__track">
                <?php foreach ($galleryImages as $i => $img): ?>
                <figure class="food-gallery__slide <?= $i === 0 ? 'is-active' : '' ?>" data-slide>
                  <div class="food-gallery__canvas"><img src="<?= h(base_url($img['src'])) ?>" alt="<?= h($img['alt']) ?>" loading="lazy" width="900" height="600"></div>
                </figure>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="food-gallery__nav food-gallery__nav--next" type="button" data-slider-next aria-label="Næste billede">
              <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
          </div>
          <div class="food-gallery__bar">
            <div class="food-gallery__dots" data-slider-dots></div>
            <div class="food-gallery__meta">
              <span class="food-gallery__counter" data-slider-counter>1 / <?= max(1, count($galleryImages)) ?></span>
              <div class="food-gallery__progress" aria-hidden="true"><span data-slider-progress></span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Oplevelse -->
    <section class="section section--experience" id="oplevelse">
      <div class="container">
        <header class="experience-head" id="oplevelse-head" data-reveal>
          <span class="eyebrow">Café LIFs ønske</span>
          <h2 class="section-title">Et hyggeligt sted<br>for <em>alle</em></h2>
          <p class="section-desc">Det Eva og teamet ønsker at skabe i Lystrup Idrætscenter — hver dag, for hver gæst.</p>
        </header>

        <div class="experience-showcase" data-reveal>
          <div class="experience-quote-card">
            <div class="experience-quote-card__mark" aria-hidden="true"><i class="fa-solid fa-quote-left"></i></div>
            <blockquote>
              “Hjemmelavet og velsmagende mad, lavet med kærlighed. Altid god service, og altid imødekommende personale.”
            </blockquote>
          </div>

          <div class="experience-highlights">
            <div class="experience-highlight">
              <i class="fa-solid fa-utensils" aria-hidden="true"></i>
              <span class="experience-highlight__label">Hjemmelavet med kærlighed</span>
            </div>
            <div class="experience-highlight">
              <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
              <span class="experience-highlight__label">God service &amp; imødekommenhed</span>
            </div>
            <div class="experience-highlight experience-highlight--accent">
              <i class="fa-solid fa-heart" aria-hidden="true"></i>
              <span class="experience-highlight__label">Alle er velkomne</span>
            </div>
          </div>
        </div>

        <div class="experience-pillars experience-pillars--wishes">
          <article class="pillar-card pillar-card--featured" data-reveal>
            <div class="pillar-card__icon"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">01</span>
              <h3>Hjemmelavet mad med kærlighed</h3>
              <p>Hjemmelavet og velsmagende mad, lavet med kærlighed — det er fundamentet for alt, vi gør i caféen.</p>
            </div>
          </article>
          <article class="pillar-card" data-reveal data-reveal-delay="60">
            <div class="pillar-card__icon"><i class="fa-solid fa-face-smile" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">02</span>
              <h3>God service, hver gang</h3>
              <p>Altid god service, og altid imødekommende personale — så du føler dig set og velkommen.</p>
            </div>
          </article>
          <article class="pillar-card" data-reveal data-reveal-delay="120">
            <div class="pillar-card__icon"><i class="fa-solid fa-people-group" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">03</span>
              <h3>Et fælles samlingspunkt</h3>
              <p>Vi skaber et fælles samlingspunkt, hvor alle kan føle sig velkommen. Caféen er åben for alle gæster, som ønsker at besøge os.</p>
            </div>
          </article>
          <article class="pillar-card" data-reveal data-reveal-delay="180">
            <div class="pillar-card__icon"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">04</span>
              <h3>Hyggeligt samvær</h3>
              <p>Hyggeligt samvær, et rart sted at være, hvor man får en god oplevelse hver gang man besøger caféen.</p>
            </div>
          </article>
          <article class="pillar-card" data-reveal data-reveal-delay="240">
            <div class="pillar-card__icon"><i class="fa-solid fa-handshake" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">05</span>
              <h3>Nærvær &amp; fællesskab</h3>
              <p>Det handler ikke kun om mad, men også om at skabe en god og hyggelig stemning, nærvær og fællesskab.</p>
            </div>
          </article>
          <article class="pillar-card" data-reveal data-reveal-delay="300">
            <div class="pillar-card__icon"><i class="fa-solid fa-star" aria-hidden="true"></i></div>
            <div>
              <span class="pillar-card__num">06</span>
              <h3>Oplevelser der huskes</h3>
              <p>Vi vil gerne give alle vores gæster en oplevelse de husker — uanset om det er gennem et måltid mad, et smil eller en god serviceoplevelse.</p>
            </div>
          </article>
        </div>
        
      </div>
    </section>

    <!-- Eva -->
    <section class="section section--eva" id="eva">
      <div class="container">
        <div class="eva-layout">
          <div class="eva-layout__photo" data-reveal>
            <img src="assets/img/eva.jpg" alt="Eva Maibritt Jexen Larsen — forpagter og vært i Café LIF" loading="lazy" width="520" height="640">
            <div class="eva-layout__badge">
              <span>Din vært</span>
              <strong>Eva Larsen</strong>
            </div>
          </div>
          <div class="eva-layout__text" data-reveal data-reveal-delay="100">
            <span class="eyebrow eyebrow--light" id="eva-head">Mød forpagteren</span>
            <h2 class="section-title section-title--light">Bag LIF står<br><em>Eva</em></h2>
            <p class="lead lead--light">Jeg elsker at skabe et sted, hvor folk har lyst til at komme igen og igen. Glæder os til at se jer — hvor maden er god, stemningen er varm, og man altid føler sig set.</p>
            <p class="body--light">For mig handler det ikke kun om at servere et måltid. Det handler om at give en oplevelse — gennem maden, et smil eller god service. Vi er her for at gøre hverdagen lidt bedre og festerne lidt nemmere.</p>
            <ul class="check-list check-list--light">
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Personlig dialog hver gang</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Fleksible løsninger til enhver anledning</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Altid åben for en snak</li>
            </ul>
            <div class="btn-row">
              <a href="<?= h(front_tel_href($phone)) ?>" class="btn btn--clay">Ring til os</a>
              <a href="mailto:<?= h($email) ?>" class="btn btn--ghost-light">Skriv til os</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Menu -->
    <section class="section section--menu" id="menu">
      <div class="container">
        <header class="section-head section-head--menu" id="menu-head" data-reveal>
          <span class="eyebrow">Menu &amp; priser</span>
          <h2 class="section-title"><?= nl2br(h($menuHeading)) ?></h2>
          <p class="section-desc"><?= h($menuIntroText) ?></p>
        </header>

        <div class="menu-intro" data-reveal>
          <div class="menu-intro__chip"><i class="fa-solid fa-clock" aria-hidden="true"></i> Dagens ret tir–tor</div>
          <div class="menu-intro__chip"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Tryk + for at bestille</div>
          <div class="menu-intro__chip"><i class="fa-solid fa-phone" aria-hidden="true"></i> Vi ringer og bekræfter</div>
        </div>

        <div class="menu-layout" data-order-menu>
          <div class="menu-layout__main">
            <div class="filter-bar filter-bar--pro" role="tablist" aria-label="Menukategorier">
              <button class="filter-btn is-active" data-menu-category="all" role="tab" aria-selected="true"><i class="fa-solid fa-border-all" aria-hidden="true"></i> Alle</button>
              <?php foreach ($menuGroups as $group): $filterCat = $group['category']; $filterKey = $group['key']; ?>
              <button class="filter-btn" data-menu-category="<?= h($filterKey) ?>" role="tab" aria-selected="false"><i class="fa-solid <?= h(front_category_icon($filterKey)) ?>" aria-hidden="true"></i> <?= h($filterCat['name'] ?? 'Menu') ?></button>
              <?php endforeach; ?>
            </div>

            <div class="menu-pro">
              <?php foreach ($menuGroups as $group): $cat = $group['category']; $key = $group['key']; ?>
              <section class="menu-pro__section <?= $key === 'tapas' ? 'menu-pro__section--featured' : '' ?>" data-menu-section data-category="<?= h($key) ?>">
                <header class="menu-pro__section-head">
                  <span class="menu-pro__section-icon" aria-hidden="true"><i class="fa-solid <?= h(front_category_icon($key)) ?>"></i></span>
                  <div>
                    <h3><?= h($cat['name'] ?? 'Menu') ?></h3>
                    <p><?= h(front_group_nav_description($group)) ?></p>
                  </div>
                </header>
                <div class="menu-pro__list">
                  <?php foreach ($group['items'] as $item): $priceNum = (float)($item['price'] ?? 0); $xlPriceNum = (float)($item['xl_price'] ?? 0); $hasXl = $xlPriceNum > 0; ?>
                  <article class="menu-pro__row <?= $item['is_featured'] ? 'menu-pro__row--highlight' : '' ?> <?= !empty($item['image_path']) ? 'menu-pro__row--with-image' : '' ?>" data-menu-item data-order-item data-id="<?= (int)$item['id'] ?>" data-category="<?= h($key) ?>" data-name="<?= h($item['title']) ?>" data-price="<?= h((string)$priceNum) ?>" data-xl-price="<?= h((string)$xlPriceNum) ?>">
                    <?php if (!empty($item['image_path'])): ?>
                    <div class="menu-pro__image">
                      <img src="<?= h(base_url($item['image_path'])) ?>" alt="<?= h($item['title']) ?>" loading="lazy" width="160" height="160">
                    </div>
                    <?php endif; ?>
                    <div class="menu-pro__main">
                      <div class="menu-pro__title-row">
                        <h4><?= h($item['title']) ?></h4>
                        <span class="menu-pro__dots" aria-hidden="true"></span>
                        <span class="menu-pro__price menu-card__price"><?= front_money_label($item) ?></span>
                      </div>
                      <?php if (!empty($item['description'])): ?><p class="menu-pro__desc"><?= h($item['description']) ?></p><?php endif; ?>
                      <?php if (!empty($item['badge']) || !empty($item['allergens'])): ?>
                      <div class="menu-pro__meta">
                        <?php if (!empty($item['badge'])): ?><span class="menu-pro__tag <?= $item['is_featured'] ? 'menu-pro__tag--accent' : '' ?>"><?= h($item['badge']) ?></span><?php endif; ?>
                        <?php if (!empty($item['allergens'])): ?><span class="menu-pro__tag"><?= h($item['allergens']) ?></span><?php endif; ?>
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="menu-pro__action">
                      <?php if ($hasXl): ?>
                      <div class="menu-pro__size-picker" aria-label="Vælg størrelse på <?= h($item['title']) ?>">
                        <span class="menu-pro__size-label">Vælg størrelse</span>
                        <div class="menu-pro__size-buttons">
                          <button class="menu-pro__add menu-pro__add--size" data-cart-add data-size="normal" aria-label="Tilføj almindelig størrelse af <?= h($item['title']) ?>">
                            <strong>Alm.</strong><small><?= h(money($priceNum)) ?></small>
                          </button>
                          <button class="menu-pro__add menu-pro__add--size menu-pro__add--xl" data-cart-add data-size="xl" aria-label="Tilføj XL-størrelse af <?= h($item['title']) ?>">
                            <strong>XL</strong><small><?= h(money($xlPriceNum)) ?></small>
                          </button>
                        </div>
                      </div>
                      <?php else: ?>
                      <button class="menu-pro__add" data-cart-add data-size="normal" aria-label="Tilføj <?= h($item['title']) ?>">Tilføj <i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                      <?php endif; ?>
                    </div>
                  </article>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endforeach; ?>
            </div>
            <p class="menu-note">* Vælg retterne med plus-knappen. Når du sender bestillingen, gemmes den direkte i Café LIFs adminområde. Vi kontakter dig og bekræfter aftalen.</p>
          </div>

          <!-- Bestilling -->
          <aside class="cart-panel" id="cart-panel" aria-label="Din bestilling">
            <div class="cart-panel__inner">
              <div class="cart-panel__head">
                <div>
                  <h3><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Din bestilling</h3>
                </div>
                <div class="cart-panel__head-actions">
                  <span class="cart-panel__count" data-cart-count>0 valg</span>
                  <button class="cart-panel__close" data-cart-close type="button" aria-label="Luk bestilling"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
              </div>
              <ol class="cart-panel__steps" aria-label="Sådan bestiller du">
                <li><span>1</span>Vælg mad</li>
                <li><span>2</span>Dine oplysninger</li>
                <li><span>3</span>Send bestilling</li>
              </ol>
              <h4 class="cart-panel__section-title">Din kurv</h4>
              <div class="cart-panel__lines" data-cart-lines></div>
              <p class="cart-panel__empty" data-cart-empty><i class="fa-solid fa-plus" aria-hidden="true"></i> Vælg retter fra menuen, så vises de her.</p>
              <div class="cart-panel__total">
                <span>Estimeret samlet pris</span>
                <strong data-cart-total>0 kr.</strong>
              </div>

              <form class="cart-order-form" id="cart-order-form" method="post" action="<?= h(base_url('/actions/order.php')) ?>" data-cart-order-form novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="cart" id="cart-json" value="">
                <div class="cart-order-form__intro"><strong>Dine oplysninger</strong><span>Vi ringer og bekræfter din bestilling.</span></div>
                <label><span>Dit navn *</span><input type="text" name="name" autocomplete="name" required placeholder="Skriv dit navn"></label>
                <label><span>Telefon *</span><input type="tel" name="phone" autocomplete="tel" required placeholder="Skriv telefonnummer"></label>
                <label><span>E-mail</span><input type="email" name="email" autocomplete="email" placeholder="din@email.dk" data-confirmation-email></label>
                <label class="cart-order-form__mail-choice"><input type="checkbox" name="confirmation_email_requested" value="1" checked data-confirmation-choice><span>Send ordrebekræftelse til min e-mail<small>Vi sender kun en bekræftelse på denne ordre.</small></span></label>
                <div class="cart-order-form__grid">
                  <label><span>Ønsket dato</span><input type="date" name="date"></label>
                  <label><span>Ønsket tid</span><input type="time" name="time"></label>
                </div>
                <label><span>Besked til Café LIF</span><textarea name="message" id="cart-note" data-cart-note rows="3" placeholder="Allergier, ønsket afhentning eller særlige ønsker..."></textarea></label>
                <div class="cart-panel__actions">
                  <button class="btn btn--outline btn--sm" data-cart-clear type="button">Ryd</button>
                  <button class="btn btn--clay btn--full" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send bestilling <span class="cart-panel__button-total" data-cart-button-total>0 kr.</span></button>
                </div>
                <p class="cart-order-form__note" data-cart-status>Bestillingen sendes direkte til Café LIFs adminside. Vi ringer og bekræfter aftalen.</p>
              </form>
            </div>
          </aside>
          <button class="cart-backdrop" data-cart-close type="button" aria-label="Luk bestilling"></button>
        </div>
      </div>

      <!-- Mobile cart bar -->
      <button class="cart-fab" id="cart-fab" type="button" aria-label="Åbn bestilling" aria-controls="cart-panel">
        <span class="cart-fab__icon"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><span class="cart-fab__badge" data-cart-fab-count hidden>0</span></span>
        <span class="cart-fab__text"><strong>Din bestilling</strong><small data-cart-fab-label>Vælg retter med plus</small></span>
        <span class="cart-fab__open">Åbn</span>
      </button>
    </section>

    <?php if ($meetingEnabled): ?>
    <!-- Mødeforplejning -->
    <section class="section section--meeting" id="moedeforplejning">
      <div class="container">
        <div class="meeting-layout" data-reveal>
          <div class="meeting-copy" id="moedeforplejning-head">
            <?php if ($meetingEyebrow !== ''): ?><span class="eyebrow"><?= h($meetingEyebrow) ?></span><?php endif; ?>
            <h2 class="section-title"><?= h($meetingTitle) ?></h2>
            <p class="lead"><?= nl2br(h($meetingIntro)) ?></p>
            <?php if ($meetingBadgeRoom !== '' || $meetingBadgeDelivery !== ''): ?>
            <div class="meeting-badges" aria-label="Muligheder for levering">
              <?php if ($meetingBadgeRoom !== ''): ?><span><i class="fa-solid fa-building" aria-hidden="true"></i> <?= h($meetingBadgeRoom) ?></span><?php endif; ?>
              <?php if ($meetingBadgeDelivery !== ''): ?><span><i class="fa-solid fa-truck" aria-hidden="true"></i> <?= h($meetingBadgeDelivery) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="btn-row">
              <a href="<?= h($meetingMailHref) ?>" class="btn btn--ghost btn--lg"><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= h($meetingOfferButton) ?></a>
              <a href="<?= h(front_tel_href($phone)) ?>" class="btn btn--ghost btn--lg"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= h($meetingCallButton) ?></a>
            </div>
          </div>
          <aside class="meeting-card" aria-label="Muligheder for mødeforplejning">
            <div class="meeting-card__head">
              <span class="meeting-card__icon"><i class="fa-solid fa-mug-saucer" aria-hidden="true"></i></span>
              <div><?php if ($meetingCardKicker !== ''): ?><small><?= h($meetingCardKicker) ?></small><?php endif; ?><strong><?= h($meetingPriceText) ?></strong></div>
            </div>
            <ul class="meeting-options">
              <?php foreach ($meetingPoints as $point): ?>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i><span><?= h($point) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <?php if ($meetingCardText !== ''): ?><p><?= h($meetingCardText) ?></p><?php endif; ?>
            <?php if ($meetingOrderEnabled): ?>
            <div class="meeting-order" data-order-item data-id="<?= (int)$meetingItem['id'] ?>" data-name="<?= h($meetingTitle) ?>" data-price="<?= h((string)$meetingOrderPrice) ?>" data-xl-price="0" data-price-label="<?= h($meetingPriceText) ?>" data-unit="person">
              <div class="meeting-order__copy">
                <small>Bestil direkte gennem Café LIF</small>
                <strong><?= $meetingOrderPrice > 0 ? h(money($meetingOrderPrice)) . ' pr. person' : h($meetingPriceText) ?></strong>
                <?php if ($meetingOrderHelp !== ''): ?><span><?= h($meetingOrderHelp) ?></span><?php endif; ?>
              </div>
              <button class="btn btn--clay btn--full" type="button" data-cart-add data-size="normal"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= h($meetingOrderButton) ?></button>
            </div>
            <?php endif; ?>
          </aside>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Sportscafé -->
    <section class="section section--sport" id="sport">
      <div class="container">
        <header class="section-head section-head--row" id="sport-head" data-reveal>
          <div>
            <span class="eyebrow">Sportscafé</span>
            <h2 class="section-title">Centrets<br>hjertebeat</h2>
          </div>
          <p class="section-desc section-desc--narrow">Før, under og efter træning og kamp — her mødes hold, familier og frivillige.</p>
        </header>

        <div class="scroll-cards" data-reveal>
          <article class="scroll-card">
            <div class="scroll-card__icon"><i class="fa-solid fa-dumbbell" aria-hidden="true"></i></div>
            <h3>Efter træning</h3>
            <p>Kolde drikke, sandwich og varme favoritter når du har brug for energi.</p>
          </article>
          <article class="scroll-card">
            <div class="scroll-card__icon"><i class="fa-solid fa-futbol" aria-hidden="true"></i></div>
            <h3>Kampdag</h3>
            <p>Fadøl, sodavand og snacks — god stemning til hele holdet.</p>
          </article>
          <article class="scroll-card">
            <div class="scroll-card__icon"><i class="fa-solid fa-child-reaching" aria-hidden="true"></i></div>
            <h3>Familier</h3>
            <p>Kaffe mens børnene træner. Nem aftenmad til hjem eller spis her.</p>
          </article>
          <article class="scroll-card">
            <div class="scroll-card__icon"><i class="fa-solid fa-hands-holding-circle" aria-hidden="true"></i></div>
            <h3>Klubbens ildsjæle</h3>
            <p>Holdspisning, møder og hyggelige sammenkomster for frivillige.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Selskaber -->
    <section class="section section--dark" id="selskaber">
      <div class="container">
        <div class="selskab-split" data-reveal>
          <div class="selskab-split__copy">
            <span class="eyebrow eyebrow--clay" id="selskaber-head">Selskaber</span>
            <h2 class="section-title section-title--light">Du bestemmer<br>anledningen.</h2>
            <p class="lead lead--light">Vi står for resten — fuldt festhjælp med indkøb, lokaler, borddækning, tjenerservice og slutrengøring.</p>
            <ul class="check-list check-list--light">
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Konfirmation, fødselsdag &amp; jubilæum</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Firmaarrangementer &amp; receptioner</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Hyggelige lokaler i centret</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i> Skræddersyede menuer</li>
            </ul>
            <a href="#kontakt" class="btn btn--clay btn--lg">Få et uforpligtende tilbud</a>
          </div>
          <div class="selskab-split__visual">
            <div class="selskab-quote">
              <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
              <p>Du bestemmer anledningen.<br><strong>Vi står for resten.</strong></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials -->
    <section class="section section--testimonials" id="anmeldelser">
      <div class="container">
        <header class="section-head section-head--center" id="anmeldelser-head" data-reveal>
          <span class="eyebrow">Anmeldelser</span>
          <h2 class="section-title">Det siger gæsterne</h2>
          <p class="section-desc">Ægte anbefalinger fra Facebook — <a href="https://www.facebook.com/profile.php?id=61580182382485&amp;sk=reviews" target="_blank" rel="noopener">se alle anmeldelser</a></p>
        </header>
        <div class="testimonial-track" data-reveal>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">SM</div>
              <div class="testimonial-card__author">
                <strong>Susanne Madsen</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“Jeg har i dag for første gang købt mad med hjem fra Café LIF — og det bliver i hvert fald ikke sidste gang! En lækker ribbenssandwich fyldt med godt stegt ribbensteg med sprød svær, tilpas lidt dressing og masser af frisk, strimlet spidskål.”</p>
          </blockquote>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">StM</div>
              <div class="testimonial-card__author">
                <strong>Stig Mogensen</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“Super lækker sandwich — virkelig godt brød.”</p>
          </blockquote>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">HB</div>
              <div class="testimonial-card__author">
                <strong>Helle Breumsø</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“Søde, fleksible og imødekommende personale, og fair priser til den gode mad.”</p>
          </blockquote>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">RN</div>
              <div class="testimonial-card__author">
                <strong>Ronnie Nørgaard</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“God mad og god service — mere kan vel ikke bedes om.”</p>
          </blockquote>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">PE</div>
              <div class="testimonial-card__author">
                <strong>Poul Erik Hollerup</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“God mad, sød betjening. Kommer igen.”</p>
          </blockquote>
          <blockquote class="testimonial-card">
            <div class="testimonial-card__head">
              <div class="testimonial-card__avatar" aria-hidden="true">DF</div>
              <div class="testimonial-card__author">
                <strong>Dorthe Foged</strong>
                <span class="testimonial-card__meta"><i class="fa-brands fa-facebook" aria-hidden="true"></i> anbefaler Café LIF</span>
              </div>
            </div>
            <div class="testimonial-card__stars" aria-label="5 ud af 5 stjerner">★★★★★</div>
            <p>“Fantastisk tilbud på lækker mad i Lystrup. Det har vi savnet.”</p>
          </blockquote>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="section section--faq" id="faq">
      <div class="container container--narrow">
        <header class="section-head section-head--center" id="faq-head" data-reveal>
          <span class="eyebrow">Spørgsmål</span>
          <h2 class="section-title">Ofte stillede</h2>
        </header>
        <div class="faq-list" data-reveal>
          <details class="faq-item">
            <summary>Hvornår kan man bestille dagens ret?</summary>
            <p>Dagens ret serveres tirsdag, onsdag og torsdag. Bestilling skal ske senest kl. 18.00 dagen før via telefon eller forespørgselsformularen. Takeaway afhentes kl. 17–19.</p>
          </details>
          <details class="faq-item">
            <summary>Kan maden tages med hjem?</summary>
            <p>Ja — dagens ret kan både nydes i caféen og bestilles som takeaway. Afhentning kl. 17–19. Angiv gerne tidspunkt i din besked.</p>
          </details>
          <details class="faq-item">
            <summary>Tilbyder I tapas og mad ud af huset?</summary>
            <p>Ja. Vi tilbyder tapasanretninger, festbuffet, smørrebrød og firmafrokostordninger. Kontakt os for et skræddersyet tilbud.</p>
          </details>
          <details class="faq-item">
            <summary>Hvornår har caféen åbent?</summary>
            <p>I forbindelse med kampe, træning og efter aftale for selskaber. Ring gerne for at høre om dagens åbningstider.</p>
          </details>
        </div>
      </div>
    </section>

    <!-- Kontakt -->
    <section class="section section--contact" id="kontakt">
      <div class="container">
        <div class="contact-split">
          <div class="contact-split__info" data-reveal>
            <span class="eyebrow" id="kontakt-head">Kontakt</span>
            <h2 class="section-title"><?= nl2br(h($contactHeading)) ?></h2>

            <div class="contact-cards">
              <a href="<?= h(front_tel_href($phone)) ?>" class="contact-card">
                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                <div>
                  <strong>Ring til os</strong>
                  <span><?= h($phone) ?></span>
                </div>
              </a>
              <a href="mailto:<?= h($email) ?>" class="contact-card">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <div>
                  <strong>Send en mail</strong>
                  <span><?= h($email) ?></span>
                </div>
              </a>
              <div class="contact-card">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <div>
                  <strong>Besøg os</strong>
                  <span><?= nl2br(h(str_replace(", ", "\n", $address))) ?></span>
                  <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener" class="contact-card__link">Åbn i Google Maps</a>
                </div>
              </div>
            </div>

            <div class="opening-hours">
              <strong>Åbningstider</strong>
              <p><?= nl2br(h($openingHours)) ?></p>
              <p class="opening-hours__highlight"><?= h(setting('opening_hours_highlight', 'Dagens ret: Tirsdag, onsdag & torsdag · takeaway kl. 17–19')) ?></p>
            </div>
          </div>
          <div class="contact-form" data-reveal data-reveal-delay="100">
            <span class="eyebrow">Kontakt</span>
            <h3>Ring eller skriv direkte</h3>
            <p><?= h($contactIntro) ?></p>
            <div class="btn-row" style="margin-top:18px">
              <a href="<?= h(front_tel_href($phone)) ?>" class="btn btn--clay btn--lg"><i class="fa-solid fa-phone" aria-hidden="true"></i> Ring nu</a>
              <a href="mailto:<?= h($email) ?>" class="btn btn--ghost btn--lg"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Send mail</a>
            </div>
            <p class="form-note" style="margin-top:18px">Aftaler om mad, selskaber og afhentning bekræftes direkte via telefon eller mail.</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__brand">
          <span class="logo logo--footer"><span class="logo__small">Café</span><span class="logo__big">LIF</span></span>
          <p><?= h($footerText) ?></p>
          <div class="footer__developer" aria-label="Hjemmesiden er udviklet af Lennart Jensen">
            <span>Udviklet af Lennart Jensen</span>
            <a href="mailto:admin@lense.dk">Admin@lense.dk</a>
          </div>
        </div>
        <div>
          <h4>Navigation</h4>
          <ul>
            <li><a href="#menu" data-nav-section="menu">Menu &amp; priser</a></li>
            <li><a href="#sport" data-nav-section="sport">Sportscafé</a></li>
            <?php if ($meetingEnabled): ?><li><a href="#moedeforplejning" data-nav-section="moedeforplejning"><?= h($meetingTitle) ?></a></li><?php endif; ?>
            <li><a href="#selskaber" data-nav-section="selskaber">Selskaber</a></li>
            <li><a href="#eva" data-nav-section="eva">Mød Eva</a></li>
          </ul>
        </div>
        <div>
          <h4>Kontakt</h4>
          <ul>
            <li><a href="<?= h(front_tel_href($phone)) ?>"><?= h($phone) ?></a></li>
            <li><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></li>
            <li><?= h($address) ?></li>
          </ul>
          <div class="footer__socials">
            <a href="https://www.facebook.com/profile.php?id=61580182382485" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
            <a href="https://www.lystrup-if.dk" target="_blank" rel="noopener" aria-label="Lystrup IF"><i class="fa-solid fa-globe" aria-hidden="true"></i></a>
          </div>
        </div>
      </div>
      <div class="footer__bottom">
        <p>&copy; <span data-year></span> Café LIF — Lystrup Idrætsforening</p>
        <p>
          <a href="privacy.html">Privatlivspolitik</a> ·
          <a href="handelsbetingelser.html">Handelsbetingelser</a> ·
          CVR 26590779
        </p>
      </div>
    </div>
  </footer>

  <!-- Mobile Dock -->
  <nav class="mobile-dock" aria-label="Hurtige handlinger">
    <a href="<?= h(front_tel_href($phone)) ?>" class="mobile-dock__item">
      <i class="fa-solid fa-phone" aria-hidden="true"></i>
      <span>Ring</span>
    </a>
    <a href="#menu" class="mobile-dock__item mobile-dock__item--primary">
      <i class="fa-solid fa-utensils" aria-hidden="true"></i>
      <span>Menu</span>
    </a>
    <a href="#kontakt" class="mobile-dock__item">
      <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
      <span>Kontakt</span>
    </a>
  </nav>

  <button class="back-top" id="back-top" type="button" aria-label="Tilbage til toppen">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>

  <script src="app.js?v=28"></script>
</body>
</html>
