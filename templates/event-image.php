<?php
/**
 * The event photo on a single event page.
 *
 * @var string $image_html  <img> markup from wp_get_attachment_image().
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="cc-event-photo"><?php echo $image_html;
// phpcs:ignore WordPress.Security.EscapeOutput -- core attachment markup.
?></div>
