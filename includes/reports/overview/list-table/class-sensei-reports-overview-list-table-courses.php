<?php
/**
 * File containing the Sensei_Reports_Overview_List_Table_Courses class.
 *
 * @package sensei
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Courses overview list table class.
 *
 * @since 4.2.1
 */
class Sensei_Reports_Overview_List_Table_Courses extends Sensei_Reports_Overview_List_Table_Abstract {
	/**
	 * Sensei grading related services.
	 *
	 * @var Sensei_Grading
	 */
	private $grading;

	/**
	 * Sensei course related services.
	 *
	 * @var Sensei_Course
	 */
	private $course;

	/**
	 * Constructor
	 *
	 * @param Sensei_Grading                                  $grading Sensei grading related services.
	 * @param Sensei_Course                                   $course Sensei course related services.
	 * @param Sensei_Reports_Overview_Data_Provider_Interface $data_provider Report data provider.
	 */
	public function __construct( Sensei_Grading $grading, Sensei_Course $course, Sensei_Reports_Overview_Data_Provider_Interface $data_provider ) {
		// Load Parent token into constructor.
		parent::__construct( 'courses', $data_provider );

		$this->grading = $grading;
		$this->course  = $course;
	}

	/**
	 * Define the columns that are going to be used in the table
	 *
	 * @return array The array of columns to use with the table
	 */
	public function get_columns() {
		if ( $this->columns ) {
			return $this->columns;
		}

		$total_completions = Sensei_Utils::sensei_check_for_activity(
			array(
				'type'   => 'sensei_course_status',
				'status' => 'complete',
			)
		);
		$columns           = array(
			'title'              => sprintf(
			// translators: Placeholder value is the number of courses.
				__( 'Course (%d)', 'sensei-lms' ),
				esc_html( $this->total_items )
			),
			'last_activity'      => __( 'Last Activity', 'sensei-lms' ),
			'completions'        => sprintf(
			// translators: Placeholder value is the number of completed courses.
				__( 'Completed (%d)', 'sensei-lms' ),
				esc_html( $total_completions )
			),
			'average_percent'    => sprintf(
			// translators: Placeholder value is the average grade of all courses.
				__( 'Average Grade (%s%%)', 'sensei-lms' ),
				esc_html( ceil( $this->grading->get_courses_average_grade() ) )
			),
			'days_to_completion' => sprintf(
			// translators: Placeholder value is average days to completion.
				__( 'Days to Completion (%d)', 'sensei-lms' ),
				ceil( $this->course->get_days_to_completion_total() )
			),
		);

		// Backwards compatible filter name, moving forward should have single filter name.
		$columns = apply_filters( 'sensei_analysis_overview_courses_columns', $columns, $this );
		$columns = apply_filters( 'sensei_analysis_overview_columns', $columns, $this );

		$this->columns = $columns;

		return $this->columns;
	}

	/**
	 * Define the columns that are going to be used in the table
	 *
	 * @return array The array of columns to use with the table
	 */
	public function get_sortable_columns() {
		$columns = array(
			'title'           => array( 'title', false ),
			'completions'     => array( 'completions', false ),
			'average_percent' => array( 'average_percent', false ),
		);

		// Backwards compatible filter name, moving forward should have single filter name.
		$columns = apply_filters( 'sensei_analysis_overview_courses_columns_sortable', $columns, $this );
		$columns = apply_filters( 'sensei_analysis_overview_columns_sortable', $columns, $this );

		return $columns;
	}

	/**
	 * Generates the overall array for a single item in the display
	 *
	 * @param object $item The current item.
	 *
	 * @return array Report row data.
	 * @throws Exception If date-time conversion fails.
	 */
	protected function get_row_data( $item ) {
		// Last Activity.
		$last_activity_date = __( 'N/A', 'sensei-lms' );
		$lessons            = $this->course->course_lessons( $item->ID, 'any', 'ids' );

		if ( 0 < count( $lessons ) ) {
			$last_activity_date = $this->get_last_activity_date( array( 'post__in' => $lessons ) );
		}

		// Get Course Completions.
		$course_args        = array(
			'post_id' => $item->ID,
			'type'    => 'sensei_course_status',
			'status'  => 'complete',
		);
		$course_completions = Sensei_Utils::sensei_check_for_activity( apply_filters( 'sensei_analysis_course_completions', $course_args, $item ) );

		// Average Grade will be N/A if the course has no lessons or quizzes, if none of the lessons
		// have a status of 'graded', 'passed' or 'failed', or if none of the quizzes have grades.
		$average_grade = __( 'N/A', 'sensei-lms' );

		// Get grades only if the course has lessons and quizzes.
		if ( ! empty( $lessons ) && $this->course->course_quizzes( $item->ID, true ) ) {
			$grade_args = array(
				'post__in' => $lessons,
				'type'     => 'sensei_lesson_status',
				'status'   => array( 'graded', 'passed', 'failed' ),
				'meta_key' => 'grade', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			);

			$percent_count = Sensei_Utils::sensei_check_for_activity( apply_filters( 'sensei_analysis_course_percentage', $grade_args, $item ), false );
			$percent_total = $this->grading::get_course_users_grades_sum( $item->ID );

			if ( $percent_count > 0 && $percent_total >= 0 ) {
				$average_grade = Sensei_Utils::quotient_as_absolute_rounded_number( $percent_total, $percent_count, 2 ) . '%';
			}
		}

		// Properties `count_of_completions` and `days_to_completion` where added to items in
		// `Sensei_Analysis_Overview_List_Table::add_days_to_completion_to_courses_queries`.
		// We made it due to improve performance of the report. Don't try to access these properties outside.
		$average_completion_days = $item->count_of_completions > 0 ? ceil( $item->days_to_completion / $item->count_of_completions ) : __( 'N/A', 'sensei-lms' );

		// Output course data.
		/** This filter is documented in wp-includes/post-template.php */
		$course_title = apply_filters( 'the_title', $item->post_title, $item->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		if ( ! $this->csv_output ) {
			$url = add_query_arg(
				array(
					'page'      => $this->page_slug,
					'course_id' => $item->ID,
					'post_type' => $this->post_type,
				),
				admin_url( 'edit.php' )
			);

			$course_title = '<strong><a class="row-title" href="' . esc_url( $url ) . '">' . $course_title . '</a></strong>';
		}

		$column_data = apply_filters(
			'sensei_analysis_overview_column_data',
			array(
				'title'              => $course_title,
				'last_activity'      => $last_activity_date,
				'completions'        => $course_completions,
				'average_percent'    => $average_grade,
				'days_to_completion' => $average_completion_days,
			),
			$item,
			$this
		);

		$escaped_column_data = array();

		foreach ( $column_data as $key => $data ) {
			$escaped_column_data[ $key ] = wp_kses_post( $data );
		}

		return $escaped_column_data;
	}

	/**
	 * Get the date on which the last lesson was marked complete.
	 *
	 * @param array $args Array of arguments to pass to the comments query.
	 *
	 * @return string The last activity date, or N/A if none.
	 *
	 * @throws Exception If date-time conversion fails.
	 */
	private function get_last_activity_date( array $args ): string {
		$default_args  = array(
			'number' => 1,
			'type'   => 'sensei_lesson_status',
			'status' => [ 'complete', 'passed', 'graded' ],
		);
		$args          = wp_parse_args( $args, $default_args );
		$last_activity = Sensei_Utils::sensei_check_for_activity( $args, true );

		if ( ! $last_activity ) {
			return __( 'N/A', 'sensei-lms' );
		}

		// Return the full date when doing a CSV export.
		if ( $this->csv_output ) {
			return $last_activity->comment_date_gmt;
		}

		$timezone           = new DateTimeZone( 'GMT' );
		$now                = new DateTime( 'now', $timezone );
		$last_activity_date = new DateTime( $last_activity->comment_date_gmt, $timezone );
		$diff_in_days       = $now->diff( $last_activity_date )->days;

		// Show a human-readable date if activity is within 6 days.
		if ( $diff_in_days < 7 ) {
			return sprintf(
			/* translators: Time difference between two dates. %s: Number of seconds/minutes/etc. */
				__( '%s ago', 'sensei-lms' ),
				human_time_diff( strtotime( $last_activity->comment_date_gmt ) )
			);
		}

		return wp_date( get_option( 'date_format' ), $last_activity_date->getTimestamp(), $timezone );
	}

	/**
	 * The text for the search button.
	 *
	 * @return string
	 */
	public function search_button() {
		return __( 'Search Courses', 'sensei-lms' );
	}

	/**
	 * Return additional filters for current report.
	 *
	 * @return array
	 */
	protected function get_additional_filters(): array {
		return [
			'last_activity_date_from' => $this->get_start_date_and_time(),
			'last_activity_date_to'   => $this->get_end_date_and_time(),
		];
	}
}
