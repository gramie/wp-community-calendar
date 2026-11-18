<?php
/**
 * The [community_calendar_submit] page: notices, the director's events and the form.
 *
 * @var string   $notice      Success message (may be empty).
 * @var string[] $errors      Validation errors from the last submit.
 * @var string   $edit_error  Message when the requested event cannot be edited.
 * @var string   $my_events   "Your events" table HTML (may be empty).
 * @var string   $form        The form HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cc-cal cc-submit">
	<?php if ( $notice ) : ?>
		<p class="cc-notice"><?php echo esc_html( $notice ); ?></p>
	<?php endif; ?>
	<?php if ( $errors ) : ?>
		<div class="cc-errors">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<?php if ( $edit_error ) : ?>
		<p class="cc-errors"><?php echo esc_html( $edit_error ); ?></p>
	<?php endif; ?>
	<?php echo $my_events; // phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/my-events.php. ?>
	<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/submit-form.php. ?>
</div>
