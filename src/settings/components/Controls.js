/**
 * Layout and form primitives, ported from react-settings-plugin so the
 * screens share markup and class names.
 */

export function Card( { children, className = '' } ) {
	return <div className={ `card ${ className }`.trim() }>{ children }</div>;
}

export function CardHead( { title, children } ) {
	return (
		<div className="card-head">
			<h2>{ title }</h2>
			{ children && <p>{ children }</p> }
		</div>
	);
}

export function CardBody( { children } ) {
	return <div className="card-body">{ children }</div>;
}

export function CardFoot( { children } ) {
	return <div className="card-foot">{ children }</div>;
}

export function Field( { label, description, htmlFor, children } ) {
	return (
		<div className="field">
			<div className="field-row">
				<div className="field-label">
					<label htmlFor={ htmlFor }>{ label }</label>
					{ description && (
						<span className="desc">{ description }</span>
					) }
				</div>
				<div className="field-control">{ children }</div>
			</div>
		</div>
	);
}

export function Help( { children } ) {
	return <p className="help">{ children }</p>;
}

export function Button( {
	variant = '',
	small = false,
	className = '',
	children,
	...rest
} ) {
	const classes = [
		'btn',
		variant ? `btn-${ variant }` : '',
		small ? 'btn-sm' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<button type="button" className={ classes } { ...rest }>
			{ children }
		</button>
	);
}

export function TextInput( { value, onChange, className = '', ...rest } ) {
	return (
		<input
			type="text"
			className={ `input ${ className }`.trim() }
			value={ value ?? '' }
			onChange={ ( event ) => onChange( event.target.value ) }
			{ ...rest }
		/>
	);
}

export function NumberInput( { value, onChange, className = '', ...rest } ) {
	return (
		<input
			type="number"
			className={ `input ${ className }`.trim() }
			value={ value ?? '' }
			onChange={ ( event ) => onChange( Number( event.target.value ) ) }
			{ ...rest }
		/>
	);
}

export function Select( { value, onChange, options, ...rest } ) {
	return (
		<select
			className="select"
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
			{ ...rest }
		>
			{ options.map( ( option ) => (
				<option
					key={ option.value }
					value={ option.value }
					disabled={ option.disabled }
				>
					{ option.label }
				</option>
			) ) }
		</select>
	);
}
