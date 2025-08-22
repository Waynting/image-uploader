<?php
/*
Plugin Name: Wayn Headless Images
Description: Headless-friendly image delivery from a custom directory (NAS/host) with REST endpoints, thumbnails, WebP, and optional shortcode.
Version: 1.0.0
Author: Wayn Liu
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('WHI_PATH', plugin_dir_path(__FILE__));
define('WHI_URL',  plugin_dir_url(__FILE__));

require_once WHI_PATH . 'inc/Settings.php';
require_once WHI_PATH . 'inc/Files.php';
require_once WHI_PATH . 'inc/Thumbs.php';
require_once WHI_PATH . 'inc/Rest.php';
require_once WHI_PATH . 'inc/Shortcode.php';

// Optional simple admin page for regenerating thumbs
if ( is_admin() ) {
    require_once WHI_PATH . 'tools/Regenerate.php';
}

// Activation sanity
register_activation_hook(__FILE__, function () {
    $defaults = [
        'base_dir'      => '',
        'base_url'      => '',
        'sizes'         => [480,960,1440],
        'jpeg_q'        => 80,
        'cors_origins'  => [],
    ];
    if ( ! get_option('wayn_images_options') ) {
        add_option('wayn_images_options', $defaults, '', false);
    }
});