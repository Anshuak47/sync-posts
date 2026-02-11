<?php
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------
   REGISTER ROUTE
------------------------------------------------------- */

add_action('rest_api_init', function () {

    register_rest_route('psync/v1', '/receive', [
        'methods'  => 'POST',
        'callback' => 'wp_psynct_receive_post',
        'permission_callback' => '__return_true'
    ]);
});


/* -------------------------------------------------------
   RECEIVE CALLBACK
------------------------------------------------------- */

function wp_psynct_receive_post(WP_REST_Request $request) {

    $settings = get_option(WP_PSYNCT_OPTION, []);

    if (!isset($settings['mode']) || $settings['mode'] !== 'target') {
        return new WP_REST_Response(['message'=>'Target mode not enabled'], 403);
    }

    $headers = $request->get_headers();

    $key    = $headers['x_psync_key'][0] ?? '';
    $sign   = $headers['x_psync_sign'][0] ?? '';
    $domain = $headers['x_psync_domain'][0] ?? '';

    if (!$key || !$sign || !$domain) {
        return new WP_REST_Response(['message'=>'Missing auth headers'], 401);
    }

    if ($key !== ($settings['target_key'] ?? '')) {
        return new WP_REST_Response(['message'=>'Invalid key'], 401);
    }

    $raw_body = $request->get_body();
    $calculated = hash_hmac('sha256', $raw_body, $settings['target_key']);

    if (!hash_equals($calculated, $sign)) {
        return new WP_REST_Response(['message'=>'Signature mismatch'], 403);
    }

    $data = json_decode($raw_body, true);

    if (!$data || empty($data['host_post_id']) || empty($data['title'])) {
        return new WP_REST_Response(['message'=>'Invalid payload'], 400);
    }

    $existing = get_posts([
        'post_type'  => 'post',
        'meta_key'   => '_psync_host_post_id',
        'meta_value' => intval($data['host_post_id']),
        'numberposts'=> 1
    ]);

    $postarr = [
        'post_type'   => 'post',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($data['title']),
        'post_content'=> wp_kses_post($data['content'] ?? ''),
        'post_excerpt'=> sanitize_text_field($data['excerpt'] ?? '')
    ];

    if (!empty($existing)) {
        $postarr['ID'] = $existing[0]->ID;
        $post_id = wp_update_post($postarr);
    } else {
        $post_id = wp_insert_post($postarr);
        update_post_meta($post_id, '_psync_host_post_id', intval($data['host_post_id']));
        update_post_meta($post_id, '_psync_host_domain', sanitize_text_field($domain));
    }

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['message'=>'Save failed'], 500);
    }

    return new WP_REST_Response([
        'status'=>'success',
        'target_post_id'=>$post_id
    ], 200);
}