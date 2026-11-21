<?php
/**
 * Wrapper for the public calendar.
 *
 * @var string $switcher   List / Calendar view links HTML (may be empty).
 * @var string $filters    Filter bar HTML (may be empty).
 * @var string $subscribe  Subscribe links HTML (may be empty).
 * @var string $body       List or month view HTML.
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="cc-cal">
	<?php echo $switcher;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/view-switcher.php.
?>
	<?php echo $filters;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/filters.php.
?>
	<?php echo $subscribe;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/subscribe-links.php.
?>
	<?php echo $body;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/event-list.php or month-grid.php.
?>
</div>
