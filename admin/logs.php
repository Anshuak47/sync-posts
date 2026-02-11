<?php
function wp_psynct_render_logs_page() {

    if (!current_user_can('manage_options')) return;

    global $wpdb;

    $table = $wpdb->prefix . 'psync_logs';

    $role   = sanitize_text_field($_GET['role'] ?? '');
    $status = sanitize_text_field($_GET['status'] ?? '');
    $paged  = max(1, intval($_GET['paged'] ?? 1));
    $limit  = 20;
    $offset = ($paged - 1) * $limit;

    $where = "WHERE 1=1";

    if ($role) {
        $where .= $wpdb->prepare(" AND role = %s", $role);
    }

    if ($status) {
        $where .= $wpdb->prepare(" AND status = %s", $status);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");

    $logs = $wpdb->get_results(
        "SELECT * FROM $table $where ORDER BY id DESC LIMIT $limit OFFSET $offset"
    );

    $total_pages = ceil($total / $limit);

    ?>
    <div class="wrap">
        <h1>Sync Logs</h1>

        <form method="get" style="margin-bottom:20px;">
            <input type="hidden" name="page" value="wp-psynct-logs">

            <select name="role">
                <option value="">All Roles</option>
                <option value="host" <?php selected($role,'host'); ?>>Host</option>
                <option value="target" <?php selected($role,'target'); ?>>Target</option>
            </select>

            <select name="status">
                <option value="">All Status</option>
                <option value="success" <?php selected($status,'success'); ?>>Success</option>
                <option value="failed" <?php selected($status,'failed'); ?>>Failed</option>
            </select>

            <button class="button">Filter</button>
        </form>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Host Post</th>
                    <th>Target Post</th>
                    <th>Target URL</th>
                    <th>Status</th>
                    <th>Time (s)</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>

            <?php if (!empty($logs)) : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->role); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->host_post_id); ?></td>
                        <td><?php echo esc_html($log->target_post_id); ?></td>
                        <td><?php echo esc_html($log->target_url); ?></td>
                        <td>
                            <span style="color:<?php echo $log->status === 'success' ? 'green' : 'red'; ?>">
                                <?php echo esc_html($log->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->time_taken); ?></td>
                        <td style="max-width:250px; word-wrap:break-word;">
                            <?php echo esc_html($log->message); ?>
                        </td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="10">No logs found.</td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div style="margin-top:20px;">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a class="button <?php echo $i == $paged ? 'button-primary' : ''; ?>"
                       href="<?php echo esc_url(add_query_arg(['paged'=>$i])); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </div>
    <?php
}
