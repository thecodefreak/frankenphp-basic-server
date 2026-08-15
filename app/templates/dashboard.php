<?php if ($warnings !== []): ?>
    <section class="card warn-card">
        <h2>Setup needed</h2>
        <ul class="warn-list">
            <?php foreach ($warnings as $warning): ?>
                <li><?= e($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php else: ?>
    <section class="card">
        <p class="empty">Everything is configured. Head to Templates to set up your first posting schedule.</p>
    </section>
<?php endif; ?>
