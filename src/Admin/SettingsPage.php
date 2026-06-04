<?php
/**
 * Admin settings page scaffold.
 *
 * @package JustasB\AiProviderProxy
 */

declare(strict_types=1);

namespace JustasB\AiProviderProxy\Admin;

use JustasB\AiProviderProxy\Plugin;
use JustasB\AiProviderProxy\Settings;

/**
 * Registers proxy settings.
 */
final class SettingsPage {
	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_menu', array($this, 'register_page'));
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'ai_provider_proxy',
			Plugin::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __('AI Provider Proxy settings.', 'ai-provider-proxy'),
				'sanitize_callback' => array(Settings::class, 'sanitize'),
				'default'           => array(
					'base_provider'  => '',
					'base_url'       => '',
					'request_params' => array(),
				),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Registers the settings page.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_options_page(
			__('AI Provider Proxy', 'ai-provider-proxy'),
			__('AI Provider Proxy', 'ai-provider-proxy'),
			'manage_options',
			'ai-provider-proxy',
			array($this, 'render_page')
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Sorry, you are not allowed to manage these settings.', 'ai-provider-proxy'));
		}

		$settings  = Settings::get();
		$providers = Settings::get_base_provider_options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields('ai_provider_proxy'); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ai-provider-proxy-base-provider"><?php esc_html_e('Base provider', 'ai-provider-proxy'); ?></label>
							</th>
							<td>
								<select id="ai-provider-proxy-base-provider" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[base_provider]">
									<option value=""><?php esc_html_e('Select a provider', 'ai-provider-proxy'); ?></option>
									<?php foreach ($providers as $provider_id => $provider_name) : ?>
										<option value="<?php echo esc_attr($provider_id); ?>" <?php selected($settings['base_provider'], $provider_id); ?>>
											<?php echo esc_html($provider_name); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e('The installed AI provider whose text-generation request implementation the proxy should reuse.', 'ai-provider-proxy'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ai-provider-proxy-base-url"><?php esc_html_e('Proxy base URL', 'ai-provider-proxy'); ?></label>
							</th>
							<td>
								<input
									id="ai-provider-proxy-base-url"
									class="regular-text code"
									type="url"
									name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[base_url]"
									value="<?php echo esc_attr($settings['base_url']); ?>"
									placeholder="http://llm-proxy.internal/v1"
								/>
								<p class="description">
									<?php esc_html_e('Internal or VPN-reachable base URL for the self-hosted proxy. No API key is sent by this plugin.', 'ai-provider-proxy'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ai-provider-proxy-request-params"><?php esc_html_e('Request parameters', 'ai-provider-proxy'); ?></label>
							</th>
							<td>
								<table class="widefat striped" id="ai-provider-proxy-request-params">
									<thead>
										<tr>
											<th><?php esc_html_e('Key', 'ai-provider-proxy'); ?></th>
											<th><?php esc_html_e('Value', 'ai-provider-proxy'); ?></th>
											<th class="screen-reader-text"><?php esc_html_e('Actions', 'ai-provider-proxy'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$request_params = $settings['request_params'];
										if (empty($request_params)) {
											$request_params = array('' => '');
										}
										foreach ($request_params as $param_key => $param_value) :
											?>
											<tr>
												<td>
													<input
														class="regular-text code"
														type="text"
														name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[request_params][keys][]"
														value="<?php echo esc_attr((string) $param_key); ?>"
														placeholder="callerId"
													/>
												</td>
												<td>
													<input
														class="regular-text code"
														type="text"
														name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[request_params][values][]"
														value="<?php echo esc_attr((string) $param_value); ?>"
														placeholder="wordpress"
													/>
												</td>
												<td>
													<button type="button" class="button ai-provider-proxy-remove-param"><?php esc_html_e('Remove', 'ai-provider-proxy'); ?></button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p>
									<button type="button" class="button" id="ai-provider-proxy-add-param"><?php esc_html_e('Add parameter', 'ai-provider-proxy'); ?></button>
								</p>
								<p class="description">
									<?php esc_html_e('Optional query parameters appended to every proxied request.', 'ai-provider-proxy'); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
			( function () {
				const table = document.getElementById( 'ai-provider-proxy-request-params' );
				const addButton = document.getElementById( 'ai-provider-proxy-add-param' );
				if ( ! table || ! addButton ) {
					return;
				}

				const tbody = table.querySelector( 'tbody' );
				const optionName = <?php echo wp_json_encode(Plugin::OPTION_NAME); ?>;

				function createRow() {
					const row = document.createElement( 'tr' );
					row.innerHTML =
						'<td><input class="regular-text code" type="text" name="' + optionName + '[request_params][keys][]" placeholder="callerId" /></td>' +
						'<td><input class="regular-text code" type="text" name="' + optionName + '[request_params][values][]" placeholder="wordpress" /></td>' +
						'<td><button type="button" class="button ai-provider-proxy-remove-param"><?php echo esc_js(__('Remove', 'ai-provider-proxy')); ?></button></td>';
					return row;
				}

				addButton.addEventListener( 'click', function () {
					tbody.appendChild( createRow() );
				} );

				table.addEventListener( 'click', function ( event ) {
					if ( ! event.target.classList.contains( 'ai-provider-proxy-remove-param' ) ) {
						return;
					}

					const rows = tbody.querySelectorAll( 'tr' );
					if ( rows.length > 1 ) {
						event.target.closest( 'tr' ).remove();
						return;
					}

					event.target.closest( 'tr' ).querySelectorAll( 'input' ).forEach( function ( input ) {
						input.value = '';
					} );
				} );
			}() );
		</script>
		<?php
	}
}
