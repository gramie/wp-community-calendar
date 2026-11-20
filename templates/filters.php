<?php
/**
 * Calendar filter bar: title search, date range, district and society.
 *
 * @var string $form_action  Page URL the GET form submits to.
 * @var array  $hidden       Extra hidden fields (name => value); needed for plain permalinks.
 * @var array  $f            Current filters: search, from, to.
 * @var array  $selects      Dropdowns: name, label, choices (entry ID => title), selected.
 * @var bool   $is_filtered  Whether any filter is active.
 * @var string $clear_url    URL that clears the filters.
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!$f["from"]) {
    // If no "From" date is specified, start from the current date
    $f["from"] = date("Y-m-d");
}
?>
<form class="cc-filters" method="get" action="<?php echo esc_url(
    $form_action,
); ?>">
	<?php foreach ($hidden as $name => $value): ?>
		<input type="hidden" name="<?php echo esc_attr(
      $name,
  ); ?>" value="<?php echo esc_attr($value); ?>">
	<?php endforeach; ?>
	<input type="search" name="cc_s" maxlength="100" placeholder="<?php esc_attr_e(
     "Search by title",
     "community-calendar",
 ); ?>" aria-label="<?php esc_attr_e(
    "Search by title",
    "community-calendar",
); ?>" value="<?php echo esc_attr($f["search"]); ?>">
	<label><?php esc_html_e(
     "From",
     "community-calendar",
 ); ?> <input type="date" name="cc_from" value="<?php echo esc_attr(
     $f["from"],
 ); ?>"></label>
	<label><?php esc_html_e(
     "To",
     "community-calendar",
 ); ?> <input type="date" name="cc_to" value="<?php echo esc_attr(
     $f["to"],
 ); ?>"></label>
	<div class="cc_filter_selects">
	<?php foreach ($selects as $select): ?>
		<select name="<?php echo esc_attr($select["name"]); ?>"
		        aria-label="<?php echo esc_attr($select["label"]); ?>"
		>
			<option value=""><?php echo esc_html($select["label"]); ?></option>
			<?php foreach ($select["choices"] as $eid => $title): ?>
				<option value="<?php echo (int) $eid; ?>" <?php selected(
    $select["selected"],
    (int) $eid,
); ?>><?php echo esc_html($title); ?></option>
			<?php endforeach; ?>
		</select>
	<?php endforeach; ?>
	</div>
	<button type="submit"><?php esc_html_e(
     "Filter",
     "community-calendar",
 ); ?></button>
	<?php if ($is_filtered): ?>
		<a href="<?php echo esc_url($clear_url); ?>"><?php esc_html_e(
    "Clear",
    "community-calendar",
); ?></a>
	<?php endif; ?>
</form>
