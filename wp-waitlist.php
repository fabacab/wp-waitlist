<?php
/**
 * Plugin Name: Waitlists for WordPress
 * Plugin URI: https://github.com/meitar/wp-waitlist
 * Description: Add user lists of almost any type, for any purpose, to any post. E.g., a "sign up list" to an event or class.
 * Version: 0.1
 * Author: maymay
 * Author URI: http://maymay.net/
 * Text Domain: wp-waitlist
 * Domain Path: /languages
 */

class WP_Waitlist {
    private $prefix = 'wp-waitlist_';

    public function __construct () {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'registerAdminScripts'));
        add_action('admin_head', array($this, 'doAdminHeadActions'));
        add_action('add_meta_boxes', array($this, 'addMetaBox'), 10, 2);

        add_action('the_post', array($this, 'addOrRemoveMeToWaitlist'));
        add_action('save_post', array($this, 'savePost'));

        add_filter('the_content', array($this, 'appendWaitlistJoinLeaveButtons'));
    }

    private function showError ($msg) {
?>
<div class="error">
    <p><?php print esc_html($msg);?></p>
</div>
<?php
    }

    private function showNotice ($msg) {
?>
<div class="updated">
    <p><?php print $msg; // No escaping because we want links, so be careful. ?></p>
</div>
<?php
    }

    public function registerL10n () {
        load_plugin_textdomain('wp-waitlist', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function registerSettings () {
        register_setting(
            $this->prefix . 'settings',
            $this->prefix . 'settings',
            array($this, 'validateSettings')
        );
    }

    public function appendWaitlistJoinLeaveButtons ($content) {
        global $post;
        if (!$post) { return $content; } // Don't run if we're not being called by WordPress.
        $lists = get_post_meta($post->ID, $this->prefix . 'lists', true);
        if (empty($lists)) { return $content; } // Don't run if there is no waitlist info for this post.
        $html = '';
        if (!post_password_required($post->ID)) {
            $current_user_id = get_current_user_id();
            if (0 === $current_user_id) { // No user is logged in.
                $html .= '<p>';
                $html .= sprintf(
                    esc_html__('%1$s with your account in order to be able to join "%2$s" lists!', 'wp-waitlist'),
                    wp_loginout(get_permalink($post->ID), false),
                    $post->post_title
                );
                $html .= '</p>';
            } else {
                $permalink = get_permalink($post->ID);
                $html .= '<ul class="' . $this->prefix . '-join-leave-button-list">';
                foreach ($lists as $list) {
                    $html .= '<li><form action="' . $permalink . '">';
                    // If a blog isn't using pretty permalinks, this form (being an HTTP GET)
                    // overrides the query string in the `action` attribute, so add the permalink manually.
                    $x = get_option('permalink_structure');
                    if (empty($x)) {
                        $qsx = explode('=', parse_url($permalink, PHP_URL_QUERY));
                        $html .= '<input type="hidden" name="' . $qsx[0] . '" value="' . $qsx[1] . '">';
                    }
                    $html .= wp_nonce_field('join_or_leave_list', $this->prefix . 'nonce', false, false);
                    $html .= '<input type="hidden" name="' . $this->prefix . 'the_post" value="' . esc_attr($post->ID) . '" />';
                    $html .= '<input type="hidden" name="' . $this->prefix . 'list_name" value="' . esc_attr($list['name']) . '" />';
                    // Is this user already on this list?
                    $user_ids = $this->getUsersOnList($post->ID, $list['name']);
                    if (in_array($current_user_id, $user_ids)) {
                        // User is currently listed, so make a "Leave List" button.
                        $html .= '<input type="hidden" name="' . $this->prefix . 'action" value="leave" />';
                        $html .= '<input type="submit" value="' . sprintf(esc_attr__('Leave "%s" List', 'wp-waitlist'), $list['name']) . '" />';
                    } else {
                        // User is not on this list, so make a "Join List" button,
                        $html .= '<input type="hidden" name="' . $this->prefix . 'action" value="join" />';
                        // but make it say join WAIT list if the list is at capacity already
                        $btn_text = '';
                        if (!empty($list['max']) && count($user_ids) >= $list['max']) {
                            $btn_text = esc_attr__('Join waitlist for "%s" list', 'wp-waitlist');
                        } else {
                            $btn_text = esc_attr('Join "%s" list', 'wp-waitlist');
                        }
                        $html .= '<input type="submit" value="' . sprintf($btn_text, $list['name']) . '" />';
                    }
                    $html .= '</form></li>';
                }
                $html .= '</ul>';
            }
        }
        return $content . $html;
    }

    public function addOrRemoveMeToWaitlist ($post) {
        // Do nothing if the nonce is invalid.
        if (isset($_REQUEST[$this->prefix . 'nonce']) && !wp_verify_nonce($_REQUEST[$this->prefix . 'nonce'], 'join_or_leave_list')) { return; }

        // Only add or remove this user to this list if this is, indeed, the list being requested.
        if (isset($_REQUEST[$this->prefix . 'the_post']) && isset($_REQUEST[$this->prefix . 'list_name']) && $post->ID == $_REQUEST[$this->prefix . 'the_post']) {
            $user_id = get_current_user_id();
            switch ($_REQUEST[$this->prefix . 'action']) {
                case 'join':
                    if ($this->addUserToList($user_id, $post->ID, $_REQUEST[$this->prefix . 'list_name'])) {
                        // TODO:
                        //$this->addNoticeOfListedUser($user_id, $post->ID, $_REQUEST[$this->prefix . 'list_name']);
                    }
                    break;
                case 'leave':
                    if ($this->removeUserFromList($user_id, $post->ID, $_REQUEST[$this->prefix . 'list_name'])) {
                        // TODO:
                        //$this->addNoticeOfUnlistedUser($user_id, $post->ID, $_REQUEST[$this->prefix . 'list_name']);
                    }
                    break;
            }
        }
    }

    public function registerAdminMenu () {
        add_options_page(
            __('WP-Waitlist Settings', 'wp-waitlist'),
            __('WP-Waitlist', 'wp-waitlist'),
            'manage_options',
            $this->prefix . 'settings',
            array($this, 'renderOptionsPage')
        );
    }

    public function savePost ($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (
            isset($_REQUEST[$this->prefix . 'enabled'])
            &&
            isset($_REQUEST[$this->prefix . 'list'])
            &&
            wp_verify_nonce($_REQUEST[$this->prefix . 'meta_box_nonce'], 'editing_' . $this->prefix . 'list')
        ) {
            // Save value through array_values() to reindex starting at 0 in case lists were removed.
            update_post_meta($post_id, $this->prefix . 'lists', array_values($_REQUEST[$this->prefix . 'list']));
        }
    }

    public function registerAdminScripts () {
        wp_register_script('wp-waitlist', plugins_url('wp-waitlist.js', __FILE__), array('jquery'));
        wp_enqueue_script('wp-waitlist');
        wp_register_style('wp-waitlist', plugins_url('wp-waitlist.css', __FILE__));
        wp_enqueue_style('wp-waitlist');
    }

    public function doAdminHeadActions () {
        $this->registerContextualHelp();
        $this->showAdminNotices();
    }

    private function registerContextualHelp () {
        // TODO
    }

    public function addMetaBox ($post_type, $post) {
        add_meta_box(
            $this->prefix . 'lists-meta-box',
            __('Waitlist Details', 'wp-waitlist'),
            array($this, 'renderMetaBox'),
            $post_type, // TODO: Parameterize this so people can choose which post types this applies to.
            'normal'
        );
    }

    private function renderWaitlistsTable ($data = array()) {
        global $post;
        // Set empty condition and condition parameter defaults.
        if (empty($data)) {
            $data[0] = array(
                'name' => '',
                'users' => array(),
                'max' => '' // beyond this number, users are "waitlisted"
            );
        }
?>
<table class="form-table">
    <thead>
        <tr>
            <th>
                <?php esc_html_e('Waitlist', 'wp-waitlist');?>
            </th>
            <th>
                <?php esc_html_e('Waitlist properties', 'wp-waitlist');?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php for ($i = 0; $i < count($data); $i++) : ?>
        <tr id="<?php esc_attr_e($this->prefix);?>list-<?php esc_attr_e($i);?>">
            <td>
                <p><label><?php esc_html_e('List name:', 'wp-waitlist');?><br />
                    <input
                        name="<?php esc_attr_e($this->prefix)?>list[<?php esc_attr_e($i);?>][name]"
                        value="<?php esc_attr_e($data[$i]['name']);?>"
                    />
                </label></p>
                <p><a href="#TK" class="button <?php esc_attr_e($this->prefix);?>list-remove"><?php esc_html_e('Remove waitlist', 'wp-waitlist');?></a></p>
            </td>
            <td>
                <fieldset id="<?php esc_attr_e($this->prefix);?>list-<?php esc_attr_e($i);?>" class="<?php esc_attr_e($this->prefix);?>list-parameters">
                    <legend><?php esc_html_e('List properties', 'wp-waitlist');?></legend>
                    <label><?php print sprintf(
                        esc_html__('Waitlist users after the first %s have already joined.', 'wp-waitlist'),
                        '<input type="number" name="' . esc_attr($this->prefix) . 'list[' . esc_attr($i) . '][max]" value="' . esc_attr($data[$i]['max']) . '" placeholder="number" min="1" />'
                    );?></label> 
                </fieldset>
                <div id="<?php esc_attr_e($this->prefix)?>list-users-<?php esc_attr_e($i);?>">
                    <p><?php esc_html_e('Users on this list:', 'wp-waitlist');?></p>
                    <ul>
                        <li>Joined:
                            <?php $this->renderListedUsersList($post->ID, $data[$i]['name'])?>
                        </li>
                        <li>Waitlisted:
                            <?php $this->renderWaitlistedUsersList($post->ID, $data[$i]['name'])?>
                        </li>
                    </ul>
                </div>
<!-- TODO: -->
<!--
                <label>
                    <input type="checkbox"
                        <?php if (isset($data[$i]['active'])) { print 'checked="checked"'; }?>
                        name="<?php esc_attr_e($this->prefix);?>list[<?php esc_attr_e($i);?>][active]"
                        value="1" /> <?php esc_html_e('Active', 'wp-waitlist');?>
                </label>
-->
            </td>
        </tr>
        <?php endfor; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2"><a href="#TK" id="<?php esc_attr_e($this->prefix);?>list-new" class="button"><?php esc_html_e('Add another waitlist', 'wp-waitlist');?></a></td>
        </tr>
    </tfoot>
</table>
<?php
    }

    public function renderMetaBox ($post) {
        wp_nonce_field('editing_' . $this->prefix . 'list', $this->prefix . 'meta_box_nonce');
        $lists = get_post_meta($post->ID, $this->prefix . 'lists', true);
?>
<fieldset><legend>Manage Waitlists</legend>
    <p><label>
        <input type="checkbox"
            id="<?php print esc_attr($this->prefix . 'enabled');?>"
            name="<?php print esc_attr($this->prefix . 'enabled');?>"
            <?php (!empty($lists)) ? print 'checked="checked"': '';?>
            />
        <?php esc_html_e('Enable WP-Waitlist for this post?', 'wp-waitlist');?>
    </label></p>
    <?php $this->renderWaitlistsTable(get_post_meta($post->ID, $this->prefix . 'lists', true));?>
</fieldset>
<?php
    }

    /**
     * Returns an array of lists for a given post object.
     */
    public function getListsForPost ($post_id) {
        $list_data = get_post_meta($post_id, $this->prefix . 'lists', true);
        if (empty($list_data)) { return false; }
        $ret = array();
        foreach ($list_data as $list) {
            $ret[] = $list['name'];
        }
        return $ret;
    }

    /**
     * Returns an array of list properties for the given list.
     */
    public function getListProperties ($post_id, $list_name = '') {
        $lists = get_post_meta($post_id, $this->prefix . 'lists', true);
        if (empty($lists)) { return false; }
        foreach ($lists as $list) {
            if ($list_name === $list['name']) {
                return $list;
            }
        }
    }

    public function getUsersOnList ($post_id, $list_name = '') {
        // Start with an underscore to tell WordPress to hide this field from default custom field UI.
        $user_ids = get_post_meta($post_id, '_' . $this->prefix . 'users_listed_on_' . $list_name . '_list');

        // Sort by join time.
        $arr_join_times = array();
        foreach ($user_ids as $user_id) {
            $arr_join_times[$user_id] = $this->getListJoinTime($post_id, $user_id, $list_name);
        }
        asort($arr_join_times);

        // Return the sorted list.
        $users = array();
        foreach ($arr_join_times as $user_id => $join_time) {
            $users[] = $user_id;
        }
        return $users;
    }

    /**
     * Returns an array of user IDs that are on the named list up to its capacity.
     *
     * @param int $post_id The post ID to which the list is attached.
     * @param string $list_name The name of the list.
     * @return array An array of WordPress user IDs, sorted by the time they joined the named list.
     */
    public function getListedUsers ($post_id, $list_name = '') {
        $users = $this->getUsersOnList($post_id, $list_name);
        $list_props = $this->getListProperties($post_id, $list_name);
        return array_slice($users, 0, $list_props['max']);
    }

    /**
     * Returns an array of user IDs that are on the named list beyond its capacity.
     *
     * @param int $post_id The post ID to which the list is attached.
     * @param string $list_name The name of the list.
     * @return array An array of WordPress user IDs, sorted by the time they joined the named list.
     */
    public function getWaitlistedUsers ($post_id, $list_name = '') {
        $users = $this->getUsersOnList($post_id, $list_name);
        $list_props = $this->getListProperties($post_id, $list_name);
        return array_slice($users, $list_props['max']);
    }

    public function getListJoinTime ($post_id, $user_id, $list_name = '') {
        return get_post_meta($post_id, '_' . $this->prefix . 'user_' . $user_id . '_' . $list_name . '_join_time', true);
    }

    /**
     * Adds a user to a list, if they are not already on it.
     *
     * @param int $user_id The user's ID number.
     * @param int $post_id The post's ID number.
     * @param string $list_name The name of the list for this post to add the user to.
     *
     * @return bool True if they have been added, false otherwise, perhaps because they are already listed.
     */
    private function addUserToList ($user_id, $post_id, $list_name = '') {
        $user_ids = $this->getUsersOnList($post_id, $list_name);
        if (!in_array($user_id, $user_ids)) {
            update_post_meta($post_id, '_' . $this->prefix . 'user_' . $user_id . '_' . $list_name . '_join_time', time());
            // Should always return true because 4th paramter `unique` is not set.
            return add_post_meta($post_id, '_' . $this->prefix . 'users_listed_on_' . $list_name . '_list', $user_id);
        } else {
            return false;
        }
    }

    private function removeUserFromList ($user_id, $post_id, $list_name = '') {
        return delete_post_meta($post_id, '_' . $this->prefix . 'users_listed_on_' . $list_name . '_list', $user_id);
    }

    private function renderUserList ($post_id, $user_ids, $list_name) {
        print '<ol>';
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            print '<li>';
            print '<a href="' . admin_url('/profile.php?uid=' . esc_attr($user_id)) . '">' . esc_html($user->display_name) . '</a>';
            print ' ' . esc_html__('joined on', 'wp-waitlist') . ' ' . date(get_option('date_format'), $this->getListJoinTime($post_id, $user_id, $list_name));
            print '</li>';
        }
        print '</ol>';
    }

    private function renderListedUsersList ($post_id, $list_name = '') {
        $user_ids = $this->getListedUsers($post_id, $list_name);
        $this->renderUserList($post_id, $user_ids, $list_name);
    }

    private function renderWaitlistedUsersList ($post_id, $list_name = '') {
        $user_ids = $this->getWaitlistedUsers($post_id, $list_name);
        $this->renderUserList($post_id, $user_ids, $list_name);
    }

    private function captureDebugOf ($var) {
        ob_start();
        var_dump($var);
        $str = ob_get_contents();
        ob_end_clean();
        return $str;
    }

    private function maybeCaptureDebugOf ($var) {
        $msg = '';
        $options = get_option($this->prefix . 'settings');
        if (isset($options['debug'])) {
            $msg .= esc_html__('Debug output:', 'wp-waitlist');
            $msg .= '<pre>' . $this->captureDebugOf($var) . '</pre>';
        }
        return $msg;
    }

    private function showAdminNotices () {
        $notices = get_option('_' . $this->prefix . 'admin_notices');
        if ($notices) {
            foreach ($notices as $msg) {
                $this->showNotice($msg);
            }
            delete_option('_' . $this->prefix . 'admin_notices');
        }
    }

    /**
     * @param array $input An array of of our unsanitized options.
     * @return array An array of sanitized options.
     */
    public function validateSettings ($input) {
        if (empty($input)) { $input = array(); }
        $safe_input = array();
        foreach ($input as $k => $v) {
            switch ($k) {
                case 'debug':
                    $safe_input[$k] = intval($v);
                break;
            }
        }
        return $safe_input;
    }
    
    /**
     * Writes the HTML for the options page, and each setting, as needed.
     */
    public function renderOptionsPage () {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-waitlist'));
        }
        $options = get_option($this->prefix . 'settings');
?>
<h2><?php esc_html_e('WP-Waitlist Settings', 'wp-waitlist');?></h2>
<form method="post" action="options.php">
<?php settings_fields($this->prefix . 'settings');?>
<fieldset><legend><?php esc_html_e('WP-Waitlist Settings', 'wp-waitlist');?></legend>
<table class="form-table" summary="<?php esc_attr_e('Configuration options for WP-Waitlist plugin.', 'wp-waitlist');?>">
    <tbody>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>debug">
                    <?php esc_html_e('Enable detailed debugging information?', 'wp-waitlist');?>
                </label>
            </th>
            <td>
                <input type="checkbox" <?php if (isset($options['debug'])) : print 'checked="checked"'; endif; ?> value="1" id="<?php esc_attr_e($this->prefix);?>debug" name="<?php esc_attr_e($this->prefix);?>settings[debug]" />
                <label for="<?php esc_attr_e($this->prefix);?>debug"><span class="description"><?php
        print sprintf(
            esc_html__('Turn this on only if you are experiencing problems using this plugin, or if you were told to do so by someone helping you fix a problem (or if you really know what you are doing). When enabled, extremely detailed technical information is displayed as a WordPress admin notice when you take certain actions. If you have also enabled WordPress\'s built-in debugging (%1$s) and debug log (%2$s) feature, additional information will be sent to a log file (%3$s). This file may contain sensitive information, so turn this off and erase the debug log file when you have resolved the issue.', 'wp-waitlist'),
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG"><code>WP_DEBUG</code></a>',
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG_LOG"><code>WP_DEBUG_LOG</code></a>',
            '<code>' . content_url() . '/debug.log' . '</code>'
        );
                ?></span></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<?php submit_button();?>
</form>
<?php
    } // end public function renderOptionsPage

    public function activate () {
        $this->registerL10n();

        flush_rewrite_rules();
    }

}

$WP_Waitlist = new WP_Waitlist();
