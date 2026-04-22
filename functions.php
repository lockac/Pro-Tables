<?php
if (!defined('ABSPATH')) exit;

// Safe value getter
if (!function_exists('ps_v')) {
    function ps_v($array, $key, $default = '') {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

// Strict comparison helper
if (!function_exists('prosheets_values_match')) {
    function prosheets_values_match($a, $b) {
        if ($a === '' || $a === null) $a = '';
        if ($b === '' || $b === null) $b = '';
        return (string)$a === (string)$b;
    }
}

// API Key Encryption/Decryption
if (!function_exists('prosheets_encrypt_key')) {
    function prosheets_encrypt_key($key) {
        if (empty($key)) return '';
        return base64_encode($key);
    }
}

if (!function_exists('prosheets_decrypt_key')) {
    function prosheets_decrypt_key($encrypted) {
        if (empty($encrypted)) return '';
        $decoded = base64_decode($encrypted, true);
        return ($decoded === false) ? '' : $decoded;
    }
}

// Helper: Convert column letter(s) to 0-based index (A=0, B=1, Z=26, AA=27)
if (!function_exists('prosheets_col_to_index')) {
    function prosheets_col_to_index($col) {
        $index = 0;
        $col = strtoupper(trim($col));
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}

// Helper: Parse A1 notation range to bounds
if (!function_exists('prosheets_parse_range')) {
    function prosheets_parse_range($range) {
        if (strpos($range, '!') !== false) {
            $range = explode('!', $range, 2)[1];
        }
        if (strpos($range, ':') === false) {
            $range = $range . ':' . $range;
        }
        list($start, $end) = explode(':', $range);
        preg_match('/([A-Z]+)(\d+)/i', $start, $s);
        preg_match('/([A-Z]+)(\d+)/i', $end, $e);
        return [
            'start_row' => (int)$s[2] - 1,
            'start_col' => prosheets_col_to_index($s[1]),
            'end_row' => (int)$e[2] - 1,
            'end_col' => prosheets_col_to_index($e[1])
        ];
    }
}

// 1. Fetch Values (Padded + Cache Bypass)
function get_prosheets_values($sheet_id, $range, $cache_time = 3600) {
    $bypass = isset($_GET['prosheets_fresh']) && current_user_can('manage_options'); // TESTING BYPASS
    
    $enc_key = get_option('prosheets_encrypted_api_key', '');
    if (empty($enc_key)) return ['error' => 'Error: API Key not configured.'];
    $api_key = trim(prosheets_decrypt_key($enc_key));
    if (empty($api_key)) return ['error' => 'Error: Invalid API Key.'];

    $cache_key = 'prosheets_val_' . md5($sheet_id . $range);
    
    // Only check cache if bypass is OFF
    if (!$bypass) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
    $response = wp_remote_get($url, array('timeout' => 30, 'sslverify' => true));
    if (is_wp_error($response)) return ['error' => 'Connection Error: ' . $response->get_error_message()];

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    if (isset($json['error'])) return ['error' => 'API Error: ' . $json['error']['message']];
    if (!isset($json['values'])) return ['values' => []];

    $raw_values = $json['values'];
    $bounds = prosheets_parse_range($range);
    $expected_cols = ($bounds['end_col'] - $bounds['start_col']) + 1;

    $padded_values = [];
    foreach ($raw_values as $row) {
        $padded_values[] = array_pad((array)$row, $expected_cols, '');
    }

    $result = ['values' => $padded_values];
    set_transient($cache_key, $result, $cache_time);
    return $result;
}

// 2. Fetch Formatting, Merges & Column Widths (Diagnostic Logging Version)
function get_prosheets_formatting($sheet_id, $range, $cache_time = 3600) {
    $bypass = isset($_GET['prosheets_fresh']) && current_user_can('manage_options');
    
    $enc_key = get_option('prosheets_encrypted_api_key', '');
    if (empty($enc_key)) return ['merges' => [], 'colors' => [], 'col_widths' => [], 'range_bounds' => [], '_raw_merges' => []];
    $api_key = trim(prosheets_decrypt_key($enc_key));
    if (empty($api_key)) return ['merges' => [], 'colors' => [], 'col_widths' => [], 'range_bounds' => [], '_raw_merges' => []];

    $cache_key = 'prosheets_fmt_' . md5($sheet_id . $range);
    if (!$bypass) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $bounds = prosheets_parse_range($range);
    $expected_cols = ($bounds['end_col'] - $bounds['start_col']) + 1;
    
    // --- CALL 1: Fetch Merges ---
    $merges_url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}?fields=sheets(merges)&key={$api_key}";
    $merges_response = wp_remote_get($merges_url, array('timeout' => 15, 'sslverify' => true));
    $all_merges = [];
    
    if (!is_wp_error($merges_response)) {
        $merges_json = json_decode(wp_remote_retrieve_body($merges_response), true);
        if (isset($merges_json['sheets']) && is_array($merges_json['sheets'])) {
            foreach ($merges_json['sheets'] as $s) {
                if (isset($s['merges']) && is_array($s['merges'])) {
                    $all_merges = array_merge($all_merges, $s['merges']);
                }
            }
        }
    }
    
    // LOGGING START
    error_log('ProSheets - Merges Debug: Bounds=' . json_encode($bounds) . ' | RawMergesCount=' . count($all_merges));
    if (!empty($all_merges)) {
        error_log('ProSheets - First Merge: ' . json_encode($all_merges[0]));
    }
    // LOGGING END

    // --- CALL 2: Fetch Data ---
    $data_url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}?ranges=" . urlencode($range) . 
                "&fields=sheets(properties/defaultColumnWidth,data(columnMetadata/pixelSize,rowData/values/effectiveFormat))&key={$api_key}";
    
    $response = wp_remote_get($data_url, array('timeout' => 20, 'sslverify' => true));
    if (is_wp_error($response)) return ['merges' => [], 'colors' => [], 'col_widths' => [], 'range_bounds' => $bounds, '_raw_merges' => $all_merges];

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    
    if (isset($json['error']) || !isset($json['sheets'][0])) {
        $result = ['merges' => [], 'colors' => [], 'col_widths' => [], 'range_bounds' => $bounds, '_raw_merges' => $all_merges];
        set_transient($cache_key, $result, $cache_time);
        return $result;
    }

    $sheet = $json['sheets'][0];
    $rowData = isset($sheet['data'][0]['rowData']) ? $sheet['data'][0]['rowData'] : [];
    
    $merges = [];
    foreach ($all_merges as $m) {
        $m_sr = (int)($m['startRowIndex'] ?? 0);
        $m_er = (int)($m['endRowIndex'] ?? 0); 
        $m_sc = (int)($m['startColumnIndex'] ?? 0);
        $m_ec = (int)($m['endColumnIndex'] ?? 0);
        
        $keep = ($m_sr <= $bounds['end_row'] && $m_er > $bounds['start_row'] &&
                 $m_sc <= $bounds['end_col'] && $m_ec > $bounds['start_col']);
                 
        // LOGGING LOOP
        error_log('ProSheets - Merge Loop: ' . json_encode($m) . ' | Keep: ' . ($keep ? 'YES' : 'NO'));
        
        if ($keep) {
            $merges[] = $m;
        }
    }
    
    // Extract colors (padded to grid)
    $colors = [];
    foreach ($rowData as $row) {
        $cells = isset($row['values']) ? $row['values'] : [];
        $row_colors = [];
        foreach ($cells as $cell) {
            $bg = '';
            if (isset($cell['effectiveFormat']['backgroundColor'])) {
                $c = $cell['effectiveFormat']['backgroundColor'];
                if (isset($c['red']) || isset($c['green']) || isset($c['blue'])) {
                    $r = intval(round(floatval($c['red'] ?? 0) * 255));
                    $g = intval(round(floatval($c['green'] ?? 0) * 255));
                    $b = intval(round(floatval($c['blue'] ?? 0) * 255));
                    $a = isset($c['alpha']) ? max(0.0, min(1.0, floatval($c['alpha']))) : 1.0;
                    $bg = ($a >= 1.0) ? sprintf('rgb(%d, %d, %d)', $r, $g, $b) : sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $a);
                }
            }
            $row_colors[] = $bg;
        }
        $colors[] = array_pad($row_colors, $expected_cols, '');
    }

    // Extract column widths
    $default_w = isset($sheet['properties']['defaultColumnWidth']) ? intval($sheet['properties']['defaultColumnWidth']) : 100;
    $col_widths = [];
    if (isset($sheet['data'][0]['columnMetadata']) && is_array($sheet['data'][0]['columnMetadata'])) {
        foreach ($sheet['data'][0]['columnMetadata'] as $idx => $meta) {
            $px = isset($meta['pixelSize']) ? intval($meta['pixelSize']) : $default_w;
            $col_widths[$idx] = $px . 'px';
        }
    }
    for ($i = 0; $i < $expected_cols; $i++) {
        if (!isset($col_widths[$i])) $col_widths[$i] = $default_w . 'px';
    }
    $col_widths = array_values($col_widths);

    $result = ['merges' => $merges, '_raw_merges' => $all_merges, 'colors' => $colors, 'col_widths' => $col_widths, 'range_bounds' => $bounds];
    set_transient($cache_key, $result, $cache_time);
    return $result;
}

// Wrapper for shortcode compatibility (now pulls cache time from admin settings)
function get_prosheets_data($sheet_id, $range, $table_id = null) {
    // Pull cache time from your admin table settings
    $config = $table_id ? prosheets_get_table_config($table_id) : [];
    
    // Adjust 'cache_time' if your admin setting uses a different key (e.g., 'refresh_rate')
    $cache_time = isset($config['cache_time']) ? intval($config['cache_time']) : 3600;
    
    $vals = get_prosheets_values($sheet_id, $range, $cache_time);
    if (isset($vals['error'])) return $vals;
    $fmt = get_prosheets_formatting($sheet_id, $range, $cache_time);
    return array_merge($vals, $fmt);
}

// Merge defaults with table-specific overrides
if (!function_exists('prosheets_get_table_config')) {
    function prosheets_get_table_config($table_id = null) {
        $defaults = get_option('prosheets_defaults', array());
        if (empty($table_id)) return $defaults;
        
        $tables = get_option('prosheets_tables', array());
        $overrides = isset($tables[$table_id]) ? $tables[$table_id] : array();
        
        $config = $defaults;
        foreach ($overrides as $key => $value) {
            if (strpos($key, '_en') !== false) {
                if (array_key_exists($key, $overrides)) $config[$key] = $value;
            } 
            elseif (strpos($key, '_rows') !== false || strpos($key, '_cols') !== false || strpos($key, '_thk') !== false || strpos($key, '_font') !== false || strpos($key, '_rad') !== false) {
                if ($value !== '' && $value !== null && is_numeric($value)) $config[$key] = $value;
            }
            else {
                if ($value !== '' && $value !== null && $value !== false) $config[$key] = $value;
            }
        }
        return $config;
    }
}
