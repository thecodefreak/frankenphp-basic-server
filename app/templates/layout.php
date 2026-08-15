<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Dashboard') . ' · Insta Autoposter') ?></title>
<link rel="stylesheet" href="/assets/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📸</text></svg>">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="shell">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark">◎</span> Autoposter</div>
        <nav>
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
            $current = $_SERVER['REQUEST_URI'] ?? '/';
            foreach ($nav as $href => [$label, $icon]):
                $active = $href === '/' ? $current === '/' : str_starts_with($current, $href);
            ?>
                <a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>">
                    <span class="nav-icon" aria-hidden="true"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main id="main" class="main">
        <header class="page-head">
            <h1><?= e($title ?? 'Dashboard') ?></h1>
            <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
        </header>
        <?php foreach ($_SESSION['flash'] ?? [] as $flash): ?>
            <div class="flash flash-<?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; unset($_SESSION['flash']); ?>
        <?= $content ?? '' ?>
    </main>
</div>
<script src="/assets/app.js" defer></script>
</body>
</html>
