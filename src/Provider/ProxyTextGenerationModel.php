<?php
/**
 * Proxy text-generation model.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\Contracts\ApiBasedModelInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Delegates text generation to the configured base provider model.
 */
final class ProxyTextGenerationModel implements ModelInterface, TextGenerationModelInterface, ApiBasedModelInterface {
	/**
	 * Model metadata.
	 *
	 * @var ModelMetadata
	 */
	private ModelMetadata $metadata;

	/**
	 * Provider metadata.
	 *
	 * @var ProviderMetadata
	 */
	private ProviderMetadata $provider_metadata;

	/**
	 * Model config.
	 *
	 * @var ModelConfig
	 */
	private ModelConfig $config;

	/**
	 * Base provider ID.
	 *
	 * @var string
	 */
	private string $base_provider;

	/**
	 * Proxy base URL.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Query parameters to append to proxy requests.
	 *
	 * @var array<string, string>
	 */
	private array $request_params;

	/**
	 * Request options.
	 *
	 * @var RequestOptions|null
	 */
	private ?RequestOptions $request_options = null;

	/**
	 * Constructor.
	 *
	 * @param ModelMetadata    $metadata Model metadata.
	 * @param ProviderMetadata $provider_metadata Provider metadata.
	 * @param string           $base_provider Base provider ID.
	 * @param string           $base_url Proxy base URL.
	 * @param array<string, string> $request_params Query parameters to append.
	 */
	public function __construct(ModelMetadata $metadata, ProviderMetadata $provider_metadata, string $base_provider, string $base_url, array $request_params = array()) {
		$this->metadata          = $metadata;
		$this->provider_metadata = $provider_metadata;
		$this->base_provider     = $base_provider;
		$this->base_url          = $base_url;
		$this->request_params    = $request_params;
		$this->config            = ModelConfig::fromArray(array());
	}

	/**
	 * Gets model metadata.
	 *
	 * @return ModelMetadata
	 */
	public function metadata(): ModelMetadata {
		return $this->metadata;
	}

	/**
	 * Gets provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	public function providerMetadata(): ProviderMetadata {
		return $this->provider_metadata;
	}

	/**
	 * Sets model config.
	 *
	 * @param ModelConfig $config Model config.
	 * @return void
	 */
	public function setConfig(ModelConfig $config): void {
		$this->config = $config;
	}

	/**
	 * Gets model config.
	 *
	 * @return ModelConfig
	 */
	public function getConfig(): ModelConfig {
		return $this->config;
	}

	/**
	 * Sets request options.
	 *
	 * @param RequestOptions $requestOptions Request options.
	 * @return void
	 */
	public function setRequestOptions(RequestOptions $requestOptions): void {
		$this->request_options = $requestOptions;
	}

	/**
	 * Gets request options.
	 *
	 * @return RequestOptions|null
	 */
	public function getRequestOptions(): ?RequestOptions {
		return $this->request_options;
	}

	/**
	 * Generates text.
	 *
	 * @param array $prompt Prompt messages.
	 * @return GenerativeAiResult
	 */
	public function generateTextResult(array $prompt): GenerativeAiResult {
		$registry            = AiClient::defaultRegistry();
		$base_provider_class = $registry->getProviderClassName($this->base_provider);

		if (! is_subclass_of($base_provider_class, AbstractApiProvider::class)) {
			throw new RuntimeException('AI Provider Proxy requires an API-based base provider.');
		}

		$base_model = $this->create_base_model($base_provider_class);
		if (! $base_model instanceof TextGenerationModelInterface) {
			throw new RuntimeException('The selected base model does not support text generation.');
		}

		if ($base_model instanceof WithRequestAuthenticationInterface) {
			$base_model->setRequestAuthentication(new NoOpRequestAuthentication());
		}

		if ($base_model instanceof WithHttpTransporterInterface) {
			$base_model->setHttpTransporter(
				new UrlRewritingHttpTransporter(
					$registry->getHttpTransporter(),
					$base_provider_class::url(),
					$this->base_url,
					$this->request_params
				)
			);
		}

		if ($base_model instanceof ApiBasedModelInterface && $this->request_options) {
			$base_model->setRequestOptions($this->request_options);
		}

		return $base_model->generateTextResult($prompt);
	}

	/**
	 * Creates the base provider model without invoking its metadata directory.
	 *
	 * @param class-string $base_provider_class Base provider class name.
	 * @return ModelInterface
	 */
	private function create_base_model(string $base_provider_class): ModelInterface {
		try {
			$method = new \ReflectionMethod($base_provider_class, 'createModel');
			$method->setAccessible(true);

			$model = $method->invoke(null, $this->metadata, $base_provider_class::metadata());
		} catch (\ReflectionException $e) {
			throw new RuntimeException('Unable to create the base provider model.');
		}

		if (! $model instanceof ModelInterface) {
			throw new RuntimeException('The base provider did not create a valid model.');
		}

		$model->setConfig($this->config);

		return $model;
	}
}
