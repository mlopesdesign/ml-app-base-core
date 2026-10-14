<?php
/**
 * Lightweight self-hosted GitHub updater for ML App Base Core.
 *
 * Mirrors the pattern used by ml-css-pro and ml-gallery-pro: a flat
 * ML_App_Base_Core_Updater class with no third-party dependency. Hooks into
 * the native WordPress update flow via pre_set_site_transient_update_plugins
 * and friends, so installs can update themselves straight from the GitHub
 * release asset attached to mlopesdesign/ml-app-base-core-releases.
 *
 * If the GitHub endpoint is unreachable or the release payload is empty the
 * updater silently no-ops, never interfering with the existing plugin
 * behaviour.
 *
 * @package MLAppBaseCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ML_App_Base_Core_Updater {

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $plugin_slug;

	/** @var string */
	private $github_repo;

	/** @var string */
	private $current_version;

	/** @var string */
	private $transient_key;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file     plugin_basename( ML_APP_BASE_CORE_FILE ).
	 * @param string $plugin_slug     Technical plugin slug.
	 * @param string $github_repo     GitHub repository in owner/repository format.
	 * @param string $current_version Installed plugin version.
	 */
	public function __construct( $plugin_file, $plugin_slug, $github_repo, $current_version ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = $plugin_slug;
		$this->github_repo     = $github_repo;
		$this->current_version = $current_version;
		$this->transient_key   = 'mlab_updater_' . md5( $plugin_slug );
	}

	/**
	 * Registers updater hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
	}

	/**
	 * Injects update data into WordPress' plugin update transient.
	 *
	 * @param mixed $transient WordPress update_plugins transient.
	 * @return mixed
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release        = $this->get_latest_release();
		$remote_version = $release ? $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) ) : '';
		$package        = '' !== $remote_version ? $this->find_zip_url( $release, $remote_version ) : '';

		$item = (object) [
			'id'            => 'github.com/' . $this->github_repo,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_file,
			'new_version'   => $remote_version,
			'url'           => 'https://github.com/' . $this->github_repo,
			'package'       => $package,
			'icons'         => $this->icons(),
			'banners'       => [],
			'banners_rtl'   => [],
			'tested'        => '6.8',
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'compatibility' => new \stdClass(),
		];

		if ( '' !== $package && version_compare( $this->current_version, $remote_version, '<' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = [];
			}
			$transient->response[ $this->plugin_file ] = $item;
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				unset( $transient->no_update[ $this->plugin_file ] );
			}
		} else {
			// Sem versao nova (ou sem o ZIP exato): no_update, para o WP mostrar o estado certo
			// e o controle de atualizacao automatica.
			$item->new_version = $this->current_version;
			$item->package     = '';
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = [];
			}
			$transient->no_update[ $this->plugin_file ] = $item;
			if ( isset( $transient->response ) && is_array( $transient->response ) ) {
				unset( $transient->response[ $this->plugin_file ] );
			}
		}

		return $transient;
	}

	/**
	 * Supplies data for WordPress' "View version details" modal.
	 *
	 * @param mixed  $result Current API result.
	 * @param string $action Requested API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );
		if ( '' === $remote_version ) {
			return $result;
		}

		$download_url = $this->find_zip_url( $release, $remote_version );
		if ( '' === $download_url ) {
			return $result;
		}

		return (object) [
			'name'           => 'ML App Base Core',
			'slug'           => $this->plugin_slug,
			'version'        => $remote_version,
			'author'         => '<a href="https://mlopesdesign.com.br">ML Lopes Design</a>',
			'author_profile' => 'https://mlopesdesign.com.br',
			'homepage'       => 'https://github.com/' . $this->github_repo,
			'download_link'  => $download_url,
			'trunk'          => $download_url,
			'requires'       => '6.0',
			'requires_php'   => '7.4',
			'tested'         => '6.8',
			'last_updated'   => $release['published_at'] ?? '',
			'sections'       => [
				'description' => '<p>ML App Base Core - backend WordPress para apps nativos (ML Lopes Design).</p>',
				'changelog'   => '<pre>' . esc_html( (string) ( $release['body'] ?? '' ) ) . '</pre>',
			],
			'banners'        => [],
			'icons'          => $this->icons(),
		];
	}

	// after_install() removido na 1.1.1: na 1.1.0 ele fazia unset($result) e depois usava
	// $result['destination'], o que levava a $wp_filesystem->move('', <pasta do plugin>, true) e
	// APAGAVA a pasta do plugin a cada atualizacao automatica. O ZIP oficial ja vem com a pasta
	// certa (ml-app-base-core/), entao nenhum ajuste pos-instalacao e necessario.

	/**
	 * Retrieves the latest stable GitHub release.
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release() {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return ! empty( $cached ) && is_array( $cached ) ? $cached : null;
		}
		$fail_ttl = 15 * MINUTE_IN_SECONDS;

		$url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 12,
				'user-agent' => 'ML-App-Base-Core/' . $this->current_version . '; WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'headers'    => [
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->transient_key, [], $fail_ttl );
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || '' === $this->normalize_version( (string) ( $data['tag_name'] ?? '' ) ) ) {
			set_transient( $this->transient_key, [], $fail_ttl );
			return null;
		}

		$remote = $this->normalize_version( (string) $data['tag_name'] );
		// Release ainda sem o ZIP exato (asset em upload): cache curto para nao atrasar a oferta.
		$ttl = '' !== $this->find_zip_url( $data, $remote ) ? 6 * HOUR_IN_SECONDS : $fail_ttl;
		set_transient( $this->transient_key, $data, $ttl );

		return $data;
	}

	/**
	 * Finds the official versioned release asset.
	 *
	 * @param array  $release        GitHub release payload.
	 * @param string $remote_version Normalized remote version (without v prefix).
	 * @return string Download URL.
	 */
	private function find_zip_url( $release, $remote_version ) {
		$expected = $this->plugin_slug . '-v' . $remote_version . '.zip';

		foreach ( ( $release['assets'] ?? [] ) as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			if ( '' !== $url && strcasecmp( $name, $expected ) === 0 ) {
				return $url;
			}
		}

		// Sem fallback para "outro ZIP" ou para o zipball do GitHub (pasta com nome errado):
		// sem o asset exato, nao ha oferta de atualizacao.
		return '';
	}

	/**
	 * Icones da tela de atualizacoes.
	 *
	 * @return array
	 */
	private function icons() {
		return [
			'1x'      => ML_APP_BASE_CORE_URL . 'assets/images/icon-128x128.png',
			'2x'      => ML_APP_BASE_CORE_URL . 'assets/images/icon-256x256.png',
			'default' => ML_APP_BASE_CORE_URL . 'assets/images/icon-256x256.png',
		];
	}

	/**
	 * Strips the v prefix and validates the version shape.
	 *
	 * @param string $tag_name GitHub tag name.
	 * @return string
	 */
	private function normalize_version( $tag_name ) {
		$version = preg_replace( '/^v/i', '', trim( (string) $tag_name ) );
		if ( ! is_string( $version ) || ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return '';
		}
		return $version;
	}
}
