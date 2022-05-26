<?php
/*
Plugin Name: Fabrica Reusable Block Instances
Plugin URI: https://github.com/yeswework/fabrica-reusable-block-instances/
Description: Shows you how many times, and where, a Reusable Block has been used.
Version: 1.0.4
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

	public static function init() {
		if (!is_admin()) { return; }
		add_action('registered_post_type', [__CLASS__, 'makePublic'], 10, 2);
		add_action('restrict_manage_posts', [__CLASS__, 'addPostTypesFilter']);
		if (isset($_GET, $_GET['block_instances']) && is_numeric($_GET['block_instances'])) {
			add_action('pre_get_posts', [__CLASS__, 'modifyListQuery']);
			add_filter('esc_html', [__CLASS__, 'modifyPageTitle'], 1000, 2);
			add_filter('views_edit-wp_block', [__CLASS__, 'removeQuickLinks'], 1000, 1);
			add_filter('manage_wp_block_posts_columns', [__CLASS__, 'addPostTypeColumn']);
			add_action('manage_posts_custom_column' , [__CLASS__, 'displayPostTypeColumn'], 1000, 2);
			add_action('manage_pages_custom_column' , [__CLASS__, 'displayPageColumn'], 1000, 2);
		} else {
			add_filter('manage_wp_block_posts_columns', [__CLASS__, 'addInstancesColumn']);
			add_action('manage_wp_block_posts_custom_column' , [__CLASS__, 'displayInstancesColumn'], 1000, 2);
		}
	}

	public static function makePublic($type, $args) {
		if ($type != 'wp_block') { return; }
		$args->show_in_menu = true;
		$args->_builtin = false;
		$args->labels->name = __('Reusable Blocks', self::$textDomain);
		$args->labels->menu_name = __('Reusable Blocks', self::$textDomain);
		$args->menu_icon = 'dashicons-screenoptions';
		$args->menu_position = 58;
	}

	public static function addPostTypesFilter($type) {
		if ($type != 'wp_block') { return; }
		$filteredPostType = empty($_GET['block_post_type']) ? 'all' : $_GET['block_post_type'];
		$postTypes = self::getPostTypes(true); ?>
		<select name="block_post_type" id="block_post_type">
			<option value="all" <?php selected('all', $filteredPostType); ?>><?= __('All post types', self::$textDomain); ?></option><?php
			foreach ($postTypes as $postType) { ?>
				<option value="<?= esc_attr($postType); ?>" <?php selected($postType, $filteredPostType); ?>><?= esc_attr(get_post_type_object($postType)->labels->name); ?></option><?php
			} ?>
		</select><?php
	}

	public static function modifyListQuery($query) {
		if ($query->get('post_type') != 'wp_block') { return; }
		$query->set('post_type', 'any');
		add_filter('posts_where', [__CLASS__, 'modifyPostsWhere']);
	}

	public static function modifyPostsWhere($where) {
		global $wpdb;
		$where .= $wpdb->prepare(" AND post_type IN " . self::getPostTypesString()
			. " AND INSTR(post_content, %s) ", $_GET['block_instances']);
		return $where;
	}

	public static function modifyPageTitle($safeText, $text) {
		if ($safeText !== __('Reusable Blocks', self::$textDomain)) { return $safeText; } // The text detected here is our own modification from line 47, by default it would be 'Blocks'
		return __('Instances of Reusable Block', self::$textDomain) . ' ‘' . get_the_title($_GET['block_instances']) . '’';
	}

	public static function removeQuickLinks($views) {
		return '';
	}

	/* 'Post type' column */

	public static function addPostTypeColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('postType' => __('Post Type', self::$textDomain)));
	}

	public static function displayPostTypeColumn($column, $ID) {
		if ($column != 'postType') { return; }
		$post = get_post($ID);
		if (!empty($post)) {
			echo $post->post_type;
			return;
		}
		echo '—';
	}

	public static function displayPageColumn($column, $ID) {
		echo 'page';
	}

	/* 'Instances' column */

	public static function addInstancesColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('instances' => __('Instances', self::$textDomain)));
	}

	public static function displayInstancesColumn($column, $ID) {
		if ($column != 'instances') { return; }
		global $wpdb;
		$query = $wpdb->prepare("SELECT COUNT(*) AS instances
			FROM {$wpdb->posts}
			WHERE INSTR(post_content, %s)
				AND post_type IN " . self::getPostTypesString()
				. " AND post_status IN ('publish', 'draft', 'future', 'pending')", $ID);
		$instances = (int) $wpdb->get_var($query);
		if ($instances > 0) {
			echo '<a href="' . admin_url('edit.php?post_type=wp_block&block_instances=' . $ID) . '">' . $instances . '</a>';
		} else {
			echo '—';
		}
	}

	private static function getPostTypes($all = false) {
		if (!$all && $_GET['block_post_type']) {
			return [$_GET['block_post_type']]; // Post type selected
		}

		// Fetch public post types and filter them through user's whitelist
		$postTypes = array_filter(
			get_post_types(['public' => true]),
			function($postType) {
				return $postType != 'attachment' && apply_filters('fbi_post_types_whitelist', $postType);
			}
		);
		return array_keys($postTypes);
	}

	private static function getPostTypesString() {
		return "('" . implode("', '", self::getPostTypes()) . "')";
	}
}

ReusableBlocks::init();
