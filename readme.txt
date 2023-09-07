=== Fabrica Synced Pattern Instances ===
Contributors: yeswework
Donate link: https://fabri.ca/donate/
Tags: blocks, block, reusable, gutenberg, blockeditor, content
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.6
Requires PHP: 5.6
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Shows you how many times, and where, a Synced Pattern has been used.

== Description ==

Provides some vital functionality missing from WP 5.X Core for users of Synced Patterns:

* Shows how many times each Synced Pattern has been used throughout the whole site
* This count links to a list of all the content which uses the block (Posts, Pages and Custom Post Types)

If you are making changes to Synced Patterns and need to keep an eye on where those changes will be seen, this plugin is for you.

This is a super-lightweight plugin, with under 100 lines of code, no settings, and it does not modify your database in any way.

Designed to work seamlessly with Fabrica Dashboard: https://wordpress.org/plugins/fabrica-dashboard/

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/fabrica-reusable-block-instances` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.

That's all. No configuration required.

== Screenshots ==

1. Synced Patterns promoted to the main menu
1. List of all Synced Patterns showing number of instances
1. List of content which uses a particular block

== Changelog ==

= 1.0.6 =
* Load results asynchronously to avoid timeouts
* Cache results for better performance
* Include results for all post types that support blocks

= 1.0.5 =
* Minor bugfixes and improvements

= 1.0.4 =
* Further query performance optimizations
* Filter by post type

= 1.0.3 =
* Performance optimization and new post type column

= 1.0.2 =
* Minor bugfixes

= 1.0.1 =
* Uses a more efficient query to count instances

= 1.0 =
* First version
