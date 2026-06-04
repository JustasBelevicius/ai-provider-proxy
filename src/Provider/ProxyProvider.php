<?php
/**
 * AI Client proxy provider.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use JustasB\AiProviderProxy\Settings;

/**
 * Provider that proxies text generation through a self-hosted endpoint.
 */
final class ProxyProvider extends AbstractProvider {
	/**
	 * Creates a model instance.
	 *
	 * @param ModelMetadata    $modelMetadata Model metadata.
	 * @param ProviderMetadata $providerMetadata Provider metadata.
	 * @return ModelInterface
	 */
	protected static function createModel(ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata): ModelInterface {
		$settings = Settings::get();
		if ('' === $settings['base_provider'] || '' === $settings['base_url'] || ! Settings::is_valid_base_provider($settings['base_provider'])) {
			throw new RuntimeException('AI Provider Proxy is not configured.');
		}

		return new ProxyTextGenerationModel($modelMetadata, $providerMetadata, $settings['base_provider'], $settings['base_url'], $settings['request_params']);
	}

	/**
	 * Creates provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			Settings::PROVIDER_ID,
			'AI Provider Proxy',
			ProviderTypeEnum::server(),
			null,
			null,
			function_exists('__')
				? __('Self-hosted proxy for text-generation AI providers.', 'ai-provider-proxy')
				: 'Self-hosted proxy for text-generation AI providers.'
		);
	}

	/**
	 * Creates provider availability.
	 *
	 * @return ProviderAvailabilityInterface
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ProxyProviderAvailability();
	}

	/**
	 * Creates model metadata directory.
	 *
	 * @return ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new ProxyModelMetadataDirectory();
	}
}
