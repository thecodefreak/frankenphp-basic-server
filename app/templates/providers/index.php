<?php $this->slot('actions', '<a class="btn btn-primary" href="/providers/new">Add provider</a>'); ?>

<?php if ($providers === []): ?>
    <section class="card">
        <div class="empty-state">
            <p>You need one <strong>text</strong> provider for captions and one <strong>image</strong> provider for pictures.</p>
            <a class="btn btn-primary" href="/providers/new">Add your first provider</a>
        </div>
    </section>
<?php else: ?>
    <?php foreach (['text' => 'Text providers (captions)', 'image' => 'Image providers (pictures)'] as $kind => $label): ?>
        <?php $rows = array_filter($providers, static fn ($p) => $p['kind'] === $kind); ?>
        <section class="card">
            <div class="card-head">
                <h2><?= e($label) ?></h2>
                <a class="btn btn-sm" href="/providers/new">Add</a>
            </div>
            <?php if ($rows === []): ?>
                <p class="empty">None configured — post generation will fail without one.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Name</th><th>Type</th><th>Model</th><th>Pricing</th><th>Default</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $provider): ?>
                            <tr>
                                <td><a href="/providers/<?= (int) $provider['id'] ?>/edit"><?= e($provider['name']) ?></a></td>
                                <td><?= e($provider['type']) ?></td>
                                <td><code><?= e($provider['model']) ?></code></td>
                                <td class="num">
                                    <?php if ($kind === 'text'): ?>
                                        <?= e(money((float) $provider['price_input_per_mtok'])) ?> / <?= e(money((float) $provider['price_output_per_mtok'])) ?> per Mtok
                                    <?php else: ?>
                                        <?= e(money((float) $provider['price_per_image'])) ?> per image
                                    <?php endif; ?>
                                </td>
                                <td><?= $provider['is_default'] ? '✓' : '' ?></td>
                                <td class="row-actions">
                                    <a href="/providers/<?= (int) $provider['id'] ?>/edit">Edit</a>
                                    <form method="post" action="/providers/<?= (int) $provider['id'] ?>/delete" data-confirm="Remove provider &quot;<?= e($provider['name']) ?>&quot;?">
                                        <button type="submit" class="btn-link" data-no-busy>Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
