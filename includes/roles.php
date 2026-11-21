<?php
/**
 * Event Manager role, capabilities and plugin activation.
 */

if (!defined("ABSPATH")) {
    exit();
}

/** Role slug that may manage its own events. Filterable: add_filter( 'cc_eventmanager_role', ... ). */
function cc_eventmanager_role()
{
    return apply_filters("cc_eventmanager_role", "eventmanager");
}

function cc_eventmanager_caps()
{
    return [
        "read" => true,
        "upload_files" => true,
        "edit_cc_events" => true,
        "publish_cc_events" => true,
        "edit_published_cc_events" => true,
        "delete_cc_events" => true,
        "delete_published_cc_events" => true,
    ];
}

function cc_setup_roles()
{
    $slug = cc_eventmanager_role();
    $role = get_role($slug);
    if (!$role) {
        $role = add_role($slug, __("Event Manager", "community-calendar"), [
            "read" => true,
        ]);
    }
    if ($role) {
        foreach (array_keys(cc_eventmanager_caps()) as $cap) {
            $role->add_cap($cap);
        }
    }

    // Administrators can manage everyone's events. Event Managers do NOT get the "others" caps,
    // so they can only create/edit/delete events they authored.
    $admin = get_role("administrator");
    if ($admin) {
        $all = array_merge(array_keys(cc_eventmanager_caps()), [
            "edit_others_cc_events",
            "delete_others_cc_events",
            "read_private_cc_events",
            "edit_private_cc_events",
            "delete_private_cc_events",
        ]);
        foreach ($all as $cap) {
            $admin->add_cap($cap);
        }
    }
    update_option("cc_caps_version", CC_VERSION);
}

function cc_activate()
{
    cc_register_post_type();
    cc_setup_roles();
    flush_rewrite_rules();
}

function cc_deactivate()
{
    flush_rewrite_rules();
}

// Re-apply caps if the Event Manager role is created later or the plugin is updated.
add_action(
    "init",
    function () {
        if (
            get_option("cc_caps_version") !== CC_VERSION ||
            !get_role(cc_eventmanager_role())
        ) {
            cc_setup_roles();
        }
    },
    1,
);
