<?php
/**
 * Main plugin loader.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy;

use WordPress\AiClient\AiClient;
use JustasB\AiProviderProxy\Admin\SettingsPage;
use JustasB\AiProviderProxy\Provider\ProxyProvider;
use JustasB\AiProviderProxy\REST\ProxyController;

/**
 * Coordinates plugin hooks.
 */
final class Plugin {
	/**
	 * Option name for proxy configuration.
	 */
	public const OPTION_NAME = 'ai_provider_proxy_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Gets the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action('init', array($this, 'load_textdomain'));
		add_action('init', array($this, 'register_provider'), 5);
		add_action('wp_connectors_init', array($this, 'register_connector'));
		add_filter('wpai_has_ai_credentials', array($this, 'filter_has_ai_credentials'), 10, 2);
		add_filter('wpai_pre_has_valid_credentials_check', array($this, 'filter_has_valid_credentials'));
		add_filter('wpai_preferred_text_models', array($this, 'filter_preferred_text_models'));
		add_filter('http_request_host_is_external', array($this, 'filter_proxy_host_is_external'), 10, 3);
		add_filter('http_allowed_safe_ports', array($this, 'filter_proxy_allowed_safe_ports'), 10, 3);

		if (is_admin()) {
			$settings_page = new SettingsPage();
			$settings_page->register_hooks();
		}

		add_action(
			'rest_api_init',
			static function (): void {
				$controller = new ProxyController();
				$controller->register_routes();
			}
		);
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ai-provider-proxy',
			false,
			dirname(plugin_basename(AI_PROVIDER_PROXY_PLUGIN_FILE)) . '/languages'
		);
	}

	/**
	 * Registers the proxy provider with the AI Client.
	 *
	 * @return void
	 */
	public function register_provider(): void {
		if (! class_exists(AiClient::class)) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		if ($registry->hasProvider(Settings::PROVIDER_ID)) {
			return;
		}

		$registry->registerProvider(ProxyProvider::class);
	}

	/**
	 * Registers connector metadata for the proxy provider.
	 *
	 * The AI Client provider is usually auto-discovered by Core's connector
	 * registry. This explicit registration/override adds plugin metadata and
	 * keeps the connector auth method set to none.
	 *
	 * @param object $registry Connector registry.
	 * @return void
	 */
	public function register_connector($registry): void {
		if (! is_object($registry) || ! method_exists($registry, 'register')) {
			return;
		}

		if (method_exists($registry, 'is_registered') && $registry->is_registered(Settings::PROVIDER_ID)) {
			if (! method_exists($registry, 'unregister')) {
				return;
			}

			$registry->unregister(Settings::PROVIDER_ID);
		}

		$registry->register(
			Settings::PROVIDER_ID,
			array(
				'name'           => __('AI Provider Proxy', 'ai-provider-proxy'),
				'description'    => __('Self-hosted proxy for text-generation AI providers.', 'ai-provider-proxy'),
				'type'           => 'ai_provider',
				'authentication' => array(
					'method' => 'none',
				),
				'plugin'         => array(
					'file'      => plugin_basename(AI_PROVIDER_PROXY_PLUGIN_FILE),
					'is_active' => '__return_true',
				),
			)
		);
	}

	/**
	 * Allows the AI plugin to treat the configured no-auth proxy as provider setup.
	 *
	 * @param bool                 $has_credentials Whether AI credentials are available.
	 * @param array<string, mixed> $connectors Registered connectors.
	 * @return bool
	 */
	public function filter_has_ai_credentials(bool $has_credentials, array $connectors): bool {
		if ($has_credentials) {
			return true;
		}

		return isset($connectors[Settings::PROVIDER_ID]) && Settings::is_configured();
	}

	/**
	 * Allows the AI plugin validity check to pass for the configured no-auth proxy.
	 *
	 * @param bool|null $has_valid_credentials Existing pre-check result.
	 * @return bool|null
	 */
	public function filter_has_valid_credentials($has_valid_credentials) {
		if (null !== $has_valid_credentials) {
			return $has_valid_credentials;
		}

		return Settings::is_configured() ? true : null;
	}

	/**
	 * Prioritizes the proxy for text-generation features.
	 *
	 * @param array<int, array{string, string}> $preferred_models Preferred provider/model pairs.
	 * @return array<int, array{string, string}>
	 */
	public function filter_preferred_text_models(array $preferred_models): array {
		if (! Settings::is_configured()) {
			return $preferred_models;
		}

		$settings     = Settings::get();
		$proxy_models = array();

		foreach ($preferred_models as $preferred_model) {
			if (! is_array($preferred_model) || 2 !== count($preferred_model)) {
				continue;
			}

			if ($settings['base_provider'] !== $preferred_model[0]) {
				continue;
			}

			$proxy_models[] = array(Settings::PROVIDER_ID, $preferred_model[1]);
		}

		return array_merge($proxy_models, $preferred_models);
	}

	/**
	 * Allows safe HTTP requests to the configured internal proxy host when enabled.
	 *
	 * @param bool   $external Whether the host is external.
	 * @param string $host Requested host.
	 * @param string $url Requested URL.
	 * @return bool
	 */
	public function filter_proxy_host_is_external(bool $external, string $host, string $url): bool {
		if ($external || ! Settings::allows_internal_http()) {
			return $external;
		}

		return Settings::is_proxy_url($url);
	}

	/**
	 * Allows the configured internal proxy port for safe HTTP requests when enabled.
	 *
	 * @param int[]  $allowed_ports Allowed ports.
	 * @param string $host Requested host.
	 * @param string $url Requested URL.
	 * @return int[]
	 */
	public function filter_proxy_allowed_safe_ports(array $allowed_ports, string $host, string $url): array {
		if (! Settings::allows_internal_http() || ! Settings::is_proxy_url($url)) {
			return $allowed_ports;
		}

		$parts = wp_parse_url($url);
		if (! is_array($parts) || empty($parts['port'])) {
			return $allowed_ports;
		}

		$allowed_ports[] = (int) $parts['port'];

		return array_values(array_unique($allowed_ports));
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
