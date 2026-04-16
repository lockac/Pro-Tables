<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    $icon_url = plugins_url('protable.svg', dirname(__FILE__, 1)); 
    add_menu_page('Pro Tables', 'Pro Tables', 'manage_options', 'protable', 'protable_master_router', $icon_url, 80);
    add_submenu_page('protable', 'API Settings', 'API Settings', 'manage_options', 'protable-api', 'protable_api_page_html');
});

function protable_master_router() {
    $view = isset($_GET['view']) ? $_GET['view'] : 'list';
    $tables = get_option('protable_tables', array());
    $header_icon_url = plugins_url('protable.svg', dirname(__FILE__, 1));

    if (isset($_GET['cache_cleared'])) echo '<div class="updated notice is-dismissible"><p>Cache refreshed.</p></div>';
    if (isset($_GET['all_cleared'])) echo '<div class="updated notice is-dismissible"><p>All caches cleared successfully.</p></div>';
    ?>
    <style>
        .protable-admin-wrap { margin-top: 10px; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .protable-header-icon { width: 20px; height: 20px; vertical-align: middle; margin-right: 10px; position: relative; top: -2px; }
        .protable-list { border: 1px solid #e5e5e5; background: #fff; width: 100%; border-collapse: collapse; margin-top: 15px; }
        .protable-list th { background: #f8f9fa; text-align: left; padding: 12px 15px; font-weight: 600; border-bottom: 2px solid #e5e5e5; }
        .protable-list td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        .col-id { width: 50px; color: #999; }
        .col-shortcode { width: 200px; }
        .col-actions { width: 160px; text-align: right !important; }
        .icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; color: #888; background: none; border: none; cursor: pointer; transition: transform 0.2s; vertical-align: middle; text-decoration:none; padding: 0; }
        .icon-btn:hover { transform: scale(1.2); color: #2271b1; }
        .pt-code-badge { background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #333; margin-right: 5px; border: 1px solid #dcdcde; }
        .pt-editor-layout { display: block; background: #fff; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; overflow: hidden; }
        .pt-tabs-sidebar { width: 100%; display: flex; background: #f0f0f1; border-bottom: 1px solid #ccd0d4; }
        .pt-tab { padding: 15px 25px; cursor: pointer; border-right: 1px solid #ccd0d4; font-weight: 500; }
        .pt-tab.active { background: #fff; color: #2271b1; font-weight: 600; border-bottom: 2px solid #2271b1; position: relative; bottom: -1px; }
        .pt-editor-content { flex: 1; padding: 30px; border-bottom: 1px solid #eee; }
        .pt-tab-content { display: none; }
        .pt-tab-content.active { display: block; }
        .freeze-flex-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .freeze-section-col { background: #f9f9f9; padding: 20px; border: 1px solid #eee; border-radius: 8px; max-width: 400px; }
        .freeze-section-col h3 { margin-top: 0; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .freeze-field-row { margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .freeze-field-row label { font-size: 12px; color: #555; white-space: nowrap; }
        .freeze-field-row select, .freeze-field-row input { width: 110px; font-size: 12px; height: 28px; }
        .freeze-field-row input[type="color"] { width: 40px !important; }
        .pt-size-50 { width: 50px !important; min-width: 50px !important; text-align: center; }
        .pt-size-refresh { width: 60px !important; min-width: 60px !important; text-align: center; }
        .pt-box-shadow-input { width: 240px !important; min-width: 240px !important; }
        .pt-sheet-table { border-spacing: 0 10px; border-collapse: separate; }
        .pt-sheet-table th { text-align: left; width: 140px; font-size: 13px; color: #333; }
        .pt-sheet-table input.regular-text { width: 350px !important; }
        .refresh-flex { display: flex; align-items: center; gap: 8px; }
        ::placeholder { color: #ccc !important; opacity: 1; }
        .pt-help-text { font-size: 11px; color: #999; margin-top: 4px; display: block; font-style: italic; }
        .section-disabled .freeze-field-row { opacity: 0.3; pointer-events: none; filter: grayscale(1); }
    </style>

    <div class="wrap protable-admin-wrap">
        <h1><img src="<?php echo $header_icon_url; ?>" class="protable-header-icon">Pro Tables</h1>
        <hr class="wp-header-end">

        <?php if ($view === 'list'): ?>
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="?page=protable&view=edit" class="button button-primary">Add New Table</a>
            <a href="?page=protable&refresh_all=1" class="button" onclick="return confirm('Refresh all data from Google Sheets?')">Refresh All Cache</a>
        </div>
        <table class="protable-list">
            <thead>
                <tr><th class="col-id">ID</th><th>Table Name</th><th>Sheet ID</th><th>Range</th><th class="col-shortcode">Shortcode</th><th class="col-actions">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $id => $t): $sc = '[protable id="'.$id.'"]'; ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td><strong><?php echo esc_html($t['name']); ?></strong></td>
                    <td><?php echo esc_html($t['sheet_id']); ?></td>
                    <td><?php echo esc_html($t['range']); ?></td>
                    <td class="col-shortcode">
                        <span class="pt-code-badge"><?php echo $sc; ?></span>
                        <button class="icon-btn pt-copy-btn" title="Copy" data-code='<?php echo $sc; ?>'><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </td>
                    <td class="col-actions">
                        <a href="?page=protable&clear_cache=1&id=<?php echo $id; ?>" class="icon-btn" title="Refresh"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg></a>
                        <a href="?page=protable&view=edit&id=<?php echo $id; ?>" class="icon-btn" title="Edit"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                        <a href="?page=protable&delete_table=<?php echo $id; ?>" class="icon-btn" title="Delete" onclick="return confirm('Delete permanently?')"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('.pt-copy-btn').click(function(e) {
                e.preventDefault();
                var text = $(this).data('code');
                var $temp = $("<input>"); $("body").append($temp);
                $temp.val(text).select(); document.execCommand("copy");
                $temp.remove(); var $btn = $(this); $btn.css('color', '#46b450');
                setTimeout(function(){ $btn.css('color', '#888'); }, 1000);
            });
        });
        </script>
        <?php endif; ?>

        <?php if ($view === 'edit'): 
            $edit_id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $d = ($edit_id && isset($tables[$edit_id])) ? $tables[$edit_id] : array();
        ?>
        <form method="post" action="<?php echo admin_url('admin.php?page=protable'); ?>">
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
            <div class="pt-editor-layout">
                <div class="pt-tabs-sidebar">
                    <div class="pt-tab active" data-target="tab-conn">Sheet</div>
                    <div class="pt-tab" data-target="tab-body">Body</div>
                    <div class="pt-tab" data-target="tab-highlight">Highlight</div>
                    <div class="pt-tab" data-target="tab-freeze">Freeze Panes</div>
                    <div class="pt-tab" data-target="tab-border">Table Border</div>
                </div>
                <div class="pt-editor-content">
                    <div id="tab-conn" class="pt-tab-content active">
                        <table class="pt-sheet-table">
                            <tr><th>Table Name</th><td><input type="text" name="table_name" class="regular-text" value="<?php echo pt_v($d, 'name'); ?>" placeholder="Price list 2024" required></td></tr>
                            <tr><th>Sheet ID</th><td><input type="text" name="sheet_id" class="regular-text" value="<?php echo pt_v($d, 'sheet_id'); ?>" required><span class="pt-help-text">Found in your Google Sheet URL between /d/ and /edit</span></td></tr>
                            <tr><th>Range</th><td><input type="text" name="range" class="regular-text" value="<?php echo pt_v($d, 'range'); ?>" placeholder="sheet1!A1:E50" required></td></tr>
                            <tr>
                                <th>Refresh Rate</th>
                                <td>
                                    <div class="refresh-flex">
                                        <input type="number" name="cache_time" class="pt-size-refresh" value="<?php echo pt_v($d, 'cache_time', 12); ?>" min="1">
                                        <select name="cache_unit" style="width: 100px;">
                                            <option value="minutes" <?php selected(pt_v($d,'cache_unit'), 'minutes'); ?>>Minutes</option>
                                            <option value="hours" <?php selected(pt_v($d,'cache_unit','hours'), 'hours'); ?>>Hours</option>
                                            <option value="days" <?php selected(pt_v($d,'cache_unit'), 'days'); ?>>Days</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div id="tab-body" class="pt-tab-content">
                        <div class="freeze-flex-container">
                            <div class="freeze-section-col">
                                <h3>Body</h3>
                                <div class="freeze-field-row"><label>Background Color</label><input type="color" name="b_bg" value="<?php echo pt_v($d, 'b_bg', '#ffffff'); ?>"></div>
                                <div class="freeze-field-row"><label>Font Size (px)</label><input type="number" name="b_font" class="pt-size-50" value="<?php echo pt_v($d, 'b_font', 14); ?>" min="8"></div>
                                <div class="freeze-field-row"><label>Font Color</label><input type="color" name="b_txt" value="<?php echo pt_v($d, 'b_txt', '#333333'); ?>"></div>
                                <div class="freeze-field-row"><label>Font Align Horizontal</label><select name="b_align"><option value="left" <?php selected(pt_v($d,'b_align'),'left');?>>Left</option><option value="center" <?php selected(pt_v($d,'b_align'),'center');?>>Center</option><option value="right" <?php selected(pt_v($d,'b_align'),'right');?>>Right</option></select></div>
                                <div class="freeze-field-row"><label>Font Align Vertical</label><select name="b_valign"><option value="top" <?php selected(pt_v($d,'b_valign'),'top');?>>Top</option><option value="middle" <?php selected(pt_v($d,'b_valign','middle'),'middle');?>>Middle</option><option value="bottom" <?php selected(pt_v($d,'b_valign'),'bottom');?>>Bottom</option></select></div>
                                <div class="freeze-field-row"><label>Border Color</label><input type="color" name="b_b_clr" value="<?php echo pt_v($d, 'b_b_clr', '#dddddd'); ?>"></div>
                                <div class="freeze-field-row"><label>Thickness (px)</label><input type="number" name="b_b_thk" class="pt-size-50" value="<?php echo pt_v($d, 'b_b_thk', 1); ?>" min="0"></div>
                            </div>
                        </div>
                    </div>
                    <div id="tab-highlight" class="pt-tab-content">
                        <div class="freeze-flex-container">
                            <div class="freeze-section-col <?php echo pt_v($d, 'hi_en', 0) ? '' : 'section-disabled'; ?>">
                                <h3>Highlight <input type="checkbox" name="hi_en" class="pt-section-toggle" value="1" <?php checked(pt_v($d, 'hi_en', 0), 1); ?>></h3>
                                <div class="freeze-field-row"><label>Background Color</label><input type="color" name="hi_bg" value="<?php echo pt_v($d, 'hi_bg', '#ffff00'); ?>"></div>
                                <div class="freeze-field-row"><label>Font Color</label><input type="color" name="hi_txt" value="<?php echo pt_v($d, 'hi_txt', '#333333'); ?>"></div>
                            </div>
                        </div>
                    </div>
                    <div id="tab-freeze" class="pt-tab-content">
                        <div class="freeze-flex-container">
                        <?php $groups = array('h'=>array('title'=>'Header (Top)','unit'=>'Number of Rows','val'=>'h_rows'),'f'=>array('title'=>'Footer (Bottom)','unit'=>'Number of Rows','val'=>'f_rows'),'l'=>array('title'=>'Sidebar (Left)','unit'=>'Number of Columns','val'=>'l_cols'),'r'=>array('title'=>'Sidebar (Right)','unit'=>'Number of Columns','val'=>'r_cols'));
                        foreach($groups as $p => $info): ?>
                        <div class="freeze-section-col <?php echo pt_v($d, $p.'_en', 0) ? '' : 'section-disabled'; ?>">
                            <h3><?php echo $info['title']; ?> <input type="checkbox" name="<?php echo $p; ?>_en" class="pt-section-toggle" value="1" <?php checked(pt_v($d, $p.'_en', 0), 1); ?>></h3>
                            <div class="freeze-field-row"><label><?php echo $info['unit']; ?></label><input type="number" name="<?php echo $info['val']; ?>" class="pt-size-50" value="<?php echo pt_v($d, $info['val'], ($p==='h'?1:0)); ?>" min="0"></div>
                            <div class="freeze-field-row"><label>Background Color</label><input type="color" name="<?php echo $p; ?>_bg" value="<?php echo pt_v($d, $p.'_bg', '#f9f9f9'); ?>"></div>
                            <div class="freeze-field-row"><label>Font Size (px)</label><input type="number" name="<?php echo $p; ?>_font" class="pt-size-50" value="<?php echo pt_v($d, $p.'_font', 14); ?>" min="8"></div>
                            <div class="freeze-field-row"><label>Font Color</label><input type="color" name="<?php echo $p; ?>_txt" value="<?php echo pt_v($d, $p.'_txt', '#333333'); ?>"></div>
                            <div class="freeze-field-row"><label>Font Align Horizontal</label><select name="<?php echo $p; ?>_align"><option value="left" <?php selected(pt_v($d,$p.'_align'),'left');?>>Left</option><option value="center" <?php selected(pt_v($d,$p.'_align','center'),'center');?>>Center</option><option value="right" <?php selected(pt_v($d,$p.'_align'),'right');?>>Right</option></select></div>
                            <div class="freeze-field-row"><label>Font Align Vertical</label><select name="<?php echo $p; ?>_valign"><option value="top" <?php selected(pt_v($d,$p.'_valign'),'top');?>>Top</option><option value="middle" <?php selected(pt_v($d,$p.'_valign','middle'),'middle');?>>Middle</option><option value="bottom" <?php selected(pt_v($d,$p.'_valign'),'bottom');?>>Bottom</option></select></div>
                            <div class="freeze-field-row"><label>Border Color</label><input type="color" name="<?php echo $p; ?>_b_clr" value="<?php echo pt_v($d, $p.'_b_clr', '#dddddd'); ?>"></div>
                            <div class="freeze-field-row"><label>Thickness (px)</label><input type="number" name="<?php echo $p; ?>_b_thk" class="pt-size-50" value="<?php echo pt_v($d, $p.'_b_thk', 1); ?>" min="0"></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="tab-border" class="pt-tab-content">
                        <div class="freeze-flex-container">
                            <div class="freeze-section-col <?php echo pt_v($d, 't_b_en', 0) ? '' : 'section-disabled'; ?>">
                                <h3>Table Border <input type="checkbox" name="t_b_en" class="pt-section-toggle" value="1" <?php checked(pt_v($d,'t_b_en',0),1); ?>></h3>
                                <div class="freeze-field-row"><label>Thickness (px)</label><input type="number" name="t_b_thk" class="pt-size-50" value="<?php echo pt_v($d, 't_b_thk', 0); ?>" min="0"></div>
                                <div class="freeze-field-row"><label>Border Color</label><input type="color" name="t_b_clr" value="<?php echo pt_v($d, 't_b_clr', '#dddddd'); ?>"></div>
                                <div class="freeze-field-row"><label>Radius (px)</label><input type="number" name="t_b_rad" class="pt-size-50" value="<?php echo pt_v($d, 't_b_rad', 0); ?>" min="0"></div>
                                <div class="freeze-field-row"><label>Box Shadow (CSS)</label><input type="text" name="t_b_shd" class="pt-box-shadow-input" placeholder="e.g. 0px 4px 10px rgba(0,0,0,0.1)" value="<?php echo pt_v($d, 't_b_shd', ''); ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px;"><input type="submit" name="save_table_entry" class="button button-primary button-large" value="Update Settings"><a href="?page=protable" class="button button-large" style="margin-left:10px;">Cancel</a></div>
        </form>
        <script>
        jQuery(document).ready(function($) {
            $('.pt-tab').click(function() {
                $('.pt-tab, .pt-tab-content').removeClass('active'); $(this).addClass('active');
                $('#' + $(this).data('target')).addClass('active');
            });
            function updateSectionStatus(el) {
                var parent = $(el).closest('.freeze-section-col');
                if ($(el).is(':checked')) { parent.removeClass('section-disabled'); } else { parent.addClass('section-disabled'); }
            }
            $('.pt-section-toggle').each(function() { updateSectionStatus(this); });
            $('.pt-section-toggle').on('change', function() { updateSectionStatus(this); });
        });
        </script>
        <?php endif; ?>
    </div>
    <?php
}

function protable_api_page_html() {
    if (isset($_POST['save_api_key'])) {
        update_option('protable_encrypted_api_key', protable_encrypt_key(sanitize_text_field($_POST['protable_api_key'])));
        echo '<div class="updated"><p>API Key Saved Securely.</p></div>';
    }
    $saved = get_option('protable_encrypted_api_key', '');
    ?>
    <div class="wrap protable-admin-wrap"><h1>API Settings</h1><div class="card" style="max-width: 600px; padding: 20px; margin-top:20px;"><form method="post"><table class="form-table"><tr><th>Google API Key</th><td><input type="password" name="protable_api_key" value="<?php echo $saved ? '********' : ''; ?>" class="regular-text"></td></tr></table><input type="submit" name="save_api_key" class="button button-primary" value="Save Securely"></form></div></div>
    <?php
}
