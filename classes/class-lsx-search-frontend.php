<?php
/**
 * LSX Search Frontend Class.
 *
 * @package lsx-search
 */
class LSX_Search_Frontend {

	public $options = false;

	public $tabs = false;

	public $facet_data = false;

	/**
	 * Determine weather or not search is enabled for this page.
	 *
	 * @var boolean
	 */
	public $search_enabled = false;

	public $search_core_suffix = false;

	public $search_prefix = false;

	/**
	 * Holds the post types enabled
	 *
	 * @var array
	 */
	public $post_types = array();

	/**
	 * Holds the taxonomies enabled for search
	 *
	 * @var array
	 */
	public $taxonomies = array();

	/**
	 * If the current search page has posts or not
	 *
	 * @var boolean
	 */
	public $has_posts = false;

	/**
	 * Construct method.
	 */
	public function __construct() {
		$this->options = \lsx\search\includes\get_options();

		add_filter( 'wpseo_json_ld_search_url', array( $this, 'change_json_ld_search_url' ), 10, 1 );
		add_action( 'wp', array( $this, 'set_vars' ), 11 );
		add_action( 'wp', array( $this, 'set_facetwp_vars' ), 12 );
		add_action( 'wp', array( $this, 'core' ), 13 );
		add_action( 'lsx_body_top', array( $this, 'check_for_results' ) );

		add_action( 'pre_get_posts', array( $this, 'filter_post_types' ) );

		add_filter( 'lsx_search_post_types', array( $this, 'register_post_types' ) );
		add_filter( 'lsx_search_taxonomies', array( $this, 'register_taxonomies' ) );
		add_filter( 'lsx_search_post_types_plural', array( $this, 'register_post_type_tabs' ) );
		add_filter( 'facetwp_sort_options', array( $this, 'facetwp_sort_options' ), 10, 2 );
		add_filter( 'wp_kses_allowed_html', array( $this, 'kses_allowed_html' ), 20, 2 );

		// Redirects.
		add_action( 'template_redirect', array( $this, 'pretty_search_redirect' ) );
		add_filter( 'pre_get_posts', array( $this, 'pretty_search_parse_query' ) );

		add_action( 'lsx_search_sidebar_top', array( $this, 'search_sidebar_top' ) );
		add_filter( 'facetwp_facet_html', array( $this, 'search_facet_html' ), 10, 2 );
	}

	/**
	 * Check all settings.
	 */
	public function set_vars() {
		$post_type = '';

		$this->post_types      = apply_filters( 'lsx_search_post_types', array() );
		$this->taxonomies      = apply_filters( 'lsx_search_taxonomies', array() );
		$this->tabs            = apply_filters( 'lsx_search_post_types_plural', array() );
		$this->options         = apply_filters( 'lsx_search_options', $this->options );
		$this->post_types      = get_post_types();
		$this->post_type_slugs = array(
			'post'        => 'posts',
			'project'     => 'projects',
			'service'     => 'services',
			'team'        => 'team',
			'testimonial' => 'testimonials',
			'video'       => 'videos',
			'product'     => 'products',
		);

		$page_for_posts = get_option( 'page_for_posts' );

		if ( is_search() ) {
			$this->search_core_suffix = 'core';
			$this->search_prefix      = 'search';
		} elseif ( is_post_type_archive( $this->post_types ) || is_tax( $this->taxonomies ) || is_page( $page_for_posts ) || is_home() || is_category() || is_tag() ) {
			$this->search_core_suffix = 'search';

			if ( is_tax( $this->taxonomies ) ) {
				$tax = get_query_var( 'taxonomy' );
				$tax = get_taxonomy( $tax );
				$post_type = $tax->object_type[0];
			} else if ( is_page( $page_for_posts ) || is_category() || is_tag() || is_home() ) {
				$post_type = 'post';
			} else {
				$post_type = get_query_var( 'post_type' );
			}

			if ( isset( $this->tabs[ $post_type ] ) ) {
				$this->search_prefix = $this->tabs[ $post_type ] . '_archive';
			}
		}

		if ( isset( $this->options['display'][ $this->search_prefix . '_enable_' . $this->search_core_suffix ] ) && ( ! empty( $this->options ) ) && 'on' == $this->options['display'][ $this->search_prefix . '_enable_' . $this->search_core_suffix ] ) {
			$this->search_enabled = true;
		}

		$this->search_enabled = apply_filters( 'lsx_search_enabled', $this->search_enabled, $this );
		$this->search_prefix = apply_filters( 'lsx_search_prefix', $this->search_prefix, $this );
	}

	/**
	 * Sets the FacetWP variables.
	 */
	public function set_facetwp_vars() {
		if ( class_exists( 'FacetWP' ) ) {
			$facet_data = FWP()->helper->get_facets();
		}

		$this->facet_data = array();

		$this->facet_data['search_form'] = array(
			'name' => 'search_form',
			'label' => esc_html__( 'Search Form', 'lsx-search' ),
		);

		if ( ! empty( $facet_data ) && is_array( $facet_data ) ) {
			foreach ( $facet_data as $facet ) {
				$this->facet_data[ $facet['name'] ] = $facet;
			}
		}
	}

	/**
	 * Check all settings.
	 */
	public function core() {

		if ( true === $this->search_enabled ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 999 );

			add_filter( 'lsx_layout', array( $this, 'lsx_layout' ), 20, 1 );
			add_filter( 'lsx_layout_selector', array( $this, 'lsx_layout_selector' ), 10, 4 );
			add_filter( 'lsx_slot_class', array( $this, 'change_slot_column_class' ) );
			add_action( 'lsx_entry_top', array( $this, 'add_label_to_title' ) );
			add_filter( 'body_class',         array( $this, 'body_class' ), 10 );

			if ( class_exists( 'LSX_Videos' ) ) {
				global $lsx_videos_frontend;
				remove_action( 'lsx_content_top', array( $lsx_videos_frontend, 'categories_tabs' ), 15 );
			}

			add_filter( 'lsx_paging_nav_disable', '__return_true' );
			add_action( 'lsx_content_top', array( $this, 'facet_top_bar' ) );
			add_action( 'lsx_content_top', array( $this, 'facetwp_tempate_open' ) );
			add_action( 'lsx_content_bottom', array( $this, 'facetwp_tempate_close' ) );
			add_action( 'lsx_content_bottom', array( $this, 'facet_bottom_bar' ) );

			if ( ! empty( $this->options['display'][ $this->search_prefix . '_layout' ] ) && '1c' !== $this->options['display'][ $this->search_prefix . '_layout' ] ) {
				add_filter( 'lsx_sidebar_enable', array( $this, 'lsx_sidebar_enable' ), 10, 1 );
			}

			add_action( 'lsx_content_wrap_before', array( $this, 'search_sidebar' ), 150 );

			if ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
				remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description' );
				remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description' );
				add_filter( 'woocommerce_show_page_title', '__return_false' );

				add_filter( 'loop_shop_columns', function() {
					return 3;
				} );

				// Actions added by LSX theme
				remove_action( 'lsx_content_wrap_before', 'lsx_global_header' );
				add_action( 'lsx_content_wrap_before', array( $this, 'wc_archive_header' ), 140 );

				// Actions added be LSX theme / woocommerce.php file
				remove_action( 'woocommerce_after_shop_loop', 'lsx_wc_sorting_wrapper', 9 );
				remove_action( 'woocommerce_after_shop_loop', 'woocommerce_catalog_ordering', 10 );
				remove_action( 'woocommerce_after_shop_loop', 'woocommerce_result_count', 20 );
				remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 30 );
				remove_action( 'woocommerce_after_shop_loop', 'lsx_wc_sorting_wrapper_close', 31 );

				// Actions added be LSX theme / woocommerce.php file
				remove_action( 'woocommerce_before_shop_loop', 'lsx_wc_sorting_wrapper', 9 );
				remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 10 );
				remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
				remove_action( 'woocommerce_before_shop_loop', 'lsx_wc_woocommerce_pagination', 30 );
				remove_action( 'woocommerce_before_shop_loop', 'lsx_wc_sorting_wrapper_close', 31 );
			}
		}
	}

	/**
	 * Adds a search class to the body to allow the styling of the sidebars etc.
	 *
	 * @param  array $classes The classes.
	 * @return array $classes The classes.
	 * @since 1.0.0
	 */
	public function body_class( $classes ) {
		$classes[] = 'lsx-search-enabled';
		return $classes;
	}

	/**
	 * Check the $wp_query global to see if there are posts in the current query.
	 *
	 * @return void
	 */
	public function check_for_results() {
		if ( true === $this->search_enabled ) {
			global $wp_query;
			if ( empty( $wp_query->posts ) ) {
				$this->has_posts = false;
				remove_action( 'lsx_content_top', array( $this, 'facet_top_bar' ) );
				remove_action( 'lsx_content_bottom', array( $this, 'facet_bottom_bar' ) );
				remove_action( 'lsx_content_wrap_before', array( $this, 'search_sidebar' ), 150 );
			} else {
				$this->has_posts = true;
			}
		}
	}

	/**
	 * Filter the post types.
	 */
	public function filter_post_types( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			if ( ! empty( $this->options ) && ! empty( $this->options['display']['search_enable_core'] ) ) {
				if ( ! empty( $this->options['general']['search_post_types'] ) && is_array( $this->options['general']['search_post_types'] ) ) {
					$post_types = array_keys( $this->options['general']['search_post_types'] );
					$query->set( 'post_type', $post_types );
				}
			}
		}
	}

	/**
	 * Sets post types with active search options.
	 */
	public function register_post_types( $post_types ) {
		$post_types = array( 'post', 'project', 'service', 'team', 'testimonial', 'video', 'product' );
		return $post_types;
	}

	/**
	 * Sets taxonomies with active search options.
	 */
	public function register_taxonomies( $taxonomies ) {
		$taxonomies = array( 'category', 'post_tag', 'project-group', 'service-group', 'team_role', 'video-category', 'product_cat', 'product_tag' );
		return $taxonomies;
	}

	/**
	 * Sets post types with active search options.
	 */
	public function register_post_type_tabs( $post_types_plural ) {
		$post_types_plural = array(
			'post' => 'posts',
			'project' => 'projects',
			'service' => 'services',
			'team' => 'team',
			'testimonial' => 'testimonials',
			'video' => 'videos',
			'product' => 'products', // WooCommerce
		);
		return $post_types_plural;
	}

	/**
	 * Enqueue styles and scripts.
	 */
	public function assets() {
		add_filter( 'lsx_defer_parsing_of_js', array( $this, 'skip_js_defer' ), 10, 4 );
		wp_enqueue_script( 'touchSwipe', LSX_SEARCH_URL . 'assets/js/vendor/jquery.touchSwipe.min.js', array( 'jquery' ), LSX_SEARCH_VER, true );
		wp_enqueue_script( 'slideandswipe', LSX_SEARCH_URL . 'assets/js/vendor/jquery.slideandswipe.min.js', array( 'jquery', 'touchSwipe' ), LSX_SEARCH_VER, true );
		wp_enqueue_script( 'lsx-search', LSX_SEARCH_URL . 'assets/js/src/lsx-search.js', array( 'jquery', 'touchSwipe', 'slideandswipe', 'jquery-ui-datepicker' ), LSX_SEARCH_VER, true );

		$params = apply_filters( 'lsx_search_js_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));

		wp_localize_script( 'lsx-search', 'lsx_customizer_params', $params );

		wp_enqueue_style( 'lsx-search', LSX_SEARCH_URL . 'assets/css/lsx-search.css', array(), LSX_SEARCH_VER );
		wp_style_add_data( 'lsx-search', 'rtl', 'replace' );
	}

	/**
	 * Adds the to-search.min.js and the to-search.js
	 *
	 * @param boolean $should_skip
	 * @param string  $tag
	 * @param string  $handle
	 * @param string  $href
	 * @return boolean
	 */
	public function skip_js_defer( $should_skip, $tag, $handle, $href ) {
		if ( ! is_admin() && ( false !== stripos( $href, 'lsx-search.min.js' ) || false !== stripos( $href, 'lsx-search.js' ) ) ) {
			$should_skip = true;
		}
		return $should_skip;
	}

	/**
	 * Redirect wordpress to the search template located in the plugin
	 *
	 * @param	$template
	 * @return	$template
	 */
	public function search_template_include( $template ) {
		if ( is_main_query() && is_search() ) {
			if ( file_exists( LSX_SEARCH_PATH . 'templates/search.php' ) ) {
				$template = LSX_SEARCH_PATH . 'templates/search.php';
			}
		}

		return $template;
	}

	/**
	 * Rewrite the search URL
	 */
	public function pretty_search_redirect() {
		global $wp_rewrite,$wp_query;

		if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) || ! $wp_rewrite->using_permalinks() ) {
			return;
		}

		$search_base = $wp_rewrite->search_base;

		if ( is_search() && ! is_admin() && strpos( $_SERVER['REQUEST_URI'], "/{$search_base}/" ) === false ) {
			$search_query = get_query_var( 's' );
			$engine = '';

			// If the search was triggered by a supplemental engine.
			if ( isset( $_GET['engine'] ) && 'default' !== $_GET['engine'] ) {
				$engine = $_GET['engine'];
				set_query_var( 'engine', $engine );
				$engine = array_search( $engine, $this->post_type_slugs, true ) . '/';
			}

			$get_array = $_GET;

			if ( is_array( $get_array ) && ! empty( $get_array ) ) {
				$vars_to_maintain = array();

				foreach ( $get_array as $ga_key => $ga_value ) {
					if ( false !== strpos( $ga_key, 'fwp_' ) ) {
						$vars_to_maintain[] = $ga_key . '=' . $ga_value;
					}
				}
			}

			$redirect_url = home_url( "/{$search_base}/" . $engine . urlencode( $search_query ) );

			if ( ! empty( $vars_to_maintain ) ) {
				$redirect_url .= '?' . implode( '&', $vars_to_maintain );
			}

			wp_redirect( $redirect_url );
			exit();
		}
	}

	/**
	 * Parse the Query and trigger a search
	 */
	public function pretty_search_parse_query( $query ) {
		$this->post_type_slugs = array(
			'post' => 'posts',
			'project' => 'projects',
			'service' => 'services',
			'team' => 'team',
			'testimonial' => 'testimonials',
			'video' => 'videos',
			'product' => 'products', // WooCommerce
		);
		if ( $query->is_search() && ! is_admin() && $query->is_main_query() ) {
			$search_query = $query->get( 's' );
			$keyword_test = explode( '/', $search_query );

			$index = array_search( $keyword_test[0], $this->post_type_slugs, true );
			if ( false !== $index ) {
				$engine = $this->post_type_slugs[ $index ];

				$query->set( 'post_type', $engine );
				$query->set( 'engine', $engine );

				if ( count( $keyword_test ) > 1 ) {
					$query->set( 's', $keyword_test[1] );
				} elseif ( post_type_exists( $engine ) ) {
					$query->set( 's', '' );
				}
			} else {
				if ( isset( $this->options['general']['search_post_types'] ) && is_array( $this->options['general']['search_post_types'] ) ) {
					$post_types = array_keys( $this->options['general']['search_post_types'] );
					$query->set( 'post_type', $post_types );
				}
			}
		}

		return $query;
	}

	/**
	 * Change the search slug to /search/ for the JSON+LD output in Yoast SEO
	 *
	 * @return url
	 */
	public function change_json_ld_search_url() {
		return trailingslashit( home_url() ) . 'search/{search_term_string}';
	}

	/**
	 * A filter to set the layout to 2 column.
	 */
	public function lsx_layout( $layout ) {
		if ( ! empty( $this->options['display'][ $this->search_prefix . '_layout' ] ) ) {
			if ( false === $this->has_posts ) {
				$layout = '1c';
			} else {
				$layout = $this->options['display'][ $this->search_prefix . '_layout' ];
			}
		}
		return $layout;
	}

	/**
	 * Outputs the Search Title Facet
	 */
	public function search_sidebar_top() {
		if ( ! empty( $this->options['display'][ $this->search_prefix . '_facets' ] ) && is_array( $this->options['display'][ $this->search_prefix . '_facets' ] ) ) {

			if ( ! is_search() ) {

				foreach ( $this->options['display'][ $this->search_prefix . '_facets' ] as $facet => $facet_useless ) {

					if ( isset( $this->facet_data[ $facet ] ) && 'search' === $this->facet_data[ $facet ]['type'] ) {
						echo wp_kses_post( '<div class="row">' );
							$this->display_facet_default( $facet );
						echo wp_kses_post( '</div>' );
						unset( $this->options['display'][ $this->search_prefix . '_facets' ][ $facet ] );
					}
				}
			} else {
				echo wp_kses_post( '<div class="row">' );
					$this->display_facet_search();
				echo wp_kses_post( '</div>' );
			}
		}
	}

	/**
	 * Overrides the search facet HTML
	 * @param $output
	 * @param $params
	 *
	 * @return string
	 */
	public function search_facet_html( $output, $params ) {
		if ( 'search' == $params['facet']['type'] ) {

			$value = (array) $params['selected_values'];
			$value = empty( $value ) ? '' : stripslashes( $value[0] );
			$placeholder = isset( $params['facet']['placeholder'] ) ? $params['facet']['placeholder'] : __( 'Search...', 'lsx-search' );
			$placeholder = facetwp_i18n( $placeholder );

			ob_start();
			?>
			<div class="col-xs-12 facetwp-item facetwp-form">
				<div class="search-form lsx-search-form 2">
					<div class="input-group facetwp-search-wrap">
						<div class="field">
							<input class="facetwp-search search-field form-control" type="text" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" value="<?php echo esc_attr( $value ); ?>">
						</div>

						<div class="field submit-button">
							<button class="search-submit btn facetwp-btn" type="submit"><?php esc_html_e( 'Search', 'lsx-search' ); ?></button>
						</div>
					</div>
				</div>
			</div>
			<?php
			$output = ob_get_clean();
		}
		return $output;
	}

	/**
	 * Change the primary and secondary column classes.
	 */
	public function lsx_layout_selector( $return_class, $class, $layout, $size ) {
		if ( ! empty( $this->options['display'][ $this->search_prefix . '_layout' ] ) ) {

			if ( '2cl' === $layout || '2cr' === $layout ) {
				$main_class    = 'col-sm-8 col-md-9';
				$sidebar_class = 'col-sm-4 col-md-3';

				if ( '2cl' === $layout ) {
					$main_class    .= ' col-sm-pull-4 col-md-pull-3';
					$sidebar_class .= ' col-sm-push-8 col-md-push-9';
				}

				if ( 'main' === $class ) {
					return $main_class;
				}

				if ( 'sidebar' === $class ) {
					return $sidebar_class;
				}
			}
		}

		return $return_class;
	}

	/**
	 * Outputs top.
	 */
	public function facet_top_bar() {
		$show_pagination     = true;
		$pagination_visible  = false;
		$show_per_page_combo = empty( $this->options['display'][ $this->search_prefix . '_disable_per_page' ] );
		$show_sort_combo     = empty( $this->options['display'][ $this->search_prefix . '_disable_all_sorting' ] );
		if ( isset( $this->options['display'][ $this->search_prefix . '_az_pagination' ] ) ) {
			$az_pagination       = $this->options['display'][ $this->search_prefix . '_az_pagination' ];
		} else {
			$az_pagination = false;
		}

		$show_pagination     = apply_filters( 'lsx_search_top_show_pagination', $show_pagination );
		$pagination_visible  = apply_filters( 'lsx_search_top_pagination_visible', $pagination_visible );
		$show_per_page_combo = apply_filters( 'lsx_search_top_show_per_page_combo', $show_per_page_combo );
		$show_sort_combo     = apply_filters( 'lsx_search_top_show_sort_combo', $show_sort_combo );
		$az_pagination       = apply_filters( 'lsx_search_top_az_pagination', $az_pagination );

		$facet_row_classes = apply_filters( 'lsx_search_top_facetwp_row_classes', '' );

		?>
		<div id="facetwp-top">
			<?php if ( $show_sort_combo || ( $show_pagination && $show_per_page_combo ) ) { ?>
				<div class="row facetwp-top-row-1 hidden-xs <?php echo esc_attr( $facet_row_classes ); ?>">
					<div class="col-xs-12">

						<?php if ( ! empty( $this->options['display'][ $this->search_prefix . '_display_result_count' ] ) ) { ?>
							<div class="row">
								<div class="col-md-12 facetwp-item facetwp-results">
									<h3 class="lsx-search-title lsx-search-title-results"><?php esc_html_e( 'Results', 'lsx-search' ); ?> <?php echo '(' . do_shortcode( '[facetwp counts="true"]' ) . ')'; ?>
									<?php if ( false !== $this->options && isset( $this->options['display'] ) && ( ! empty( $this->options['display'][ $this->search_prefix . '_display_clear_button' ] ) ) && ( 'on' === $this->options['display'][ $this->search_prefix . '_display_clear_button' ] || 'on' === $this->options['display']['products_search_display_clear_button'] ) ) { ?>
										<span class="clear-facets hidden">- <a title="<?php esc_html_e( 'Clear the current search filters.', 'lsx-search' ); ?>" class="facetwp-results-clear" type="button" onclick="<?php echo esc_attr( apply_filters( 'lsx_search_clear_function', 'lsx_search.clearFacets(this);' ) ); ?>"><?php esc_html_e( 'Clear', 'lsx-search' ); ?></a></span>
									<?php } ?>
									</h3>
								</div>
							</div>
						<?php } ?>

						<?php do_action( 'lsx_search_facetwp_top_row' ); ?>

						<?php if ( $show_sort_combo ) { ?>
							<?php echo do_shortcode( '[facetwp sort="true"]' ); ?>
						<?php } ?>

					</div>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Outputs bottom.
	 */
	public function facet_bottom_bar() {
		?>
		<?php
		$show_pagination    = true;
		$pagination_visible = false;
		if ( isset( $this->options['display'][ $this->search_prefix . '_az_pagination' ] ) ) {
			$az_pagination = $this->options['display'][ $this->search_prefix . '_az_pagination' ];
		} else {
			$az_pagination = false;
		}

		$show_per_page_combo = empty( $this->options['display'][ $this->search_prefix . '_disable_per_page' ] );
		$show_sort_combo     = empty( $this->options['display'][ $this->search_prefix . '_disable_all_sorting' ] );

		$show_pagination     = apply_filters( 'lsx_search_bottom_show_pagination', $show_pagination );
		$pagination_visible  = apply_filters( 'lsx_search_bottom_pagination_visible', $pagination_visible );
		$show_per_page_combo = apply_filters( 'lsx_search_bottom_show_per_page_combo', $show_per_page_combo );
		$show_sort_combo     = apply_filters( 'lsx_search_bottom_show_sort_combo', $show_sort_combo );

		if ( $show_pagination || ! empty( $az_pagination ) ) { ?>
			<div id="facetwp-bottom">
				<div class="row facetwp-bottom-row-1">
					<div class="col-xs-12">
						<?php do_action( 'lsx_search_facetwp_bottom_row' ); ?>

						<?php //if ( $show_sort_combo ) { ?>
							<?php //echo do_shortcode( '[facetwp sort="true"]' ); ?>
						<?php //} ?>

						<?php //if ( ( $show_pagination && $show_per_page_combo ) || $show_per_page_combo ) { ?>
							<?php //echo do_shortcode( '[facetwp per_page="true"]' ); ?>
						<?php //} ?>

						<?php
						if ( $show_pagination ) {
							$output_pagination = do_shortcode( '[facetwp pager="true"]' );
							if ( ! empty( $this->options['display'][ $this->search_prefix . '_facets' ] ) && is_array( $this->options['display'][ $this->search_prefix . '_facets' ] ) ) {
								foreach ( $this->options['display'][ $this->search_prefix . '_facets' ] as $facet => $facet_useless ) {
									if ( isset( $this->facet_data[ $facet ] ) && in_array( $this->facet_data[ $facet ]['type'], array( 'pager' ) ) ) {
										$output_pagination = do_shortcode( '[facetwp facet="pager_"]' );
									}
								}
							}
							echo wp_kses_post( $output_pagination );
						?>
						<?php } ?>
					</div>
				</div>
			</div>
		<?php }
	}

	/**
	 * Adds in the closing facetwp div
	 *
	 * @return void
	 */
	public function facetwp_tempate_open() {
		?>
		<div class="facetwp-template">
		<?php
	}

	/**
	 * Adds in the closing facetwp div
	 *
	 * @return void
	 */
	public function facetwp_tempate_close() {
		?>
		</div>
		<?php
	}

	/**
	 * Disables default sidebar.
	 */
	public function lsx_sidebar_enable( $sidebar_enabled ) {
		$sidebar_enabled = false;
		return $sidebar_enabled;
	}

	/**
	 * Outputs custom sidebar.
	 */
	public function search_sidebar() {

		$this->options = apply_filters( 'lsx_search_sidebar_options', $this->options );
		?>
			<?php do_action( 'lsx_search_sidebar_before' ); ?>

			<div id="secondary" class="facetwp-sidebar widget-area <?php echo esc_attr( lsx_sidebar_class() ); ?>" role="complementary">

				<?php do_action( 'lsx_search_sidebar_top' ); ?>

				<?php if ( ! empty( $this->options['display'][ $this->search_prefix . '_facets' ] ) && is_array( $this->options['display'][ $this->search_prefix . '_facets' ] ) ) { ?>
					<div class="row facetwp-row lsx-search-filer-area">
						<h3 class="facetwp-filter-title"><?php echo esc_html_e( 'Refine by', 'lsx-search' ); ?></h3>
						<div class="col-xs-12 facetwp-item facetwp-filters-button hidden-sm hidden-md hidden-lg">
							<button class="ssm-toggle-nav btn btn-block" rel="lsx-search-filters"><?php esc_html_e( 'Filters', 'lsx-search' ); ?> <i class="fa fa-chevron-down" aria-hidden="true"></i></button>
						</div>

						<div class="ssm-overlay ssm-toggle-nav" rel="lsx-search-filters"></div>

						<div class="col-xs-12 facetwp-item-wrap facetwp-filters-wrap" rel="lsx-search-filters">
							<div class="row hidden-sm hidden-md hidden-lg ssm-row-margin-bottom">
								<div class="col-xs-12 facetwp-item facetwp-filters-button">
									<button class="ssm-close-btn ssm-toggle-nav btn btn-block" rel="lsx-search-filters"><?php esc_html_e( 'Close Filters', 'lsx-search' ); ?> <i class="fa fa-times" aria-hidden="true"></i></button>
								</div>
							</div>

							<div class="row">
								<?php
								// Slider.
								foreach ( $this->options['display'][ $this->search_prefix . '_facets' ] as $facet => $facet_useless ) {
									if ( isset( $this->facet_data[ $facet ] ) && ! in_array( $this->facet_data[ $facet ]['type'], array( 'alpha', 'search', 'pager' ) ) ) {
										$this->display_facet_default( $facet );
									}
								}
								?>
							</div>

							<div class="row hidden-sm hidden-md hidden-lg ssm-row-margin-top">
								<div class="col-xs-12 facetwp-item facetwp-filters-button">
									<button class="ssm-apply-btn ssm-toggle-nav btn btn-block" rel="lsx-search-filters"><?php esc_html_e( 'Apply Filters', 'lsx-search' ); ?> <i class="fa fa-check" aria-hidden="true"></i></button>
								</div>
							</div>
						</div>
					</div>
				<?php } ?>

				<?php do_action( 'lsx_search_sidebar_bottom' ); ?>
			</div>

			<?php do_action( 'lsx_search_sidebar_after' ); ?>
		<?php
	}

	/**
	 * Check if the pager facet is on
	 *
	 * @return void
	 */
	public function pager_facet_enabled() {

		$pager_facet_off = false;

		if ( ! empty( $this->options['display'][ $this->search_prefix . '_facets' ] ) && is_array( $this->options['display'][ $this->search_prefix . '_facets' ] ) ) {
			foreach ( $this->options['display'][ $this->search_prefix . '_facets' ] as $facet => $facet_useless ) {
				if ( isset( $this->facet_data[ $facet ] ) && ! in_array( $this->facet_data[ $facet ]['type'], array( 'pager' ) ) ) {
					$pager_facet_off = true;
				}
			}
		}

		return $pager_facet_off;
	}

	/**
	 * Display WooCommerce archive title.
	 */
	public function wc_archive_header() {
		$default_size   = 'sm';
		$size           = apply_filters( 'lsx_bootstrap_column_size', $default_size );
		?>
			<div class="archive-header-wrapper banner-woocommerce col-<?php echo esc_attr( $size ); ?>-12">
				<?php lsx_global_header_inner_bottom(); ?>
				<header class="archive-header">
					<h1 class="archive-title"><?php woocommerce_page_title(); ?></h1>
				</header>
			</div>
		<?php
	}

	/**
	 * Display facet search.
	 */
	public function display_facet_search() {
		?>
		<div class="col-xs-12 facetwp-item facetwp-form">
			<form class="search-form lsx-search-form" action="/" method="get">
				<div class="input-group">
					<div class="field">
						<input class="facetwp-search search-field form-control" name="s" type="search" placeholder="<?php esc_html_e( 'Search', 'lsx-search' ); ?>..." autocomplete="off" value="<?php echo get_search_query() ?>">
					</div>

					<div class="field submit-button">
						<button class="search-submit btn" type="submit"><?php esc_html_e( 'Search', 'lsx-search' ); ?></button>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Display facet default.
	 */
	public function display_facet_default( $facet ) {

		$show_collapse = ! isset( $this->options['display']['enable_collapse'] ) || 'on' !== $this->options['display']['enable_collapse'];
		$col_class = '';

		if ( 'search' === $this->facet_data[ $facet ]['type'] ) : ?>
			<?php echo do_shortcode( '[facetwp facet="' . $facet . '"]' ); ?>
		<?php else : ?>
			<div class="col-xs-12 facetwp-item parent-facetwp-facet-<?php echo esc_html( $facet ); ?> <?php echo esc_attr( $col_class ); ?>">
				<?php if ( ! $show_collapse ) { ?>
					<div class="facetwp-collapsed">
						<h3 class="lsx-search-title"><?php echo wp_kses_post( $this->facet_data[ $facet ]['label'] ); ?></h3>
						<button title="<?php echo esc_html_e( 'Click to Expand', 'lsx-search' ); ?>" class="facetwp-collapse" type="button" data-toggle="collapse" data-target="#collapse-<?php echo esc_html( $facet ); ?>" aria-expanded="false" aria-controls="collapse-<?php echo esc_html( $facet ); ?>"></button>
					</div>
					<div id="collapse-<?php echo esc_html( $facet ); ?>" class="collapse">
						<?php echo do_shortcode( '[facetwp facet="' . $facet . '"]' ); ?>
					</div>
				<?php } else { ?>
					<h3 class="lsx-search-title"><?php echo wp_kses_post( $this->facet_data[ $facet ]['label'] ); ?></h3>
					<?php echo do_shortcode( '[facetwp facet="' . $facet . '"]' ); ?>
				<?php } ?>
			</div>
		<?php
		endif;
	}

	/**
	 * Changes slot column class.
	 */
	public function change_slot_column_class( $class ) {
		if ( is_post_type_archive( 'video' ) || is_tax( 'video-category' ) ) {
			$column_class = 'col-xs-12 col-sm-4';
		}

		return $column_class;
	}

	/**
	 * Add post type label to the title.
	 */
	public function add_label_to_title() {
		if ( is_search() ) {
			if ( ! empty( $this->options['display']['search_enable_pt_label'] ) ) {
				$post_type = get_post_type();
				$post_type = str_replace( '_', ' ', $post_type );
				$post_type = str_replace( '-', ' ', $post_type );
				if ( 'tribe events' === $post_type ) {
					$post_type = 'Events';
				}
				echo wp_kses_post( ' <span class="label label-default lsx-label-post-type">' . $post_type . '</span>' );
			}
		}
	}

	/**
	 * Changes the sort options.
	 */
	public function facetwp_sort_options( $options, $params ) {
		$this->set_vars();

		if ( true === $this->search_enabled ) {
			if ( 'default' !== $params['template_name'] && 'wp' !== $params['template_name'] ) {
				return $options;
			}

			if ( ! empty( $this->options['display'][ $this->search_prefix . '_disable_date_sorting' ] ) ) {
				unset( $options['date_desc'] );
				unset( $options['date_asc'] );
			}

			if ( ! empty( $this->options['display'][ $this->search_prefix . '_disable_az_sorting' ] ) ) {
				unset( $options['title_desc'] );
				unset( $options['title_asc'] );
			}
		}

		return $options;
	}

	/**
	 * @param $allowedtags
	 * @param $context
	 *
	 * @return mixed
	 */
	public function kses_allowed_html( $allowedtags, $context ) {
		$allowedtags['a']['data-value'] = true;
		$allowedtags['a']['data-selection']  = true;
		$allowedtags['button']['data-toggle'] = true;
		return $allowedtags;
	}
}
