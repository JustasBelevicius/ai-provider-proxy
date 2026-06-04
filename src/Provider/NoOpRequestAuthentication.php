<?php
/**
 * No-op request authentication.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Satisfies provider implementations that expect an auth object without adding credentials.
 */
final class NoOpRequestAuthentication implements RequestAuthenticationInterface {
	/**
	 * Returns the request unchanged.
	 *
	 * @param Request $request Request.
	 * @return Request
	 */
	public function authenticateRequest(Request $request): Request {
		return $request;
	}

	/**
	 * Gets JSON schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function getJsonSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
		);
	}
}
