import { createRoot } from '@wordpress/element';
import App from './App';
import './editor.scss';

const root = document.getElementById( 'odocs-editor-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
