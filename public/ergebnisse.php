<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$results = rcb_rows("SELECT r.id, r.event_id, r.title, r.content, r.updated_at, e.title AS event_title, e.event_date FROM results r LEFT JOIN events e ON e.id=r.event_id WHERE r.published = 1 ORDER BY COALESCE(e.event_date, r.updated_at) DESC, r.id DESC");
rcb_header('results');
echo rcb_page_hero('ergebnisse', 'Rennergebnisse', 'Meisterschaftswertungen und Ergebnisse der Rennen bei der RC Bande Zwickau.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($results): ?>
<h2><?= rcb_text('ergebnisse.berichte.titel', 'Aktuelle Ergebnisse & Rennberichte') ?></h2>
<div class="rcb-grid">
<?php foreach ($results as $item): ?>
<article class="rcb-card"<?= !empty($item['event_id']) ? ' id="termin-' . (int)$item['event_id'] . '" style="scroll-margin-top:110px"' : '' ?>><div class="rcb-card-body">
<?php if (!empty($item['event_date'])): ?><span class="rcb-pill"><?= rcb_h(rcb_date((string)$item['event_date'])) ?></span><?php endif; ?>
<h3><?= rcb_h($item['title']) ?></h3>
<?php if (!empty($item['event_title'])): ?><p class="rcb-muted">Veranstaltung: <?= rcb_h($item['event_title']) ?></p><?php endif; ?>
<?php if ((string)$item['content'] !== ''): ?><div class="rcb-content"><?= nl2br(rcb_h($item['content'])) ?></div><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div style="margin-top:24px">
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
<li>21.01.2024 Chemnitzer Clubrennen No. 1</li>
<li><a href="https://www.myrcm.ch/myrcm/main?dId[O]=51129&amp;pLa=de&amp;dId[E]=74592&amp;tId=E&amp;hId[1]=org">19.11.2023 TT-02 Cup in Chemnitz</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69680&amp;hId[1]=search">17.09.2023 3. Lauf Offroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69677&amp;hId[1]=search">13.08.2023 3. Lauf Onroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=69676&amp;hId[1]=search">11.06.2023 2. Lauf Onroad Cup</a></li>
<li><a href="https://www.myrcm.ch/myrcm/main?pLa=de&amp;dFi=Zwickau+&amp;dId[E]=70910&amp;hId[1]=search">29.04.2023 1. Lauf Onroad Cup</a></li>
</ul>') ?></div></article>
</div>
</div></section>
<?php rcb_footer(); ?>
