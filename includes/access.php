<?php
/**
 * Event Managers work on the front end only: keeps them out of wp-admin and provides Edit/Delete links.
 */

if (!defined("ABSPATH")) {
    exit();
}

/** True for users who should use the front end instead of wp-admin. Filter: cc_frontend_only_user. */
function cc_is_frontend_only_user($user = null)
{
    $user = $user ? $user : wp_get_current_user();
    if (!$user || !$user->exists()) {
        return false;
    }
    $result =
        user_can($user, "edit_cc_events") &&
        !user_can($user, "edit_others_cc_events") &&
        !user_can($user, "manage_options");
    return (bool) apply_filters("cc_frontend_only_user", $result, $user);
}

/** URL of the submission page: the one chosen in settings, else the first page containing the shortcode. */
function cc_submit_page_url()
{
    $id = absint(get_option("cc_submit_page"));
    if (!$id) {
        $id = get_transient("cc_submit_page_auto");
        if (false === $id) {
            global $wpdb;
            $id = (int) $wpdb->get_var(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ( 'page', 'post' ) AND post_content LIKE '%[community\\_calendar\\_submit%' ORDER BY ID ASC LIMIT 1",
            );
            set_transient("cc_submit_page_auto", $id, 10 * MINUTE_IN_SECONDS);
        }
    }
    return $id ? (string) get_permalink((int) $id) : "";
}

add_action("admin_init", function () {
    if (wp_doing_ajax() || !cc_is_frontend_only_user()) {
        return;
    }
    $allowed = apply_filters("cc_frontend_only_allowed_pages", [
        "admin-post.php",
        "async-upload.php",
        "admin-ajax.php",
    ]);
    if (
        isset($GLOBALS["pagenow"]) &&
        in_array($GLOBALS["pagenow"], $allowed, true)
    ) {
        return;
    }
    $url = cc_submit_page_url();
    wp_safe_redirect($url ? $url : home_url("/"));
    exit();
});

add_filter("show_admin_bar", function ($show) {
    return cc_is_frontend_only_user() ? false : $show;
});

add_filter(
    "login_redirect",
    function ($redirect, $requested, $user) {
        if ($user instanceof WP_User && cc_is_frontend_only_user($user)) {
            $url = cc_submit_page_url();
            if ($url) {
                return $url;
            }
        }
        return $redirect;
    },
    10,
    3,
);

// Any "Edit" link WordPress or the theme generates for an event goes to the front-end form.
add_filter(
    "get_edit_post_link",
    function ($link, $post_id) {
        if (
            "cc_event" === get_post_type($post_id) &&
            cc_is_frontend_only_user()
        ) {
            $url = cc_submit_page_url();
            if ($url) {
                return add_query_arg("cc_edit", (int) $post_id, $url);
            }
        }
        return $link;
    },
    10,
    2,
);

/** "Edit | Delete" links shown to the people allowed to change an event. */
function cc_owner_links($post_id)
{
    if (!is_user_logged_in() || !current_user_can("edit_post", $post_id)) {
        return "";
    }
    $url = cc_submit_page_url();
    if (!$url) {
        return "";
    }
    return cc_get_template("owner-links", [
        "edit_url" => add_query_arg("cc_edit", (int) $post_id, $url),
        "delete_url" => current_user_can("delete_post", $post_id)
            ? wp_nonce_url(
                add_query_arg("cc_delete", (int) $post_id, $url),
                "cc_delete_" . (int) $post_id,
            )
            : "",
    ]);
}
