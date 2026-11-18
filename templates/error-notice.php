<?php
/**
 * Admin notice listing why event dates were not saved.
 *
 * @var string[] $errors  Error messages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice notice-error">
	<p><strong><?php esc_html_e( 'Event dates were not saved:', 'community-calendar' ); ?></strong></p>
	<ul>
		<?php foreach ( $errors as $error ) : ?>
			<li><?php echo esc_html( $error ); ?></li>
		<?php endforeach; ?>
	</ul>
</div>
