<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Dashboard') . ' · Insta Autoposter') ?></title>
<link rel="stylesheet" href="/assets/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📸</text></svg>">
<script>
    const stored = localStorage.getItem('theme');
    if (stored && stored !== 'system') document.documentElement.setAttribute('data-theme', stored);
</script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<?php
$nav = [
    '/' => ['Dashboard', '▦'],
    '/calendar' => ['Calendar', '▤'],
    '/posts' => ['Posts', '❏'],
    '/templates' => ['Templates', '✦'],
    '/providers' => ['AI Providers', '⌁'],
    '/accounts' => ['Instagram', '◈'],
    '/settings' => ['Settings', '⚙'],
];
$current = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
?>

<header class="topbar">
    <button type="button" class="btn btn-icon" data-nav-toggle aria-expanded="false" aria-label="Toggle navigation">☰</button>
    <strong><?= e($title ?? 'Dashboard') ?></strong>
</header>

<div class="scrim" data-scrim></div>

<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="/"><span class="brand-mark" aria-hidden="true">◎</span> Autoposter</a>
        <nav aria-label="Main">
            <?php foreach ($nav as $href => [$label, $icon]): ?>
                <?php $active = $href === '/' ? $current === '/' : str_starts_with($current, $href); ?>
                <a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <button type="button" class="theme-toggle" data-theme-toggle>
                <span data-theme-icon aria-hidden="true">◐</span>
                <span>Theme: <span data-theme-label>System</span></span>
            </button>
        </div>
    </aside>

    <main id="main" class="main">
        <header class="page-head">
            <div>
                <?php if (!empty($back)): ?>
                    <a class="back-link" href="<?= e($back[0]) ?>">← <?= e($back[1]) ?></a>
                <?php endif; ?>
                <h1><?= e($title ?? 'Dashboard') ?></h1>
            </div>
            <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
        </header>

        <div class="flash-region" role="status" aria-live="polite">
            <?php foreach ($_SESSION['flash'] ?? [] as $flash): ?>
                <div class="flash flash-<?= e($flash['kind']) ?>" data-flash>
                    <span aria-hidden="true"><?= $flash['kind'] === 'error' ? '⚠' : '✓' ?></span>
                    <div class="flash-body"><?= e($flash['message']) ?></div>
                    <button type="button" class="flash-close" data-flash-close aria-label="Dismiss">×</button>
                </div>
            <?php endforeach; unset($_SESSION['flash']); ?>
        </div>

        <?= $content ?? '' ?>
    </main>
</div>
<script src="/assets/app.js" defer></script>
</body>
</html>
