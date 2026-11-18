<?php
/**
 * Month calendar grid.
 *
 * @var string   $prev_url        Previous month link.
 * @var string   $next_url        Next month link.
 * @var string   $month_label     e.g. "November 2026".
 * @var string[] $weekday_labels  Seven column headings, starting on the site's week start.
 * @var array    $weeks           Rows of cells: number, classes, items (url, title, time), more.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cc-nav">
	<a href="<?php echo esc_url( $prev_url ); ?>">&laquo; <?php esc_html_e( 'Previous', 'community-calendar' ); ?></a>
	<strong><?php echo esc_html( $month_label ); ?></strong>
	<a href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'community-calendar' ); ?> &raquo;</a>
</div>
<table class="cc-month">
	<thead>
		<tr>
			<?php foreach ( $weekday_labels as $label ) : ?>
				<th><?php echo esc_html( $label ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $weeks as $week ) : ?>
			<tr>
				<?php foreach ( $week as $cell ) : ?>
					<td class="<?php echo esc_attr( $cell['classes'] ); ?>">
						<span class="cc-num"><?php echo (int) $cell['number']; ?></span>
						<?php foreach ( $cell['items'] as $item ) : ?>
							<a class="cc-item" href="<?php echo esc_url( $item['url'] ); ?>" title="<?php echo esc_attr( $item['time'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						<?php endforeach; ?>
						<?php if ( $cell['more'] > 0 ) : ?>
							<?php /* translators: %d: number of additional events */ ?>
							<span class="cc-meta"><?php echo esc_html( sprintf( __( '+%d more', 'community-calendar' ), $cell['more'] ) ); ?></span>
						<?php endif; ?>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
