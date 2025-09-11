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
                            onClick: () => onEdit(field),
                            className: 'btn-edit'
                        }, 'Edit'),
                        !field.is_default && React.createElement('button', {
                            key: 'btn-delete',
                            onClick: () => onDelete(field.id),
                            className: 'btn-delete'
                        }, 'Delete'),
                        React.createElement('button', {
                            key: 'btn-toggle',
                            onClick: () => onToggle(field.id),
                            className: `btn-toggle ${field.enabled ? 'enabled' : 'disabled'}`
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
                            onClick: () => onEdit({ section }),
                            className: 'btn-add-field'
                        }, 'Add First Field')
                    ])
                ])
            )
        ]);
    };
    
    // Simple Modal Component
    const SimpleModal = ({ isOpen, onClose, title, children }) => {
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
                    React.createElement('h2', { key: 'title' }, title),
                    React.createElement('button', {
                        key: 'close',
                        onClick: onClose,
                        className: 'modal-close'
                    }, '×')
                ]),
                React.createElement('div', {
                    key: 'modal-body',
                    className: 'modal-body'
                }, children)
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
        
        // Load initial data from WordPress localized script
        useEffect(() => {
            if (window.hezarfen_checkout_field_editor) {
                const data = window.hezarfen_checkout_field_editor;
                const customFields = data.custom_fields_data || {};
                const defaultFields = data.default_fields_data || {};
                
                // Organize fields by section
                const organizedFields = {
                    billing: [],
                    shipping: [],
                    order: []
                };
                
                // Add custom fields
                Object.entries(customFields).forEach(([fieldId, fieldData]) => {
                    const section = fieldData.section || 'billing';
                    organizedFields[section].push({
                        id: fieldId,
                        ...fieldData,
                        is_default: false
                    });
                });
                
                // Add default fields
                Object.entries(defaultFields).forEach(([fieldId, fieldData]) => {
                    const section = fieldData.section || 'billing';
                    organizedFields[section].push({
                        id: fieldId,
                        ...fieldData,
                        is_default: true
                    });
                });
                
                setFields(organizedFields);
                setLoading(false);
            }
        }, []);
        
        const handleDragEnd = (result) => {
            // Handle drag and drop logic here
            console.log('Drag ended:', result);
        };
        
        const handleEditField = (field) => {
            setModalField(field);
            setIsModalOpen(true);
        };
        
        const handleDeleteField = (fieldId) => {
            if (confirm('Are you sure you want to delete this field?')) {
                console.log('Delete field:', fieldId);
            }
        };
        
        const handleToggleField = (fieldId) => {
            console.log('Toggle field:', fieldId);
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
                    onClick: () => handleEditField({ section: 'billing', is_default: false }),
                    className: 'btn-add-field'
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
            
            React.createElement(SimpleModal, {
                key: 'modal',
                isOpen: isModalOpen,
                onClose: () => {
                    setIsModalOpen(false);
                    setModalField(null);
                },
                title: modalField?.id ? 'Edit Field' : 'Add New Field'
            }, React.createElement('p', {}, 'Field editor form will be implemented here'))
        ]);
    };
    
    // Make component globally available
    window.CheckoutFieldEditor = CheckoutFieldEditor;
    
})();