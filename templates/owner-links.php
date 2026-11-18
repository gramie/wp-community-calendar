<?php
/**
 * Edit / Delete links for an event the current user may change.
 *
 * @var string $edit_url    Front-end edit link.
 * @var string $delete_url  Front-end delete link (empty if the user may not delete).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="cc-owner cc-meta">
	<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit this event', 'community-calendar' ); ?></a>
	<?php if ( $delete_url ) : ?>
		&middot;
		<a href="<?php echo esc_url( $delete_url ); ?>" data-cc-confirm="<?php esc_attr_e( 'Move this event to the trash?', 'community-calendar' ); ?>"><?php esc_html_e( 'Delete', 'community-calendar' ); ?></a>
	<?php endif; ?>
</p>
