=== Fabrica Synced Pattern Instances ===
Contributors: yeswework
Donate link: https://fabri.ca/donate/
Tags: blocks, block, reusable, gutenberg, blockeditor, content
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.7
Requires PHP: 5.6
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Shows you how many times, and where, a Synced Pattern has been used.

== Description ==

Adds some basic but vital indexing functionality for Synced Patterns (previously called Reusable Blocks until WP 6.3):

* Shows Patterns in the main WordPress admin menu
* Adds a column to the Patterns list indicating how many times a Synced Pattern appears throughout the site
* This count links to a list of all the content (Posts, Pages, and other public Post Types) which includes the block

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

= 1.0.7 =
* Support renamed Synced Patterns in WP 6.3
* Distinguish synced and unsynced patterns

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
