export function Notice( { tone = 'info', children } ) {
	return (
		<div
			className={ `corex-email-app__notice is-${ tone }` }
			role={ tone === 'error' ? 'alert' : 'status' }
		>
			{ children }
		</div>
	);
}

export function Field( { label, error, textarea, wide, ...input } ) {
	const Control = textarea ? 'textarea' : 'input';
	const controlId = input.id || `corex-email-${ input.name }`;

	return (
		<label htmlFor={ controlId } className={ wide ? 'is-wide' : '' }>
			{ label }
			<Control
				{ ...input }
				id={ controlId }
				aria-invalid={ error ? 'true' : undefined }
			/>
			{ error && (
				<span className="corex-email-app__field-error">{ error }</span>
			) }
		</label>
	);
}
