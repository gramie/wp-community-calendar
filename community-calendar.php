<?php
/**
 * Plugin Name: Community Calendar
 * Description: A community events calendar. Users with the "event manager" role can create and edit their own events (with recurrence, image, district and society pulled from Gravity Forms entries).
 * Version: 1.7.75
 * Requires PHP: 7.4
 * Text Domain: community-calendar
 */

if (!defined("ABSPATH")) {
    exit();
}

define("CC_VERSION", "1.7.75");
define("CC_FILE", __FILE__);
define("CC_DIR", plugin_dir_path(__FILE__));
define("CC_URL", plugin_dir_url(__FILE__));

// Code lives in includes/, markup in templates/, styles and scripts in assets/.
$cc_includes = [
    "helpers", // Meta access, labels, template loader, asset registration.
    "roles", // Event Manager role, capabilities, activation.
    "post-type", // Event post type and admin list columns.
    "gravity-forms", // District / society choices from Gravity Forms entries.
    "settings", // Settings > Community Calendar.
    "recurrence", // Repeat rules, occurrences and event queries.
    "meta-box", // Admin event fields, saving and validation.
    "shortcodes", // [community_calendar] and the single event page.
    "ical", // iCalendar feed.
    "frontend-submit", // [community_calendar_submit] add / edit / delete form.
    "access", // Keeps event managers out of wp-admin; Edit/Delete links.
    "geo", // Event photo, geocoding and OpenStreetMap map.
];
foreach ($cc_includes as $cc_include) {
    require_once CC_DIR . "includes/" . $cc_include . ".php";
}
unset($cc_includes, $cc_include);

register_activation_hook(CC_FILE, "cc_activate");
register_deactivation_hook(CC_FILE, "cc_deactivate");
