<?php
/**
 * The director's own events, with Edit / View / Delete actions.
 *
 * @var array $rows  Events: title, when, status, edit_url, view_url, delete_url.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cc-my">
	<h3><?php esc_html_e( 'Your events', 'community-calendar' ); ?></h3>
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'community-calendar' ); ?></th>
				<th><?php esc_html_e( 'Starts', 'community-calendar' ); ?></th>
				<th><?php esc_html_e( 'Status', 'community-calendar' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['title'] ); ?></td>
					<td><?php echo esc_html( $row['when'] ); ?></td>
					<td><?php echo esc_html( $row['status'] ); ?></td>
					<td>
						<?php if ( $row['edit_url'] ) : ?>
							<a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'community-calendar' ); ?></a>
						<?php endif; ?>
						<?php if ( $row['view_url'] ) : ?>
							&middot; <a href="<?php echo esc_url( $row['view_url'] ); ?>"><?php esc_html_e( 'View', 'community-calendar' ); ?></a>
						<?php endif; ?>
						<?php if ( $row['delete_url'] ) : ?>
							&middot; <a href="<?php echo esc_url( $row['delete_url'] ); ?>" data-cc-confirm="<?php esc_attr_e( 'Move this event to the trash?', 'community-calendar' ); ?>"><?php esc_html_e( 'Delete', 'community-calendar' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
