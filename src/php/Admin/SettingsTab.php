<?php
/**
 * Settings tab for Media Cleanup.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Admin;

defined( 'ABSPATH' ) || exit;

use VirtualMediaFolders\Addon\AbstractSettingsTab;
use VmfaMediaCleanup\Plugin;

/**
 * Media Cleanup settings tab.
 */
class SettingsTab extends AbstractSettingsTab {

	/** @inheritDoc */
	protected function get_tab_slug(): string {
		return 'media-cleanup';
	}

	/** @inheritDoc */
	protected function get_tab_label(): string {
		return __( 'Media Cleanup', 'vmfa-media-cleanup' );
	}

	/** @inheritDoc */
	protected function get_text_domain(): string {
		return 'vmfa-media-cleanup';
	}

	/** @inheritDoc */
	protected function get_build_path(): string {
		return VMFA_MEDIA_CLEANUP_PATH . 'build/';
	}

	/** @inheritDoc */
	protected function get_build_url(): string {
		return VMFA_MEDIA_CLEANUP_URL . 'build/';
	}

	/** @inheritDoc */
	protected function get_languages_path(): string {
		return VMFA_MEDIA_CLEANUP_PATH . 'languages';
	}

	/** @inheritDoc */
	protected function get_plugin_version(): string {
		return VMFA_MEDIA_CLEANUP_VERSION;
	}

	/** @inheritDoc */
	protected function get_localized_name(): string {
		return 'vmfaMediaCleanup';
	}

	/** @inheritDoc */
	protected function get_localized_data(): array {
		return array(
			'restUrl'      => rest_url( 'vmfa-cleanup/v1/' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'settings'     => Plugin::get_instance()->get_settings(),
			'folders'      => $this->get_folders(),
			'adminUrl'     => admin_url(),
			'activeSubtab' => isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'scan', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/** @inheritDoc */
	protected function get_tab_definition(): array {
		return array(
			'title'    => $this->get_tab_label(),
			'callback' => array( $this, 'render_tab' ),
			'subtabs'  => array(
				'scan'      => __( 'Scan', 'vmfa-media-cleanup' ),
				'unused'    => __( 'Unused', 'vmfa-media-cleanup' ),
				'duplicate' => __( 'Duplicates', 'vmfa-media-cleanup' ),
				'oversized' => __( 'Oversized', 'vmfa-media-cleanup' ),
				'flagged'   => __( 'Flagged', 'vmfa-media-cleanup' ),
				'trash'     => __( 'Trash', 'vmfa-media-cleanup' ),
				'settings'  => __( 'Settings', 'vmfa-media-cleanup' ),
			),
		);
	}

	/**
	 * Render tab content.
	 *
	 * @param string $active_tab    The currently active tab slug.
	 * @param string $active_subtab The currently active subtab slug.
	 * @return void
	 */
	public function render_tab( string $active_tab, string $active_subtab ): void {
		if ( empty( $active_subtab ) ) {
			$active_subtab = 'scan';
		}

		?>
		<div class="vmfa-tab-content">
			<div id="<?php echo esc_attr( $this->get_app_container_id() ); ?>" data-subtab="<?php echo esc_attr( $active_subtab ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Get folders from parent plugin.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_folders(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'vmfo_folder',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$folders = array();
		foreach ( $terms as $term ) {
			$folders[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => $term->parent,
			);
		}

		return $folders;
	}
}
