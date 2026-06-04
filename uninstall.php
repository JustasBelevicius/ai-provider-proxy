<?php
/**
 * Uninstall handler for AI Provider Proxy.
 *
 * @package JustasB\AiProviderProxy
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('ai_provider_proxy_settings');

delete_option('ai_provider_proxy_settings');
