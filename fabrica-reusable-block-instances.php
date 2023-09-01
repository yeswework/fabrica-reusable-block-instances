<?php
/*
Plugin Name: Fabrica Reusable Block Instances
Plugin URI: https://github.com/yeswework/fabrica-reusable-block-instances/
Description: Shows you how many times, and where, a Reusable Block has been used.
Version: 1.0.6
Author: Fabrica
Author URI: https://fabri.ca/
Text Domain: fabrica-reusable-block-instances
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

namespace Fabrica\ReusableBlockInstances;

if (!defined('WPINC')) { die(); }

require_once('inc/base.php');
register_deactivation_hook(__FILE__, [Base::class, 'handlePluginDeactivation']);
