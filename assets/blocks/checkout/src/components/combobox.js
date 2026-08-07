/**
 * A lightweight, accessible, searchable combobox for the checkout block.
 *
 * Filtering is purely client-side over the options that are already loaded — no
 * per-keystroke AJAX. The markup reuses WooCommerce's own
 * `wc-blocks-components-select` classes so the field matches the native checkout
 * fields, with a custom filtered dropdown list below the input.
 *
 * @param {Object}                                props               Component props.
 * @param {string}                                props.id            Field id.
 * @param {string}                                [props.className]   Extra wrapper class.
 * @param {string}                                props.label         Field label.
 * @param {string}                                props.value         Selected option value.
 * @param {Function}                              props.onChange      Called with the new value.
 * @param {Array<{value: string, label: string}>} props.options       Option list.
 * @param {boolean}                               [props.disabled]    Whether the field is disabled.
 * @param {string}                                props.placeholder   Placeholder text.
 * @param {string}                                props.noResultsText "No results" text.
 * @return {JSX.Element} The combobox.
 */
import { useState, useRef, useEffect, useMemo } from '@wordpress/element';

/**
 * Normalises a string for accent-insensitive Turkish search: Turkish-aware
 * lowercasing (so "İ" → "i", "I" → "ı") followed by folding Turkish letters to
 * their ASCII counterparts, so typing "izmir" matches "İzmir", "sanliurfa"
 * matches "Şanlıurfa", etc.
 *
 * @param {string} value Input string.
 * @return {string} Normalised string.
 */
const normalize = ( value ) =>
	( value || '' )
		.toLocaleLowerCase( 'tr' )
		.replace( /ı/g, 'i' )
		.replace( /ğ/g, 'g' )
		.replace( /ü/g, 'u' )
		.replace( /ş/g, 's' )
		.replace( /ö/g, 'o' )
		.replace( /ç/g, 'c' );

const Combobox = ( {
	id,
	className = '',
	label,
	value,
	onChange,
	options,
	disabled = false,
	placeholder,
	noResultsText,
} ) => {
	const [ open, setOpen ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const [ highlight, setHighlight ] = useState( 0 );
	const rootRef = useRef( null );
	const listRef = useRef( null );

	const selectedLabel = useMemo( () => {
		const found = options.find( ( o ) => o.value === value );
		return found ? found.label : '';
	}, [ options, value ] );

	const filtered = useMemo( () => {
		const q = normalize( query.trim() );
		if ( ! q ) {
			return options;
		}
		return options.filter( ( o ) => normalize( o.label ).includes( q ) );
	}, [ options, query ] );

	// Close the dropdown when clicking outside the component.
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const onDocMouseDown = ( event ) => {
			if ( rootRef.current && ! rootRef.current.contains( event.target ) ) {
				setOpen( false );
				setQuery( '' );
			}
		};
		document.addEventListener( 'mousedown', onDocMouseDown );
		return () => document.removeEventListener( 'mousedown', onDocMouseDown );
	}, [ open ] );

	// Reset the highlighted row whenever the visible list changes.
	useEffect( () => {
		setHighlight( 0 );
	}, [ query, open ] );

	// Keep the highlighted row scrolled into view.
	useEffect( () => {
		if ( open && listRef.current && listRef.current.children[ highlight ] ) {
			listRef.current.children[ highlight ].scrollIntoView( {
				block: 'nearest',
			} );
		}
	}, [ highlight, open ] );

	const choose = ( option ) => {
		onChange( option.value );
		setOpen( false );
		setQuery( '' );
	};

	const onKeyDown = ( event ) => {
		if ( disabled ) {
			return;
		}

		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				if ( ! open ) {
					setOpen( true );
					return;
				}
				setHighlight( ( h ) => Math.min( h + 1, filtered.length - 1 ) );
				break;
			case 'ArrowUp':
				event.preventDefault();
				setHighlight( ( h ) => Math.max( h - 1, 0 ) );
				break;
			case 'Enter':
				if ( open && filtered[ highlight ] ) {
					event.preventDefault();
					choose( filtered[ highlight ] );
				}
				break;
			case 'Escape':
				setOpen( false );
				setQuery( '' );
				break;
			default:
				break;
		}
	};

	return (
		<div
			ref={ rootRef }
			className={ `wc-blocks-components-select hezarfen-combobox ${ className }`.trim() }
		>
			<div className="wc-blocks-components-select__container">
				<label htmlFor={ id } className="wc-blocks-components-select__label">
					{ label }
				</label>
				<input
					id={ id }
					type="text"
					role="combobox"
					aria-expanded={ open }
					aria-autocomplete="list"
					autoComplete="off"
					className="wc-blocks-components-select__select hezarfen-combobox__input"
					value={ open ? query : selectedLabel }
					placeholder={ placeholder }
					disabled={ disabled }
					onFocus={ () => setOpen( true ) }
					onChange={ ( event ) => {
						setQuery( event.target.value );
						setOpen( true );
					} }
					onKeyDown={ onKeyDown }
				/>
				{ open && ! disabled && (
					<ul
						ref={ listRef }
						className="hezarfen-combobox__list"
						role="listbox"
					>
						{ filtered.length === 0 && (
							<li className="hezarfen-combobox__empty">
								{ noResultsText }
							</li>
						) }
						{ filtered.map( ( option, index ) => (
							<li
								key={ option.value }
								role="option"
								aria-selected={ option.value === value }
								className={
									'hezarfen-combobox__option' +
									( index === highlight ? ' is-highlighted' : '' ) +
									( option.value === value ? ' is-selected' : '' )
								}
								onMouseDown={ ( event ) => {
									event.preventDefault();
									choose( option );
								} }
								onMouseEnter={ () => setHighlight( index ) }
							>
								{ option.label }
							</li>
						) ) }
					</ul>
				) }
			</div>
		</div>
	);
};

export default Combobox;
