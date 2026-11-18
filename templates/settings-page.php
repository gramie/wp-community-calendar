<?php
/**
 * Settings > Community Calendar.
 *
 * @var bool  $gf_active  Whether Gravity Forms is active.
 * @var array $forms      Gravity Forms forms (id, title).
 * @var array $types      Label for each list: district, society.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Community Calendar', 'community-calendar' ); ?></h1>
	<?php if ( ! $gf_active ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Gravity Forms is not active. District and society lists will be empty until it is.', 'community-calendar' ); ?></p></div>
	<?php endif; ?>
	<p><?php esc_html_e( 'Choose the Gravity Forms whose entries supply the district and society choices, and the ID of the field holding each entry\'s title (e.g. 1, or 1.3 for a sub-field such as a Name\'s first name).', 'community-calendar' ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'cc_settings' ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( $types as $type => $label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<select name="cc_<?php echo esc_attr( $type ); ?>_form">
							<option value=""><?php esc_html_e( '— Select form —', 'community-calendar' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<option value="<?php echo (int) $form['id']; ?>" <?php selected( (int) get_option( "cc_{$type}_form" ), (int) $form['id'] ); ?>>
									<?php echo esc_html( $form['title'] . ' (#' . $form['id'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						&nbsp;<?php esc_html_e( 'Title field ID:', 'community-calendar' ); ?>
						<input type="text" size="6" name="cc_<?php echo esc_attr( $type ); ?>_field" value="<?php echo esc_attr( get_option( "cc_{$type}_field", '1' ) ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="cc_geo_suffix"><?php esc_html_e( 'Map search area', 'community-calendar' ); ?></label></th>
				<td>
					<input type="text" id="cc_geo_suffix" name="cc_geo_suffix" class="regular-text" value="<?php echo esc_attr( get_option( 'cc_geo_suffix', '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Québec, Canada', 'community-calendar' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. Added to each event location when looking it up on OpenStreetMap, so "Town Hall" finds yours and not one on another continent.', 'community-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Submission page', 'community-calendar' ); ?></th>
				<td>
					<?php
					wp_dropdown_pages( array(
						'name'              => 'cc_submit_page',
						'selected'          => absint( get_option( 'cc_submit_page' ) ),
						'show_option_none'  => __( '— Auto-detect —', 'community-calendar' ),
						'option_none_value' => 0,
					) );
					?>
					<p class="description"><?php esc_html_e( 'The page containing [community_calendar_submit]. Directors are sent here instead of the WordPress admin, and Edit/Delete links on events point here.', 'community-calendar' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<h2><?php esc_html_e( 'Shortcodes', 'community-calendar' ); ?></h2>
	<p>
		<code>[community_calendar]</code> &mdash; <?php esc_html_e( 'upcoming list.', 'community-calendar' ); ?>
		<code>[community_calendar view="month"]</code> &mdash; <?php esc_html_e( 'month grid.', 'community-calendar' ); ?>
		<code>[community_calendar_submit]</code> &mdash; <?php esc_html_e( 'form for directors to add, edit and delete their events.', 'community-calendar' ); ?>
	</p>
	<p><?php esc_html_e( 'Calendar options: view="list|month" (the view shown first), switcher="yes|no" (let visitors switch views), days="90" (list horizon), filters="yes|no", ical="yes|no", type="SLUG_OR_ID", district="ENTRY_ID", society="ENTRY_ID".', 'community-calendar' ); ?></p>
	<p>
		<?php
		printf(
			/* translators: %s: link to the Event Types screen */
			esc_html__( 'Event types are managed under %s.', 'community-calendar' ),
			'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=cc_event_type&post_type=cc_event' ) ) . '">' . esc_html__( 'Calendar → Event Types', 'community-calendar' ) . '</a>'
		);
		?>
	</p>
</div>
