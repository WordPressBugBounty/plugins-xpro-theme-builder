<?php
/**
 * Xpro Theme Builder Function
 *
 * @package  xpro-theme-builder
 */


/**
 * ---------------------------------------------------------------------------
 * Sidebar (Structure template)
 * ---------------------------------------------------------------------------
 * Sidebar templates have no display conditions. They are assigned from the
 * "Xpro Sidebar" Customizer section (global) or the "Xpro Sidebar" box on a
 * page/post (override), separately for the left and the right side.
 */

/**
 * Whether a Xpro Theme Builder post is a usable Custom Sidebar template.
 *
 * @param int $id Template post ID.
 *
 * @return bool
 * @since 1.3.0
 */
function xpro_theme_builder_is_custom_sidebar( $id ) {
	$id = absint( $id );

	if ( ! $id || 'xpro-themer' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return false;
	}

	if ( 'type_sidebar' !== get_post_meta( $id, 'xpro_theme_builder_template_type', true ) ) {
		return false;
	}

	return true;
}

/**
 * Published Custom Sidebar templates as [ ID => title ].
 *
 * @return array
 * @since 1.3.0
 */
function xpro_theme_builder_get_custom_sidebars() {
	$posts = get_posts(
		array(
			'post_type'      => 'xpro-themer',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'xpro_theme_builder_template_type',
					'value' => 'type_sidebar',
				),
			),
		)
	);

	$sidebars = array();

	foreach ( $posts as $post ) {
		if ( xpro_theme_builder_is_custom_sidebar( $post->ID ) ) {
			$sidebars[ $post->ID ] = '' !== $post->post_title
				? $post->post_title
				: sprintf( '#%d', $post->ID );
		}
	}

	return apply_filters( 'xpro_theme_builder_custom_sidebars', $sidebars );
}

/**
 * Every option a "which sidebar?" select can offer.
 *
 * @return array
 * @since 1.3.0
 */
function xpro_theme_builder_get_sidebar_choices() {
	$choices = array(
		'default' => __( 'Default Sidebar (WordPress)', 'xpro-theme-builder' ),
	);

	foreach ( xpro_theme_builder_get_custom_sidebars() as $id => $title ) {
		$choices[ $id ] = $title;
	}

	return $choices;
}

/**
 * ID of the page/post the current request is about.
 *
 * @return int
 */
function xpro_theme_builder_get_sidebar_context_id() {
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}

	if ( is_home() && ! is_front_page() ) {
		return (int) get_option( 'page_for_posts' );
	}

	if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
		return (int) wc_get_page_id( 'shop' );
	}

	return 0;
}

/**
 * Sidebar layout chosen in the Xpro theme.
 *
 * @return string 'left', 'right', 'none' or ''.
 * @since 1.3.1
 */
function xpro_theme_builder_get_theme_sidebar_layout() {
	$keys = apply_filters(
		'xpro_theme_builder_sidebar_layout_keys',
		array(
			'xpro-sidebar-layout',
			'xpro-main-sidebar-layout',
			'xpro-sidebar',
			'xpro_sidebar_layout',
			'sidebar-layout',
		)
	);

	$context_id = xpro_theme_builder_get_sidebar_context_id();
	$raw        = '';

	foreach ( $keys as $key ) {
		$value = '';

		if ( $context_id ) {
			$value = get_post_meta( $context_id, $key, true );
		}

		if ( ( '' === $value || 'default' === $value ) && function_exists( 'xpro_get_option' ) ) {
			$value = xpro_get_option( $key );
		}

		if ( '' === $value || false === $value || null === $value ) {
			$value = get_theme_mod( $key, '' );
		}

		if ( is_string( $value ) && '' !== $value && 'default' !== $value ) {
			$raw = $value;
			break;
		}
	}

	// Xpro theme: global layout option of the current screen.
	if ( '' === $raw && function_exists( 'xpro_get_option' ) ) {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
			$theme_key = 'xpro_shop_layout';
		} elseif ( is_page() ) {
			$theme_key = 'xpro_page_layout';
		} elseif ( is_singular( 'post' ) ) {
			$theme_key = 'xpro_single_layout';
		} elseif ( is_home() ) {
			$theme_key = 'xpro_blog_layout';
		} elseif ( is_archive() || is_search() ) {
			$theme_key = 'xpro_archive_layout';
		} else {
			$theme_key = '';
		}

		if ( '' !== $theme_key ) {
			$defaults = array(
				'xpro_page_layout' => 'full-layout',
				'xpro_shop_layout' => 'left-layout',
			);
			$raw      = (string) xpro_get_option( $theme_key, isset( $defaults[ $theme_key ] ) ? $defaults[ $theme_key ] : 'right-layout' );
		}
	}

	if ( '' === $raw ) {
		if ( function_exists( 'astra_page_layout' ) ) {
			$raw = astra_page_layout();
		} elseif ( function_exists( 'generate_get_layout' ) ) {
			$raw = generate_get_layout();
		} elseif ( function_exists( 'oceanwp_post_layout' ) ) {
			$raw = oceanwp_post_layout();
		}
	}

	$raw = apply_filters( 'xpro_theme_builder_theme_sidebar_layout', $raw );
	$raw = is_string( $raw ) ? strtolower( $raw ) : '';

	if ( false !== strpos( $raw, 'left' ) ) {
		return 'left';
	}

	if ( false !== strpos( $raw, 'right' ) ) {
		return 'right';
	}

	if (
		false !== strpos( $raw, 'no' ) ||
		false !== strpos( $raw, 'none' ) ||
		false !== strpos( $raw, 'full' )
	) {
		return 'none';
	}

	return '';
}

/**
 * Get the automatic Custom Sidebar template ID.
 *
 * Only returns a sidebar when the requested position matches
 * the configured Xpro Sidebar Layout.
 *
 * @param string $position Sidebar position: left or right.
 *
 * @return int|false
 */
function xpro_theme_builder_get_auto_sidebar_id( $position = 'right' ) {

	$position = 'left' === $position ? 'left' : 'right';

	$layout = xpro_theme_builder_get_theme_sidebar_layout();

	// If no sidebar layout is configured, use right sidebar by default.
	if ( '' === $layout ) {
		$layout = 'right';
	}

	// Do not automatically create both left and right sidebars.
	if ( 'left' !== $layout && 'right' !== $layout ) {
		return false;
	}

	if ( $position !== $layout ) {
		return false;
	}

	$ids = array_keys( xpro_theme_builder_get_custom_sidebars() );

	return apply_filters(
		'xpro_theme_builder_auto_sidebar_id',
		$ids ? (int) $ids[0] : false,
		$position
	);
}

/**
 * Custom Sidebar template ID used for a side of the content area.
 *
 * @param string $position 'left', 'right', or empty to follow the theme layout.
 *
 * @return int|false
 * @since 1.3.0
 */
function xpro_theme_builder_get_selected_sidebar_id( $position ) {
	$position = 'left' === $position ? 'left' : 'right';
	$key      = 'xpro_theme_builder_sidebar_' . $position;
	$id       = false;

	$context_id = xpro_theme_builder_get_sidebar_context_id();
	$values     = array();

	if ( $context_id ) {
		$values[] = (string) get_post_meta( $context_id, $key, true );
	}

	$values[] = (string) get_theme_mod( $key, '' );

	foreach ( $values as $value ) {
		if ( 'default' === $value ) {
			// Explicit Default Sidebar (WordPress).
			$id = false;
			break;
		}

		if ( '' !== $value && xpro_theme_builder_is_custom_sidebar( $value ) ) {
			$id = absint( $value );
			break;
		}

		// Empty, or the chosen template was deleted/unpublished:
		// try the next level.
		$id = null;
	}

	if ( null === $id ) {
		$id = xpro_theme_builder_get_auto_sidebar_id( $position );
	}

	return apply_filters( 'xpro_theme_builder_selected_sidebar_id', $id, $position );
}

/**
 * Sidebar currently chosen for the site.
 *
 * @return array { mode: 'default'|'custom', template: int }
 */
function xpro_theme_builder_get_sidebar_setting() {
	foreach ( array( 'right', 'left' ) as $side ) {
		$value = (string) get_theme_mod( 'xpro_theme_builder_sidebar_' . $side, '' );

		if ( 'default' === $value ) {
			return array(
				'mode'     => 'default',
				'template' => 0,
			);
		}

		if ( '' !== $value && xpro_theme_builder_is_custom_sidebar( $value ) ) {
			return array(
				'mode'     => 'custom',
				'template' => absint( $value ),
			);
		}
	}

	return array(
		'mode'     => 'custom',
		'template' => 0,
	);
}

/**
 * Save the site sidebar from a Sidebar template screen.
 * Default = explicit WordPress sidebar. Custom = chosen Xpro Sidebar
 * (applied once the template is published).
 *
 * @param int    $post_id  Sidebar template post ID.
 * @param string $mode     Sidebar mode.
 * @param int    $template Selected sidebar template ID.
 */
function xpro_theme_builder_apply_sidebar_settings( $post_id, $mode, $template = 0 ) {
	if ( 'default' === $mode ) {
		set_theme_mod( 'xpro_theme_builder_sidebar_left', 'default' );
		set_theme_mod( 'xpro_theme_builder_sidebar_right', 'default' );
		return;
	}

	$template = absint( $template ) ? absint( $template ) : absint( $post_id );

	if ( xpro_theme_builder_is_custom_sidebar( $template ) ) {
		set_theme_mod( 'xpro_theme_builder_sidebar_left', (string) $template );
		set_theme_mod( 'xpro_theme_builder_sidebar_right', (string) $template );
	}
}

/**
 * Print the selected Custom Sidebar for a side.
 *
 * @param string $position 'left' or 'right'.
 *
 * @return bool
 * @since 1.3.0
 */
function xpro_theme_builder_render_sidebar( $position = '' ) {
	if ( 'left' !== $position && 'right' !== $position ) {
		$layout = xpro_theme_builder_get_theme_sidebar_layout();

		if ( 'none' === $layout ) {
			return false;
		}

		$position = 'left' === $layout ? 'left' : 'right';
	}

	$id = xpro_theme_builder_get_selected_sidebar_id( $position );

	if ( ! $id ) {
		return false;
	}

	return Xpro_Theme_Builder_Sidebar::instance()->print_sidebar( $id, $position );
}

add_action( 'xpro_theme_builder_render_sidebar', 'xpro_theme_builder_render_sidebar' );

/**
 * Print Xpro Singular/Archive content with selected Custom Sidebars.
 *
 * @param callable $callback Prints the main content.
 *
 * @since 1.3.1
 */
function xpro_theme_builder_render_with_sidebars( $callback ) {
	if ( ! is_callable( $callback ) ) {
		return;
	}

	$layout = xpro_theme_builder_get_theme_sidebar_layout();
	$left   = xpro_theme_builder_get_selected_sidebar_id( 'left' );
	$right  = xpro_theme_builder_get_selected_sidebar_id( 'right' );

	// Follow the theme's Sidebar Layout (Left / Right / No Sidebar).
	if ( 'none' === $layout ) {
		$left  = false;
		$right = false;
	} elseif ( 'left' === $layout ) {
		$left  = $left ? $left : $right;
		$right = false;
	} elseif ( 'right' === $layout ) {
		$right = $right ? $right : $left;
		$left  = false;
	}

	if ( ! $left && ! $right ) {
		call_user_func( $callback );
		return;
	}

	Xpro_Theme_Builder_Sidebar::instance()->enqueue_styles_for(
		array_filter( array( $left, $right ) )
	);

	echo '<div class="xpro-theme-builder-sidebar-layout">';

	if ( $left ) {
		echo '<aside class="xpro-theme-builder-sidebar-col xpro-theme-builder-sidebar-col-left">';

		Xpro_Theme_Builder_Sidebar::instance()->print_sidebar( $left, 'left' );

		echo '</aside>';
	}

	echo '<div class="xpro-theme-builder-sidebar-main">';

	call_user_func( $callback );

	echo '</div>';

	if ( $right ) {
		echo '<aside class="xpro-theme-builder-sidebar-col xpro-theme-builder-sidebar-col-right">';

		Xpro_Theme_Builder_Sidebar::instance()->print_sidebar( $right, 'right' );

		echo '</aside>';
	}

	echo '</div>';
}

/**
 * Checks if Header is enabled from Xpro_Theme_Builder.
 *
 * @return bool True if header is enabled. False if header is not enabled
 * @since 1.0.0
 */
function xpro_theme_builder_header_enabled() {
	$header_id = Xpro_Theme_Builder_Main::get_settings( 'type_header', '' );
	$status    = false;

	if ( '' !== $header_id ) {
		$status = true;
	}

	return apply_filters( 'xpro_theme_builder_header_enabled', $status );
}

/**
 * Checks if Footer is enabled from Xpro_Theme_Builder.
 *
 * @return bool True if header is enabled. False if header is not enabled.
 * @since 1.0.0
 */
function xpro_theme_builder_footer_enabled() {
	$footer_id = Xpro_Theme_Builder_Main::get_settings( 'type_footer', '' );
	$status    = false;

	if ( '' !== $footer_id ) {
		$status = true;
	}

	return apply_filters( 'xpro_theme_builder_footer_enabled', $status );
}

/**
 * Get Xpro_Theme_Builder Header ID
 *
 * @return (String|boolean) header id if it is set else returns false.
 * @since 1.0.0
 */
function get_xpro_theme_builder_header_id() {
	$header_id = Xpro_Theme_Builder_Main::get_settings( 'type_header', '' );

	if ( '' === $header_id ) {
		$header_id = false;
	}

	return apply_filters( 'get_xpro_theme_builder_header_id', $header_id );
}

/**
 * Get Xpro_Theme_Builder Footer ID
 *
 * @return (String|boolean) header id if it is set else returns false.
 * @since 1.0.0
 */
function get_xpro_theme_builder_footer_id() {
	$footer_id = Xpro_Theme_Builder_Main::get_settings( 'type_footer', '' );

	if ( '' === $footer_id ) {
		$footer_id = false;
	}

	return apply_filters( 'get_xpro_theme_builder_footer_id', $footer_id );
}

/**
 * Display header markup.
 *
 * @since 1.0.0
 */
function xpro_theme_builder_render_header() {

	if ( false === apply_filters( 'enable_xpro_theme_builder_render_header', true ) ) {
		return;
	}

	$sticky = get_post_meta( get_xpro_theme_builder_header_id(), 'xpro_theme_builder_sticky' );
	$class  = ( 'enable' === $sticky[0] ) ? ' xtb-header-sticky' : '';

	?>
	<header class="xpro-theme-builder-header<?php echo esc_attr( $class ); ?>" itemscope="itemscope" itemtype="https://schema.org/WPHeader">
		<p class="main-title xpro-hidden" itemprop="headline"><a href="<?php echo esc_url( get_bloginfo( 'url' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php esc_html( bloginfo( 'name' ) ); ?></a></p>
		<nav class="xpro-theme-builder-header-nav">
			<?php Xpro_Theme_Builder_Main::get_header_content(); ?>
		</nav>
	</header>

	<?php

}

/**
 * Display footer markup.
 *
 * @since 1.0.0
 */
function xpro_theme_builder_render_footer() {

	if ( false === apply_filters( 'enable_xpro_theme_builder_render_footer', true ) ) {
		return;
	}

	?>
	<footer itemtype="https://schema.org/WPFooter" itemscope="itemscope" id="xpro-theme-builder-footer"
			role="contentinfo">
		<?php Xpro_Theme_Builder_Main::get_footer_content(); ?>
	</footer>
	<?php

}


/**
 * Get Xpro_Theme_Builder Before Footer ID
 *
 * @return String|boolean before footer id if it is set else returns false.
 * @since 1.0.0
 */
function xpro_theme_builder_get_singular_id() {

	$singular_id = Xpro_Theme_Builder_Main::get_settings( 'type_singular', '' );

	if ( '' === $singular_id ) {
		$singular_id = false;
	}

	return apply_filters( 'get_xpro_theme_builder_singular_id', $singular_id );
}

/**
 * Checks if Before Footer is enabled from Xpro_Theme_Builder.
 *
 * @return bool True if before footer is enabled. False if before footer is not enabled.
 * @since 1.0.0
 */
function xpro_theme_builder_is_singular_enabled() {

	$singular_id = Xpro_Theme_Builder_Main::get_settings( 'type_singular', '' );
	$status      = false;

	if ( '' !== $singular_id ) {
		$status = true;
	}

	return apply_filters( 'xpro_theme_builder_singular_enabled', $status );
}

/**
 * Display before footer markup.
 *
 * @since 1.0.0
 */
function xpro_theme_builder_render_singular() {

	if ( false === apply_filters( 'enable_xpro_theme_builder_render_singular', true ) ) {
		return;
	}
	?>
	<div class="xpro-theme-builder-singular-wrapper">
		<?php
		xpro_theme_builder_render_with_sidebars(
			array( 'Xpro_Theme_Builder_Main', 'get_singular_content' )
		);
		?>
	</div>
	<?php

}

/**
 * Get Xpro_Theme_Builder Before Footer ID
 *
 * @return String|boolean before footer id if it is set else returns false.
 * @since 1.0.0
 */
function xpro_theme_builder_get_archive_id() {

	$archive_id = Xpro_Theme_Builder_Main::get_settings( 'type_archive', '' );

	if ( '' === $archive_id ) {
		$archive_id = false;
	}

	return apply_filters( 'get_xpro_theme_builder_archive_id', $archive_id );
}

/**
 * Checks if Before Footer is enabled from Xpro_Theme_Builder.
 *
 * @return bool True if before footer is enabled. False if before footer is not enabled.
 * @since 1.0.0
 */
function xpro_theme_builder_is_archive_enabled() {

	$archive_id = Xpro_Theme_Builder_Main::get_settings( 'type_archive', '' );
	$status     = false;

	if ( '' !== $archive_id ) {
		$status = true;
	}

	return apply_filters( 'xpro_theme_builder_archive_enabled', $status );
}

/**
 * Display before footer markup.
 *
 * @since 1.0.0
 */
function xpro_theme_builder_render_archive() {

	if ( false === apply_filters( 'enable_xpro_theme_builder_render_archive', true ) ) {
		return;
	}
	?>
	<div class="xpro-theme-builder-archive-wrapper">
		<?php Xpro_Theme_Builder_Main::get_archive_content(); ?>
	</div>
	<?php

}
