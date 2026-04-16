<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'protable_process_admin_actions');
function protable_process_admin_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'protable') return;
    $tables = get_option('protable_tables', array());

    if (isset($_GET['refresh_all'])) {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_protable_%' OR option_name LIKE '_transient_timeout_protable_%'");
        wp_safe_redirect(admin_url('admin.php?page=protable&all_cleared=1'));
        exit;
    }

    if (isset($_GET['clear_cache']) && isset($_GET['id'])) {
        $tid = intval($_GET['id']);
        if (isset($tables[$tid])) {
            delete_transient('protable_' . md5($tables[$tid]['sheet_id'] . $tables[$tid]['range']));
            wp_safe_redirect(admin_url('admin.php?page=protable&cache_cleared=1'));
            exit;
        }
    }

    if (isset($_GET['delete_table'])) {
        unset($tables[$_GET['delete_table']]);
        update_option('protable_tables', $tables);
        wp_safe_redirect(admin_url('admin.php?page=protable'));
        exit;
    }

    if (isset($_POST['save_table_entry'])) {
        $id_to_save = !empty($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
        if (!$id_to_save) {
            $numeric_keys = array_filter(array_keys($tables), 'is_numeric');
            $id_to_save = !empty($numeric_keys) ? max($numeric_keys) + 1 : 1;
        }

        $tables[$id_to_save] = array(
            'name'       => sanitize_text_field($_POST['table_name']),
            'sheet_id'   => sanitize_text_field($_POST['sheet_id']),
            'range'      => sanitize_text_field($_POST['range']),
            'cache_time' => intval($_POST['cache_time']),
            'cache_unit' => sanitize_text_field($_POST['cache_unit']),
            'g_bg' => sanitize_hex_color($_POST['g_bg']), 'g_font' => intval($_POST['g_font']), 'g_txt' => sanitize_hex_color($_POST['g_txt']), 'g_align' => sanitize_text_field($_POST['g_align']), 'g_valign' => sanitize_text_field($_POST['g_valign']), 'g_b_clr' => sanitize_hex_color($_POST['g_b_clr']), 'g_b_thk' => intval($_POST['g_b_thk']),
            'b_bg' => sanitize_hex_color($_POST['b_bg']), 'b_font' => intval($_POST['b_font']), 'b_txt' => sanitize_hex_color($_POST['b_txt']), 'b_align' => sanitize_text_field($_POST['b_align']), 'b_valign' => sanitize_text_field($_POST['b_valign']), 'b_b_clr' => sanitize_hex_color($_POST['b_b_clr']), 'b_b_thk' => intval($_POST['b_b_thk']),
            // Highlight Save Logic
            'hi_en'  => isset($_POST['hi_en']) ? 1 : 0,
            'hi_bg'  => sanitize_hex_color($_POST['hi_bg']),
            'hi_txt' => sanitize_hex_color($_POST['hi_txt']),
            // Border & Panes
            't_b_en'  => isset($_POST['t_b_en']) ? 1 : 0,
            't_b_thk' => intval($_POST['t_b_thk']),
            't_b_clr' => sanitize_hex_color($_POST['t_b_clr']),
            't_b_rad' => intval($_POST['t_b_rad']),
            't_b_shd' => sanitize_text_field($_POST['t_b_shd']),
            'h_en' => isset($_POST['h_en']), 'h_rows' => intval($_POST['h_rows']), 'h_bg' => sanitize_hex_color($_POST['h_bg']), 'h_txt' => sanitize_hex_color($_POST['h_txt']), 'h_font' => intval($_POST['h_font']), 'h_align' => sanitize_text_field($_POST['h_align']), 'h_valign' => sanitize_text_field($_POST['h_valign']), 'h_b_clr' => sanitize_hex_color($_POST['h_b_clr']), 'h_b_thk' => intval($_POST['h_b_thk']),
            'f_en' => isset($_POST['f_en']), 'f_rows' => intval($_POST['f_rows']), 'f_bg' => sanitize_hex_color($_POST['f_bg']), 'f_txt' => sanitize_hex_color($_POST['f_txt']), 'f_font' => intval($_POST['f_font']), 'f_align' => sanitize_text_field($_POST['f_align']), 'f_valign' => sanitize_text_field($_POST['f_valign']), 'f_b_clr' => sanitize_hex_color($_POST['f_b_clr']), 'f_b_thk' => intval($_POST['f_b_thk']),
            'l_en' => isset($_POST['l_en']), 'l_cols' => intval($_POST['l_cols']), 'l_bg' => sanitize_hex_color($_POST['l_bg']), 'l_txt' => sanitize_hex_color($_POST['l_txt']), 'l_font' => intval($_POST['l_font']), 'l_align' => sanitize_text_field($_POST['l_align']), 'l_valign' => sanitize_text_field($_POST['l_valign']), 'l_b_clr' => sanitize_hex_color($_POST['l_b_clr']), 'l_b_thk' => intval($_POST['l_b_thk']),
            'r_en' => isset($_POST['r_en']), 'r_cols' => intval($_POST['r_cols']), 'r_bg' => sanitize_hex_color($_POST['r_bg']), 'r_txt' => sanitize_hex_color($_POST['r_txt']), 'r_font' => intval($_POST['r_font']), 'r_align' => sanitize_text_field($_POST['r_align']), 'r_valign' => sanitize_text_field($_POST['r_valign']), 'r_b_clr' => sanitize_hex_color($_POST['r_b_clr']), 'r_b_thk' => intval($_POST['r_b_thk']),
        );

        update_option('protable_tables', $tables);
        wp_safe_redirect(admin_url('admin.php?page=protable'));
        exit;
    }
}
