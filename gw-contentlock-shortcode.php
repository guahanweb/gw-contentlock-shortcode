<?php
/**
 * Plugin Name: GW | Content Lock
 * Plugin URI: http://www.guahanweb.com
 * Description: Lock content within a post to be time-released
 * Version: 0.1
 * Tested With: 4.3.1
 * Author: Garth Henson
 * Author URI: http://www.guahanweb.com
 * Licence: GPLv2 or later
 * Text Domain: gw
 * Domain Path: /languages
 */

use GW\ContentLock;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/autoload.php';
$plugin = ContentLock\Plugin::instance(__FILE__);
