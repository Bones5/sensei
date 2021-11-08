/**
 * WordPress dependencies
 */
import { useContext } from '@wordpress/element';
import { Icon, chevronUp, chevronDown } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import cn from 'classnames';

/**
 * Internal dependencies
 */
import { QuestionContext } from '../question-block/question-context';

/**
 * Toggle control for showing answer feedback blocks.
 */
export const AnswerFeedbackToggle = () => {
	const {
		answerFeedback: { showAnswerFeedback, toggleAnswerFeedback },
	} = useContext( QuestionContext );

	return (
		<div
			className={ cn(
				'sensei-lms-question-block__answer-feedback-toggle',
				{ 'is-visible': showAnswerFeedback }
			) }
		>
			<button
				className="sensei-lms-question-block__answer-feedback-toggle__header"
				onClick={ () => toggleAnswerFeedback( ! showAnswerFeedback ) }
			>
				{ __( 'Answer Feedback', 'sensei-lms' ) }
				<Icon
					className="sensei-lms-question-block__answer-feedback-toggle__icon"
					icon={ showAnswerFeedback ? chevronUp : chevronDown }
				/>
			</button>

			<div className="sensei-lms-question-block__answer-feedback-toggle__help">
				{ __(
					'Show feedback to students after they submit the quiz.',
					'sensei-lms'
				) }
			</div>
		</div>
	);
};
