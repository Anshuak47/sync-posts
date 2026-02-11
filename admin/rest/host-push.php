<?php
if (!defined('ABSPATH')) exit;


/* -------------------------------------------------------
   TRIGGER ON POST SAVE
------------------------------------------------------- */

add_action('save_post_post', 'wp_psynct_maybe_push_post', 20, 3);

function wp_psynct_maybe_push_post($post_id, $post, $update) {

    // Prevent autosave / revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Only publish posts
    if ($post->post_status !== 'publish') return;

    $settings = get_option(WP_PSYNCT_OPTION, []);

    // Only in host mode
    if (!isset($settings['mode']) || $settings['mode'] !== 'host') {
        return;
    }

    if (empty($settings['targets'])) {
        return;
    }

    wp_psynct_push_to_targets($post_id, $post, $settings['targets']);
}

function wp_psynct_push_to_targets($post_id, $post, $targets) {

    $payload = [
        'host_post_id' => $post_id,
        'title'        => $post->post_title,
        'content'      => $post->post_content,
        'excerpt'      => $post->post_excerpt,
        'slug'         => $post->post_name,
        'host_domain'  => parse_url(home_url(), PHP_URL_HOST),
    ];

    $json = wp_json_encode($payload);

    foreach ($targets as $target) {

        if (empty($target['target_url']) || empty($target['key'])) {
            continue;
        }

        $endpoint = trailingslashit($target['target_url']) . 'wp-json/psync/v1/receive';

        $signature = hash_hmac('sha256', $json, $target['key']);

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-PSYNC-KEY'    => $target['key'],
                'X-PSYNC-SIGN'   => $signature,
                'X-PSYNC-DOMAIN' => parse_url(home_url(), PHP_URL_HOST),
            ],
            'body' => $json
        ]);

        // Optional basic error handling
        if (is_wp_error($response)) {
            error_log('PSYNC Error: ' . $response->get_error_message());
            continue;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            error_log('PSYNC Failed with code: ' . $code);
        }
    }
}
