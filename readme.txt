=== WP-Waitlist ===
Contributors: maymay
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Waitlists%20for%20WordPress&amp;item_number=wp-waitlists&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: developer, user management, user lists
Requires at least: 3.1
Tested up to: 3.9
Stable tag: 0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Waitlists for WordPress lets you create and manage user lists of almost any type in any post.

== Description ==

Add one or more "lists" to any post. Registered users of your site can then join or leave the list. Lists can be used for any purpose (event RSVPs, running polls or surveys, etc.) and can be unobtrusively added to any post type. Optionally, lists can have a maximum number of users on it that you define, after which any user who joins the list is automatically added to an overflow "waitlist."

This plugin also serves the needs of plugin developers who are looking for a simple way to manage sets of users on a per-post basis. A simple set of public functions are exposed to other installed plugins that developers can use to get information about the lists themselves, and the users listed on them.

= Code examples =

After installing and activating this plugin, include it in your own plugin project as follows:

`<?php
/**
 * Plugin Name: My plugin project
 * Plugin URI: http://example.com/
 * Description: Example plugin for playing with WP-Waitlist.
 * Author: Me, myself, and I
 * Version: 1.0
 */

class My_Plugin {
    private $WP_Waitlist;

    public function __construct () {
        add_action('init', array($this, 'registerDepdencies'));
    }

    public function registerDepdencies () {
        global $WP_Waitlist;
        if (!$WP_Waitlist) {
            // WP-Waitlist is not available, issue an error.
        } else {
            $this->$WP_Waitlist = $WP_Waitlist;
        }
    }

}

$My_Plugin = new My_Plugin();
`

At that point, you can call WP-Waitlist's functions in your own plugin as follows:

`
public function myPluginLearnsAboutWaitlists ($post_id) {
    // Get an array of all lists that the author of this post created.
    $lists = $this->WP_Waitlist->getListsForPost($post_id);

    // You can iterate through the lists attached to this post.
    foreach ($lists as $list_name) {
        $list_properties = $this->WP_Waitlist->getListProperties($post_id, $list_name);
        foreach ($list_properties as $property_name => $property_value) {
            print "$property_name is $property_value <br />";
        }

        // You can also learn which users are on the list...
        $user_ids = $this->WP_Waitlist->getListedUsers($post_id, $list_name);
        foreach ($user_ids as $id) {
            $this_wp_user = get_userdata($id); // $this_wp_user is now a WP_User object.
        }

        // ...and which users have been waitlisted (joined after the list reached capacity).
        $waitlisted_users = $this->WP_Waitlist->getWaitlistedUsers($post_id, $list_name);

        // You can also get an array all users who have added themselves to the list, sorted by date.
        $all_user_ids_on_list = $this->WP_Waitlist->getUsersOnList($post_id, $list_name);
    }

}
`

= Plugins that use this one =

Know of a plugin that's using WP-Waitlist? Let us know by posting in [the support forum](https://wordpress.org/support/plugin/wp-waitlist/). :)

* [WordPress Volunteer Project Manager](https://wordpress.org/plugins/volunteer-project-manager/)

== Installation ==

1. Download the plugin file.
1. Unzip the file into your 'wp-content/plugins/' directory.
1. Go to your WordPress administration panel and activate the plugin.
1. In the Waitlist Details meta box on any post editing screen, enter a new list name, then publish the post. A join button will automatically appear on the published post.

== Changelog ==

= Verson 0.1 =

* Initial release.

== Other notes ==

Maintaining this plugin is a labor of love. However, if you like it, please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Waitlists%20for%20WordPress&amp;item_number=wp-waitlists&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted) for your use of the plugin or, better yet, contributing directly to [my's Cyberbusking fund](http://Cyberbusking.org/). (Publishing royalties ain't exactly the lucrative income it used to be, y'know?) Your support is appreciated!
