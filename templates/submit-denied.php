<?php
/**
 * Shown instead of the submission form to visitors who may not use it.
 *
 * @var string $reason     "login" (not logged in) or "role" (logged in but not an Event Manager).
 * @var string $login_url  Login URL (login reason).
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<?php if ("login" === $reason): ?>
	<p>
		<?php printf /* translators: %s: login link */(
      esc_html__("Please %s to submit events.", "community-calendar"),
      '<a href="' .
          esc_url($login_url) .
          '">' .
          esc_html__("log in", "community-calendar") .
          "</a>",
  ); ?>
	</p>
<?php else: ?>
	<p><?php esc_html_e(
     "Only Event Managers can submit events.",
     "community-calendar",
 ); ?></p>
<?php endif; ?>
