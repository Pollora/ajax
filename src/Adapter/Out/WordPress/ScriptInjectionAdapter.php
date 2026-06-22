<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

/**
 * WordPress adapter that injects the AJAX URL as a JavaScript global.
 *
 * Hooks into `wp_head` at priority 1 to output a `<script>` tag
 * exposing `Pollora.ajaxurl` for frontend AJAX calls.
 */
class ScriptInjectionAdapter
{
    /**
     * Register a `wp_head` hook that outputs the AJAX URL as a JS variable.
     *
     * Produces: `<script>var Pollora = { ajaxurl: "…/admin-ajax.php" };</script>`
     */
    public function registerAjaxUrlScript(): void
    {
        add_action('wp_head', function (): void {
            echo '<script type="text/javascript">var Pollora = { ajaxurl: "'.esc_url(admin_url('admin-ajax.php')).'" };</script>';
        }, 1);
    }
}
