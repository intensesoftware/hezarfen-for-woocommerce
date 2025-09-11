// Simple React Checkout Field Editor
(function() {
    'use strict';
    
    const { useState, useEffect } = React;
    
    // Simple Field Component
    const Field = ({ field, onEdit, onDelete, onToggle, onMoveUp, onMoveDown, index, totalFields }) => {
        const isHalfWidth = field.column_width === 'half';
        
        return React.createElement('div', {
            className: `field-item ${isHalfWidth ? 'half-width' : 'full-width'} ${field.is_default ? 'default-field' : 'custom-field'}`
        }, [
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
                    
                    !field.is_default && index > 0 && React.createElement('button', {
                        key: 'btn-up',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onMoveUp(index);
                        },
                        className: 'btn-move',
                        type: 'button',
                        title: 'Move Up'
                    }, '↑'),
                    
                    !field.is_default && index < totalFields - 1 && React.createElement('button', {
                        key: 'btn-down',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onMoveDown(index);
                        },
                        className: 'btn-move',
                        type: 'button',
                        title: 'Move Down'
                    }, '↓'),
                    
                    React.createElement('button', {
                        key: 'btn-toggle-width',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const newWidth = field.column_width === 'half' ? 'full' : 'half';
                            onEdit({ ...field, column_width: newWidth });
                        },
                        className: 'btn-width',
                        type: 'button',
                        title: 'Toggle Width'
                    }, isHalfWidth ? '1/1' : '1/2')
                ])
            ])
        ]);
    };
    
    // Section Component
    const Section = ({ section, fields, onEdit, onDelete, onToggle, onFieldReorder }) => {
        const handleMoveUp = (index) => {
            if (index === 0) return;
            
            const newFields = [...fields];
            [newFields[index], newFields[index - 1]] = [newFields[index - 1], newFields[index]];
            
            onFieldReorder(section, newFields);
        };
        
        const handleMoveDown = (index) => {
            if (index === fields.length - 1) return;
            
            const newFields = [...fields];
            [newFields[index], newFields[index + 1]] = [newFields[index + 1], newFields[index]];
            
            onFieldReorder(section, newFields);
        };
        
        // Organize fields into rows
        const organizeFieldsIntoRows = (fields) => {
            const rows = [];
            
            for (let i = 0; i < fields.length; i++) {
                const currentField = fields[i];
                const nextField = fields[i + 1];
                
                // If current field is half-width and next field is also half-width, group them
                if (currentField.column_width === 'half' && nextField && nextField.column_width === 'half') {
                    rows.push({
                        type: 'row',
                        fields: [currentField, nextField]
                    });
                    i++; // Skip next field
                } else {
                    rows.push({
                        type: 'single',
                        field: currentField
                    });
                }
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
            
            React.createElement('div', {
                key: 'section-fields',
                className: 'section-fields'
            }, [
                fieldRows.map((row, rowIndex) => {
                    if (row.type === 'row') {
                        return React.createElement('div', {
                            key: `row-${rowIndex}`,
                            className: 'field-row'
                        }, row.fields.map((field, fieldIndex) => {
                            const globalIndex = fields.findIndex(f => f.id === field.id);
                            return React.createElement(Field, {
                                key: field.id,
                                field: field,
                                index: globalIndex,
                                totalFields: fields.length,
                                onEdit: onEdit,
                                onDelete: onDelete,
                                onToggle: onToggle,
                                onMoveUp: handleMoveUp,
                                onMoveDown: handleMoveDown
                            });
                        }));
                    } else {
                        const globalIndex = fields.findIndex(f => f.id === row.field.id);
                        return React.createElement('div', {
                            key: `single-${rowIndex}`,
                            className: 'field-row'
                        }, React.createElement(Field, {
                            key: row.field.id,
                            field: row.field,
                            index: globalIndex,
                            totalFields: fields.length,
                            onEdit: onEdit,
                            onDelete: onDelete,
                            onToggle: onToggle,
                            onMoveUp: handleMoveUp,
                            onMoveDown: handleMoveDown
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
        
        // Load fields on component mount
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
                const response = await fetch(window.hezarfen_checkout_field_editor.ajax_url, {
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
        
        const handleToggleField = async (fieldId) => {
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
            }, React.createElement('p', {}, 'Simple modal - field editing coming soon'))
        ]);
    };
    
    // Make component globally available
    window.CheckoutFieldEditor = CheckoutFieldEditor;
    
})();