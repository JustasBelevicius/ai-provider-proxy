# AI Provider Proxy

AI Provider Proxy is a WordPress plugin that registers a no-auth text-generation
provider for the WordPress AI Client. It delegates request construction to an
installed AI provider, then rewrites the outbound provider URL to a configured
self-hosted, internal, or VPN-reachable proxy base URL.

Use it when WordPress should talk to an internal proxy endpoint instead of
calling the upstream provider directly, while still reusing the upstream
provider's text-generation request format and model metadata.

## Requirements

- WordPress 6.9 or later.
- PHP 7.4 or later.
- WordPress AI Client classes available in the environment.
- At least one installed AI provider that is registered with the AI Client and
  supports text generation.

## Installation

1. Copy this directory to `wp-content/plugins/ai-provider-proxy`.
2. Activate **AI Provider Proxy** in WordPress.
3. Configure the plugin from **Settings > AI Provider Proxy**.

## Configuration

The settings page exposes three fields:

- **Base provider** - the installed AI provider whose text-generation request
  implementation should be reused.
- **Proxy base URL** - the base URL for the self-hosted proxy, for example
  `https://llm-proxy.example.com/v1` or `http://llm-proxy.internal/v1`.
- **Request parameters** - optional query parameters appended to every proxied
  request, for example `callerId=wordpress`.

The plugin stores settings in the `ai_provider_proxy_settings` option. On
uninstall, that option is deleted.

## How It Works

When configured, the plugin:

- registers an `AI Provider Proxy` AI connector with `authentication.method`
  set to `none`;
- registers the AI Client provider ID `proxy`;
- mirrors text-generation model metadata from the configured base provider;
- falls back to matching preferred text models when model metadata cannot be
  fetched;
- creates the selected base provider model for text generation;
- disables base-provider request authentication for the proxied request;
- rewrites URLs from the base provider URL to the configured proxy base URL;
- appends configured query parameters to proxied requests;
- prioritizes matching proxy models in the AI Client preferred text model list.

Only text-generation models are proxied.

## Internal HTTP Proxies

WordPress blocks some local/internal HTTP requests through safe HTTP checks. To
allow requests to the configured proxy URL and port, set this environment
variable:

```sh
AI_PROVIDER_PROXY_ALLOW_INTERNAL_HTTP=1
```

Accepted truthy values are `1`, `true`, `yes`, and `on`.

## REST Endpoint

Administrators can inspect the current proxy status with:

```http
GET /wp-json/ai-provider-proxy/v1/proxy
```

The response includes:

- `configured`
- `version`
- `base_provider`
- `base_url`
- `request_params`
- `providers`

## Structure

- `plugin.php` - plugin header and bootstrap.
- `src/autoload.php` - PSR-4 autoloader for `JustasB\AiProviderProxy`.
- `src/Plugin.php` - hook registration and plugin coordination.
- `src/Settings.php` - settings normalization, validation, and helpers.
- `src/Admin/` - Settings API integration and admin UI.
- `src/REST/` - REST status controller.
- `src/Provider/` - proxy provider, text model delegation, no-op auth, and URL rewriting transporter.
- `languages/` - translation files.
- `uninstall.php` - cleanup for plugin-owned options.

## Development Notes

- The plugin intentionally sends no API key. Authentication is expected to be
  handled by the proxy endpoint, the network boundary, or infrastructure around
  the proxy.
- The selected base provider must be API-based for runtime text generation.
- `readme.txt` is the WordPress.org-style plugin readme. `README.md` is the
  repository-oriented documentation.
