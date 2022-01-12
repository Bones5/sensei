/* eslint-disable camelcase */

/**
 * WordPress dependencies
 */
import {
	BlockControls,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	PanelRow,
	RangeControl,
	ToggleControl,
	BaseControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import NumberControl from '../../editor-components/number-control';
import { isQuestionEmpty } from '../data';
import {
	PaginationSidebarSettings,
	PaginationToolbarSettings,
} from './pagination-settings';
import { useOpenQuizSettings } from './use-open-quiz-settings';
import CogIcon from '../../../icons/cog.svg';

/**
 * Quiz settings.
 *
 * @param {Object}   props                    Block props.
 * @param {Object}   props.attributes         Block attributes
 * @param {Object}   props.attributes.options Current setting options.
 * @param {Function} props.setAttributes      Set attributes function.
 * @param {string}   props.clientId           Block ID.
 */
const QuizSettings = ( {
	attributes: { options },
	setAttributes,
	clientId,
} ) => {
	const {
		passRequired,
		quizPassmark,
		autoGrade,
		allowRetakes,
		randomQuestionOrder,
		showQuestions,
		failedShowAnswerFeedback,
		failedShowCorrectAnswers,
		failedIndicateIncorrect,
		pagination,
	} = options;

	const createChangeHandler = ( optionKey ) => ( value ) =>
		setAttributes( { options: { ...options, [ optionKey ]: value } } );

	// Update the pagination options function used for block settings.
	const updatePagination = ( updatedPagination ) =>
		setAttributes( {
			options: {
				...options,
				pagination: { ...pagination, ...updatedPagination },
			},
		} );

	const openQuizSettings = useOpenQuizSettings( clientId );

	const questions = useSelect(
		( select ) =>
			select( 'core/block-editor' )
				.getBlock( clientId )
				.innerBlocks.filter(
					( questionBlock ) =>
						! isQuestionEmpty( questionBlock.attributes )
				),
		[ clientId ]
	);

	const questionCount = questions.reduce(
		( count, question ) =>
			count +
			( question.attributes.type === 'category-question'
				? question.attributes.options.number
				: 1 ),
		0
	);

	useEffect( () => {
		if ( showQuestions > questionCount ) {
			setAttributes( {
				options: { ...options, showQuestions: questionCount },
			} );
		}
	}, [ options, questionCount, setAttributes, showQuestions ] );

	return (
		<>
			<Button
				className="sensei-lms-quiz-block__settings-link"
				onClick={ openQuizSettings }
				icon={ CogIcon }
			>
				{ __( 'Quiz settings', 'sensei-lms' ) }
			</Button>
			<InspectorControls>
				<PanelBody
					title={ __( 'Quiz settings', 'sensei-lms' ) }
					initialOpen={ true }
				>
					<PanelRow>
						<ToggleControl
							checked={ passRequired }
							onChange={ createChangeHandler( 'passRequired' ) }
							label={ __( 'Pass Required', 'sensei-lms' ) }
						/>
					</PanelRow>
					{ passRequired && (
						<>
							<PanelRow>
								<RangeControl
									label={ 'Passing Grade (%)' }
									value={ quizPassmark }
									onChange={ createChangeHandler(
										'quizPassmark'
									) }
									min={ 0 }
									max={ 100 }
									initialPosition={ 100 }
								/>
							</PanelRow>
							<PanelRow>
								<div>
									<BaseControl
										id="sensei-lms-quiz-block-failed-feedback-options"
										className="sensei-lms-subsection-control"
										help={ __(
											'What students see when reviewing their quiz after grading.',
											'sensei-lms'
										) }
									>
										<h3>
											{ __(
												'If student does not pass quiz',
												'sensei-lms'
											) }
										</h3>
									</BaseControl>
									<ToggleControl
										checked={ failedIndicateIncorrect }
										onChange={ createChangeHandler(
											'failedIndicateIncorrect'
										) }
										label={ __(
											'Indicate which questions are incorrect.',
											'sensei-lms'
										) }
									/>
									<ToggleControl
										checked={ failedShowCorrectAnswers }
										onChange={ createChangeHandler(
											'failedShowCorrectAnswers'
										) }
										label={ __(
											'Show correct answers.',
											'sensei-lms'
										) }
									/>
									<ToggleControl
										checked={ failedShowAnswerFeedback }
										onChange={ createChangeHandler(
											'failedShowAnswerFeedback'
										) }
										label={ __(
											'Show “Answer Feedback” text.',
											'sensei-lms'
										) }
									/>
								</div>
							</PanelRow>
							<hr />
						</>
					) }
					<PanelRow>
						<ToggleControl
							checked={ autoGrade }
							onChange={ createChangeHandler( 'autoGrade' ) }
							label={ __( 'Auto Grade', 'sensei-lms' ) }
							help={ __(
								'Automatically grade Multiple Choice, True/False and Gap Fill questions that have a non-zero point value.',
								'sensei-lms'
							) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							checked={ allowRetakes }
							onChange={ createChangeHandler( 'allowRetakes' ) }
							label={ __( 'Allow Retakes', 'sensei-lms' ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							checked={ randomQuestionOrder }
							onChange={ createChangeHandler(
								'randomQuestionOrder'
							) }
							label={ __(
								'Random Question Order',
								'sensei-lms'
							) }
						/>
					</PanelRow>
					<PanelRow>
						<NumberControl
							id="sensei-quiz-settings-show-questions"
							label={ __( 'Number of Questions', 'sensei-lms' ) }
							help={ __(
								'Display a random selection of questions.',
								'sensei-lms'
							) }
							allowReset
							resetLabel={ __( 'All', 'sensei-lms' ) }
							min={ 0 }
							max={ questionCount }
							step={ 1 }
							value={ showQuestions }
							placeholder={ __( 'All', 'sensei-lms' ) }
							onChange={ createChangeHandler( 'showQuestions' ) }
						/>
					</PanelRow>
				</PanelBody>
				<PaginationSidebarSettings
					settings={ pagination }
					updatePagination={ updatePagination }
				/>
				<PanelColorSettings
					title={ __( 'Color settings', 'sensei-lms' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: pagination.progressBarColor,
							onChange: ( value ) =>
								updatePagination( { progressBarColor: value } ),
							label: __( 'Progress bar color', 'sensei-lms' ),
						},
						{
							value: pagination.progressBarBackground,
							onChange: ( value ) =>
								updatePagination( {
									progressBarBackground: value,
								} ),
							label: __(
								'Progress bar background color',
								'sensei-lms'
							),
						},
					] }
				/>
			</InspectorControls>
			<BlockControls>
				<PaginationToolbarSettings
					settings={ pagination }
					onChange={ updatePagination }
				/>
			</BlockControls>
		</>
	);
};

export default QuizSettings;
