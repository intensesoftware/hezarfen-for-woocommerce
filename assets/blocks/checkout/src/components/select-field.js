/**
 * A select field that reuses WooCommerce's native block select markup/classes
 * (`wc-blocks-components-select`) so it visually matches the core checkout
 * fields — same border, label and spacing.
 *
 * @param {Object}                                       props             Component props.
 * @param {string}                                       props.id          Field id.
 * @param {string}                                       [props.className]  Extra wrapper class.
 * @param {string}                                       props.label       Field label.
 * @param {string}                                       props.value       Selected value.
 * @param {Function}                                     props.onChange    Change handler (native event).
 * @param {Array<{value: string, label: string}>}        props.options     Option list.
 * @param {boolean}                                      [props.disabled]   Whether the select is disabled.
 * @param {string}                                       props.placeholder Empty-option label.
 * @return {JSX.Element} The select field.
 */
const SelectField = ( {
	id,
	className = '',
	label,
	value,
	onChange,
	options,
	disabled = false,
	placeholder,
} ) => (
	<div className={ `wc-blocks-components-select ${ className }`.trim() }>
		<div className="wc-blocks-components-select__container">
			<label htmlFor={ id } className="wc-blocks-components-select__label">
				{ label }
			</label>
			<select
				id={ id }
				size="1"
				className="wc-blocks-components-select__select"
				value={ value }
				onChange={ onChange }
				disabled={ disabled }
			>
				<option value="">{ placeholder }</option>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		</div>
	</div>
);

export default SelectField;
