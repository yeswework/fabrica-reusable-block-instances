<?php

namespace Fabrica\ReusableBlockInstances;

use WP_Error;

if (!defined('WPINC')) { die(); }

class Base {
	public const NS = 'fabrica-reusable-block-instances';

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
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAssets']);
		add_action('restrict_manage_posts', [__CLASS__, 'addPostTypesFilter']);
		add_action('wp_ajax_' . self::NS . '_get_block_instances', [__CLASS__, 'getBlockInstance']);
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
		$args->labels->name = __('Reusable Blocks', self::NS);
		$args->labels->menu_name = __('Reusable Blocks', self::NS);
		$args->menu_icon = 'dashicons-screenoptions';
		$args->menu_position = 58;
	}

	public static function enqueueAssets() {
		// script
		$base = 'js/admin.js';
		$path = path_join(plugin_dir_path(__DIR__), $base);
		$url = plugins_url($base, __DIR__);
		if (!file_exists($path)) { return; }
		wp_enqueue_script(Base::NS . '-script', $url , [], filemtime($path), true);
		wp_localize_script(Base::NS . '-script', 'app', [
			'ns' => Base::NS,
			'url' => [
				'ajax' => admin_url('admin-ajax.php'),
				'edit' => admin_url('edit.php'),
			],
		]);
	}

	public static function addPostTypesFilter($type) {
		if ($type != 'wp_block') { return; }
		$filteredPostType = empty($_GET['block_post_type']) ? 'all' : $_GET['block_post_type'];
		$postTypes = self::getPostTypes(true); ?>
		<select name="block_post_type" id="block_post_type">
			<option value="all" <?php selected('all', $filteredPostType); ?>><?= __('All post types', self::NS); ?></option><?php
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
		$where .= $wpdb->prepare(" AND post_type IN " . self::getPostTypesPlaceholder()
			. " AND INSTR(post_content, '{\"ref\":%d}') ", ...array_merge(self::getPostTypes(), [$_GET['block_instances']]));
		return $where;
	}

	public static function modifyPageTitle($safeText, $text) {
		if ($safeText !== __('Reusable Blocks', self::NS)) { return $safeText; } // The text detected here is our own modification from line 47, by default it would be 'Blocks'
		return __('Instances of Reusable Block', self::NS) . ' ‘' . get_the_title($_GET['block_instances']) . '’';
	}

	public static function removeQuickLinks($views) {
		return '';
	}

	/* 'Post type' column */

	public static function addPostTypeColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('postType' => __('Post Type', self::NS)));
	}

	public static function displayPostTypeColumn($column, $id) {
		if ($column != 'postType') { return; }
		$post = get_post($id);
		if (!empty($post)) {
			echo $post->post_type;
			return;
		}
		echo '—';
	}

	public static function displayPageColumn($column, $id) {
		echo 'page';
	}

	/* 'Instances' column */

	public static function addInstancesColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('instances' => __('Instances', self::NS)));
	}

	public static function displayInstancesColumn($column, $id) {
		if ($column != 'instances') { return; } ?>

		<span class="<?= self::NS ?>-instances <?= self::NS ?>-instances--waiting" data-block-id="<?= $id ?>" style="display:flex">waiting to load...</span><?php
	}

	private static function getPostTypes($all = false) {
		if (!$all && !empty($_REQUEST['block_post_type'])) {
			return [$_REQUEST['block_post_type']]; // Post type selected
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

	private static function getPostTypesPlaceholder() {
		return "(" . implode(', ', array_fill(0, count(self::getPostTypes()), '%s')) . ")";
	}

	public static function getBlockInstance() {
		$id = $_POST['block_id'];
		global $wpdb;
		$query = $wpdb->prepare("SELECT COUNT(*) AS instances
			FROM {$wpdb->posts}
			WHERE INSTR(post_content, '{\"ref\":%d}')
				AND post_type IN " . self::getPostTypesPlaceholder()
				. " AND post_status IN ('publish', 'draft', 'future', 'pending')",
			$id,
			...self::getPostTypes()
		);

		wp_send_json_success(['instances' => (int) $wpdb->get_var($query)]);
	}
}

Base::init();