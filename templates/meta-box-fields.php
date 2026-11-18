<?php
/**
 * Event fields (admin meta box and front-end form).
 *
 * @var int   $post_id    Event ID (0 for a new event).
 * @var bool  $front      True on the front-end form (no nonce, no sidebar hint).
 * @var string $freq      Repeat frequency: "", daily, weekly, monthly, monthly_nth, yearly.
 * @var int[] $weekdays   Selected ISO weekdays for weekly repeats.
 * @var int   $nth        Which week for "monthly on the…": 1-4 or -1 (last).
 * @var int   $nth_day    ISO weekday for "monthly on the…".
 * @var array $districts  District choices (entry ID => title).
 * @var array $societies  Society choices (entry ID => title).
 * @var int   $sel_d      Selected district entry ID.
 * @var int   $sel_s      Selected society entry ID.
 * @var array $types      Event type choices (term ID => name).
 * @var int[] $sel_types Selected event type term IDs.
 * @var array $v          Field values: start, end, interval, until, location, cost, website, email, phone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( ! $front ) {
	wp_nonce_field( 'cc_save_event', 'cc_nonce' );
} ?>
<table class="cc-table">
	<tr><th><label for="cc_start"><?php esc_html_e( 'Starts', 'community-calendar' ); ?> *</label></th>
		<td><input type="datetime-local" id="cc_start" name="cc[start]" value="<?php echo esc_attr( $v['start'] ); ?>" required></td></tr>
	<tr><th><label for="cc_end"><?php esc_html_e( 'Ends', 'community-calendar' ); ?></label></th>
		<td><input type="datetime-local" id="cc_end" name="cc[end]" value="<?php echo esc_attr( $v['end'] ); ?>"></td></tr>

	<tr><th><label for="cc_freq"><?php esc_html_e( 'Repeats', 'community-calendar' ); ?></label></th>
		<td>
			<select id="cc_freq" name="cc[freq]" class="cc-auto">
				<option value=""><?php esc_html_e( 'Does not repeat', 'community-calendar' ); ?></option>
				<option value="daily" <?php selected( $freq, 'daily' ); ?>><?php esc_html_e( 'Daily', 'community-calendar' ); ?></option>
				<option value="weekly" <?php selected( $freq, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'community-calendar' ); ?></option>
				<option value="monthly" <?php selected( $freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly (same date)', 'community-calendar' ); ?></option>
				<option value="monthly_nth" <?php selected( $freq, 'monthly_nth' ); ?>><?php esc_html_e( 'Monthly on the… (e.g. first Monday, last Tuesday)', 'community-calendar' ); ?></option>
				<option value="yearly" <?php selected( $freq, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'community-calendar' ); ?></option>
			</select>
		</td></tr>
	<tr class="cc-rec"><th><label for="cc_interval"><?php esc_html_e( 'Repeat every', 'community-calendar' ); ?></label></th>
		<td><input type="number" min="1" max="99" id="cc_interval" name="cc[interval]" class="cc-narrow" value="<?php echo esc_attr( $v['interval'] ); ?>">
			<span id="cc_unit"></span></td></tr>
	<tr class="cc-rec cc-weekly"><th><?php esc_html_e( 'On', 'community-calendar' ); ?></th>
		<td class="cc-weekdays">
			<?php foreach ( cc_weekday_names() as $n => $name ) : ?>
				<label><input type="checkbox" name="cc[weekdays][]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, $weekdays, true ) ); ?>> <?php echo esc_html( $name ); ?></label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Leave empty to repeat on the start date\'s weekday.', 'community-calendar' ); ?></p>
		</td></tr>
	<tr class="cc-rec cc-nth"><th><?php esc_html_e( 'On the', 'community-calendar' ); ?></th>
		<td>
			<select name="cc[nth]" class="cc-auto" aria-label="<?php esc_attr_e( 'Which week', 'community-calendar' ); ?>">
				<?php foreach ( cc_nth_labels() as $val => $label ) : ?>
					<option value="<?php echo (int) $val; ?>" <?php selected( $nth, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="cc[nth_day]" class="cc-auto" aria-label="<?php esc_attr_e( 'Which weekday', 'community-calendar' ); ?>">
				<?php for ( $d = 1; $d <= 7; $d++ ) : ?>
					<option value="<?php echo (int) $d; ?>" <?php selected( $nth_day, $d ); ?>><?php echo esc_html( cc_weekday_full( $d ) ); ?></option>
				<?php endfor; ?>
			</select>
			<?php esc_html_e( 'of the month', 'community-calendar' ); ?>
			<p class="description"><?php esc_html_e( 'The start date moves to the first date that matches.', 'community-calendar' ); ?></p>
		</td></tr>
	<tr class="cc-rec"><th><label for="cc_until"><?php esc_html_e( 'Repeat until', 'community-calendar' ); ?></label></th>
		<td><input type="date" id="cc_until" name="cc[until]" value="<?php echo esc_attr( $v['until'] ); ?>">
			<p class="description"><?php esc_html_e( 'Repeats run for at most one year after the start date. Leave empty to repeat for the full year.', 'community-calendar' ); ?></p></td></tr>

	<tr><th><label for="cc_location"><?php esc_html_e( 'Location', 'community-calendar' ); ?></label></th>
		<td><input type="text" id="cc_location" name="cc[location]" value="<?php echo esc_attr( $v['location'] ); ?>"></td></tr>
	<tr><th><label for="cc_cost"><?php esc_html_e( 'Cost', 'community-calendar' ); ?></label></th>
		<td><input type="text" id="cc_cost" name="cc[cost]" placeholder="<?php esc_attr_e( 'Free, $10, $5–$15…', 'community-calendar' ); ?>" value="<?php echo esc_attr( $v['cost'] ); ?>"></td></tr>
	<tr><th><label for="cc_website"><?php esc_html_e( 'Website', 'community-calendar' ); ?></label></th>
		<td><input type="url" id="cc_website" name="cc[website]" placeholder="https://" value="<?php echo esc_attr( $v['website'] ); ?>"></td></tr>
	<tr><th><label for="cc_email"><?php esc_html_e( 'Organizer email', 'community-calendar' ); ?></label></th>
		<td><input type="email" id="cc_email" name="cc[email]" value="<?php echo esc_attr( $v['email'] ); ?>"></td></tr>
	<tr><th><label for="cc_phone"><?php esc_html_e( 'Organizer telephone', 'community-calendar' ); ?></label></th>
		<td><input type="tel" id="cc_phone" name="cc[phone]" value="<?php echo esc_attr( $v['phone'] ); ?>"></td></tr>

	<?php if ( $types ) : ?>
	<tr><th><label for="cc_types"><?php esc_html_e( 'Event types', 'community-calendar' ); ?></label></th>
		<td><select id="cc_types" name="cc[types][]" multiple size="<?php echo (int) min( 8, max( 3, count( $types ) ) ); ?>">
			<?php foreach ( $types as $tid => $tname ) : ?>
				<option value="<?php echo (int) $tid; ?>" <?php selected( in_array( (int) $tid, $sel_types, true ) ); ?>><?php echo esc_html( $tname ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Select one or more. Hold Ctrl (⌘ on Mac) to choose several.', 'community-calendar' ); ?></p></td></tr>
	<?php endif; ?>
	<tr><th><label for="cc_district"><?php esc_html_e( 'District', 'community-calendar' ); ?></label></th>
		<td><select id="cc_district" name="cc[district]">
			<option value=""><?php esc_html_e( '— None —', 'community-calendar' ); ?></option>
			<?php foreach ( $districts as $eid => $title ) : ?>
				<option value="<?php echo (int) $eid; ?>" <?php selected( $sel_d, $eid ); ?>><?php echo esc_html( $title ); ?></option>
			<?php endforeach; ?>
		</select></td></tr>
	<tr><th><label for="cc_society"><?php esc_html_e( 'Society', 'community-calendar' ); ?></label></th>
		<td><select id="cc_society" name="cc[society]">
			<option value=""><?php esc_html_e( '— None —', 'community-calendar' ); ?></option>
			<?php foreach ( $societies as $eid => $title ) : ?>
				<option value="<?php echo (int) $eid; ?>" <?php selected( $sel_s, $eid ); ?>><?php echo esc_html( $title ); ?></option>
			<?php endforeach; ?>
		</select></td></tr>
</table>
<?php if ( ! $front ) : ?>
	<p class="description"><?php esc_html_e( 'Use the "Event image" box in the sidebar to upload the event image.', 'community-calendar' ); ?></p>
<?php endif; ?>
