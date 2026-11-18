<?php
/**
 * Links to subscribe to the iCal feed or download it.
 *
 * @var string $ical_url    Feed URL.
 * @var string $webcal_url  Same URL with the webcal:// scheme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="cc-subscribe cc-meta">
	<a href="<?php echo esc_url( $webcal_url ); ?>"><?php esc_html_e( 'Subscribe to this calendar', 'community-calendar' ); ?></a>
	&middot;
	<a href="<?php echo esc_url( $ical_url ); ?>"><?php esc_html_e( 'Download .ics', 'community-calendar' ); ?></a>
</p>
