<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;

beforeEach(function (): void {
    $GLOBALS['wp_actions'] = [];
});

afterEach(function (): void {
    unset($GLOBALS['wp_actions']);
});

describe('ScriptInjectionAdapter', function (): void {
    it('hooks a callback on wp_head with priority 1', function (): void {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();

        expect($GLOBALS['wp_actions'])->toHaveCount(1)
            ->and($GLOBALS['wp_actions'][0]['hook'])->toBe('wp_head')
            ->and($GLOBALS['wp_actions'][0]['priority'])->toBe(1);
    });

    it('prints the Pollora.ajaxurl script pointing at admin-ajax.php', function (): void {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();

        ob_start();
        ($GLOBALS['wp_actions'][0]['callback'])();
        $output = ob_get_clean();

        expect($output)->toBe('<script type="text/javascript">var Pollora = { ajaxurl: "https://example.com/wp-admin/admin-ajax.php" };</script>');
    });
});
