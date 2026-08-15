<section class="card error-card">
    <p class="error-status"><?= (int) $status ?></p>
    <h2><?= e(match (true) {
        $status === 404 => 'Page not found',
        $status === 409 => 'Conflict',
        $status >= 500 => 'Server error',
        default => 'Request failed',
    }) ?></h2>
    <pre class="error-message"><?= e($message) ?></pre>
    <a class="btn" href="/">Back to dashboard</a>
</section>
