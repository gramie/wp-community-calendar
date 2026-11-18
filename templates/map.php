<?php
/**
 * OpenStreetMap map (or a search link when the location could not be found).
 *
 * @var string $mode        "embed" or "search".
 * @var string $embed_url   OpenStreetMap embed URL (embed mode).
 * @var string $large_url   Link to the full map (embed mode).
 * @var string $search_url  OpenStreetMap search link (search mode).
 * @var string $title       Accessible title of the map (embed mode).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( 'search' === $mode ) : ?>
	<p class="cc-map-wrap"><a href="<?php echo esc_url( $search_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Find this location on OpenStreetMap', 'community-calendar' ); ?></a></p>
<?php else : ?>
	<div class="cc-map-wrap">
		<iframe class="cc-map" title="<?php echo esc_attr( $title ); ?>" src="<?php echo esc_url( $embed_url ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
		<p class="cc-meta">
			<a href="<?php echo esc_url( $large_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View larger map', 'community-calendar' ); ?></a>
			&middot; &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> <?php esc_html_e( 'contributors', 'community-calendar' ); ?>
		</p>
	</div>
<?php endif; ?>
