<?php
/**
 * Content Lock: LOCKED template
 *
 * This template can be overridden by copying this file to your-theme/contentlock-plugin-templates/content-locked.php
 *
 * @author Garth Henson
 * @package GW/ContentLock/Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit; // don't allow direct access

/*
 * Available variables:
 *   $id      => ID of the content (provided in the shortcode)
 *   $release => number of seconds left until release
 *   $mask    => message to display when locked
 *   $format  => string to render for countdown timer - {{TIME}} replaced with countdown
 */

?><div class="contentlock locked contentlock-<?php echo $id; ?>" data-contentlock="<?php echo $id; ?>" data-locktime="<?php echo $release; ?>" data-post="<?php echo $post; ?>">
    <div class="header lock-counter">
        <h2>
            <i class="fas fa-lock"></i>
            <span class="title"><?php do_action('contentlock-render_header_locked', $release); ?></span>
        </h2>
    </div>
    <div class="content-mask">
        <div class="content">
            <h3><?php echo $mask; ?></h3>
        </div>
    </div>
</div>
