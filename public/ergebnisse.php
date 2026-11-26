<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$results = rcb_rows("SELECT r.id, r.event_id, r.title, r.content, r.updated_at, e.title AS event_title, e.event_date FROM results r LEFT JOIN events e ON e.id=r.event_id WHERE r.published = 1 ORDER BY COALESCE(e.event_date, r.updated_at) DESC, r.id DESC");
// Vergangene Rennen aus den Terminen: erscheinen hier automatisch, mit Ergebnis-Link (z. B. MyRCM) oder Rennbericht.
$lastDayExpr = "COALESCE(NULLIF(end_date, ''), event_date)";
$raceEvents = array_values(array_filter(
    rcb_rows("SELECT * FROM events WHERE published = 1 AND event_date IS NOT NULL AND event_date <> '' AND $lastDayExpr < ? ORDER BY event_date DESC LIMIT 60", [rcb_today()]),
    static function (array $e): bool {
        if (!rcb_event_is_race($e)) { return false; }
        $hasLink = rcb_safe_url((string)($e['results_url'] ?? '')) || in_array((int)$e['id'], rcb_event_result_ids(), true);
        $recent = (string)($e['end_date'] ?: $e['event_date']) >= date('Y-m-d', strtotime('-21 days'));
        return $hasLink || $recent;
    }
));
rcb_header('results');
echo rcb_page_hero('ergebnisse', 'Rennergebnisse', 'Ergebnisse der Rennen mit der RC Bande Zwickau.');
?>
<?php if ($raceEvents && rcb_section_show('ergebnisse.veranstaltungen')): ?>
<section class="rcb-section<?= rcb_section_mark('ergebnisse.veranstaltungen') ?>"><div class="rcb-wrap">
<h2><?= rcb_text('ergebnisse.veranstaltungen.titel', 'Ergebnisse unserer Veranstaltungen') ?></h2>
<div class="rcb-results-list">
<?php foreach ($raceEvents as $e): [$day, $monthYear] = rcb_event_date_parts($e['event_date'] ?? null, $e['end_date'] ?? null); $url = (string)($e['results_url'] ?? ''); $report = in_array((int)$e['id'], rcb_event_result_ids(), true); ?>
<article class="rcb-result-row">
<div class="rcb-event-date"><span class="rcb-event-day"><?= rcb_h($day) ?></span><span class="rcb-event-month"><?= rcb_h($monthYear) ?></span></div>
<div class="rcb-result-body"><h3><?= rcb_h($e['title']) ?></h3><?php if ((string)$e['location'] !== ''): ?><p class="rcb-muted"><?= rcb_h($e['location']) ?></p><?php endif; ?></div>
<div class="rcb-result-actions">
<?php if (rcb_safe_url($url)): ?><a class="rcb-btn rcb-btn-result" href="<?= rcb_h($url) ?>" target="_blank" rel="noopener noreferrer"><span class="rcb-btn-icon" aria-hidden="true">🏁</span><span>Ergebnisse<small>bei <?= rcb_h(rcb_link_label($url)) ?> ↗</small></span></a><?php endif; ?>
<?php if ($report): ?><a class="rcb-btn rcb-btn-light" href="#bericht-<?= (int)$e['id'] ?>">Rennbericht</a><?php endif; ?>
<?php if (!rcb_safe_url($url) && !$report): ?><span class="rcb-tag">Ergebnisse folgen</span><?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
</div></section>
<?php endif; ?>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($results): ?>
<h2><?= rcb_text('ergebnisse.berichte.titel', 'Aktuelle Ergebnisse & Rennberichte') ?></h2>
<div class="rcb-grid">
<?php foreach ($results as $item): ?>
<article class="rcb-card"<?= !empty($item['event_id']) ? ' id="bericht-' . (int)$item['event_id'] . '" style="scroll-margin-top:110px"' : '' ?>><div class="rcb-card-body">
<?php if (!empty($item['event_date'])): ?><span class="rcb-pill"><?= rcb_h(rcb_date((string)$item['event_date'])) ?></span><?php endif; ?>
<h3><?= rcb_h($item['title']) ?></h3>
<?php if (!empty($item['event_title'])): ?><p class="rcb-muted">Veranstaltung: <?= rcb_h($item['event_title']) ?></p><?php endif; ?>
<?php if ((string)$item['content'] !== ''): ?><div class="rcb-content"><?= nl2br(rcb_h($item['content'])) ?></div><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (rcb_section_show('ergebnisse.archiv')): ?><div class="<?= trim(rcb_section_mark('ergebnisse.archiv')) ?>" style="margin-top:24px">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('ergebnisse.rennen', '<h2>Rennergebnisse</h2><ul>
<li><a href="https://www.myrcm.ch/myrcm/main?dId[O]=48536&amp;pLa=de&amp;dId[E]=94707&amp;tId=E&amp;hId[1]=org">14.06.2026 Tamiya Euro Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?dId[O]=48536&amp;pLa=de&amp;dId[E]=97360&amp;tId=E&amp;hId[1]=org">16.–17.05.2026 RCK-Challenge</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=zwickau&amp;dId[E]=90830&amp;hId[1]=search">20.–21.09.2025 RCK-Challenge</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=zwickau&amp;dId[E]=89753&amp;hId[1]=search">06.–07.09.2025 5. Lauf Sachsencup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=zwickau&amp;dId[E]=87500&amp;hId[1]=search">16.08.2025 RCK-Kleinserie</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?dId%5BO%5D=48536&amp;pLa=de&amp;dId%5BE%5D=87499&amp;tId=E&amp;hId%5B1%5D=org">15.06.2025 RCK-Kleinserie</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau&amp;dId%5BE%5D=87875&amp;hId%5B1%5D=search">10./11.05.2025 RCK-Challenge</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?dId%5BO%5D=51129&amp;pLa=de&amp;dId%5BE%5D=84811&amp;tId=E&amp;hId%5B1%5D=org">02.03.2025 RCK-Challenge Chemnitz</a></li>
<li><a href="https://www.myrcm.ch/myrcm/report/de/80303/340034">24.–26.01.2025 EGC Lauf 6 Messe Chemnitz</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=zwickau&amp;dId[E]=81233&amp;hId[1]=search">22.09.2024 RCK-Kleinserie</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau&amp;dId%5BE%5D=81217&amp;hId%5B1%5D=search">18.08.2024 RCK-Challenge</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=zwickau&amp;dId[E]=75500&amp;hId[1]=search">16.06.2024 Tamiya Euro Cup</a></li>
<li>21.01.2024 Chemnitzer Clubrennen Nr. 1</li>
<li><a href="https://www.myrcm.ch/myrcm/main?dId[O]=51129&amp;pLa=de&amp;dId[E]=74592&amp;tId=E&amp;hId[1]=org">19.11.2023 TT-02 Cup in Chemnitz</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69680&amp;hId[1]=search">17.09.2023 3. Lauf Offroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69677&amp;hId[1]=search">13.08.2023 3. Lauf Onroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69676&amp;hId[1]=search">11.06.2023 2. Lauf Onroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=70910&amp;hId[1]=search">29.04.2023 1. Lauf Onroad Cup</a></li>
</ul>') ?></div></article>
</div><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
