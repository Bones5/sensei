<?php
/**
 * File containing Sensei_Course_Theme class.
 *
 * @package sensei-lms
 * @since   3.13.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Load the 'Sensei Course Theme' theme for the /learn subsite.
 *
 * @since 3.15.0
 */
class Sensei_Course_Theme {
	/**
	 * URL prefix for loading the course theme.
	 */
	const QUERY_VAR = 'learn';

	/**
	 * Update when rewrite rules change to make sure they are flushed.
	 */
	const REWRITE_VERSION = '3';

	/**
	 * Course theme preview query var.
	 */
	const PREVIEW_QUERY_VAR = 'sensei_theme_preview';

	/**
	 * Directory for the course theme.
	 */
	const THEME_NAME = 'sensei-course-theme';

	/**
	 * Instance of class.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Active theme on the site before override.
	 *
	 * @var string
	 */
	private $original_theme;

	/**
	 * Sensei_Course_Theme constructor. Prevents other instances from being created outside of `self::instance()`.
	 */
	private function __construct() {
	}

	/**
	 * Fetches an instance of the class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes the Course Theme.
	 */
	public function init() {
		Sensei_Course_Theme_Templates::instance()->init();

		add_action( 'setup_theme', [ $this, 'add_query_var' ], 1 );
		add_action( 'registered_post_type', [ $this, 'add_post_type_rewrite_rules' ], 10, 2 );
		add_action( 'registered_taxonomy', [ $this, 'add_taxonomy_rewrite_rules' ], 10, 3 );
		add_action( 'shutdown', [ $this, 'maybe_flush_rewrite_rules' ] );
		add_action( 'setup_theme', [ $this, 'maybe_override_theme' ], 2 );
		add_action( 'template_redirect', [ Sensei_Course_Theme_Lesson::instance(), 'init' ] );
		add_action( 'template_redirect', [ Sensei_Course_Theme_Quiz::instance(), 'init' ] );
		add_action( 'template_redirect', [ $this, 'load_theme' ] );
		add_filter( 'the_content', [ $this, 'add_lesson_video_to_content' ], 80, 1 );
	}


	/**
	 * Is the theme active for the current request.
	 *
	 * @return bool
	 */
	public function is_active() {
		return self::THEME_NAME === get_stylesheet();
	}

	/**
	 * Add the URL prefix the theme is active under.
	 *
	 * @param string $path Optional path to prefix.
	 *
	 * @return string|void
	 */
	public function get_theme_redirect_url( $path = '' ) {

		if ( '' === get_option( 'permalink_structure' ) || get_query_var( 'preview' ) ) {
			return home_url( add_query_arg( [ self::QUERY_VAR => 1 ], $path ) );
		}

		return home_url( '/' . self::QUERY_VAR . '/' . $path );
	}

	/**
	 * Replace theme for the current request if it's for course theme mode.
	 */
	public function maybe_override_theme() {

		// Do a cheaper preliminary check first.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! preg_match( '#' . preg_quote( '/' . self::QUERY_VAR . '/', '#' ) . '#i', $uri ) && ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// Then parse the request and make sure the query var is correct.
		wp();

		if ( get_query_var( self::QUERY_VAR ) ) {
			$this->override_theme();
		}
	}

	/**
	 * Load course theme styles and add related filters.
	 *
	 * @return void
	 */
	public function load_theme() {

		if ( ! Sensei_Course_Theme_Option::should_use_learning_mode() ) {
			return;
		}

		Sensei_Course_Theme_Compat::instance()->load_theme();

		add_filter( 'sensei_use_sensei_template', '__return_false' );
		add_filter( 'body_class', [ $this, 'add_sensei_theme_body_class' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );

		add_action( 'template_redirect', [ $this, 'admin_menu_init' ], 20 );
		add_action( 'admin_init', [ $this, 'admin_menu_init' ], 20 );

		/**
		 * Fires when learning mode is loaded for a page.
		 *
		 * @since 4.0.2
		 * @hook  sensei_course_learning_mode_load_theme
		 */
		do_action( 'sensei_course_learning_mode_load_theme' );
	}

	/**
	 * Load a bundled theme for the request.
	 */
	public function override_theme() {

		$this->original_theme = get_stylesheet();

		add_filter( 'theme_root', [ $this, 'get_plugin_themes_root' ] );
		add_filter( 'pre_option_stylesheet_root', [ $this, 'get_plugin_themes_root' ] );
		add_filter( 'pre_option_template_root', [ $this, 'get_plugin_themes_root' ] );
		add_filter( 'pre_option_template', [ $this, 'theme_template' ] );
		add_filter( 'pre_option_stylesheet', [ $this, 'theme_stylesheet' ] );
		add_filter( 'theme_root_uri', [ $this, 'theme_root_uri' ] );

		/**
		 * Fires when the theme is override is added for learning mode.
		 *
		 * @since 4.0.2
		 * @hook  sensei_course_learning_mode_override_theme
		 */
		do_action( 'sensei_course_learning_mode_override_theme' );

		$this->load_theme();

		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Add learning mode prefix as query var.
	 *
	 * @access private
	 */
	public function add_query_var() {
		global $wp;

		$wp->add_query_var( self::QUERY_VAR );
	}


	/**
	 * Flush rewrite rules if changed.
	 *
	 * @access private
	 */
	public function maybe_flush_rewrite_rules() {

		if ( self::REWRITE_VERSION !== get_option( 'sensei_course_theme_query_var_flushed' ) ) {
			flush_rewrite_rules( false );
			update_option( 'sensei_course_theme_query_var_flushed', self::REWRITE_VERSION );
		}
	}


	/**
	 * Add a route with a /learn prefix for using course theme for a post type.
	 *
	 * @access private
	 *
	 * @param string       $post_type Post type name.
	 * @param WP_Post_Type $args      Post type object.
	 */
	public function add_post_type_rewrite_rules( $post_type, $args ) {

		if ( ! in_array( $post_type, [ 'lesson', 'quiz' ], true ) ) {
			return;
		}

		$slug = preg_quote( $args->rewrite['slug'] ?? $post_type, '/' );

		add_rewrite_rule( '^' . self::QUERY_VAR . '/' . $slug . '/([^/]+)(?:/([0-9]+))?\??(.*)', 'index.php?' . self::QUERY_VAR . '=1&' . $post_type . '=$matches[1]&page=$matches[2]&$matches[3]', 'top' );
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^?]+)' );

	}

	/**
	 * Add a route with a /learn prefix for using course theme for a taxonomy.
	 *
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $object_type Array of object types supported by the taxonomy.
	 * @param array  $args        Arguments used to register the taxonomy.
	 */
	public function add_taxonomy_rewrite_rules( $taxonomy, $object_type, $args ) {
		if ( ! in_array( $taxonomy, [ 'module' ], true ) ) {
			return;
		}

		$slug = preg_quote( $args['rewrite']['slug'] ?? $taxonomy, '/' );

		add_rewrite_rule( '^' . self::QUERY_VAR . '/' . $slug . '/([^/]+)(?:/([0-9]+))?\??(.*)', 'index.php?' . self::QUERY_VAR . '=1&' . $taxonomy . '=$matches[1]', 'top' );
	}

	/**
	 * Get course theme name.
	 *
	 * @access private
	 *
	 * @return string
	 */
	public function theme_template() {
		return self::THEME_NAME;
	}

	/**
	 * Get course theme name.
	 *
	 * @access private
	 *
	 * @return string
	 */
	public function theme_stylesheet() {
		return self::THEME_NAME;
	}

	/**
	 * Root URL for bundled themes.
	 *
	 * @access private
	 *
	 * @return string
	 */
	public function theme_root_uri() {
		return Sensei()->plugin_url . '/themes';
	}

	/**
	 * Root directory for bundled themes.
	 *
	 * @access private
	 *
	 * @return string
	 */
	public function get_plugin_themes_root() {
		return Sensei()->plugin_path() . 'themes';
	}

	/**
	 * Directory for course theme.
	 *
	 * @access private
	 *
	 * @return string
	 */
	public function get_course_theme_root() {
		return $this->get_plugin_themes_root() . '/' . self::THEME_NAME;
	}

	/**
	 * Add Sensei theme body class.
	 *
	 * @access private
	 *
	 * @param string[] $classes The html classess to be added.
	 *
	 * @return string[] $classes
	 */
	public function add_sensei_theme_body_class( $classes ) {
		$classes[] = self::THEME_NAME;

		return $classes;
	}

	/**
	 * Enqueue styles.
	 *
	 * @access private
	 */
	public function enqueue_styles() {
		Sensei()->assets->enqueue( self::THEME_NAME . '-style', 'css/learning-mode.css' );

		Sensei()->assets->enqueue( self::THEME_NAME . '-script', 'course-theme/learning-mode.js' );
		Sensei()->assets->enqueue_script( 'sensei-blocks-frontend' );

		$check_circle_icon = Sensei()->assets->get_icon( 'check-circle' );
		wp_add_inline_script( self::THEME_NAME . '-script', "window.sensei = window.sensei || {}; window.sensei.checkCircleIcon = '$check_circle_icon';", 'before' );

		$this->enqueue_fonts();

		if ( Sensei_Course_Theme_Option::should_override_theme() ) {

			Sensei()->assets->enqueue( self::THEME_NAME . '-theme-style', 'css/learning-mode.theme.css' );

			/**
			 * Fires when the override theme styles are loaded for learning mode.
			 *
			 * @since 4.0.2
			 * @hook  sensei_course_learning_mode_load_override_styles
			 */
			do_action( 'sensei_course_learning_mode_load_override_styles' );
		}

	}

	/**
	 * Enqueue Google fonts.
	 *
	 * @access private
	 */
	public function enqueue_fonts() {
		$font_families = [ 'family=Inter:wght@300;400;500;600;700', 'family=Source+Serif+Pro:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700;1,900' ];

		$fonts_url = esc_url_raw( 'https://fonts.googleapis.com/css2?' . implode( '&', array_unique( $font_families ) ) . '&display=swap' );

		//phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External resource.
		wp_enqueue_style( 'sensei-course-theme-fonts', $fonts_url, [], null );
	}

	/**
	 * Tells if sensei theme is in preview mode.
	 *
	 * @param int $course_id The id of the course.
	 *
	 * @return bool
	 */
	public static function is_preview_mode( $course_id ) {
		// Do not allow sensei preview if not an administrator.
		if ( ! current_user_can( 'manage_sensei' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The user is administrator at this point. No need.
		$query_var = isset( $_GET[ self::PREVIEW_QUERY_VAR ] ) ? intval( $_GET[ self::PREVIEW_QUERY_VAR ] ) : 0;

		// Do not allow sensei preview if requested course id does not match.
		if ( $query_var !== $course_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the url for sensei theme customization.
	 */
	public static function get_sensei_theme_customize_url() {
		// Get the last modified lesson.
		$result = get_posts(
			[
				'posts_per_page' => 1,
				'post_type'      => 'lesson',
				'orderby'        => 'modified',
				'meta'           => [
					'key'     => '_lesson_course',
					'compare' => 'EXISTS',
				],
			]
		);
		if ( empty( $result ) ) {
			return '';
		}

		$lesson      = $result[0];
		$course_id   = get_post_meta( $lesson->ID, '_lesson_course', true );
		$preview_url = '/?p=' . $lesson->ID;
		if ( ! Sensei_Course_Theme_Option::has_learning_mode_enabled( $course_id ) ) {
			$preview_url .= '&' . self::PREVIEW_QUERY_VAR . '=' . $course_id;
		}
		return '/wp-admin/customize.php?autofocus[section]=sensei-course-theme&url=' . rawurlencode( $preview_url );
	}

	/**
	 * Replace 'Edit site' in admin bar to point to the current theme template.
	 *
	 * @access private
	 */
	public function admin_menu_init() {

		if ( ! function_exists( 'wp_is_block_theme' ) ) {
			return;
		}

		remove_action( 'admin_bar_menu', 'wp_admin_bar_edit_site_menu', 40 );
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_edit_site_menu' ], 39 );

	}

	/**
	 * Add 'Edit site' in admin bar opening the current theme template.
	 *
	 * @access private
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar object.
	 *
	 * @return void
	 */
	public function add_admin_bar_edit_site_menu( $wp_admin_bar ) {

		if ( ! current_user_can( 'edit_theme_options' ) || is_admin() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'site-editor',
				'title' => __( 'Edit Site', 'sensei-lms' ),
				'href'  => admin_url( 'site-editor.php?postType=wp_template&postId=' . self::THEME_NAME . '//' . get_post_type() ),
			)
		);
	}

	/**
	 * Get the original active site theme.
	 *
	 * @return mixed
	 */
	public function get_original_theme() {
		return $this->original_theme;
	}

	/**
	 * Filter the post content when using learning mode to add the video
	 * added through the legacy video embed meta box.
	 *
	 * @param string $content The post content.
	 *
	 * @return string The post content with the video.
	 */
	public function add_lesson_video_to_content( $content ) {
		$course_id = \Sensei_Utils::get_current_course();

		if ( is_admin() || ! is_single() || 'lesson' !== get_post_type() || ! Sensei_Course_Theme_Option::has_learning_mode_enabled( $course_id ) ) {
			return $content;
		}

		ob_start();
		Sensei()->frontend->sensei_lesson_video( get_the_ID() );
		$video = ob_get_clean();

		// Checks if video is already added in the content to avoid it duplicated when `the_content`
		// filter is called more than once.
		if ( ! empty( $video ) && false === strpos( $content, $video ) ) {
			return $video . $content;
		}

		return $content;
	}
}
