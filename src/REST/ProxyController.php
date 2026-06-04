<?php
/**
 * REST controller scaffold.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\REST;

use JustasB\AiProviderProxy\Settings;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Provides REST endpoints for proxy operations.
 */
final class ProxyController extends WP_REST_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'ai-provider-proxy/v1';
		$this->rest_base = 'proxy';
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array($this, 'get_status'),
					'permission_callback' => array($this, 'get_status_permissions_check'),
				),
				'schema' => array($this, 'get_public_item_schema'),
			)
		);
	}

	/**
	 * Checks whether the current user can view proxy status.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	public function get_status_permissions_check(WP_REST_Request $request): bool {
		return current_user_can('manage_options');
	}

	/**
	 * Returns proxy status.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_status(WP_REST_Request $request): WP_REST_Response {
		$settings = Settings::get();

		return new WP_REST_Response(
			array(
				'configured'    => Settings::is_configured(),
				'version'       => AI_PROVIDER_PROXY_VERSION,
				'base_provider' => $settings['base_provider'],
				'base_url'      => $settings['base_url'],
				'request_params' => $settings['request_params'],
				'providers'     => Settings::get_base_provider_options(),
			)
		);
	}

	/**
	 * Retrieves the endpoint schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ($this->schema) {
			return $this->add_additional_fields_schema($this->schema);
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ai-provider-proxy-status',
			'type'       => 'object',
			'properties' => array(
				'configured' => array(
					'description' => __('Whether the proxy provider has been configured.', 'ai-provider-proxy'),
					'type'        => 'boolean',
					'context'     => array('view'),
				),
				'version'    => array(
					'description' => __('Plugin version.', 'ai-provider-proxy'),
					'type'        => 'string',
					'context'     => array('view'),
				),
				'base_provider' => array(
					'description' => __('Selected base provider.', 'ai-provider-proxy'),
					'type'        => 'string',
					'context'     => array('view'),
				),
				'base_url'  => array(
					'description' => __('Configured proxy base URL.', 'ai-provider-proxy'),
					'type'        => 'string',
					'context'     => array('view'),
				),
				'request_params' => array(
					'description' => __('Query parameters appended to proxied requests.', 'ai-provider-proxy'),
					'type'        => 'object',
					'context'     => array('view'),
				),
				'providers' => array(
					'description' => __('Available base providers.', 'ai-provider-proxy'),
					'type'        => 'object',
					'context'     => array('view'),
				),
			),
		);

		return $this->add_additional_fields_schema($this->schema);
	}
}
