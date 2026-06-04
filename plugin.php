<?php
/**
 * Plugin Name: AI Provider Proxy
 * Plugin URI: https://github.com/WordPress/wordpress-develop
 * Description: Proxy provider scaffold for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 0.1.0
 * Author: Justas Belevicius
 * Author URI: https://make.wordpress.org/ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-proxy
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy;

if (! defined('ABSPATH')) {
	return;
}

define('AI_PROVIDER_PROXY_VERSION', '0.1.0');
define('AI_PROVIDER_PROXY_PLUGIN_FILE', __FILE__);
define('AI_PROVIDER_PROXY_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/src/autoload.php';

Plugin::get_instance()->register_hooks();
