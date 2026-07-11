import { __ } from '@wordpress/i18n';

export function StudioTabs( { tabs, active, onChange } ) {
	return (
		<nav
			className="corex-email-app__tabs"
			aria-label={ __( 'Email Studio sections', 'corex' ) }
		>
			{ tabs.map( ( tab ) => (
				<button
					key={ tab.key }
					type="button"
					className={ active === tab.key ? 'is-active' : '' }
					aria-current={ active === tab.key ? 'page' : undefined }
					onClick={ () => onChange( tab.key ) }
				>
					{ tab.label }
				</button>
			) ) }
		</nav>
	);
}
