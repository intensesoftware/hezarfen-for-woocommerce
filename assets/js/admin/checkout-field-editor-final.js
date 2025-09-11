// Simple React Checkout Field Editor - Final Version
(function() {
    'use strict';
    
    const { useState, useEffect } = React;
    
    // Field Component
    const Field = ({ field, onEdit, onDelete }) => {
        const isHalfWidth = field.column_width === 'half';
        
        return React.createElement('div', {
            className: `field-item ${isHalfWidth ? 'half-width' : 'full-width'} ${field.is_default ? 'default-field' : 'custom-field'}`,
            draggable: field.is_default === false, // Explicitly check for false
            onDragStart: (e) => {
                // Only custom fields can be dragged
                if (field.is_default) {
                    e.preventDefault();
                    return false;
                }
                
                // Stop event bubbling
                e.stopPropagation();
                
                e.dataTransfer.setData('text/plain', field.id);
                e.dataTransfer.effectAllowed = 'move';
                e.currentTarget.style.opacity = '0.5';
                e.currentTarget.classList.add('dragging');
            },
            onDragEnd: (e) => {
                e.currentTarget.style.opacity = '1';
                e.currentTarget.classList.remove('dragging');
            },
            onDragOver: (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'move';
                e.currentTarget.classList.add('drag-over');
            },
            onDragLeave: (e) => {
                e.currentTarget.classList.remove('drag-over');
            },
            onDrop: (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                e.currentTarget.classList.remove('drag-over');
                
                const draggedId = e.dataTransfer.getData('text/plain');
                if (draggedId && draggedId !== field.id) {
                    window.handleFieldDrop && window.handleFieldDrop(draggedId, field.id);
                }
            },
            onClick: (e) => {
                // Prevent click from bubbling to parent elements
                e.stopPropagation();
            }
        }, [
            !field.is_default && React.createElement('div', {
                key: 'drag-handle',
                className: 'drag-handle'
            }, '⋮⋮'),
            
            React.createElement('div', {
                key: 'content',
                className: 'field-content'
            }, [
                React.createElement('div', {
                    key: 'info',
                    className: 'field-info'
                }, [
                    React.createElement('span', {
                        key: 'name',
                        className: 'field-name'
                    }, field.label || 'Unnamed Field'),
                    React.createElement('div', {
                        key: 'meta',
                        className: 'field-meta'
                    }, [
                        React.createElement('span', {
                            key: 'type',
                            className: 'field-type'
                        }, (field.type || 'text').toUpperCase()),
                        isHalfWidth && React.createElement('span', {
                            key: 'width',
                            className: 'width-badge'
                        }, '1/2'),
                        field.required && React.createElement('span', {
                            key: 'required',
                            className: 'required-badge'
                        }, 'Required')
                    ])
                ]),
                
                React.createElement('div', {
                    key: 'actions',
                    className: 'field-actions'
                }, [
                    React.createElement('button', {
                        key: 'width-toggle',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Toggle width between full and half
                            const newWidth = field.column_width === 'half' ? 'full' : 'half';
                            const updatedField = { ...field, column_width: newWidth };
                            
                            // Update the field in state
                            window.updateFieldWidth && window.updateFieldWidth(field.id, newWidth);
                        },
                        className: `btn-width ${field.column_width === 'half' ? 'half' : 'full'}`,
                        type: 'button',
                        title: `Current: ${field.column_width === 'half' ? '1/2' : '1/1'} - Click to toggle`
                    }, field.column_width === 'half' ? '1/2→1/1' : '1/1→1/2'),
                    
                    React.createElement('button', {
                        key: 'edit',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onEdit(field);
                        },
                        className: 'btn-edit',
                        type: 'button'
                    }, 'Edit'),
                    
                    !field.is_default && React.createElement('button', {
                        key: 'delete',
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
    const Section = ({ section, fields, onEdit, onDelete, onFieldReorder }) => {
        // Organize fields into rows
        const organizeIntoRows = (fields) => {
            const rows = [];
            
            for (let i = 0; i < fields.length; i++) {
                const current = fields[i];
                const next = fields[i + 1];
                
                if (current.column_width === 'half' && next && next.column_width === 'half') {
                    rows.push({ type: 'row', fields: [current, next] });
                    i++; // Skip next
                } else {
                    rows.push({ type: 'single', field: current });
                }
            }
            
            return rows;
        };
        
        const rows = organizeIntoRows(fields);
        
        return React.createElement('div', {
            className: 'section'
        }, [
            React.createElement('div', {
                key: 'header',
                className: 'section-header'
            }, [
                React.createElement('h3', { key: 'title' }, 
                    section.charAt(0).toUpperCase() + section.slice(1) + ' Fields'
                ),
                React.createElement('span', { key: 'count', className: 'field-count' }, fields.length)
            ]),
            
            React.createElement('div', {
                key: 'fields',
                className: 'section-fields'
            }, [
                rows.map((row, index) => {
                    if (row.type === 'row') {
                        return React.createElement('div', {
                            key: `row-${index}`,
                            className: 'field-row'
                        }, row.fields.map(field =>
                            React.createElement(Field, {
                                key: field.id,
                                field: field,
                                onEdit: onEdit,
                                onDelete: onDelete
                            })
                        ));
                    } else {
                        return React.createElement('div', {
                            key: `single-${index}`,
                            className: 'field-row'
                        }, React.createElement(Field, {
                            key: row.field.id,
                            field: row.field,
                            onEdit: onEdit,
                            onDelete: onDelete
                        }));
                    }
                }),
                
                fields.length === 0 && React.createElement('div', {
                    key: 'empty',
                    className: 'empty-section'
                }, [
                    React.createElement('p', { key: 'text' }, 'No fields in this section'),
                    React.createElement('button', {
                        key: 'add',
                        onClick: () => onEdit({ section }),
                        className: 'btn-add-field',
                        type: 'button'
                    }, 'Add First Field')
                ])
            ])
        ]);
    };
    
    // Simple Modal
    const Modal = ({ isOpen, onClose, field }) => {
        if (!isOpen) return null;
        
        return React.createElement('div', {
            className: 'modal-overlay',
            onClick: onClose
        }, React.createElement('div', {
            className: 'modal-content',
            onClick: (e) => e.stopPropagation()
        }, [
            React.createElement('div', {
                key: 'header',
                className: 'modal-header'
            }, [
                React.createElement('h2', { key: 'title' }, field?.id ? 'Edit Field' : 'Add Field'),
                React.createElement('button', {
                    key: 'close',
                    onClick: onClose,
                    type: 'button'
                }, '×')
            ]),
            React.createElement('div', {
                key: 'body',
                className: 'modal-body'
            }, [
                React.createElement('p', { key: 'info' }, `Field: ${field?.label || 'New Field'}`),
                React.createElement('p', { key: 'section' }, `Section: ${field?.section || 'billing'}`),
                React.createElement('button', {
                    key: 'close-btn',
                    onClick: onClose,
                    type: 'button'
                }, 'Close')
            ])
        ]));
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
        
        // Load actual fields from WordPress
        useEffect(() => {
            loadFields();
        }, []);
        
        const loadFields = async () => {
            try {
                setLoading(true);
                
                // Try to get fields from AJAX
                const response = await fetch(window.hezarfen_checkout_field_editor.ajax_url + '?action=hezarfen_get_checkout_fields&nonce=' + window.hezarfen_checkout_field_editor.nonce);
                const result = await response.json();
                
                if (result.success) {
                    setFields(result.data);
                } else {
                    // Fallback to localized data
                    const data = window.hezarfen_checkout_field_editor;
                    const organizedFields = {
                        billing: [],
                        shipping: [],
                        order: []
                    };
                    
                    // Process custom fields
                    if (data.custom_fields_data) {
                        Object.entries(data.custom_fields_data).forEach(([fieldId, fieldData]) => {
                            const section = fieldData.section || 'billing';
                            organizedFields[section].push({
                                id: fieldId,
                                ...fieldData,
                                is_default: false
                            });
                        });
                    }
                    
                    // Process default fields
                    if (data.default_fields_data) {
                        Object.entries(data.default_fields_data).forEach(([fieldId, fieldData]) => {
                            const section = fieldData.section || 'billing';
                            organizedFields[section].push({
                                id: fieldId,
                                ...fieldData,
                                is_default: true
                            });
                        });
                    }
                    
                    setFields(organizedFields);
                }
            } catch (error) {
                console.error('Error loading fields:', error);
                // Show empty state
                setFields({ billing: [], shipping: [], order: [] });
            } finally {
                setLoading(false);
            }
        };
        
        // Global drag handler
        useEffect(() => {
            window.handleFieldDrop = (draggedId, targetId) => {
                console.log(`Moving field ${draggedId} to position of ${targetId}`);
                
                // Create new fields object
                const newFields = { ...fields };
                let draggedField = null;
                let draggedFromSection = null;
                let targetSection = null;
                let targetIndex = -1;
                
                // Find and remove dragged field
                Object.keys(newFields).forEach(section => {
                    const index = newFields[section].findIndex(f => f.id === draggedId);
                    if (index !== -1) {
                        [draggedField] = newFields[section].splice(index, 1);
                        draggedFromSection = section;
                    }
                });
                
                // Find target position
                Object.keys(newFields).forEach(section => {
                    const index = newFields[section].findIndex(f => f.id === targetId);
                    if (index !== -1) {
                        targetSection = section;
                        targetIndex = index;
                    }
                });
                
                // Insert at target position
                if (draggedField && targetSection && targetIndex !== -1) {
                    // Update field section
                    draggedField.section = targetSection;
                    
                    // Insert before target field
                    newFields[targetSection].splice(targetIndex, 0, draggedField);
                    
                    // Auto-adjust column widths for both sections
                    if (draggedFromSection) {
                        newFields[draggedFromSection] = autoAdjustWidths(newFields[draggedFromSection]);
                    }
                    newFields[targetSection] = autoAdjustWidths(newFields[targetSection]);
                    
                    setFields(newFields);
                    
                    console.log('Field moved successfully');
                }
            };
            
            return () => {
                window.handleFieldDrop = null;
            };
        }, [fields]);
        
        // Width update handler
        useEffect(() => {
            window.updateFieldWidth = (fieldId, newWidth) => {
                console.log(`Updating field ${fieldId} to width ${newWidth}`);
                
                const newFields = { ...fields };
                
                // Find and update the field
                Object.keys(newFields).forEach(section => {
                    const fieldIndex = newFields[section].findIndex(f => f.id === fieldId);
                    if (fieldIndex !== -1) {
                        newFields[section][fieldIndex] = {
                            ...newFields[section][fieldIndex],
                            column_width: newWidth
                        };
                    }
                });
                
                setFields(newFields);
                console.log('Field width updated');
            };
            
            return () => {
                window.updateFieldWidth = null;
            };
        }, [fields]);
        
        const autoAdjustWidths = (sectionFields) => {
            // DON'T auto-adjust widths - let user control this manually
            // Just return fields as they are
            return sectionFields.map(field => ({
                ...field,
                // Keep existing width or default to full
                column_width: field.column_width || 'full'
            }));
        };
        
        const handleEdit = (field) => {
            setModalField(field);
            setIsModalOpen(true);
        };
        
        const handleDelete = (fieldId) => {
            if (!confirm('Delete this field?')) return;
            
            const newFields = { ...fields };
            Object.keys(newFields).forEach(section => {
                newFields[section] = newFields[section].filter(f => f.id !== fieldId);
            });
            setFields(newFields);
        };
        
        if (loading) {
            return React.createElement('div', {
                className: 'checkout-field-editor'
            }, React.createElement('div', {
                className: 'loading'
            }, 'Loading...'));
        }
        
        return React.createElement('div', {
            className: 'checkout-field-editor'
        }, [
            React.createElement('div', {
                key: 'header',
                className: 'editor-header'
            }, [
                React.createElement('h1', { key: 'title' }, 'Checkout Fields'),
                React.createElement('button', {
                    key: 'add',
                    onClick: () => handleEdit({ section: 'billing' }),
                    className: 'btn-add-field',
                    type: 'button'
                }, 'Add New Field')
            ]),
            
            React.createElement('div', {
                key: 'sections',
                className: 'sections-container'
            }, Object.entries(fields).map(([sectionKey, sectionFields]) =>
                React.createElement(Section, {
                    key: sectionKey,
                    section: sectionKey,
                    fields: sectionFields,
                    onEdit: handleEdit,
                    onDelete: handleDelete
                })
            )),
            
            React.createElement(Modal, {
                key: 'modal',
                isOpen: isModalOpen,
                onClose: () => setIsModalOpen(false),
                field: modalField
            })
        ]);
    };
    
    // Make available globally
    window.CheckoutFieldEditor = CheckoutFieldEditor;
    
})();