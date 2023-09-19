<?php

namespace Fabrica\ReusableBlockInstances;

use Error;
use WP_Error;

if (!defined('WPINC')) { die(); }

class Base {
	public const NS = 'fabrica-reusable-block-instances';
	public const CACHE_PERIOD = 7 * DAY_IN_SECONDS;

	// Insert element into array after given key, from https://gist.github.com/wpscholar/0deadce1bbfa4adb4e4c
	public static function arrayInsertAfter(array $array, $key, array $new) {
		$keys = array_keys($array);
		$index = array_search($key, $keys, true);
		$pos = false === $index ? count($array) : $index + 1;
		return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
	}

	public static function init() {
		add_action('post_updated', [__CLASS__, 'handlePostUpdate'], 10, 3);
		if (!is_admin()) { return; }
		add_filter('gettext_' . self::NS, [__CLASS__, 'translateText']);
		add_action('registered_post_type', [__CLASS__, 'makePublic'], 10, 2);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAssets']);
		add_action('restrict_manage_posts', [__CLASS__, 'addPostTypesFilter']);
		add_action('wp_ajax_' . self::NS . '_get_block_instances', [__CLASS__, 'getBlockInstance']);
		if (isset($_GET, $_GET['block_instances']) && is_numeric($_GET['block_instances'])) {
			add_action('pre_get_posts', [__CLASS__, 'modifyListQuery']);
			add_filter('esc_html', [__CLASS__, 'modifyPageTitle'], 1000, 2);
			add_filter('views_edit-wp_block', [__CLASS__, 'removeQuickLinks'], 1000, 1);
			add_filter('user_has_cap', [__CLASS__, 'removeRowQuickLinks'], 1000, 3);
			add_filter('manage_wp_block_posts_columns', [__CLASS__, 'addPostTypeColumn']);
			add_action('manage_posts_custom_column' , [__CLASS__, 'displayPostTypeColumn'], 1000, 2);
			add_action('manage_pages_custom_column' , [__CLASS__, 'displayPageColumn'], 1000, 2);
		} else {
			add_filter('manage_wp_block_posts_columns', [__CLASS__, 'addInstancesColumn']);
			add_action('manage_wp_block_posts_custom_column' , [__CLASS__, 'displayInstancesColumn'], 1000, 2);
		}
	}

	public static function handlePluginDeactivation() {
		// get all plugin's block instances transients
		global $wpdb;
		$transients = $wpdb->get_col("SELECT option_name
			FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fabrica-reusable-block-instances_block-%'
		");

		// delete the transients
		$prefixLength = strlen('_transient_');
		foreach ($transients as $transient) {
			delete_transient(substr($transient, $prefixLength));
		}
	}

	// `true` if current WP version support [un]synced patterns, `false` if it uses reusable blocks instead
	private static function supportsPattern() {
		return version_compare(get_bloginfo('version'), '6.3', '>=');
	}

	private static function hasPostTypeFilter() {
		return !empty($_REQUEST['block_post_type']) && $_REQUEST['block_post_type'] != 'all';
	}

	public static function translateText($text) {
		if (self::supportsPattern()) { return $text; }
		if ($text == 'Synced Patterns' || $text == 'Patterns') {
			return 'Reusable Blocks';
		} else if ($text == 'Instances of Synced Pattern') {
			return 'Instances of Reusable Block';
		}
		return $text;
	}

	public static function makePublic($type, $args) {
		if ($type != 'wp_block') { return; }
		$args->show_in_menu = true;
		$args->_builtin = false;
		$args->menu_icon = 'dashicons-screenoptions';
		$args->menu_position = 58;
		if (self::supportsPattern()) { return; }

		// labels for WP versions previous to Patterns support
		$args->labels->name = __('Reusable Blocks', self::NS);
		$args->labels->menu_name = __('Reusable Blocks', self::NS);
	}

	public static function enqueueAssets() {
		// style
		$base = 'css/admin.css';
		$path = path_join(plugin_dir_path(__DIR__), $base);
		$url = plugins_url($base, __DIR__);
		if (file_exists($path)) {
			wp_enqueue_style(Base::NS . '-style', $url , [], filemtime($path));
		}

		// script
		$base = 'js/admin.js';
		$path = path_join(plugin_dir_path(__DIR__), $base);
		$url = plugins_url($base, __DIR__);
		if (!file_exists($path)) { return; }
		wp_enqueue_script(Base::NS . '-script', $url , [], filemtime($path), true);
		wp_localize_script(Base::NS . '-script', 'fabricaReusableBlockInstances', [
			'ns' => Base::NS,
			'url' => [
				'ajax' => admin_url('admin-ajax.php'),
				'edit' => admin_url('edit.php'),
			],
			'nonce' => wp_create_nonce(self::NS),
		]);
	}

	public static function addPostTypesFilter($type) {
		if ($type != 'wp_block') { return; }
		$filteredPostType = empty($_GET['block_post_type']) ? 'all' : $_GET['block_post_type'];
		$postTypes = self::getPostTypes(true);
		if (self::hasPostTypeFilter()) {
			// add `block_instances` to form to keep in block posts list when filtering ?>
			<input type="hidden" name="block_instances" class="post_type_page" value="<?= $_REQUEST['block_instances'] ?>"><?php
		} ?>
		<select name="block_post_type" id="block_post_type">
			<option value="all" <?php selected('all', $filteredPostType); ?>><?= __('All post types', self::NS); ?></option><?php
			foreach ($postTypes as $postType) { ?>
				<option value="<?= esc_attr($postType); ?>" <?php selected($postType, $filteredPostType); ?>><?= esc_attr(get_post_type_object($postType)->labels->name); ?></option><?php
			} ?>
		</select><?php
	}

	public static function modifyListQuery($query) {
		if ($query->get('post_type') != 'wp_block') { return; }
		add_filter('posts_where', [__CLASS__, 'modifyPostsWhere']);
	}

	public static function modifyPostsWhere($where) {
		global $wpdb;
		$where = $wpdb->prepare(" AND post_type IN " . self::getPostTypesPlaceholder()
			. " AND INSTR(post_content, '{\"ref\":%d} /-->') "
			. " AND post_status IN ('publish', 'draft', 'future', 'pending', 'private')",
			...array_merge(self::getPostTypes(), [$_GET['block_instances']])
		);
		return $where;
	}

	public static function modifyPageTitle($safeText, $text) {
		if ($safeText !== __('Synced Patterns', self::NS)) { return $safeText; } // The text detected here is our own modification from `makePublic()`, by default it would be 'Blocks'
		return __('Instances of Synced Pattern', self::NS) . ' ‘' . get_the_title($_GET['block_instances']) . '’';
	}

	public static function removeQuickLinks($views) {
		return '';
	}

	// remove `delete_post` capability for post types that fail at getting the delete post link
	public static function removeRowQuickLinks($allcaps, $caps, $args) {
		if ($args[0] != 'delete_post' || empty($args[2])) { return $allcaps; }
		$postType = get_post_type($args[2]);
		if (!in_array($postType, ['wp_template', 'wp_template_part'])) { return $allcaps; }

		foreach ($caps as $cap) {
			$allcaps[$cap] = false;
		}
		return $allcaps;
	}

	/* 'Post type' column */

	public static function addPostTypeColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('postType' => __('Post Type', self::NS)));
	}

	public static function displayPostTypeColumn($column, $id) {
		if ($column != 'postType') { return; }
		$post = get_post($id);
		echo empty($post) ? '—' : $post->post_type;
	}

	public static function displayPageColumn($column, $id) {
		echo 'page';
	}

	/* 'Instances' column */

	public static function addInstancesColumn($columns) {
		return self::arrayInsertAfter($columns, 'title', array('instances' => __('Instances', self::NS)));
	}

	private static function getInstancesRef($id) {
		return self::NS . '_block-' . $id;
	}

	public static function displayInstancesColumn($column, $id) {
		if ($column != 'instances') { return; }
		$instances = self::hasPostTypeFilter() ? false: get_transient(self::getInstancesRef($id)); ?>

		<span class="<?= self::NS ?>-instances <?= empty($instances) && $instances !== "0" ? self::NS . '-instances--waiting' : '' ?>" data-block-id="<?= $id ?>"><?php
			if ($instances == '—') { ?>
				<span class="<?= self::NS ?>-instances__unsynced"><?= __('not synced', self::NS) ?></span><?php
			} else if (is_numeric($instances)) { ?>
				<a href="<?= admin_url('edit.php?post_type=wp_block&block_instances=' . $id) ?>"><?= $instances ?></a><?php
			} else { ?>
				<span title="Waiting to load" class="dashicons-before dashicons-clock"></span><?php
			} ?>
		</span><?php
	}

	private static function getPostTypes($all = false) {
		if (!$all && self::hasPostTypeFilter()) {
			return [$_REQUEST['block_post_type']]; // Post type selected
		}

		// Fetch public post types and filter them through user's whitelist
		$postTypes = array_filter(
			get_post_types(),
			fn($postType) => use_block_editor_for_post_type($postType) && apply_filters('fbi_post_types_whitelist', $postType)
		);
		return array_keys($postTypes);
	}

	private static function getPostTypesPlaceholder() {
		return "(" . implode(', ', array_fill(0, count(self::getPostTypes()), '%s')) . ")";
	}

	/* Asynchronous data loading and caching */

	public static function handlePostUpdate($postId, $newPost, $oldPost) {
		$oldIds = [];
		preg_match_all('%{"ref":(\d+)} /-->%', $oldPost->post_content, $oldIds);
		$oldIds = array_unique($oldIds[1] ?? []);
		$newIds = [];
		preg_match_all('%{"ref":(\d+)} /-->%', $newPost->post_content, $newIds);
		$newIds = array_unique($newIds[1] ?? []);
		$changedIds = array_merge(array_diff($oldIds, $newIds), array_diff($newIds, $oldIds));

		// delete cached results for every synced pattern that is new or has been completely removed from the post
		foreach ($changedIds as $id) {
			delete_transient(self::getInstancesRef($id));
		}
	}

	public static function getBlockInstance() {
		if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', self::NS)) {
			wp_send_json_error(['message' => 'invalid nonce'], 500);
		}
		if (empty($_POST['block_id'])) {
			wp_send_json_error(['message' => 'missing block id'], 500);
		}

		// check if instances for this synced pattern are cached
		$id = $_POST['block_id'];
		if (!self::hasPostTypeFilter()) {
			$transientRef = self::getInstancesRef($id);
			$instances = get_transient($transientRef);
			if (!empty($instances)) {
				wp_send_json_success(['instances' => $instances]);
			}
		}

		// not cached: get number of synced pattern instances from DB
		global $wpdb;
		$query = $wpdb->prepare("SELECT COUNT(*) AS instances, (
				SELECT meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id = %d
					AND meta_key = 'wp_pattern_sync_status'
			) AS sync_status
			FROM {$wpdb->posts}
			WHERE INSTR(post_content, '{\"ref\":%d} /-->')
				AND post_type IN " . self::getPostTypesPlaceholder()
				. " AND post_status IN ('publish', 'draft', 'future', 'pending', 'private')",
			$id, $id,
			...self::getPostTypes()
		);
		$row = $wpdb->get_row($query, ARRAY_A);
		$instances = $row['sync_status'] == 'unsynced' ? '—' : $row['instances'];
		if (!self::hasPostTypeFilter()) {
			set_transient($transientRef, $instances, self::CACHE_PERIOD);
		}

		wp_send_json_success(['instances' => $instances]);
	}
}

Base::init();
