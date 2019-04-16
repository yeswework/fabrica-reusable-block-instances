<?php
/*
Plugin Name: Fabrica Reusable Block Instances
Plugin URI: https://fabri.ca/
Description: Shows you how many times, and where, a Reusable Block has been used.
Version: 0.1.0
Author: Fabrica
Author URI: https://fabri.ca/
Text Domain: fabrica-reusable-block-instances
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

namespace Fabrica\ReusableBlockInstances;

if (!defined('WPINC')) { die(); }

class ReusableBlocks {
	public static $textDomain = 'fabrica-reusable-block-instances';

	// Insert element into array after given key, from https://gist.github.com/wpscholar/0deadce1bbfa4adb4e4c
	public static function arrayInsertAfter(array $array, $key, array $new) {
		$keys = array_keys($array);
		$index = array_search($key, $keys, true);
		$pos = false === $index ? count($array) : $index + 1;
		return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
	}

	public function __construct() {
		if (!is_admin()) { return; }
		add_action('registered_post_type', array($this, 'makePublic'), 10, 2);
		if (isset($_GET, $_GET['block_instances']) && is_numeric($_GET['block_instances'])) {
			add_action('pre_get_posts', array($this, 'modifyListQuery'));
			add_filter('esc_html', array($this, 'modifyPageTitle'), 1000, 2);
			add_filter('views_edit-wp_block', array($this, 'removeQuickLinks'), 1000, 1);
		} else {
			add_filter('manage_wp_block_posts_columns', array($this, 'addColumns'));
			add_action('manage_wp_block_posts_custom_column' , array($this, 'displayColumn'), 1000, 2);
		}
	}

	public function makePublic($type, $args) {
		if ($type != 'wp_block') { return; }
		$args->show_in_menu = true;
		$args->_builtin = false;
		$args->labels->name = __('Reusable Blocks');
		$args->labels->menu_name = __('Reusable Blocks');
		$args->menu_icon = 'dashicons-screenoptions';
		$args->menu_position = 58;
	}

	public function modifyListQuery($query) {
		$query->set('post_type', 'any');
		add_filter('posts_where', array($this, 'modifyPostsWhere'));
	}

	public function modifyPostsWhere($where) {
		$where .= ' AND post_content LIKE \'%<!-- wp:block {"ref":' . $_GET['block_instances'] . '}%\' ';
		return $where;
	}

	public function modifyPageTitle($safeText, $text) {
		if ($safeText != __('Reusable Blocks')) { return $safeText; } // The text detected here is our own modification from line 47, by default it would be 'Blocks'
		return __('Instances of Reusable Block', self::$textDomain) . ' ‘' . get_the_title($_GET['block_instances']) . '’';
	}

	public function removeQuickLinks($views) {
		return '';
	}

	public function addColumns($columns) {
		return self::arrayInsertAfter($columns, 'title', array('instances' => __('Instances', self::$textDomain)));
	}

	public function displayColumn($column, $ID) {
		if ($column != 'instances') { return; }
		global $wpdb;
		$tag = '<!-- wp:block {"ref":' . $ID . '}';
		$search = '%' . $wpdb->esc_like($tag) . '%';
		$wpdb->query('set @csum := 0;');
		$sql = "SELECT ID, (@count := (LENGTH(post_content) - LENGTH(REPLACE(post_content, %s, ''))) DIV LENGTH(%s)) AS instances, (@csum := @csum + @count) AS total_instances FROM {$wpdb->prefix}posts WHERE post_content LIKE %s and post_status IN ('publish', 'draft', 'future', 'pending')";
		$query = $wpdb->prepare($sql, $tag, $tag, $search);
		$results = $wpdb->get_results($query, ARRAY_A);
		$instances = (int) end($results)['total_instances'];
		if ($instances > 0) {
			echo '<a href="' . admin_url('edit.php?post_type=wp_block&block_instances=' . $ID) . '">' . $instances . '</a>';
		} else {
			echo '—';
		}
	}
}

new ReusableBlocks;
