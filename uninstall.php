<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

function rrze_autoshare_uninstall_site(): void {
    $optionNames = [
        'rrze_autoshare',
        'rrze_autoshare_bluesky_access_jwt',
        'rrze_autoshare_bluesky_refresh_jwt',
        'rrze_autoshare_bluesky_did',
        'rrze_autoshare_mastodon_client_id',
        'rrze_autoshare_mastodon_client_secret',
        'rrze_autoshare_mastodon_access_token',
        'rrze_autoshare_bluesky_credentials_migrated',
        'rrze_autoshare_bluesky_tokens_migrated',
        'rrze_autoshare_encrypted_service_options_migrated',
    ];
    $scheduledHooks = [
        'rrze_autoshare_bluesky_refresh_token',
        'rrze_autoshare_bluesky_publish_post',
        'rrze_autoshare_mastodon_publish_post',
    ];

    foreach ($scheduledHooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    foreach ($optionNames as $optionName) {
        delete_option($optionName);
    }

    delete_transient('rrze_autoshare_publication_backoff_bluesky');
    delete_transient('rrze_autoshare_publication_backoff_mastodon');

    $userIds = get_users(['fields' => 'ids']);
    foreach ($userIds as $userId) {
        delete_transient('rrze_autoshare_mastodon_oauth_state_' . $userId);
    }
}

if (!is_multisite()) {
    rrze_autoshare_uninstall_site();
    return;
}

$siteIds = get_sites(['fields' => 'ids']);
foreach ($siteIds as $siteId) {
    switch_to_blog($siteId);
    rrze_autoshare_uninstall_site();
    restore_current_blog();
}
