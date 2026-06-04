<?php
/**
 * URL rewriting HTTP transporter.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Provider;

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Rewrites provider request URLs to the configured proxy base URL.
 */
final class UrlRewritingHttpTransporter implements HttpTransporterInterface {
	/**
	 * Wrapped transporter.
	 *
	 * @var HttpTransporterInterface
	 */
	private HttpTransporterInterface $transporter;

	/**
	 * Original provider base URL.
	 *
	 * @var string
	 */
	private string $original_base_url;

	/**
	 * Proxy base URL.
	 *
	 * @var string
	 */
	private string $proxy_base_url;

	/**
	 * Query parameters to append.
	 *
	 * @var array<string, string>
	 */
	private array $request_params;

	/**
	 * Constructor.
	 *
	 * @param HttpTransporterInterface $transporter Wrapped transporter.
	 * @param string                   $original_base_url Original base URL.
	 * @param string                   $proxy_base_url Proxy base URL.
	 * @param array<string, string>    $request_params Query parameters to append.
	 */
	public function __construct(HttpTransporterInterface $transporter, string $original_base_url, string $proxy_base_url, array $request_params = array()) {
		$this->transporter       = $transporter;
		$this->original_base_url = untrailingslashit($original_base_url);
		$this->proxy_base_url    = untrailingslashit($proxy_base_url);
		$this->request_params    = $request_params;
	}

	/**
	 * Sends a rewritten request.
	 *
	 * @param Request             $request Request.
	 * @param RequestOptions|null $options Optional request options.
	 * @return Response
	 */
	public function send(Request $request, ?RequestOptions $options = null): Response {
		return $this->transporter->send($this->rewrite_request($request), $options);
	}

	/**
	 * Rewrites a request URI when it targets the original provider base URL.
	 *
	 * @param Request $request Request.
	 * @return Request
	 */
	private function rewrite_request(Request $request): Request {
		$uri = $request->getUri();
		if (0 !== strpos($uri, $this->original_base_url)) {
			return $request;
		}

		$suffix = substr($uri, strlen($this->original_base_url));
		if ('' !== $suffix && '/' !== $suffix[0] && '?' !== $suffix[0]) {
			return $request;
		}

		$rewritten_uri = $this->proxy_base_url . $suffix;
		if (! empty($this->request_params)) {
			$rewritten_uri = add_query_arg($this->request_params, $rewritten_uri);
		}

		$data          = null !== $request->getData() ? $request->getData() : $request->getBody();

		return new Request(
			$request->getMethod(),
			$rewritten_uri,
			$request->getHeaders(),
			$data,
			$request->getOptions()
		);
	}
}
