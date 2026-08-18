<section class="card" data-calendar>
    <div class="calendar-head">
        <button type="button" class="btn btn-sm btn-icon" data-cal-prev aria-label="Previous month">‹</button>
        <span class="calendar-title" data-cal-title>&nbsp;</span>
        <button type="button" class="btn btn-sm btn-icon" data-cal-next aria-label="Next month">›</button>
        <button type="button" class="btn btn-sm" data-cal-today>Today</button>
        <span class="spacer"></span>
        <label class="visually-hidden" for="cal-month">Jump to month</label>
        <input type="month" id="cal-month" data-cal-month>
    </div>

    <div class="calendar-summary" data-cal-summary></div>

    <div class="calendar-grid" data-cal-grid></div>

    <div class="legend">
        <span class="legend-item"><span class="legend-dot" style="background: var(--ok)"></span> Published</span>
        <span class="legend-item"><span class="legend-dot" style="background: var(--accent)"></span> Planned</span>
        <span class="legend-item"><span class="legend-dot" style="background: var(--danger)"></span> Failed</span>
        <span class="legend-item"><span class="legend-dot" style="background: var(--warn)"></span> Skipped / cancelled</span>
        <span class="legend-item">Cost shown per day is the AI spend for that day's posts.</span>
    </div>
</section>
