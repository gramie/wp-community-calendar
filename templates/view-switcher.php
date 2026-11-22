<?php
/**
 * Links that switch the calendar between the list view and the calendar (month) view.
 *
 * @var array $views  Each: label, url, current (bool).
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="calendar-head">
    <nav class="cc-views" aria-label="<?php esc_attr_e(
        "Calendar view",
        "community-calendar",
    ); ?>">
	<?php foreach ($views as $view): ?>
		<a href="<?php echo esc_url($view["url"]); ?>" class="<?php echo $view[
    "current"
]
    ? "is-current"
    : ""; ?>" <?php echo $view["current"]
    ? 'aria-current="page"'
    : ""; ?>><?php echo esc_html($view["label"]); ?></a>
	<?php endforeach; ?>
    </nav>
    <?php if (current_user_can("edit_cc_events")) { ?>
    <div class="add-event has-light-green-cyan-background-color has-black-color"><a href="/oha-calendar-add-event">Add event</a></div>
    <?php } ?>
</div>
