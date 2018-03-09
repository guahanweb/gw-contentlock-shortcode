<?php
/**
 * Content Lock: UNLOCKED template
 *
 * This template can be overridden by copying this file to your-theme/contentlock-plugin-templates/content-unlocked.php
 *
 * @author Garth Henson
 * @package GW/ContentLock/Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit; // don't allow direct access

/*
 * Available variables:
 *   $id      => ID of the content (provided in the shortcode)
 *   $since   => Actual unlock time of this content
 *   $content => Content to be displayed
 */

?><div class="contentlock unlocked contentlock-<?php echo $id; ?>">
    <div class="header unlock-message">
        <h2>
            <i class="fas fa-lock-open"></i>
            <span class="title"><?php do_action('contentlock-render_header_unlocked', $since); ?></span>
        </h2>
    </div>
    <div class="content-mask">
        <div class="content">
            <?php do_action('contentlock-render_content', $content); ?>
        </div>
    </div>
</div>

