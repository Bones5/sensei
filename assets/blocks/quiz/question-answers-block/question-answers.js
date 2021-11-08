/**
 * WordPress dependencies
 */
import { useContext } from '@wordpress/element';

/**
 * External dependencies
 */
import cn from 'classnames';

/**
 * Internal dependencies
 */
import { AnswerFeedbackToggle } from '../answer-feedback-block/answer-feedback-toggle';
import { QuestionContext } from '../question-block/question-context';

/**
 * Question Description control.
 *
 * @param {Object} props
 */
const QuestionAnswers = ( props ) => {
	const {
		answer,
		setAttributes,
		AnswerBlock,
		hasSelected,
		canHaveFeedback,
	} = useContext( QuestionContext );
	return (
		<div className={ cn( 'sensei-lms-question-answers-block' ) }>
			{ AnswerBlock?.edit && (
				<>
					<AnswerBlock.edit
						attributes={ answer }
						setAttributes={ ( next ) =>
							setAttributes( {
								answer: { ...answer, ...next },
							} )
						}
						hasSelected={ hasSelected }
						blockProps={ props }
					/>
					{ canHaveFeedback && hasSelected && (
						<AnswerFeedbackToggle />
					) }
				</>
			) }
		</div>
	);
};

export default QuestionAnswers;
