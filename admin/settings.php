<?php
if (!defined('ABSPATH')) exit;
/* -------------------------------------------------------
   SANITIZE SETTINGS
------------------------------------------------------- */

function wp_psynct_sanitize_settings($input) {

    $output = [];

    $mode = isset($input['mode']) ? sanitize_text_field($input['mode']) : 'host';
    $output['mode'] = in_array($mode, ['host', 'target']) ? $mode : 'host';

    /* ---------------- HOST MODE ---------------- */

    if ($output['mode'] === 'host') {

        $output['targets'] = [];

        if (!empty($input['targets']) && is_array($input['targets'])) {

            foreach ($input['targets'] as $row) {

                $url = isset($row['target_url']) ? esc_url_raw($row['target_url']) : '';
                $existing_key = isset($row['key']) ? sanitize_text_field($row['key']) : '';

                if (!$url) {
                    continue;
                }

                $parsed = wp_parse_url($url);
                $domain = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';

                // Preserve existing key (non-editable)
                if ($existing_key && strlen($existing_key) >= 16) {
                    $key = $existing_key;
                } else {
                    $key = wp_generate_password(32, false, false);
                }

                $output['targets'][] = [
                    'target_url' => $url,
                    'domain'     => $domain,
                    'key'        => $key
                ];
            }
        }

    }

    /* ---------------- TARGET MODE ---------------- */

    if ($output['mode'] === 'target') {

        $allowed = ['French', 'Spanish', 'Hindi'];

        $output['target_key'] = isset($input['target_key'])
            ? sanitize_text_field($input['target_key'])
            : '';

        $lang = isset($input['translation_language'])
            ? sanitize_text_field($input['translation_language'])
            : 'French';

        $output['translation_language'] = in_array($lang, $allowed)
            ? $lang
            : 'French';

        $output['chatgpt_api_key'] = isset($input['chatgpt_api_key'])
            ? sanitize_text_field($input['chatgpt_api_key'])
            : '';
    }

    return $output;
}


/* -------------------------------------------------------
   SETTINGS PAGE
------------------------------------------------------- */

function wp_psynct_render_settings_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = get_option(WP_PSYNCT_OPTION, []);
    $mode = isset($settings['mode']) ? $settings['mode'] : 'host';
    ?>

    <div class="wrap">
        <h1>WP Post Sync Translator</h1>

        <form method="post" action="options.php">
            <?php settings_fields('wp_psynct_group'); ?>

            <table class="form-table">
                <tr>
                    <th>Mode</th>
                    <td>
                        <label>
                        <input type="radio"
       class="psync-mode-radio"
       name="<?php echo WP_PSYNCT_OPTION; ?>[mode]"
       value="host"
       <?php checked($mode,'host'); ?>>
                            Host
                        </label>
                        <br>
                        <label>
                        <input type="radio"
       class="psync-mode-radio"
       name="<?php echo WP_PSYNCT_OPTION; ?>[mode]"
       value="target"
       <?php checked($mode,'target'); ?>>

                            Target
                        </label>
                    </td>
                </tr>
            </table>


            <!-- HOST SECTION -->
            <div id="psync-host-section" class="psync-section">

                <h2>Host Configuration</h2>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Target URL</th>
                            <th>Key</th>
                        </tr>
                    </thead>
                    <tbody id="psynct-target-rows">

                    <?php
                    $targets = $settings['targets'] ?? [];
                    foreach ($targets as $index => $target) :
                    ?>
                        <tr>
                            <td>
                                <input type="url"
                                    name="<?php echo WP_PSYNCT_OPTION; ?>[targets][<?php echo esc_attr($index); ?>][target_url]"
                                    value="<?php echo esc_url($target['target_url']); ?>"
                                    class="regular-text">
                            </td>
                            <td>
                                <input type="text"
                                    readonly
                                    name="<?php echo WP_PSYNCT_OPTION; ?>[targets][<?php echo esc_attr($index); ?>][key]"
                                    value="<?php echo esc_attr($target['key']); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="psynct-add-row">
                        Add New Target
                    </button>
                </p>
            </div>


            <!-- TARGET SECTION -->
            <div id="psync-target-section" class="psync-section">

                <h2>Target Configuration</h2>

                <table class="form-table">
                    <tr>
                        <th>Host Key</th>
                        <td>
                            <input type="text"
                                name="<?php echo WP_PSYNCT_OPTION; ?>[target_key]"
                                value="<?php echo esc_attr($settings['target_key'] ?? ''); ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th>Translation Language</th>
                        <td>
                            <select name="<?php echo WP_PSYNCT_OPTION; ?>[translation_language]">
                                <?php foreach (['French','Spanish','Hindi'] as $lang) : ?>
                                    <option value="<?php echo esc_attr($lang); ?>"
                                        <?php selected($settings['translation_language'] ?? '', $lang); ?>>
                                        <?php echo esc_html($lang); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>ChatGPT API Key</th>
                        <td>
                            <input type="password"
                                name="<?php echo WP_PSYNCT_OPTION; ?>[chatgpt_api_key]"
                                value="<?php echo esc_attr($settings['chatgpt_api_key'] ?? ''); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>

        </form>
    </div>

    <?php
}