<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

/**
 * WordPress adapter to inject the AJAX URL as a JS variable in the HTML head.
 */
class ScriptInjectionAdapter
{
    /**
     * Register the AJAX URL JS variable in the HTML head using wp_head.
     */
    public function registerAjaxUrlScript(): void
    {
        add_action('wp_head', function (): void {
            echo '<script type="text/javascript">var Pollora = { ajaxurl: "'.esc_url(admin_url('admin-ajax.php')).'" };</script>';
        }, 1);
    }
}
