<?php
/**
 * Links that switch the calendar between the list view and the calendar (month) view.
 *
 * @var array $views  Each: label, url, current (bool).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav class="cc-views" aria-label="<?php esc_attr_e( 'Calendar view', 'community-calendar' ); ?>">
	<?php foreach ( $views as $view ) : ?>
		<a href="<?php echo esc_url( $view['url'] ); ?>" class="<?php echo $view['current'] ? 'is-current' : ''; ?>" <?php echo $view['current'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $view['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
