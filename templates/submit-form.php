<?php
/**
 * Front-end form to add or edit an event.
 *
 * @var string $heading     "Add an event" or "Edit event".
 * @var string $button      Submit button label.
 * @var string $page        URL of this page (where the form returns to).
 * @var int    $edit_id     ID of the event being edited, or 0.
 * @var string $title       Title field value.
 * @var string $content     Description field value.
 * @var string $fields      The event fields HTML (templates/meta-box-fields.php).
 * @var string $image_html  Current event image <img> markup (may be empty).
 * @var string $max_size    Maximum upload size, e.g. "8 MB".
 * @var string $cancel_url  Cancel link when editing (empty when adding).
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="event-form">
<h3><?php echo esc_html($heading); ?></h3>
<form method="post" enctype="multipart/form-data" class="cc-form">
	<?php wp_nonce_field("cc_front_submit", "cc_front_nonce"); ?>
	<input type="hidden" name="cc_post_id" value="<?php echo (int) $edit_id; ?>">
	<input type="hidden" name="cc_return" value="<?php echo esc_url($page); ?>">

	<p>
		<label for="cc_title"><strong><?php esc_html_e(
      "Title",
      "community-calendar",
  ); ?> *</strong></label><br>
		<input type="text" id="cc_title" name="cc_title" class="cc-title-input" required value="<?php echo esc_attr(
      $title,
  ); ?>">
	</p>

	<p><strong><?php esc_html_e(
     "Description",
     "community-calendar",
 ); ?></strong></p>
	<?php wp_editor($content, "cc_content", [
     "textarea_name" => "cc_content",
     "media_buttons" => false,
     "teeny" => true,
     "textarea_rows" => 8,
 ]); ?>

	<?php echo $fields;
// phpcs:ignore WordPress.Security.EscapeOutput -- built by templates/meta-box-fields.php.
?>

	<p>
		<label for="cc_image"><strong><?php esc_html_e(
      "Event image",
      "community-calendar",
  ); ?></strong></label><br>
		<?php if ($image_html): ?>
			<?php echo $image_html;
      // phpcs:ignore WordPress.Security.EscapeOutput -- core get_the_post_thumbnail() markup.
      ?><br>
			<label><input type="checkbox" name="cc_remove_image" value="1"> <?php esc_html_e(
       "Remove current image",
       "community-calendar",
   ); ?></label><br>
		<?php endif; ?>
		<input type="file" id="cc_image" name="cc_image" accept="image/jpeg,image/png,image/gif,image/webp"><br>
		<span class="cc-meta">
			 /* translators: %s: maximum upload size, e.g. 8 MB */<?php

echo esc_html(
       sprintf(
           __(
               "JPG, PNG, GIF or WebP, up to %s. Uploading a new image replaces the current one.",
               "community-calendar",
           ),
           $max_size,
       ),
   ); ?>
		</span>
	</p>

	<p>
		<button type="submit"><?php echo esc_html($button); ?></button>
		<?php if ($cancel_url): ?>
			<a href="<?php echo esc_url($cancel_url); ?>"><?php esc_html_e(
    "Cancel",
    "community-calendar",
); ?></a>
		<?php endif; ?>
	</p>
</form>
</div>
