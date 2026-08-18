<?php $this->slot('actions', '<a class="btn btn-primary" href="/templates/new">New template</a>'); ?>

<?php if ($templates === []): ?>
    <section class="card">
        <div class="empty-state">
            <p>Templates decide what gets posted and when. Create one to get started.</p>
            <a class="btn btn-primary" href="/templates/new">Create your first template</a>
        </div>
    </section>
<?php else: ?>
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Name</th><th>Subject</th><th>Providers</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $template): ?>
                    <?php
                    $schedule = json_decode($template['schedule_json'], true) ?: ['times' => [], 'weekdays' => []];
                    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                    $days = array_map(static fn ($d) => $dayNames[$d] ?? '', $schedule['weekdays']);
                    ?>
                    <tr>
                        <td>
                            <a href="/templates/<?= (int) $template['id'] ?>/edit"><?= e($template['name']) ?></a>
                            <?php if ($template['is_default']): ?> <span class="pill">default</span><?php endif; ?>
                        </td>
                        <td class="wrap"><?= e(mb_strimwidth($template['subject'], 0, 46, '…')) ?></td>
                        <td><?= e($template['text_provider_name'] ?? '—') ?> / <?= e($template['image_provider_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($schedule['times'] === [] || $days === []): ?>
                                <span class="empty">not set</span>
                            <?php else: ?>
                                <?= e(implode(', ', $schedule['times'])) ?><br>
                                <span class="field-hint"><?= e(implode(' ', $days)) ?> · <?= e($template['timezone']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill <?= $template['is_active'] ? 'pill-published' : '' ?>"><?= $template['is_active'] ? 'active' : 'inactive' ?></span></td>
                        <td class="row-actions">
                            <a href="/templates/<?= (int) $template['id'] ?>/edit">Edit</a>
                            <form method="post" action="/templates/<?= (int) $template['id'] ?>/delete" data-confirm="Remove template &quot;<?= e($template['name']) ?>&quot;? Its posts stay in history but stop generating.">
                                <button type="submit" class="btn-link" data-no-busy>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
