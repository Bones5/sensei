/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';

import { useSetting } from '@wordpress/block-editor';
/**
 * External dependencies
 */
import { compact } from 'lodash';

const findColorBySlug = ( colors, slug ) =>
	colors?.find( ( color ) => color.slug === slug )?.color;

/**
 * useColors hook.
 *
 * This hook encapsulate the logic to update attributes when colors are updated.
 * It is heavily dependent of the Gutenberg with-colors.
 *
 * @param {Object}   props                            Hook Props.
 * @param {Function} props.setCategoryBackgroundColor Set the background color. It is created by Gutenberg with-colors hook.
 * @param {Function} props.setCategoryTextColor       Set the category text color. It is created by Gutenberg with-colors hook.
 * @param {Function} props.attributes                 Block attributes.
 * @param {Function} props.setAttributes              Update the block attributes to be stored during the save.
 * @param {Function} props.categoryTextColor          Selected category Text Color Object enhanced by Gutenberg with-colors hook.
 * @param {Function} props.categoryBackgroundColor    Selected category Background Color Object enhanced by Gutenberg with-colors hook.
 * @return {Object}  Object containing the textColor, backgroundColor and the setter to update them.
 */
const useColors = ( {
	setCategoryBackgroundColor,
	setCategoryTextColor,
	attributes,
	setAttributes,
	categoryTextColor,
	categoryBackgroundColor,
} ) => {
	// It set the block saved colors
	const { categoryStyle } = attributes;
	const themeColors = useSetting( 'color.palette.theme' );

	// It set the internal colors state using the colors incoming from a saved block or the theme colors.
	useEffect( () => {
		setCategoryTextColor(
			categoryStyle?.style.color ||
				findColorBySlug( themeColors, 'primary' )
		);
		setCategoryBackgroundColor(
			categoryStyle?.style.backgroundColor ||
				findColorBySlug( themeColors, 'background' )
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ themeColors ] );

	// It updates the block attributes with the new colors already parsed by with-colors hook.
	useEffect( () => {
		setAttributes( {
			...attributes,
			categoryStyle: {
				classes: compact( [
					categoryTextColor?.class,
					categoryBackgroundColor?.class,
				] ),
				style: {
					color: categoryTextColor?.color,
					backgroundColor: categoryBackgroundColor?.color,
				},
			},
		} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ categoryTextColor, categoryBackgroundColor ] );

	return {
		setTextColor: setCategoryTextColor,
		setBackgroundColor: setCategoryBackgroundColor,
		textColor: categoryTextColor || null,
		backgroundColor: categoryBackgroundColor || null,
	};
};

export default useColors;
