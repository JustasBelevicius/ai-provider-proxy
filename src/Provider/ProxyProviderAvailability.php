<?php
/**
 * Proxy provider availability.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use JustasB\AiProviderProxy\Settings;

/**
 * Reports whether the proxy has usable settings.
 */
final class ProxyProviderAvailability implements ProviderAvailabilityInterface {
	/**
	 * Checks whether the provider is configured.
	 *
	 * @return bool
	 */
	public function isConfigured(): bool {
		return Settings::is_configured();
	}
}
