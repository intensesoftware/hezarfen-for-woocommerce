// React Checkout Field Editor with Native Drag & Drop
(function() {
    'use strict';
    
    const { useState, useEffect } = React;
    
    // Field Component with Drag & Drop
    const Field = ({ field, onEdit, onDelete, onToggle }) => {
        // Safety checks for field properties
        if (!field || !field.id) {
            return React.createElement('div', { className: 'field-item-error' }, 'Invalid field data');
        }
        
        const isHalfWidth = field.column_width === 'half';
        
        const handleDragStart = (e) => {
            if (field.is_default) {
                e.preventDefault();
                return;
            }
            
            e.dataTransfer.setData('text/plain', JSON.stringify({
                id: field.id,
                section: field.section,
                index: field.index
            }));
            e.dataTransfer.effectAllowed = 'move';
            
            // Add dragging class
            setTimeout(() => {
                e.target.classList.add('dragging');
            }, 0);
        };
        
        const handleDragEnd = (e) => {
            e.target.classList.remove('dragging');
        };
        
        const handleDragOver = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        };
        
        const handleDrop = (e) => {
            e.preventDefault();
            
            try {
                const dragData = JSON.parse(e.dataTransfer.getData('text/plain'));
                const dropTarget = {
                    id: field.id,
                    section: field.section,
                    index: field.index
                };
                
                if (dragData.id !== dropTarget.id) {
                    // Trigger reorder
                    window.triggerFieldReorder && window.triggerFieldReorder(dragData, dropTarget);
                }
            } catch (error) {
                console.error('Drop error:', error);
            }
        };
        
        return React.createElement('div', {
            className: `field-item ${isHalfWidth ? 'half-width' : 'full-width'} ${field.is_default ? 'default-field' : 'custom-field'}`,
            draggable: !field.is_default,
            onDragStart: handleDragStart,
            onDragEnd: handleDragEnd,
            onDragOver: handleDragOver,
            onDrop: handleDrop,
            'data-field-id': field.id
        }, [
            // Drag handle (visual only)
            !field.is_default && React.createElement('div', {
                key: 'drag-handle',
                className: 'drag-handle'
            }, React.createElement('svg', {
                width: 12,
                height: 12,
                viewBox: '0 0 24 24',
                fill: 'none'
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
                    }, field.label || 'Unnamed Field'),
                    React.createElement('div', {
                        key: 'field-meta',
                        className: 'field-meta'
                    }, [
                        React.createElement('span', {
                            key: 'field-type',
                            className: 'field-type'
                        }, (field.type || 'text').toUpperCase()),
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
                    }, 'Delete')
                ])
            ])
        ]);
    };
    
    // Section Component
    const Section = ({ section, fields, onEdit, onDelete, onToggle, onFieldReorder }) => {
        const [dragOverField, setDragOverField] = useState(null);
        
        // Add index to fields for easier management
        const fieldsWithIndex = fields.map((field, index) => ({
            ...field,
            index: index
        }));
        
        // Set up global reorder function for this section
        useEffect(() => {
            window.triggerFieldReorder = (dragData, dropData) => {
                if (dragData.section === section || dropData.section === section) {
                    handleFieldReorder(dragData, dropData);
                }
            };
        }, [fields]);
        
        const handleFieldReorder = (dragData, dropData) => {
            const dragIndex = dragData.index;
            const dropIndex = dropData.index;
            
            if (dragIndex === dropIndex) return;
            
            // Create new field order
            const newFields = [...fields];
            const [draggedField] = newFields.splice(dragIndex, 1);
            
            // Insert at new position
            const insertIndex = dragIndex < dropIndex ? dropIndex - 1 : dropIndex;
            newFields.splice(insertIndex, 0, draggedField);
            
            // Auto-adjust column widths based on positions
            const adjustedFields = autoAdjustWidths(newFields);
            
            onFieldReorder(section, adjustedFields);
        };
        
        // Auto-adjust column widths based on adjacent fields
        const autoAdjustWidths = (sectionFields) => {
            const adjusted = [...sectionFields];
            
            for (let i = 0; i < adjusted.length; i++) {
                const current = adjusted[i];
                const next = adjusted[i + 1];
                
                // If current and next field are both present, make them half-width
                if (next && !current.force_full_width && !next.force_full_width) {
                    current.column_width = 'half';
                    next.column_width = 'half';
                    i++; // Skip next field since we processed it
                } else {
                    // Single field gets full width
                    current.column_width = 'full';
                }
            }
            
            return adjusted;
        };
        
        // Organize fields into visual rows
        const organizeIntoRows = (fields) => {
            const rows = [];
            
            for (let i = 0; i < fields.length; i++) {
                const current = fields[i];
                const next = fields[i + 1];
                
                if (current.column_width === 'half' && next && next.column_width === 'half') {
                    // Create a row with two half-width fields
                    rows.push({
                        type: 'row',
                        fields: [current, next]
                    });
                    i++; // Skip next field
                } else {
                    // Single field row
                    rows.push({
                        type: 'single',
                        field: current
                    });
                }
            }
            
            return rows;
        };
        
        const fieldRows = organizeIntoRows(fieldsWithIndex);
        
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
            
            React.createElement('div', {
                key: 'section-fields',
                className: `section-fields ${dragOverField ? 'drag-active' : ''}`,
                onDragOver: (e) => {
                    e.preventDefault();
                },
                onDrop: (e) => {
                    e.preventDefault();
                    setDragOverField(null);
                }
            }, [
                fieldRows.map((row, rowIndex) => {
                    if (row.type === 'row') {
                        return React.createElement('div', {
                            key: `row-${rowIndex}`,
                            className: 'field-row'
                        }, row.fields.map(field =>
                            React.createElement(Field, {
                                key: field.id,
                                field: field,
                                onEdit: onEdit,
                                onDelete: onDelete,
                                onToggle: onToggle
                            })
                        ));
                    } else {
                        return React.createElement('div', {
                            key: `single-${rowIndex}`,
                            className: 'field-row'
                        }, React.createElement(Field, {
                            key: row.field.id,
                            field: row.field,
                            onEdit: onEdit,
                            onDelete: onDelete,
                            onToggle: onToggle
                        }));
                    }
                }),
                
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
        ]);
    };
    
    // Simple Modal
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
                        className: 'modal-close',
                        type: 'button'
                    }, '×')
                ]),
                React.createElement('div', {
                    key: 'modal-body',
                    className: 'modal-body'
                }, children)
            ])
        );
    };
    
    // Main Component
    const CheckoutFieldEditor = () => {
        const [fields, setFields] = useState({
            billing: [],
            shipping: [],
            order: []
        });
        
        const [modalField, setModalField] = useState(null);
        const [isModalOpen, setIsModalOpen] = useState(false);
        const [loading, setLoading] = useState(true);
        
        const sections = {
            billing: 'Billing',
            shipping: 'Shipping', 
            order: 'Order'
        };
        
        // Load fields
        useEffect(() => {
            loadFields();
        }, []);
        
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
        
        const handleFieldReorder = async (section, newFields) => {
            const updatedFields = { ...fields };
            updatedFields[section] = newFields;
            setFields(updatedFields);
            
            try {
                await fetch(window.hezarfen_checkout_field_editor.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'hezarfen_reorder_checkout_fields',
                        nonce: window.hezarfen_checkout_field_editor.nonce,
                        fields: JSON.stringify(updatedFields)
                    })
                });
            } catch (error) {
                console.error('Error saving field order:', error);
                loadFields(); // Revert on error
            }
        };
        
        const handleEditField = (field) => {
            setModalField(field);
            setIsModalOpen(true);
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
                }
            } catch (error) {
                console.error('Error deleting field:', error);
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
                    onClick: (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleEditField({ section: 'billing', is_default: false });
                    },
                    className: 'btn-add-field',
                    type: 'button'
                }, 'Add New Field')
            ]),
            
            React.createElement('div', {
                key: 'sections-container',
                className: 'sections-container'
            }, Object.entries(sections).map(([sectionKey, sectionLabel]) =>
                React.createElement(Section, {
                    key: sectionKey,
                    section: sectionKey,
                    fields: fields[sectionKey] || [],
                    onEdit: handleEditField,
                    onDelete: handleDeleteField,
                    onToggle: handleToggleField,
                    onFieldReorder: handleFieldReorder
                })
            )),
            
            React.createElement(SimpleModal, {
                key: 'modal',
                isOpen: isModalOpen,
                onClose: () => {
                    setIsModalOpen(false);
                    setModalField(null);
                },
                title: modalField?.id ? 'Edit Field' : 'Add New Field'
            }, React.createElement('div', {}, [
                React.createElement('p', { key: 'info' }, 'Field: ' + (modalField?.label || 'New Field')),
                React.createElement('p', { key: 'section' }, 'Section: ' + (modalField?.section || 'billing')),
                React.createElement('button', {
                    key: 'close-btn',
                    onClick: () => {
                        setIsModalOpen(false);
                        setModalField(null);
                    },
                    type: 'button'
                }, 'Close')
            ]))
        ]);
    };
    
    // Make component globally available
    window.CheckoutFieldEditor = CheckoutFieldEditor;
    
})();