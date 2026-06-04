<?php
/**
 * Proxy settings access.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy;

use WordPress\AiClient\AiClient;

/**
 * Reads and validates proxy configuration.
 */
final class Settings {
	/**
	 * Provider ID used by this plugin.
	 */
	public const PROVIDER_ID = 'proxy';

	/**
	 * Returns normalized settings.
	 *
	 * @return array{base_provider: string, base_url: string, request_params: array<string, string>}
	 */
	public static function get(): array {
		$value = get_option(Plugin::OPTION_NAME, array());
		if (! is_array($value)) {
			$value = array();
		}

		return array(
			'base_provider' => isset($value['base_provider']) && is_string($value['base_provider'])
				? sanitize_key($value['base_provider'])
				: '',
			'base_url'      => isset($value['base_url']) && is_string($value['base_url'])
				? self::normalize_base_url($value['base_url'])
				: '',
			'request_params' => isset($value['request_params'])
				? self::sanitize_request_params($value['request_params'])
				: array(),
		);
	}

	/**
	 * Checks whether the proxy has enough configuration to run.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$settings = self::get();

		return '' !== $settings['base_provider']
			&& '' !== $settings['base_url']
			&& self::is_valid_base_provider($settings['base_provider']);
	}

	/**
	 * Sanitizes raw settings.
	 *
	 * @param mixed $value Raw settings.
	 * @return array{base_provider: string, base_url: string, request_params: array<string, string>}
	 */
	public static function sanitize($value): array {
		if (! is_array($value)) {
			return array(
				'base_provider'  => '',
				'base_url'       => '',
				'request_params' => array(),
			);
		}

		$value         = wp_unslash($value);
		$base_provider = isset($value['base_provider']) && is_scalar($value['base_provider'])
			? sanitize_key((string) $value['base_provider'])
			: '';
		$base_url      = isset($value['base_url']) && is_scalar($value['base_url'])
			? self::normalize_base_url((string) $value['base_url'])
			: '';
		$request_params = isset($value['request_params'])
			? self::sanitize_request_params($value['request_params'])
			: array();

		if (! self::is_valid_base_provider($base_provider)) {
			$base_provider = '';
		}

		return array(
			'base_provider'  => $base_provider,
			'base_url'       => $base_url,
			'request_params' => $request_params,
		);
	}

	/**
	 * Sanitizes optional request query parameters.
	 *
	 * Accepts either an associative array or query-string/newline formatted text.
	 *
	 * @param mixed $value Raw request parameters.
	 * @return array<string, string>
	 */
	public static function sanitize_request_params($value): array {
		$params = array();

		if (is_array($value)) {
			if (isset($value['keys'], $value['values']) && is_array($value['keys']) && is_array($value['values'])) {
				foreach ($value['keys'] as $index => $key) {
					$param_value = $value['values'][$index] ?? '';
					if (! is_scalar($key) || ! is_scalar($param_value)) {
						continue;
					}

					$key = self::sanitize_request_param_key((string) $key);
					if ('' === $key) {
						continue;
					}

					$params[$key] = sanitize_text_field((string) $param_value);
				}

				return $params;
			}

			foreach ($value as $key => $param_value) {
				if (! is_scalar($key) || ! is_scalar($param_value)) {
					continue;
				}

				$key = self::sanitize_request_param_key((string) $key);
				if ('' === $key) {
					continue;
				}

				$params[$key] = sanitize_text_field((string) $param_value);
			}

			return $params;
		}

		if (! is_scalar($value)) {
			return $params;
		}

		$raw_pairs = preg_split('/[\r\n&]+/', (string) $value);
		if (! is_array($raw_pairs)) {
			return $params;
		}

		foreach ($raw_pairs as $raw_pair) {
			$raw_pair = trim($raw_pair);
			if ('' === $raw_pair) {
				continue;
			}

			$parts = explode('=', $raw_pair, 2);
			$key   = self::sanitize_request_param_key(rawurldecode(trim($parts[0])));
			if ('' === $key) {
				continue;
			}

			$param_value  = isset($parts[1]) ? rawurldecode(trim($parts[1])) : '';
			$params[$key] = sanitize_text_field($param_value);
		}

		return $params;
	}

	/**
	 * Sanitizes a request parameter key.
	 *
	 * @param string $key Parameter key.
	 * @return string
	 */
	private static function sanitize_request_param_key(string $key): string {
		$key = trim($key);
		if ('' === $key || ! preg_match('/^[A-Za-z0-9_.~-]+$/', $key)) {
			return '';
		}

		return $key;
	}

	/**
	 * Returns selectable base providers.
	 *
	 * @return array<string, string> Provider ID to display name.
	 */
	public static function get_base_provider_options(): array {
		$options = array();

		if (! function_exists('wp_get_connectors') || ! class_exists(AiClient::class)) {
			return $options;
		}

		$registry = AiClient::defaultRegistry();
		foreach ((array) wp_get_connectors() as $connector_id => $connector_data) {
			if (! is_string($connector_id) || self::PROVIDER_ID === $connector_id || ! is_array($connector_data)) {
				continue;
			}

			if (($connector_data['type'] ?? '') !== 'ai_provider') {
				continue;
			}

			if (isset($connector_data['plugin']['is_active']) && is_callable($connector_data['plugin']['is_active']) && ! (bool) call_user_func($connector_data['plugin']['is_active'])) {
				continue;
			}

			if (! $registry->hasProvider($connector_id)) {
				continue;
			}

			$options[$connector_id] = isset($connector_data['name']) && is_string($connector_data['name'])
				? $connector_data['name']
				: $connector_id;
		}

		asort($options);

		return $options;
	}

	/**
	 * Checks whether a provider can be selected as the proxy base.
	 *
	 * @param string $provider_id Provider ID.
	 * @return bool
	 */
	public static function is_valid_base_provider(string $provider_id): bool {
		if ('' === $provider_id || self::PROVIDER_ID === $provider_id || ! class_exists(AiClient::class)) {
			return false;
		}

		return AiClient::defaultRegistry()->hasProvider($provider_id);
	}

	/**
	 * Normalizes and validates a proxy base URL.
	 *
	 * @param string $url Raw URL.
	 * @return string Normalized URL, or empty string when invalid.
	 */
	public static function normalize_base_url(string $url): string {
		$url = trim($url);
		if ('' === $url) {
			return '';
		}

		$url = esc_url_raw($url, array('http', 'https'));
		if (! $url) {
			return '';
		}

		$parts = wp_parse_url($url);
		if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return '';
		}

		if (! in_array($parts['scheme'], array('http', 'https'), true)) {
			return '';
		}

		return untrailingslashit($url);
	}

	/**
	 * Checks whether internal HTTP proxy URLs are explicitly allowed.
	 *
	 * @return bool
	 */
	public static function allows_internal_http(): bool {
		$value = getenv('AI_PROVIDER_PROXY_ALLOW_INTERNAL_HTTP');

		return is_string($value) && in_array(strtolower($value), array('1', 'true', 'yes', 'on'), true);
	}

	/**
	 * Checks whether a URL targets the configured proxy base URL.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public static function is_proxy_url(string $url): bool {
		$settings = self::get();
		if ('' === $settings['base_url']) {
			return false;
		}

		$base = wp_parse_url($settings['base_url']);
		$test = wp_parse_url($url);
		if (! is_array($base) || ! is_array($test)) {
			return false;
		}

		$base_host = isset($base['host']) ? strtolower((string) $base['host']) : '';
		$test_host = isset($test['host']) ? strtolower((string) $test['host']) : '';
		if ('' === $base_host || $base_host !== $test_host) {
			return false;
		}

		$base_scheme = isset($base['scheme']) ? strtolower((string) $base['scheme']) : '';
		$test_scheme = isset($test['scheme']) ? strtolower((string) $test['scheme']) : '';
		if ($base_scheme !== $test_scheme) {
			return false;
		}

		$base_port = isset($base['port']) ? (int) $base['port'] : self::default_port($base_scheme);
		$test_port = isset($test['port']) ? (int) $test['port'] : self::default_port($test_scheme);
		if ($base_port !== $test_port) {
			return false;
		}

		$base_path = isset($base['path']) ? untrailingslashit((string) $base['path']) : '';
		$test_path = isset($test['path']) ? (string) $test['path'] : '';
		if ('' === $base_path || '/' === $base_path) {
			return true;
		}

		return 0 === strpos($test_path, $base_path . '/') || $test_path === $base_path;
	}

	/**
	 * Returns the default port for a scheme.
	 *
	 * @param string $scheme URL scheme.
	 * @return int
	 */
	private static function default_port(string $scheme): int {
		return 'https' === $scheme ? 443 : 80;
	}
}
