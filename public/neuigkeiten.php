<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id > 0) {
    $items = rcb_rows('SELECT * FROM news WHERE id = ? AND published = 1 LIMIT 1', [$id]);
    if (!$items) {
        http_response_code(404);
    }
    $item = $items[0] ?? null;
    rcb_header('news');
    if (!$item) {
        echo '<section class="rcb-section"><div class="rcb-wrap"><h1>Neuigkeit nicht gefunden</h1><p>Diese Meldung ist nicht verfügbar.</p><a class="rcb-btn" href="/neuigkeiten.php">Alle Neuigkeiten</a></div></section>';
    } else {
        echo '<article class="rcb-section"><div class="rcb-wrap">';
        echo '<a href="/neuigkeiten.php">← Alle Neuigkeiten</a>';
        if ($item['image_url'] !== '') {
            echo '<img src="' . rcb_h($item['image_url']) . '" alt="' . rcb_h($item['title']) . '" style="display:block;width:100%;max-height:520px;object-fit:cover;margin:20px 0;border-radius:12px">';
        }
        echo '<p class="rcb-pill">' . rcb_h(rcb_date(substr((string)$item['created_at'], 0, 10))) . '</p>';
        echo '<h1>' . rcb_h($item['title']) . '</h1>';
        if ($item['summary'] !== '') {
            echo '<p class="rcb-muted">' . nl2br(rcb_h($item['summary'])) . '</p>';
        }
        echo '<div class="rcb-content">' . nl2br(rcb_h($item['content'])) . '</div>';
        echo '</div></article>';
    }
    rcb_footer();
    exit;
}
$items = rcb_rows('SELECT * FROM news WHERE published = 1 ORDER BY created_at DESC, id DESC');
rcb_header('news');
?>
<section class="rcb-section"><div class="rcb-wrap">
    <h1>Neuigkeiten</h1><p class="rcb-muted">Aktuelle Informationen aus dem Verein.</p>
    <?php if ($items): ?><div class="rcb-grid">
        <?php foreach ($items as $item): ?>
        <article class="rcb-card">
            <?php if ($item['image_url'] !== ''): ?><img class="rcb-card-image" src="<?= rcb_h($item['image_url']) ?>" alt="<?= rcb_h($item['title']) ?>" loading="lazy"><?php endif; ?>
            <div class="rcb-card-body">
                <span class="rcb-pill"><?= rcb_h(rcb_date(substr((string)$item['created_at'], 0, 10))) ?></span>
                <h2><?= rcb_h($item['title']) ?></h2>
                <?php if ($item['summary'] !== ''): ?><p><?= nl2br(rcb_h($item['summary'])) ?></p><?php endif; ?>
                <a class="rcb-btn" href="/neuigkeiten.php?id=<?= (int)$item['id'] ?>">Weiterlesen</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div><?php else: ?><div class="rcb-empty">Es gibt noch keine veröffentlichten Neuigkeiten.</div><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
