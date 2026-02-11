<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------
   REGISTER ROUTE
------------------------------------------------------- */

add_action('rest_api_init', function () {

    register_rest_route('psync/v1', '/receive', [
        'methods'  => 'POST',
        'callback' => 'wp_psynct_receive_post',
        'permission_callback' => '__return_true',
        'args' => [
            'host_post_id' => [
                'required' => true,
                'type'     => 'integer'
            ],
            'title' => [
                'required' => true,
                'type'     => 'string'
            ],
            'content' => [
                'required' => true,
                'type'     => 'string'
            ],
            'excerpt' => [
                'required' => false,
                'type'     => 'string'
            ],
            'slug' => [
                'required' => true,
                'type'     => 'string'
            ],
            'host_domain' => [
                'required' => true,
                'type'     => 'string'
            ],
        ]
    ]);


    // Disconnect target
    

    register_rest_route('psync/v1', '/disconnect', [
        'methods'  => 'POST',
        'callback' => 'wp_psynct_disconnect_target',
        'permission_callback' => '__return_true'
    ]);

});


/* -------------------------------------------------------
   RECEIVE CALLBACK
------------------------------------------------------- */

function wp_psynct_receive_post(WP_REST_Request $request) {
    $start_time = microtime(true);
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
        wp_psynct_log([
            'role' => 'target',
            'action' => 'receive',
            'status' => 'failed',
            'message' => 'Invalid key'
        ]);

        return new WP_REST_Response(['message'=>'Invalid key'], 401);
    }

    $raw_body = $request->get_body();
    $calculated = hash_hmac('sha256', $raw_body, $settings['target_key']);

    if (!hash_equals($calculated, $sign)) {
        return new WP_REST_Response(['message'=>'Signature mismatch'], 403);
    }

    $data = json_decode($raw_body, true);

    $host_domain = strtolower(
        preg_replace('/^www\./', '', sanitize_text_field($data['host_domain']))
    );
    
    if (!$data || empty($data['host_post_id']) || empty($data['title'])) {
        return new WP_REST_Response(['message'=>'Invalid payload'], 400);
    }

    $existing = get_posts([
        'post_type' => 'post',
        'numberposts' => 1,
        'meta_query' => [
            [
                'key'   => '_psync_host_post_id',
                'value' => intval($data['host_post_id'])
            ],
            [
                'key'   => '_psync_host_domain',
                'value' => $host_domain
            ]
        ]
    ]);
    /* ---------------- TRANSLATION ---------------- */

    $original_content = $data['content'] ?? '';
    $translated_content = $original_content;
    
    $language = $settings['translation_language'] ?? '';
    $api_key  = $settings['chatgpt_api_key'] ?? '';
    
    if (!empty($language) && !empty($api_key) && !empty($original_content)) {
    
        $translation_result = wp_psynct_translate_content(
            $original_content,
            $language,
            $api_key
        );
        if (is_wp_error($translation_result)) {
    
            wp_psynct_log([
                'role' => 'target',
                'action' => 'translation',
                'host_post_id' => intval($data['host_post_id']),
                'status' => 'failed',
                'message' => $translation_result->get_error_message()
            ]);
    
            return new WP_REST_Response([
                'message' => 'Translation failed'
            ], 500);
        }
    
        $translated_content = $translation_result;
    }
    
    $postarr = [
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_title'   => sanitize_text_field($data['title']),
        'post_content' => $translated_content,
        'post_excerpt' => sanitize_text_field($data['excerpt']),
        'post_name' => sanitize_title($data['slug']),
    ];

    if (!empty($existing)) {
        $postarr['ID'] = $existing[0]->ID;
        $post_id = wp_update_post($postarr);

    } else {
        $post_id = wp_insert_post($postarr);
        update_post_meta($post_id, '_psync_host_post_id', intval($data['host_post_id']));
        update_post_meta($post_id, '_psync_host_domain', sanitize_text_field($data['host_domain']));

    }

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['message'=>'Save failed'], 500);
    }
    /* ---------------- TAXONOMY SYNC ---------------- */

    if (!is_wp_error($post_id)) {

        /* Categories */
        if (!empty($data['categories']) && is_array($data['categories'])) {

            $category_ids = [];

            foreach ($data['categories'] as $cat_name) {

                $cat_name = sanitize_text_field($cat_name);

                if (!$cat_name) continue;

                $term = term_exists($cat_name, 'category');

                if (!$term) {
                    $term = wp_insert_term($cat_name, 'category');
                }

                if (!is_wp_error($term)) {
                    $category_ids[] = is_array($term) ? $term['term_id'] : $term;
                }
            }

            if (!empty($category_ids)) {
                wp_set_post_terms($post_id, $category_ids, 'category');
            }
        }

        /* Tags */
        if (!empty($data['tags']) && is_array($data['tags'])) {

            $tag_ids = [];

            foreach ($data['tags'] as $tag_name) {

                $tag_name = sanitize_text_field($tag_name);

                if (!$tag_name) continue;

                $term = term_exists($tag_name, 'post_tag');

                if (!$term) {
                    $term = wp_insert_term($tag_name, 'post_tag');
                }

                if (!is_wp_error($term)) {
                    $tag_ids[] = is_array($term) ? $term['term_id'] : $term;
                }
            }

            if (!empty($tag_ids)) {
                wp_set_post_terms($post_id, $tag_ids, 'post_tag');
            }
        }
    }

    /* ---------------- FEATURED IMAGE SYNC ---------------- */

    if (!empty($data['featured_image']) && filter_var($data['featured_image'], FILTER_VALIDATE_URL)) {

        $image_url = esc_url_raw($data['featured_image']);

        // Prevent duplicate downloads
        $existing_image = get_post_meta($post_id, '_psync_featured_image_source', true);

        if ($existing_image !== $image_url) {

            // Download and attach image
            $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');

            if (!is_wp_error($attachment_id)) {

                set_post_thumbnail($post_id, $attachment_id);

                // Store source URL to prevent re-download
                update_post_meta($post_id, '_psync_featured_image_source', $image_url);
            }
        }
    }

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['message'=>'Save failed'], 500);
    }
    $time_taken = round(microtime(true) - $start_time, 4);

    wp_psynct_log([
        'role'           => 'target',
        'action'         => 'receive',
        'host_post_id'   => intval($data['host_post_id']),
        'target_post_id' => $post_id,
        'status'         => 'success',
        'message'        => 'Post synced successfully',
        'time_taken'     => $time_taken
    ]);
    
    return new WP_REST_Response([
        'status'=>'success',
        'target_post_id'=>$post_id
    ],200);
}

function wp_psynct_translate_gutenberg_content($content, $language, $api_key) {

    $blocks = parse_blocks($content);

    if (empty($blocks)) {
        return $content;
    }

    foreach ($blocks as &$block) {

        // Only translate blocks with inner HTML
        if (!empty($block['innerHTML'])) {

            $translated = wp_psynct_translate_content(
                $block['innerHTML'],
                $language,
                $api_key
            );

            if (is_wp_error($translated)) {
                return $translated;
            }

            $block['innerHTML'] = $translated;
        }

        // Recursively handle nested blocks
        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = wp_psynct_translate_nested_blocks(
                $block['innerBlocks'],
                $language,
                $api_key
            );
        }
    }

    return serialize_blocks($blocks);
}

function wp_psynct_translate_nested_blocks($blocks, $language, $api_key) {

    foreach ($blocks as &$block) {

        if (!empty($block['innerHTML'])) {

           $translated = wp_psynct_translate_content(
                $block['innerHTML'],
                $language,
                $api_key
            );

            if (is_wp_error($translated)) {
                return $translated;
            }

            $block['innerHTML'] = $translated;
        }

        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = wp_psynct_translate_nested_blocks(
                $block['innerBlocks'],
                $language,
                $api_key
            );
        }
    }

    return $blocks;
}


function wp_psynct_translate_chunk($chunk, $language, $api_key) {

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ],
        'body' => wp_json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a strict HTML translator. 
                    Return ONLY valid HTML.
                    Do NOT use markdown.
                    Do NOT use backticks.
                    Do NOT wrap output in ``` blocks.
                    Do NOT convert quotes to smart quotes.
                    Preserve all existing HTML tags exactly as they appear.'
                ],
                [
                    'role' => 'user',
                    'content' => "Translate the following HTML content to {$language}. 
                    Return only the translated HTML content with the same structure.\n\n{$chunk}"
                ]
            ],
            'temperature' => 0.2
        ])
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    error_log('OpenAI raw response: ' . $body);
    if (!empty($decoded['error'])) {
        return new WP_Error(
            'translation_failed',
            $decoded['error']['message']
        );
    }
    
    if (empty($decoded['choices'][0]['message']['content'])) {
        return new WP_Error('translation_failed', 'Empty translation response');
    }

    $content = trim($decoded['choices'][0]['message']['content']);

    // Remove accidental ```html wrappers
    $content = preg_replace('/^```html/i', '', $content);
    $content = preg_replace('/^```/i', '', $content);
    $content = preg_replace('/```$/i', '', $content);
    
    // // Normalize smart quotes back to standard quotes
    // $content = str_replace(
    //     ['“','”','‘','’'],
    //     ['"','"',"'", "'"],
    //     $content
    // );

    $content = str_replace('`html`', 'html', $content);
    $content = str_replace('`', '', $content);
    
    return trim($content);
    
}

function wp_psynct_split_content_into_chunks($content, $size = 2500) {

    $chunks = [];
    $length = strlen($content);

    for ($i = 0; $i < $length; $i += $size) {
        $chunks[] = substr($content, $i, $size);
    }

    return $chunks;
}


function wp_psynct_translate_content($content, $language, $api_key) {

    $chunks = wp_psynct_split_content_into_chunks($content, 2500);

    $translated = '';

    foreach ($chunks as $chunk) {

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a strict HTML translator.
                            Return ONLY valid HTML.
                            Do NOT use markdown.
                            Do NOT use backticks.
                            Preserve all HTML tags exactly.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Translate the following HTML content to {$language} and return only translated HTML:\n\n{$chunk}"
                    ]
                ],
                'temperature' => 0.2
            ])
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!empty($decoded['error'])) {
            return new WP_Error('translation_failed', $decoded['error']['message']);
        }

        if (empty($decoded['choices'][0]['message']['content'])) {
            return new WP_Error('translation_failed', 'Empty translation response');
        }

        $piece = trim($decoded['choices'][0]['message']['content']);

        // Cleanup
        $piece = preg_replace('/^```html/i', '', $piece);
        $piece = preg_replace('/^```/i', '', $piece);
        $piece = preg_replace('/```$/i', '', $piece);
        $piece = str_replace('`', '', $piece);

        $translated .= trim($piece);
    }

    return $translated;
}


function wp_psynct_disconnect_target(WP_REST_Request $request) {

    $settings = get_option(WP_PSYNCT_OPTION, []);

    if (($settings['mode'] ?? '') !== 'target') {
        return new WP_REST_Response(['message' => 'Not in target mode'], 403);
    }

    $headers = $request->get_headers();

    $key  = $headers['x_psync_key'][0] ?? '';
    $sign = $headers['x_psync_sign'][0] ?? '';
    $domain = $headers['x_psync_domain'][0] ?? '';

    if (!$key || !$sign || !$domain) {
        return new WP_REST_Response(['message' => 'Missing headers'], 401);
    }

    if ($key !== ($settings['target_key'] ?? '')) {
        return new WP_REST_Response(['message' => 'Invalid key'], 401);
    }

    $raw_body = $request->get_body();
    $calc = hash_hmac('sha256', $raw_body, $settings['target_key']);

    if (!hash_equals($calc, $sign)) {
        return new WP_REST_Response(['message' => 'Signature mismatch'], 403);
    }

    // Clear connection
    $settings['target_key'] = '';
    update_option(WP_PSYNCT_OPTION, $settings);

    wp_psynct_log([
        'role' => 'target',
        'action' => 'disconnect',
        'status' => 'success',
        'message' => 'Connection removed by host'
    ]);

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Disconnected successfully'
    ], 200);
}
