<?php
/**
 * File containing the Lesson_Video class.
 *
 * @package sensei
 * @since
 */
namespace Sensei\Blocks\Course_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Sensei_Blocks;
use \Sensei_Course;
use \Sensei_Utils;

/**
 * Class Lesson_Video is responsible for rendering the Lesson video template block.
 */
class Lesson_Video {
	/**
	 * Block JSON file.
	 */
	const BLOCK_JSON_FILE = '/lesson-blocks/course-theme-lesson-video.block.json';

	/**
	 * Lesson_Actions constructor.
	 */
	public function __construct() {
		$block_json_path = Sensei()->assets->src_path( 'course-theme/blocks' ) . self::BLOCK_JSON_FILE;
		Sensei_Blocks::register_sensei_block(
			'sensei-lms/course-theme-lesson-video',
			array(
				'render_callback' => array( $this, 'render' ),
				'style'           => 'sensei-theme-blocks',
			),
			$block_json_path
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @access private
	 *
	 * @return string The block HTML.
	 */
	public function render( array $attributes = array() ) : string {
		// TODO: remove_action( 'sensei_lesson_video', [ \Sensei_Frontend, 'sensei_lesson_video' ] );
		$lesson_id = Sensei_Utils::get_current_lesson();
		$user_id   = get_current_user_id();

		if ( empty( $lesson_id ) || empty( $user_id ) ) {
			return '';
		}

		$course_id = Sensei()->lesson->get_course_id( $lesson_id );

		if (
			! Sensei_Course::is_user_enrolled( $course_id )
			|| Sensei_Utils::user_completed_lesson( $lesson_id )
		) {
			return '';
		}

		$content = Sensei_Utils::get_featured_video_html( $lesson_id ) ?? 'No Lesson Video';
		preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#i', $content, $matches );
		$video_link = $matches[0][0] ?? '';

		if ( empty( $content ) || empty( $video_link ) ) {
			return '';
		}

		$wrapper_attr = get_block_wrapper_attributes(
			[
				'class' => 'sensei-course-theme-lesson-video wp-block-video is-type-video wp-has-aspect-ratio',
			]
		);
		$embed_url = $this->generate_video_embed_url( $video_link );
		$markup       = $this->get_embed_markup( $embed_url );

		return sprintf(
			'<figure %s>
				%s
				</figure>',
			$wrapper_attr,
			$markup
		);
	}

	/**
	 * This method returns the markup for the embed link.
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function get_embed_markup( $link ) {
		return sprintf(
			'<iframe loading="lazy" width="560" height="315" src="%s" ></iframe>',
			$link
		);
	}

	/**
	 * This method generates an embed link if it detects Vimeo/YouTube Video URLs.
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function generate_video_embed_url( $url ) {
		if ( strpos( $url, 'vimeo.com/' ) !== false ) {
			$video_id = explode( 'vimeo.com/', $url )[1];
			if ( strpos( $video_id, '&' ) !== false ) {
				$video_id = explode( '&', $video_id )[0];
			}
			$embed_url = 'https://player.vimeo.com/video/' . $video_id;
		} elseif ( strpos( $url, 'youtube.com/' ) !== false ) {
			$video_id = explode( 'v=', $url )[1];
			if ( strpos( $video_id, '&' ) !== false ) {
				$video_id = explode( '&', $video_id )[0];
			}
			$embed_url = 'https://www.youtube.com/embed/' . $video_id;
		} elseif ( strpos( $url, 'youtu.be/' ) !== false ) {
			$video_id = explode( 'youtu.be/', $url )[1];
			if ( strpos( $video_id, '&' ) !== false ) {
				$video_id = explode( '&', $video_id )[0];
			}
			$embed_url = 'https://www.youtube.com/embed/' . $video_id;
		} else {
			$embed_url = $url;
		}
		return $embed_url;
	}
}
