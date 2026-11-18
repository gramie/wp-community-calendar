<?php
/**
 * Upcoming events list, grouped by day.
 *
 * @var array  $groups         Days: heading, events (url, title, thumb, meta, tags, owner).
 * @var string $empty_message  Shown when there are no events.
 * @var array|null $nav     Previous / next month links when listing one month: prev_url, next_url, label.
 * @var string $note        Explains the period shown (upcoming mode).
 * @var string $more_url    Link that extends the upcoming period (may be empty).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( $nav ) : ?>
	<div class="cc-nav">
		<a href="<?php echo esc_url( $nav['prev_url'] ); ?>">&laquo; <?php esc_html_e( 'Previous', 'community-calendar' ); ?></a>
		<strong><?php echo esc_html( $nav['label'] ); ?></strong>
		<a href="<?php echo esc_url( $nav['next_url'] ); ?>"><?php esc_html_e( 'Next', 'community-calendar' ); ?> &raquo;</a>
	</div>
<?php endif; ?>
<?php if ( ! $groups ) : ?>
	<p><?php echo esc_html( $empty_message ); ?></p>
<?php else : ?>
	<?php foreach ( $groups as $group ) : ?>
		<h3 class="cc-day-heading"><?php echo esc_html( $group['heading'] ); ?></h3>
		<?php foreach ( $group['events'] as $event ) : ?>
			<div class="cc-event">
				<?php if ( $event['thumb'] ) : ?>
					<a href="<?php echo esc_url( $event['url'] ); ?>"><?php echo $event['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput -- core get_the_post_thumbnail() markup. ?></a>
				<?php endif; ?>
				<div>
					<h4><a href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a></h4>
					<div class="cc-meta"><?php echo esc_html( $event['meta'] ); ?></div>
					<?php if ( $event['tags'] ) : ?>
						<div class="cc-meta"><?php echo esc_html( $event['tags'] ); ?></div>
					<?php endif; ?>
					<?php echo $event['owner']; // phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/owner-links.php. ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endforeach; ?>
<?php endif; ?>
<?php if ( $note ) : ?>
	<p class="cc-meta">
		<?php echo esc_html( $note ); ?>
		<?php if ( $more_url ) : ?>
			<a href="<?php echo esc_url( $more_url ); ?>"><?php esc_html_e( 'Show more events', 'community-calendar' ); ?></a>
		<?php endif; ?>
	</p>
<?php endif; ?>
