// React Checkout Field Editor - Compiled Version
(function() {
    'use strict';
    
    const { useState, useEffect, useRef } = React;
    const { DragDropContext, Droppable, Draggable } = window.ReactBeautifulDnd;
    
    // Field Component
    const Field = ({ field, index, onEdit, onDelete, onToggle }) => {
        const isHalfWidth = field.column_width === 'half';
        
        return React.createElement(Draggable, {
            draggableId: field.id,
            index: index,
            isDragDisabled: field.is_default
        }, (provided, snapshot) => 
            React.createElement('div', {
                ref: provided.innerRef,
                ...provided.draggableProps,
                className: `field-item ${isHalfWidth ? 'half-width' : 'full-width'} ${
                    snapshot.isDragging ? 'dragging' : ''
                } ${field.is_default ? 'default-field' : 'custom-field'}`
            }, [
                !field.is_default && React.createElement('div', {
                    key: 'drag-handle',
                    ...provided.dragHandleProps,
                    className: 'drag-handle'
                }, React.createElement('svg', {
                    width: 12,
                    height: 12,
                    viewBox: '0 0 24 24'
                }, [
                    React.createElement('circle', { key: 1, cx: 9, cy: 12, r: 1, fill: 'currentColor' }),
                    React.createElement('circle', { key: 2, cx: 9, cy: 5, r: 1, fill: 'currentColor' }),
                    React.createElement('circle', { key: 3, cx: 9, cy: 19, r: 1, fill: 'currentColor' }),
                    React.createElement('circle', { key: 4, cx: 15, cy: 12, r: 1, fill: 'currentColor' }),
                    React.createElement('circle', { key: 5, cx: 15, cy: 5, r: 1, fill: 'currentColor' }),
                    React.createElement('circle', { key: 6, cx: 15, cy: 19, r: 1, fill: 'currentColor' })
                ])),
                
                React.createElement('div', {
                    key: 'field-content',
                    className: 'field-content'
                }, [
                    React.createElement('div', {
                        key: 'field-info',
                        className: 'field-info'
                    }, [
                        React.createElement('span', {
                            key: 'field-name',
                            className: 'field-name'
                        }, field.label),
                        React.createElement('div', {
                            key: 'field-meta',
                            className: 'field-meta'
                        }, [
                            React.createElement('span', {
                                key: 'field-type',
                                className: 'field-type'
                            }, field.type),
                            isHalfWidth && React.createElement('span', {
                                key: 'width-badge',
                                className: 'width-badge'
                            }, '1/2'),
                            field.required && React.createElement('span', {
                                key: 'required-badge',
                                className: 'required-badge'
                            }, 'Required'),
                            !field.enabled && React.createElement('span', {
                                key: 'disabled-badge',
                                className: 'disabled-badge'
                            }, 'Disabled')
                        ])
                    ]),
                    
                    React.createElement('div', {
                        key: 'field-actions',
                        className: 'field-actions'
                    }, [
                        React.createElement('button', {
                            key: 'btn-edit',
                            onClick: (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onEdit(field);
                            },
                            className: 'btn-edit',
                            type: 'button'
                        }, 'Edit'),
                        !field.is_default && React.createElement('button', {
                            key: 'btn-delete',
                            onClick: (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onDelete(field.id);
                            },
                            className: 'btn-delete',
                            type: 'button'
                        }, 'Delete'),
                        React.createElement('button', {
                            key: 'btn-toggle',
                            onClick: (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onToggle(field.id);
                            },
                            className: `btn-toggle ${field.enabled ? 'enabled' : 'disabled'}`,
                            type: 'button'
                        }, field.enabled ? 'Disable' : 'Enable')
                    ])
                ])
            ])
        );
    };
    
    // Section Component
    const Section = ({ section, fields, onEdit, onDelete, onToggle }) => {
        // Organize fields into rows
        const organizeFieldsIntoRows = (fields) => {
            const rows = [];
            let currentRow = [];
            let fieldIndex = 0;
            
            fields.forEach((field) => {
                if (field.column_width === 'half') {
                    currentRow.push({ ...field, originalIndex: fieldIndex });
                    
                    if (currentRow.length === 2) {
                        rows.push({ type: 'row', fields: currentRow, startIndex: fieldIndex - 1 });
                        currentRow = [];
                    }
                } else {
                    if (currentRow.length > 0) {
                        rows.push({ type: 'row', fields: currentRow, startIndex: fieldIndex - currentRow.length });
                        currentRow = [];
                    }
                    
                    rows.push({ 
                        type: 'single', 
                        field: { ...field, originalIndex: fieldIndex }, 
                        index: fieldIndex 
                    });
                }
                fieldIndex++;
            });
            
            if (currentRow.length > 0) {
                rows.push({ type: 'row', fields: currentRow, startIndex: fieldIndex - currentRow.length });
            }
            
            return rows;
        };
        
        const fieldRows = organizeFieldsIntoRows(fields);
        
        return React.createElement('div', {
            className: 'section'
        }, [
            React.createElement('div', {
                key: 'section-header',
                className: 'section-header'
            }, [
                React.createElement('h3', { key: 'title' }, section.charAt(0).toUpperCase() + section.slice(1) + ' Fields'),
                React.createElement('span', { key: 'count', className: 'field-count' }, fields.length)
            ]),
            
            React.createElement(Droppable, {
                key: 'droppable',
                droppableId: section,
                type: 'field'
            }, (provided, snapshot) =>
                React.createElement('div', {
                    ref: provided.innerRef,
                    ...provided.droppableProps,
                    className: `section-fields ${snapshot.isDraggingOver ? 'drag-over' : ''}`
                }, [
                    fieldRows.map((row, rowIndex) => {
                        if (row.type === 'row') {
                            return React.createElement('div', {
                                key: `row-${rowIndex}`,
                                className: 'field-row'
                            }, row.fields.map((field, fieldIndex) =>
                                React.createElement(Field, {
                                    key: field.id,
                                    field: field,
                                    index: row.startIndex + fieldIndex,
                                    onEdit: onEdit,
                                    onDelete: onDelete,
                                    onToggle: onToggle
                                })
                            ));
                        } else {
                            return React.createElement(Field, {
                                key: row.field.id,
                                field: row.field,
                                index: row.index,
                                onEdit: onEdit,
                                onDelete: onDelete,
                                onToggle: onToggle
                            });
                        }
                    }),
                    provided.placeholder,
                    
                    fields.length === 0 && React.createElement('div', {
                        key: 'empty-section',
                        className: 'empty-section'
                    }, [
                        React.createElement('p', { key: 'text' }, 'No fields in this section'),
                        React.createElement('button', {
                            key: 'add-btn',
                            onClick: (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onEdit({ section });
                            },
                            className: 'btn-add-field',
                            type: 'button'
                        }, 'Add First Field')
                    ])
                ])
            )
        ]);
    };
    
    // Field Editor Modal Component
    const FieldEditorModal = ({ field, isOpen, onClose, onSave, fieldTypes, sections }) => {
        const [formData, setFormData] = useState({
            name: '',
            label: '',
            type: 'text',
            section: 'billing',
            placeholder: '',
            required: false,
            enabled: true,
            column_width: 'full',
            options: '',
            priority: 10
        });
        
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        
        // Update form data when field changes
        useEffect(() => {
            if (field && isOpen) {
                setFormData({
                    name: field.name || '',
                    label: field.label || '',
                    type: field.type || 'text',
                    section: field.section || 'billing',
                    placeholder: field.placeholder || '',
                    required: field.required || false,
                    enabled: field.enabled !== false,
                    column_width: field.column_width || 'full',
                    options: field.options || '',
                    priority: field.priority || 10
                });
                setErrors({});
            }
        }, [field, isOpen]);
        
        const handleSubmit = async (e) => {
            e.preventDefault();
            
            // Validate form
            const newErrors = {};
            if (!formData.name.trim()) newErrors.name = 'Field name is required';
            if (!formData.label.trim()) newErrors.label = 'Field label is required';
            if (!/^[a-zA-Z0-9_]+$/.test(formData.name)) {
                newErrors.name = 'Field name can only contain letters, numbers, and underscores';
            }
            
            if (Object.keys(newErrors).length > 0) {
                setErrors(newErrors);
                return;
            }
            
            setSaving(true);
            
            try {
                await onSave(formData);
                onClose();
            } catch (error) {
                console.error('Error saving field:', error);
            } finally {
                setSaving(false);
            }
        };
        
        const handleInputChange = (field, value) => {
            setFormData(prev => ({ ...prev, [field]: value }));
            if (errors[field]) {
                setErrors(prev => ({ ...prev, [field]: '' }));
            }
        };
        
        if (!isOpen) return null;
        
        return React.createElement('div', {
            className: 'modal-overlay',
            onClick: onClose
        }, 
            React.createElement('div', {
                className: 'modal-content',
                onClick: (e) => e.stopPropagation()
            }, [
                React.createElement('div', {
                    key: 'modal-header',
                    className: 'modal-header'
                }, [
                    React.createElement('h2', { key: 'title' }, field?.id ? 'Edit Field' : 'Add New Field'),
                    React.createElement('button', {
                        key: 'close',
                        onClick: onClose,
                        className: 'modal-close',
                        type: 'button'
                    }, '×')
                ]),
                
                React.createElement('form', {
                    key: 'modal-form',
                    onSubmit: handleSubmit,
                    className: 'modal-body'
                }, [
                    // Basic Info Row
                    React.createElement('div', {
                        key: 'basic-row',
                        className: 'form-row'
                    }, [
                        React.createElement('div', {
                            key: 'name-field',
                            className: 'form-field'
                        }, [
                            React.createElement('label', { key: 'label' }, 'Field Name'),
                            React.createElement('input', {
                                key: 'input',
                                type: 'text',
                                value: formData.name,
                                onChange: (e) => handleInputChange('name', e.target.value),
                                disabled: field?.is_default,
                                className: errors.name ? 'error' : '',
                                placeholder: 'e.g. custom_field_1'
                            }),
                            errors.name && React.createElement('span', {
                                key: 'error',
                                className: 'field-error'
                            }, errors.name)
                        ]),
                        
                        React.createElement('div', {
                            key: 'label-field',
                            className: 'form-field'
                        }, [
                            React.createElement('label', { key: 'label' }, 'Field Label'),
                            React.createElement('input', {
                                key: 'input',
                                type: 'text',
                                value: formData.label,
                                onChange: (e) => handleInputChange('label', e.target.value),
                                className: errors.label ? 'error' : '',
                                placeholder: 'e.g. Company Name'
                            }),
                            errors.label && React.createElement('span', {
                                key: 'error',
                                className: 'field-error'
                            }, errors.label)
                        ])
                    ]),
                    
                    // Type and Section Row
                    React.createElement('div', {
                        key: 'type-section-row',
                        className: 'form-row'
                    }, [
                        React.createElement('div', {
                            key: 'type-field',
                            className: 'form-field'
                        }, [
                            React.createElement('label', { key: 'label' }, 'Field Type'),
                            React.createElement('select', {
                                key: 'select',
                                value: formData.type,
                                onChange: (e) => handleInputChange('type', e.target.value),
                                disabled: field?.is_default
                            }, Object.entries(fieldTypes).map(([value, label]) =>
                                React.createElement('option', {
                                    key: value,
                                    value: value
                                }, label)
                            ))
                        ]),
                        
                        React.createElement('div', {
                            key: 'section-field',
                            className: 'form-field'
                        }, [
                            React.createElement('label', { key: 'label' }, 'Section'),
                            React.createElement('select', {
                                key: 'select',
                                value: formData.section,
                                onChange: (e) => handleInputChange('section', e.target.value),
                                disabled: field?.is_default
                            }, Object.entries(sections).map(([value, label]) =>
                                React.createElement('option', {
                                    key: value,
                                    value: value
                                }, label)
                            ))
                        ])
                    ]),
                    
                    // Placeholder Field
                    React.createElement('div', {
                        key: 'placeholder-field',
                        className: 'form-field'
                    }, [
                        React.createElement('label', { key: 'label' }, 'Placeholder'),
                        React.createElement('input', {
                            key: 'input',
                            type: 'text',
                            value: formData.placeholder,
                            onChange: (e) => handleInputChange('placeholder', e.target.value),
                            placeholder: 'Enter placeholder text...'
                        })
                    ]),
                    
                    // Column Width Selection
                    React.createElement('div', {
                        key: 'width-field',
                        className: 'form-field'
                    }, [
                        React.createElement('label', { key: 'label' }, 'Column Width'),
                        React.createElement('div', {
                            key: 'width-selector',
                            className: 'width-selector'
                        }, [
                            React.createElement('button', {
                                key: 'full-width',
                                type: 'button',
                                className: `width-option ${formData.column_width === 'full' ? 'active' : ''}`,
                                onClick: () => handleInputChange('column_width', 'full')
                            }, [
                                React.createElement('div', {
                                    key: 'preview',
                                    className: 'width-preview full'
                                }, React.createElement('div', {
                                    className: 'preview-field'
                                }, 'Full Width')),
                                'Full Width (1/1)'
                            ]),
                            
                            React.createElement('button', {
                                key: 'half-width',
                                type: 'button',
                                className: `width-option ${formData.column_width === 'half' ? 'active' : ''}`,
                                onClick: () => handleInputChange('column_width', 'half')
                            }, [
                                React.createElement('div', {
                                    key: 'preview',
                                    className: 'width-preview half'
                                }, [
                                    React.createElement('div', {
                                        key: 'field1',
                                        className: 'preview-field'
                                    }, 'Half'),
                                    React.createElement('div', {
                                        key: 'field2',
                                        className: 'preview-field'
                                    }, 'Half')
                                ]),
                                'Half Width (1/2)'
                            ])
                        ])
                    ]),
                    
                    // Options Field (for select/radio)
                    (formData.type === 'select' || formData.type === 'radio') && React.createElement('div', {
                        key: 'options-field',
                        className: 'form-field'
                    }, [
                        React.createElement('label', { key: 'label' }, 'Options (one per line)'),
                        React.createElement('textarea', {
                            key: 'textarea',
                            value: formData.options,
                            onChange: (e) => handleInputChange('options', e.target.value),
                            placeholder: 'Option 1\nOption 2\nkey|Display Value',
                            rows: 4
                        })
                    ]),
                    
                    // Checkboxes
                    React.createElement('div', {
                        key: 'checkboxes',
                        className: 'form-checkboxes'
                    }, [
                        React.createElement('label', {
                            key: 'required-checkbox',
                            className: 'checkbox-field'
                        }, [
                            React.createElement('input', {
                                key: 'input',
                                type: 'checkbox',
                                checked: formData.required,
                                onChange: (e) => handleInputChange('required', e.target.checked)
                            }),
                            'Required Field'
                        ]),
                        
                        React.createElement('label', {
                            key: 'enabled-checkbox',
                            className: 'checkbox-field'
                        }, [
                            React.createElement('input', {
                                key: 'input',
                                type: 'checkbox',
                                checked: formData.enabled,
                                onChange: (e) => handleInputChange('enabled', e.target.checked)
                            }),
                            'Enabled'
                        ])
                    ]),
                    
                    // Modal Footer
                    React.createElement('div', {
                        key: 'modal-footer',
                        className: 'modal-footer'
                    }, [
                        React.createElement('button', {
                            key: 'cancel',
                            type: 'button',
                            onClick: onClose,
                            className: 'btn-cancel',
                            disabled: saving
                        }, 'Cancel'),
                        
                        React.createElement('button', {
                            key: 'save',
                            type: 'submit',
                            className: 'btn-save',
                            disabled: saving
                        }, saving ? 'Saving...' : (field?.id ? 'Update Field' : 'Add Field'))
                    ])
                ])
            ])
        );
    };
    
    // Main Checkout Field Editor Component
    const CheckoutFieldEditor = () => {
        const [fields, setFields] = useState({
            billing: [],
            shipping: [],
            order: []
        });
        
        const [modalField, setModalField] = useState(null);
        const [isModalOpen, setIsModalOpen] = useState(false);
        const [loading, setLoading] = useState(true);
        
        const fieldTypes = {
            text: 'Text',
            email: 'Email',
            tel: 'Phone',
            number: 'Number',
            textarea: 'Textarea',
            select: 'Select',
            radio: 'Radio',
            checkbox: 'Checkbox',
            date: 'Date'
        };
        
        const sections = {
            billing: 'Billing',
            shipping: 'Shipping', 
            order: 'Order'
        };
        
        // Load fields on component mount and prevent unwanted form submissions
        useEffect(() => {
            loadFields();
            
            // Prevent any unwanted form submissions in the React component area
            const preventFormSubmit = (e) => {
                const target = e.target;
                const reactRoot = document.getElementById('hezarfen-checkout-field-editor-react-root');
                
                // Only prevent if it's within our React component and NOT our modal form
                if (reactRoot && reactRoot.contains(target)) {
                    if (target.tagName === 'FORM' && !target.closest('.modal-content')) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    if (target.type === 'submit' && !target.closest('.modal-content')) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }
            };
            
            document.addEventListener('submit', preventFormSubmit, true);
            document.addEventListener('click', preventFormSubmit, true);
            
            return () => {
                document.removeEventListener('submit', preventFormSubmit, true);
                document.removeEventListener('click', preventFormSubmit, true);
            };
        }, []);
        
        const handleDragEnd = (result) => {
            // Handle drag and drop logic here
            console.log('Drag ended:', result);
        };
        
        const handleEditField = (field) => {
            setModalField(field);
            setIsModalOpen(true);
        };
        
        const handleSaveField = async (fieldData) => {
            try {
                const response = await fetch(window.hezarfen_checkout_field_editor.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'hezarfen_save_checkout_field',
                        nonce: window.hezarfen_checkout_field_editor.nonce,
                        field_data: JSON.stringify(fieldData)
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Reload fields
                    await loadFields();
                    setIsModalOpen(false);
                    setModalField(null);
                } else {
                    throw new Error(result.data?.message || 'Failed to save field');
                }
            } catch (error) {
                console.error('Error saving field:', error);
                alert('Error saving field: ' + error.message);
            }
        };
        
        const handleDeleteField = async (fieldId) => {
            if (!confirm('Are you sure you want to delete this field?')) return;
            
            try {
                const response = await fetch(window.hezarfen_checkout_field_editor.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'hezarfen_delete_checkout_field',
                        nonce: window.hezarfen_checkout_field_editor.nonce,
                        field_id: fieldId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadFields();
                } else {
                    throw new Error(result.data?.message || 'Failed to delete field');
                }
            } catch (error) {
                console.error('Error deleting field:', error);
                alert('Error deleting field: ' + error.message);
            }
        };
        
        const handleToggleField = async (fieldId) => {
            // Find the field and toggle its enabled state
            const newFields = { ...fields };
            let targetField = null;
            
            Object.keys(newFields).forEach(section => {
                const field = newFields[section].find(f => f.id === fieldId);
                if (field) {
                    field.enabled = !field.enabled;
                    targetField = field;
                }
            });
            
            if (targetField) {
                setFields(newFields);
                await handleSaveField(targetField);
            }
        };
        
        const loadFields = async () => {
            try {
                setLoading(true);
                const response = await fetch(window.hezarfen_checkout_field_editor.ajax_url + '?action=hezarfen_get_checkout_fields&nonce=' + window.hezarfen_checkout_field_editor.nonce);
                const result = await response.json();
                
                if (result.success) {
                    setFields(result.data);
                }
            } catch (error) {
                console.error('Error loading fields:', error);
            } finally {
                setLoading(false);
            }
        };
        
        if (loading) {
            return React.createElement('div', {
                className: 'checkout-field-editor'
            }, React.createElement('div', {
                className: 'loading'
            }, 'Loading fields...'));
        }
        
        return React.createElement('div', {
            className: 'checkout-field-editor'
        }, [
            React.createElement('div', {
                key: 'editor-header',
                className: 'editor-header'
            }, [
                React.createElement('h1', { key: 'title' }, 'Checkout Fields'),
                React.createElement('button', {
                    key: 'add-btn',
                    onClick: (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleEditField({ section: 'billing', is_default: false });
                    },
                    className: 'btn-add-field',
                    type: 'button'
                }, 'Add New Field')
            ]),
            
            React.createElement(DragDropContext, {
                key: 'drag-context',
                onDragEnd: handleDragEnd
            }, 
                React.createElement('div', {
                    className: 'sections-container'
                }, Object.entries(sections).map(([sectionKey, sectionLabel]) =>
                    React.createElement(Section, {
                        key: sectionKey,
                        section: sectionKey,
                        fields: fields[sectionKey] || [],
                        onEdit: handleEditField,
                        onDelete: handleDeleteField,
                        onToggle: handleToggleField
                    })
                ))
            ),
            
            React.createElement(FieldEditorModal, {
                key: 'modal',
                field: modalField,
                isOpen: isModalOpen,
                onClose: () => {
                    setIsModalOpen(false);
                    setModalField(null);
                },
                onSave: handleSaveField,
                fieldTypes: fieldTypes,
                sections: sections
            })
        ]);
    };
    
    // Make component globally available
    window.CheckoutFieldEditor = CheckoutFieldEditor;
    
})();