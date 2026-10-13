<?php
/**
 * Xpro Theme Builder - Sidebar (Structure template).
 *
 * Adds "Sidebar" as an additional template type next to Header and Footer.
 *
 * Placement: follows the active layout in Xpro Settings > Sidebar Layout.
 * Rendering: through the standard WordPress sidebar API.
 *
 * @package xpro-theme-builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xpro_Theme_Builder_Sidebar
 */
final class Xpro_Theme_Builder_Sidebar {

	/**
	 * Instance.
	 *
	 * @var Xpro_Theme_Builder_Sidebar
	 */
	private static $instance = null;

	/**
	 * Active overrides for this request.
	 *
	 * @var array
	 */
	private $active = array();

	/**
	 * Sidebar indexes already printed.
	 *
	 * @var array
	 */
	private $printed = array();

	/**
	 * Guard against a sidebar template rendering itself.
	 *
	 * @var bool
	 */
	private $rendering = false;

	/**
	 * Sides that have a Custom Sidebar on this request.
	 *
	 * @var array
	 */
	private $active_sides = array();

	/**
	 * Get instance.
	 *
	 * @return Xpro_Theme_Builder_Sidebar
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {

		// Frontend. Runs after the theme compatibility classes.
		add_action(
			'wp',
			array( $this, 'setup_frontend' ),
			20
		);
	}

	/**
	 * Map WordPress sidebar IDs to their positions.
	 *
	 * @return array
	 */
	private function get_sidebar_map() {

		return apply_filters(
			'xpro_theme_builder_sidebar_positions',
			array(
				'xpro-main-sidebar'        => '',
				'xpro-woocommerce-sidebar' => '',
				'sidebar-1'       => '',
				'sidebar'         => '',
				'sidebar-main'    => '',
				'primary-sidebar' => '',
				'sidebar-left'    => 'left',
				'sidebar-right'   => 'right',
			)
		);
	}

	/**
	 * Get the side on which the active theme displays its sidebar.
	 *
	 * @return string
	 */
	private function get_theme_layout_side() {

		$xpro_layout = xpro_theme_builder_get_theme_sidebar_layout();

		if ( 'left' === $xpro_layout || 'right' === $xpro_layout ) {
			return $xpro_layout;
		}

		$layout = '';

		if ( function_exists( 'astra_page_layout' ) ) {

			$layout = astra_page_layout();

		} elseif ( function_exists( 'generate_get_layout' ) ) {

			$layout = generate_get_layout();

		} elseif ( function_exists( 'oceanwp_post_layout' ) ) {

			$layout = oceanwp_post_layout();
		}

		$layout = is_string( $layout ) ? $layout : '';

		if ( false !== strpos( $layout, 'left-sidebar' ) ) {
			return 'left';
		}

		if ( false !== strpos( $layout, 'right-sidebar' ) ) {
			return 'right';
		}

		return '';
	}

	/**
	 * Configure custom sidebar replacements for the frontend.
	 */
	public function setup_frontend() {

		if (
			is_admin() ||
			is_feed() ||
			is_embed() ||
			'xpro-themer' === get_post_type()
		) {
			return;
		}

		$selected = array(
			'left'  => xpro_theme_builder_get_selected_sidebar_id( 'left' ),
			'right' => xpro_theme_builder_get_selected_sidebar_id( 'right' ),
		);

		if ( ! $selected['left'] && ! $selected['right'] ) {
			return;
		}

		// Respect No Sidebar in Xpro Settings.
		if ( 'none' === xpro_theme_builder_get_theme_sidebar_layout() ) {
			return;
		}

		$this->active_sides = array_filter( $selected );

		$map = $this->get_sidebar_map();

		// Find a likely content sidebar for unsupported themes.
		global $wp_registered_sidebars;

		if (
			is_array( $wp_registered_sidebars ) &&
			! array_intersect_key( $map, $wp_registered_sidebars )
		) {

			foreach ( array_keys( $wp_registered_sidebars ) as $registered ) {

				if (
					false !== stripos( $registered, 'sidebar' ) &&
					! preg_match(
						'/footer|header|offcanvas|off-canvas|filter|wp_inactive/i',
						$registered
					)
				) {
					$map[ $registered ] = '';
					break;
				}
			}
		}

		foreach ( $map as $index => $position ) {

			if ( '' === $position ) {

				// Try a theme-specific position filter.
				$position = apply_filters(
					'xpro_theme_builder_sidebar_position',
					'',
					$index
				);

				// Detect the active theme's layout.
				if (
					'left' !== $position &&
					'right' !== $position
				) {
					$position = $this->get_theme_layout_side();
				}

				// Fall back to the selected sidebar position.
				if (
					'left' !== $position &&
					'right' !== $position
				) {
					$position = $selected['right'] ? 'right' : 'left';
				}

				// Use the selected side if the theme's side is not selected.
				if ( empty( $selected[ $position ] ) ) {
					$position = 'left' === $position ? 'right' : 'left';
				}
			}

			if ( ! empty( $selected[ $position ] ) ) {

				$this->active[ $index ] = array(
					'id'       => (int) $selected[ $position ],
					'position' => $position,
				);
			}
		}

		if ( empty( $this->active ) ) {
			return;
		}

		// Make WordPress treat the custom sidebar as active.
		add_filter(
			'is_active_sidebar',
			array( $this, 'filter_is_active_sidebar' ),
			10,
			2
		);

		add_filter(
			'dynamic_sidebar_has_widgets',
			array( $this, 'filter_has_widgets' ),
			10,
			2
		);

		// Replace widgets for the selected sidebars on this request.
		add_filter(
			'sidebars_widgets',
			array( $this, 'filter_sidebars_widgets' )
		);

		// Render the Elementor sidebar inside the theme's sidebar wrapper.
		add_action(
			'dynamic_sidebar_before',
			array( $this, 'on_dynamic_sidebar_before' ),
			10,
			2
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_styles' ),
			20
		);
	}

	/**
	 * Mark the selected custom sidebar as active.
	 *
	 * @param bool   $is_active Whether the sidebar is active.
	 * @param string $index     Sidebar index.
	 *
	 * @return bool
	 */
	public function filter_is_active_sidebar( $is_active, $index ) {

		return isset( $this->active[ $index ] )
			? true
			: $is_active;
	}

	/**
	 * Tell WordPress that the selected sidebar has widgets.
	 *
	 * @param bool   $has_widgets Whether the sidebar has widgets.
	 * @param string $index       Sidebar index.
	 *
	 * @return bool
	 */
	public function filter_has_widgets( $has_widgets, $index ) {

		return isset( $this->active[ $index ] )
			? true
			: $has_widgets;
	}

	/**
	 * Replace widgets in selected sidebars.
	 *
	 * @param array $sidebars_widgets Registered sidebar widgets.
	 *
	 * @return array
	 */
	public function filter_sidebars_widgets( $sidebars_widgets ) {

		if ( ! is_array( $sidebars_widgets ) ) {
			return $sidebars_widgets;
		}

		foreach ( array_keys( $this->active ) as $index ) {
			$sidebars_widgets[ $index ] = array();
		}

		return $sidebars_widgets;
	}

	/**
	 * Print the custom sidebar inside the theme's sidebar call.
	 *
	 * @param string $index       Sidebar index.
	 * @param bool   $has_widgets Whether the sidebar has widgets.
	 */
	public function on_dynamic_sidebar_before(
		$index,
		$has_widgets = false
	) {

		if (
			! isset( $this->active[ $index ] ) ||
			isset( $this->printed[ $index ] )
		) {
			return;
		}

		$this->printed[ $index ] = true;

		$this->print_sidebar(
			$this->active[ $index ]['id'],
			$this->active[ $index ]['position']
		);
	}

	/**
	 * Render a custom Elementor sidebar template.
	 *
	 * @param int    $id       Sidebar template ID.
	 * @param string $position Sidebar position.
	 *
	 * @return bool
	 */
	public function print_sidebar( $id, $position ) {

		if ( $this->rendering ) {
			return false;
		}

		$this->rendering = true;

		$content = Xpro_Theme_Builder_Main::get_sidebar_content( $id );

		$this->rendering = false;

		if ( '' === $content ) {
			return false;
		}

		$html = sprintf(
			'<div class="xpro-theme-builder-sidebar-wrapper xpro-theme-builder-sidebar-%1$s" data-xpro-sidebar-id="%2$d">%3$s</div>',
			esc_attr( $position ),
			absint( $id ),
			$content
		);

		echo apply_filters(
			'xpro_theme_builder_sidebar_markup',
			$html,
			$id,
			$position
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return true;
	}

	/**
	 * Enqueue Elementor CSS for active custom sidebars.
	 */
	public function enqueue_styles() {

		$this->enqueue_styles_for(
			wp_list_pluck( $this->active, 'id' )
		);
	}

	/**
	 * Enqueue Elementor CSS for the specified sidebar templates.
	 *
	 * @param array $ids Sidebar template IDs.
	 */
	public function enqueue_styles_for( $ids ) {

		if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return;
		}

		foreach (
			array_unique( array_map( 'absint', (array) $ids ) )
			as $id
		) {

			$css_file = new \Elementor\Core\Files\CSS\Post( $id );

			$css_file->enqueue();
		}
	}
}

Xpro_Theme_Builder_Sidebar::instance();