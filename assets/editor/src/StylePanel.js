/**
 * Global design settings (shared by all templates and documents).
 */
import { __ } from '@wordpress/i18n';
import Field from './fields';

export default function StylePanel( { fields, style, setStyle } ) {
	return (
		<div className="odocs-inspector">
			<p className="odocs-help">{ __( 'These settings apply to all documents.', 'order-documents' ) }</p>
			{ fields.map( ( field ) => (
				<Field
					key={ field.id }
					field={ { ...field, allowAccent: false } }
					value={ style[ field.id ] }
					onChange={ ( v ) => setStyle( { ...style, [ field.id ]: v } ) }
				/>
			) ) }
		</div>
	);
}
