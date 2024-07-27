import { __ } from '@wordpress/i18n';
import { Disabled, PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit ( { attributes, setAttributes } ) {
	const toggleAttribute = ( attributeName ) => ( newValue ) =>
		setAttributes( { [ attributeName ]: newValue } );

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody
					title={__( 'Settings', 'easy-digital-downloads' )}
				>
					<SelectControl
						label={__( 'Type', 'easy-digital-downloads' )}
						value={attributes.widget_type}
						options={[
							{
								'value': 'buttons',
								'label': __( 'Buttons', 'easy-digital-downloads' )
							},
							{
								'value': 'dropdown',
								'label': __( 'Dropdown', 'easy-digital-downloads' )
							}
						]}
						onChange={toggleAttribute( 'widget_type' )}
					/>
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block="edd/currency-selector"
					attributes={{ ...attributes }}
				/>
			</Disabled>
		</div>
	);
}
