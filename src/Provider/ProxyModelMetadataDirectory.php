<?php
/**
 * Proxy model metadata directory.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use JustasB\AiProviderProxy\Settings;

/**
 * Mirrors text-generation model metadata from the configured base provider.
 */
final class ProxyModelMetadataDirectory implements ModelMetadataDirectoryInterface {
	/**
	 * Cached model metadata.
	 *
	 * @var array<string, ModelMetadata>|null
	 */
	private ?array $models = null;

	/**
	 * Lists text-generation model metadata.
	 *
	 * @return list<ModelMetadata>
	 */
	public function listModelMetadata(): array {
		return array_values($this->get_model_map());
	}

	/**
	 * Checks whether model metadata exists.
	 *
	 * @param string $modelId Model ID.
	 * @return bool
	 */
	public function hasModelMetadata(string $modelId): bool {
		$models = $this->get_model_map();

		return isset($models[$modelId]);
	}

	/**
	 * Gets metadata for a model.
	 *
	 * @param string $modelId Model ID.
	 * @return ModelMetadata
	 */
	public function getModelMetadata(string $modelId): ModelMetadata {
		$models = $this->get_model_map();
		if (! isset($models[$modelId])) {
			throw new InvalidArgumentException(sprintf('Model metadata not found: %s', $modelId));
		}

		return $models[$modelId];
	}

	/**
	 * Gets the model metadata map.
	 *
	 * @return array<string, ModelMetadata>
	 */
	private function get_model_map(): array {
		if (null !== $this->models) {
			return $this->models;
		}

		$this->models = array();
		$settings     = Settings::get();
		if ('' === $settings['base_provider'] || '' === $settings['base_url'] || ! Settings::is_valid_base_provider($settings['base_provider'])) {
			return $this->models;
		}

		try {
			$registry            = AiClient::defaultRegistry();
			$base_provider_class = $registry->getProviderClassName($settings['base_provider']);
			$directory           = clone $base_provider_class::modelMetadataDirectory();

			if ($directory instanceof WithRequestAuthenticationInterface) {
				$directory->setRequestAuthentication(new NoOpRequestAuthentication());
			}

			if ($directory instanceof WithHttpTransporterInterface && is_subclass_of($base_provider_class, AbstractApiProvider::class)) {
				$directory->setHttpTransporter(
					new UrlRewritingHttpTransporter(
						$registry->getHttpTransporter(),
						$base_provider_class::url(),
						$settings['base_url'],
						$settings['request_params']
					)
				);
			}

			foreach ($directory->listModelMetadata() as $model_metadata) {
				if (! $this->supports_text_generation($model_metadata)) {
					continue;
				}

				$this->models[$model_metadata->getId()] = $model_metadata;
			}
		} catch (\Throwable $e) {
			$this->models = array();
		}

		if (empty($this->models)) {
			$this->models = $this->get_fallback_text_model_map($settings['base_provider']);
		}

		return $this->models;
	}

	/**
	 * Checks whether a model supports text generation.
	 *
	 * @param ModelMetadata $model_metadata Model metadata.
	 * @return bool
	 */
	private function supports_text_generation(ModelMetadata $model_metadata): bool {
		foreach ($model_metadata->getSupportedCapabilities() as $capability) {
			if ($capability->isTextGeneration()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds fallback metadata from the AI plugin's preferred models.
	 *
	 * This avoids requiring a remote authenticated model-list request before the
	 * proxy itself can be selected for text generation.
	 *
	 * @param string $base_provider Base provider ID.
	 * @return array<string, ModelMetadata>
	 */
	private function get_fallback_text_model_map(string $base_provider): array {
		$models           = array();
		$preferred_models = $this->get_preferred_text_models();

		foreach ($preferred_models as $preferred_model) {
			if (! is_array($preferred_model) || 2 !== count($preferred_model) || $base_provider !== $preferred_model[0]) {
				continue;
			}

			$model_id = (string) $preferred_model[1];
			if (isset($models[$model_id])) {
				continue;
			}

			$models[$model_id] = $this->create_fallback_text_model_metadata($model_id);
		}

		return $models;
	}

	/**
	 * Returns preferred text models without relying on the AI plugin function.
	 *
	 * @return array<int, array{string, string}>
	 */
	private function get_preferred_text_models(): array {
		$preferred_models = array(
			array('anthropic', 'claude-sonnet-4-6'),
			array('google', 'gemini-3-flash-preview'),
			array('google', 'gemini-2.5-flash'),
			array('openai', 'gpt-5.4-mini'),
			array('openai', 'gpt-4.1-mini'),
		);

		return (array) apply_filters('wpai_preferred_text_models', $preferred_models);
	}

	/**
	 * Creates broad text-generation metadata for a known preferred model.
	 *
	 * @param string $model_id Model ID.
	 * @return ModelMetadata
	 */
	private function create_fallback_text_model_metadata(string $model_id): ModelMetadata {
		$text_modality = ModalityEnum::text();

		return new ModelMetadata(
			$model_id,
			$model_id,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			array(
				new SupportedOption(OptionEnum::inputModalities(), array(array($text_modality))),
				new SupportedOption(OptionEnum::outputModalities(), array(array($text_modality))),
				new SupportedOption(OptionEnum::systemInstruction()),
				new SupportedOption(OptionEnum::candidateCount()),
				new SupportedOption(OptionEnum::maxTokens()),
				new SupportedOption(OptionEnum::temperature()),
				new SupportedOption(OptionEnum::topP()),
				new SupportedOption(OptionEnum::stopSequences()),
				new SupportedOption(OptionEnum::presencePenalty()),
				new SupportedOption(OptionEnum::frequencyPenalty()),
				new SupportedOption(OptionEnum::outputMimeType(), array('text/plain', 'application/json')),
				new SupportedOption(OptionEnum::outputSchema()),
				new SupportedOption(OptionEnum::customOptions()),
			)
		);
	}
}
