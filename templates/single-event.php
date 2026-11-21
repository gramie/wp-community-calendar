<?php
/**
 * Single event page: owner links, photo, details, description and map.
 *
 * @var string $owner_links  Edit/Delete links for people who may change the event.
 * @var string $image        Event photo HTML (may be empty).
 * @var array  $rows         Detail rows: label, type (text|link|email|tel), text, url.
 * @var string $content      The event description (already filtered).
 * @var string $map          Map HTML (may be empty).
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<p><a href="/oha-event-calendar">Return to Calendar</a></p>
<?php echo $owner_links;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/owner-links.php.
?>
<?php echo $content;
// phpcs:ignore WordPress.Security.EscapeOutput -- post content, already run through the_content filters.
?>
<dl class="cc-single">
	<?php foreach ($rows as $row): ?>
		<dt><?php echo esc_html($row["label"]); ?></dt>
		<dd>
			<?php if ("link" === $row["type"]): ?>
				<a href="<?php echo esc_url(
        $row["url"],
    ); ?>" rel="noopener"><?php echo esc_html($row["text"]); ?></a>
			<?php elseif ("email" === $row["type"]): ?>
				<a href="mailto:<?php echo esc_attr(
        antispambot($row["text"]),
    ); ?>"><?php echo esc_html(antispambot($row["text"])); ?></a>
			<?php elseif ("tel" === $row["type"]): ?>
				<a href="tel:<?php echo esc_attr(
        preg_replace("/[^0-9+]/", "", $row["text"]),
    ); ?>"><?php echo esc_html($row["text"]); ?></a>
			<?php else: ?>
				<?php echo esc_html($row["text"]); ?>
			<?php endif; ?>
		</dd>
	<?php endforeach; ?>
</dl>
<?php echo $map; // phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/map.php. ?>
