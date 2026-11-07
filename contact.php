<?php
require __DIR__ . '/includes/layout.php';
$cp = $C['contactPage'];
$ct = $C['contact'];
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = contact_handle($C);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $result['ok'], 'errors' => $result['errors'], 'message' => $result['ok'] ? $cp['successMessage'] : '']);
        exit;
    }
    if ($result['ok']) {
        header('Location: contact.php?sent=1#form', true, 303);
        exit;
    }
}
$sent = isset($_GET['sent']);
$vals = $result['values'] ?? [];
$errs = $result['errors'] ?? [];
$wa = wa_link($ct);
$socials = array_values(array_filter($ct['socials'], function ($x) {
    return trim((string)$x['url']) !== '';
}));
$fval = function ($k) use ($vals) {
    return e($vals[$k] ?? '');
};

// service choices (a service picked on the Services page is always in the list)
$serviceList = form_services($C);
$curService = (string)($vals['service'] ?? mdj_clean_str($_GET['service'] ?? '', 60));
if ($curService !== '' && !in_array($curService, $serviceList, true)) {
    array_unshift($serviceList, $curService);
}
$budgetList = line_items((string)$cp['budgetOptions']);
$curBudget = (string)($vals['budget'] ?? '');
if ($budgetList && $curBudget !== '' && !in_array($curBudget, $budgetList, true)) {
    array_unshift($budgetList, $curBudget);
}
$chev = '<svg class="sel-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';

page_open('contact', $cp['eyebrow'] . ' — ' . $C['settings']['siteName']);
?>
<section class="phead sec" data-sec="phead" data-chapter="<?= e($cp['eyebrow']) ?>">
<div class="wrap">
<?= eyebrow($cp['eyebrow']) ?>
<?= title_lines($cp['title1'], $cp['title2'], 'display ph-title', 'h1') ?>
<?php if ($cp['text'] !== ''): ?><p class="ph-intro rv"><?= e($cp['text']) ?></p><?php endif; ?>
</div>
</section>

<section class="ct sec" data-sec="ct" id="form" data-chapter="<?= e($cp['formTitle']) ?>">
<div class="ct-grid">
<div class="ct-cards">
<?php if ($ct['email'] !== ''): ?>
<a class="ct-card glass rv" href="mailto:<?= e($ct['email']) ?>"><span class="ct-ico"><?= soc_icon('mail', 22) ?></span><span><span class="mono ct-l">Email</span><span class="ct-v"><?= e($ct['email']) ?></span></span><span class="ct-go"><?= icon('out', 18) ?></span></a>
<?php endif; ?>
<?php if ($wa !== ''): ?>
<a class="ct-card glass rv" href="<?= e($wa) ?>" target="_blank" rel="noopener"><span class="ct-ico"><?= soc_icon('chat', 22) ?></span><span><span class="mono ct-l">WhatsApp</span><span class="ct-v"><?= e($ct['whatsapp']) ?></span></span><span class="ct-go"><?= icon('out', 18) ?></span></a>
<?php endif; ?>
<?php if ($ct['bookingUrl'] !== ''): ?>
<a class="ct-card glass rv" href="<?= e(safe_href($ct['bookingUrl'])) ?>" target="_blank" rel="noopener"><span class="ct-ico"><?= icon('calendar', 22) ?></span><span><span class="mono ct-l">Book a call</span><span class="ct-v">Pick a time that suits you</span></span><span class="ct-go"><?= icon('out', 18) ?></span></a>
<?php endif; ?>
<div class="ct-card glass rv"><span class="ct-ico"><?= icon('pin', 22) ?></span><span><span class="mono ct-l"><?= e($ct['responseTime']) ?></span><span class="ct-v"><?= e($ct['location']) ?></span></span></div>
<?php if ($socials): ?>
<div class="ct-follow">
<div class="mono rv ct-fl">Follow</div>
<div class="ct-socials">
<?php foreach ($socials as $so): ?><a class="soc rv" href="<?= e(safe_href($so['url'])) ?>" target="_blank" rel="noopener" data-magnet="1"><span class="soc-ico"><?= soc_icon(social_glyph((string)$so['label'], (string)$so['url']), 16) ?></span><?= e($so['label']) ?></a><?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</div>

<form class="form hud rv rv-card<?= $sent ? ' is-sent' : '' ?>" id="contactForm" method="post" action="contact.php#form" novalidate>
<div class="form-body"<?= $sent ? ' hidden' : '' ?>>
<h2><?= e($cp['formTitle']) ?></h2>
<p class="form-intro"><?= e($cp['formIntro']) ?></p>
<input type="hidden" name="t" value="<?= e(form_token()) ?>">
<div class="f-hp" hidden aria-hidden="true"><label>Leave this empty <input type="text" name="hp_field" value="" tabindex="-1" autocomplete="off"></label></div>
<div class="frow">
<div class="fld<?= isset($errs['name']) ? ' bad' : '' ?>"><label for="f-name"><?= e($cp['nameLabel']) ?></label><input id="f-name" name="name" type="text" autocomplete="name" required maxlength="80" placeholder="<?= e($cp['namePlaceholder']) ?>" value="<?= $fval('name') ?>"><?php if (isset($errs['name'])): ?><span class="ferr"><?= e($errs['name']) ?></span><?php endif; ?></div>
<div class="fld<?= isset($errs['email']) ? ' bad' : '' ?>"><label for="f-email"><?= e($cp['emailLabel']) ?></label><input id="f-email" name="email" type="email" autocomplete="email" required maxlength="120" placeholder="<?= e($cp['emailPlaceholder']) ?>" value="<?= $fval('email') ?>"><?php if (isset($errs['email'])): ?><span class="ferr"><?= e($errs['email']) ?></span><?php endif; ?></div>
</div>
<div class="frow">
<div class="fld"><label for="f-service"><?= e($cp['serviceLabel']) ?></label>
<div class="sel-wrap"><select id="f-service" name="service"<?= $curService === '' ? ' class="is-empty"' : '' ?>>
<option value=""><?= e($cp['servicePlaceholder'] !== '' ? $cp['servicePlaceholder'] : 'Select a service') ?></option>
<?php foreach ($serviceList as $opt): ?>
<option value="<?= e($opt) ?>"<?= $curService === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
<?php endforeach; ?>
</select><?= $chev ?></div></div>
<div class="fld"><label for="f-budget"><?= e($cp['budgetLabel']) ?></label>
<?php if ($budgetList): ?>
<div class="sel-wrap"><select id="f-budget" name="budget"<?= $curBudget === '' ? ' class="is-empty"' : '' ?>>
<option value=""><?= e($cp['budgetPlaceholder'] !== '' ? $cp['budgetPlaceholder'] : 'Select a budget range') ?></option>
<?php foreach ($budgetList as $opt): ?>
<option value="<?= e($opt) ?>"<?= $curBudget === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
<?php endforeach; ?>
</select><?= $chev ?></div>
<?php else: ?>
<input id="f-budget" name="budget" type="text" maxlength="60" placeholder="<?= e($cp['budgetPlaceholder']) ?>" value="<?= $fval('budget') ?>">
<?php endif; ?>
</div>
</div>
<div class="fld<?= isset($errs['message']) ? ' bad' : '' ?>"><label for="f-message"><?= e($cp['messageLabel']) ?></label><div class="ta-wrap"><textarea id="f-message" name="message" required maxlength="4000" placeholder="<?= e($cp['messagePlaceholder']) ?>"><?= $fval('message') ?></textarea><span class="f-count mono" aria-hidden="true"><span id="msgCount">0</span> / 4000</span></div><?php if (isset($errs['message'])): ?><span class="ferr"><?= e($errs['message']) ?></span><?php endif; ?></div>
<button class="btn btn-primary f-send" type="submit"><span class="b-lbl"><?= e($cp['formButton']) ?></span><?= arrow_svg() ?><span class="b-spin" aria-hidden="true"></span></button>
<?php if (trim((string)$cp['privacyNote']) !== ''): ?><p class="f-privacy"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10.5" width="16" height="10.5" rx="2"></rect><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"></path></svg><?= e($cp['privacyNote']) ?></p><?php endif; ?>
<div id="formStatus" aria-live="polite">
<?php if (isset($errs['form'])): ?><div class="f-status f-err"><?= e($errs['form']) ?></div><?php endif; ?>
</div>
</div>
<div class="form-done" id="formDone"<?= $sent ? '' : ' hidden' ?> role="status" tabindex="-1">
<div class="fd-ico" aria-hidden="true"><svg width="74" height="74" viewBox="0 0 74 74"><circle class="fd-ring" cx="37" cy="37" r="34" fill="none" stroke-width="2"></circle><path class="fd-tick" d="m23 38 9.5 9.5L52 28" fill="none" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"></path></svg></div>
<h2 class="fd-title"><?= e($cp['successTitle']) ?></h2>
<p class="fd-text" id="formDoneText"><?= e($cp['successMessage']) ?></p>
<?php if (trim((string)$cp['anotherButton']) !== ''): ?><a class="btn btn-ghost" id="formAgain" href="contact.php#form"><?= e($cp['anotherButton']) ?></a><?php endif; ?>
</div>
</form>
</div>
</section>

<section class="contact sec ct-planet" data-sec="contact">
<div class="planet-wrap" aria-hidden="true"><div class="planet"></div><div class="flare"></div><div class="sunrise"></div></div>
</section>
<?php page_close(); ?>
