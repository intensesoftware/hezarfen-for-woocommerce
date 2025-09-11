import React, { useState, useEffect, useRef } from 'react';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

// Field Component
const Field = ({ field, index, onEdit, onDelete, onToggle }) => {
  const isHalfWidth = field.column_width === 'half';
  
  return (
    <Draggable draggableId={field.id} index={index} isDragDisabled={field.is_default}>
      {(provided, snapshot) => (
        <div
          ref={provided.innerRef}
          {...provided.draggableProps}
          className={`field-item ${isHalfWidth ? 'half-width' : 'full-width'} ${
            snapshot.isDragging ? 'dragging' : ''
          } ${field.is_default ? 'default-field' : 'custom-field'}`}
        >
          {!field.is_default && (
            <div {...provided.dragHandleProps} className="drag-handle">
              <svg width="12" height="12" viewBox="0 0 24 24">
                <circle cx="9" cy="12" r="1" fill="currentColor"/>
                <circle cx="9" cy="5" r="1" fill="currentColor"/>
                <circle cx="9" cy="19" r="1" fill="currentColor"/>
                <circle cx="15" cy="12" r="1" fill="currentColor"/>
                <circle cx="15" cy="5" r="1" fill="currentColor"/>
                <circle cx="15" cy="19" r="1" fill="currentColor"/>
              </svg>
            </div>
          )}
          
          <div className="field-content">
            <div className="field-info">
              <span className="field-name">{field.label}</span>
              <div className="field-meta">
                <span className="field-type">{field.type}</span>
                {isHalfWidth && <span className="width-badge">1/2</span>}
                {field.required && <span className="required-badge">Required</span>}
                {!field.enabled && <span className="disabled-badge">Disabled</span>}
              </div>
            </div>
            
            <div className="field-actions">
              <button onClick={() => onEdit(field)} className="btn-edit">
                Edit
              </button>
              {!field.is_default && (
                <button onClick={() => onDelete(field.id)} className="btn-delete">
                  Delete
                </button>
              )}
              <button 
                onClick={() => onToggle(field.id)} 
                className={`btn-toggle ${field.enabled ? 'enabled' : 'disabled'}`}
              >
                {field.enabled ? 'Disable' : 'Enable'}
              </button>
            </div>
          </div>
        </div>
      )}
    </Draggable>
  );
};

// Field Row Component (for organizing half-width fields)
const FieldRow = ({ fields, startIndex, onEdit, onDelete, onToggle }) => {
  return (
    <div className="field-row">
      {fields.map((field, index) => (
        <Field
          key={field.id}
          field={field}
          index={startIndex + index}
          onEdit={onEdit}
          onDelete={onDelete}
          onToggle={onToggle}
        />
      ))}
    </div>
  );
};

// Section Component
const Section = ({ section, fields, onEdit, onDelete, onToggle, onReorder }) => {
  // Organize fields into rows based on column width
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
        // If there's an incomplete row, push it first
        if (currentRow.length > 0) {
          rows.push({ type: 'row', fields: currentRow, startIndex: fieldIndex - currentRow.length });
          currentRow = [];
        }
        
        // Add full-width field
        rows.push({ 
          type: 'single', 
          field: { ...field, originalIndex: fieldIndex }, 
          index: fieldIndex 
        });
      }
      fieldIndex++;
    });
    
    // Handle remaining half-width field
    if (currentRow.length > 0) {
      rows.push({ type: 'row', fields: currentRow, startIndex: fieldIndex - currentRow.length });
    }
    
    return rows;
  };
  
  const fieldRows = organizeFieldsIntoRows(fields);
  
  return (
    <div className="section">
      <div className="section-header">
        <h3>{section.charAt(0).toUpperCase() + section.slice(1)} Fields</h3>
        <span className="field-count">{fields.length}</span>
      </div>
      
      <Droppable droppableId={section} type="field">
        {(provided, snapshot) => (
          <div
            ref={provided.innerRef}
            {...provided.droppableProps}
            className={`section-fields ${snapshot.isDraggingOver ? 'drag-over' : ''}`}
          >
            {fieldRows.map((row, rowIndex) => {
              if (row.type === 'row') {
                return (
                  <FieldRow
                    key={`row-${rowIndex}`}
                    fields={row.fields}
                    startIndex={row.startIndex}
                    onEdit={onEdit}
                    onDelete={onDelete}
                    onToggle={onToggle}
                  />
                );
              } else {
                return (
                  <Field
                    key={row.field.id}
                    field={row.field}
                    index={row.index}
                    onEdit={onEdit}
                    onDelete={onDelete}
                    onToggle={onToggle}
                  />
                );
              }
            })}
            {provided.placeholder}
            
            {fields.length === 0 && (
              <div className="empty-section">
                <p>No fields in this section</p>
                <button onClick={() => onEdit({ section })} className="btn-add-field">
                  Add First Field
                </button>
              </div>
            )}
          </div>
        )}
      </Droppable>
    </div>
  );
};

// Field Editor Modal
const FieldModal = ({ field, isOpen, onClose, onSave, fieldTypes, sections }) => {
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
    show_for_countries: []
  });
  
  const [showPreview, setShowPreview] = useState(false);
  
  useEffect(() => {
    if (field) {
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
        show_for_countries: field.show_for_countries || []
      });
    }
  }, [field]);
  
  const handleSubmit = (e) => {
    e.preventDefault();
    onSave({ ...field, ...formData });
  };
  
  const handleColumnWidthChange = (width) => {
    setFormData({ ...formData, column_width: width });
  };
  
  if (!isOpen) return null;
  
  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{field?.id ? 'Edit Field' : 'Add New Field'}</h2>
          <button onClick={onClose} className="modal-close">×</button>
        </div>
        
        <form onSubmit={handleSubmit} className="modal-body">
          <div className="form-row">
            <div className="form-field">
              <label>Field Name</label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
                disabled={field?.is_default}
              />
            </div>
            
            <div className="form-field">
              <label>Field Label</label>
              <input
                type="text"
                value={formData.label}
                onChange={(e) => setFormData({ ...formData, label: e.target.value })}
                required
              />
            </div>
          </div>
          
          <div className="form-row">
            <div className="form-field">
              <label>Field Type</label>
              <select
                value={formData.type}
                onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                disabled={field?.is_default}
              >
                {Object.entries(fieldTypes).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
            
            <div className="form-field">
              <label>Section</label>
              <select
                value={formData.section}
                onChange={(e) => setFormData({ ...formData, section: e.target.value })}
                disabled={field?.is_default}
              >
                {Object.entries(sections).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
          </div>
          
          <div className="form-field">
            <label>Placeholder</label>
            <input
              type="text"
              value={formData.placeholder}
              onChange={(e) => setFormData({ ...formData, placeholder: e.target.value })}
            />
          </div>
          
          <div className="form-field">
            <label>Column Width</label>
            <div className="width-selector">
              <button
                type="button"
                className={`width-option ${formData.column_width === 'full' ? 'active' : ''}`}
                onClick={() => handleColumnWidthChange('full')}
              >
                <div className="width-preview full">
                  <div className="preview-field">Full Width</div>
                </div>
                Full Width (1/1)
              </button>
              
              <button
                type="button"
                className={`width-option ${formData.column_width === 'half' ? 'active' : ''}`}
                onClick={() => handleColumnWidthChange('half')}
              >
                <div className="width-preview half">
                  <div className="preview-field">Half</div>
                  <div className="preview-field">Half</div>
                </div>
                Half Width (1/2)
              </button>
            </div>
          </div>
          
          {(formData.type === 'select' || formData.type === 'radio') && (
            <div className="form-field">
              <label>Options (one per line)</label>
              <textarea
                value={formData.options}
                onChange={(e) => setFormData({ ...formData, options: e.target.value })}
                placeholder="Option 1&#10;Option 2&#10;key|Display Value"
                rows={4}
              />
            </div>
          )}
          
          <div className="form-checkboxes">
            <label className="checkbox-field">
              <input
                type="checkbox"
                checked={formData.required}
                onChange={(e) => setFormData({ ...formData, required: e.target.checked })}
              />
              Required Field
            </label>
            
            <label className="checkbox-field">
              <input
                type="checkbox"
                checked={formData.enabled}
                onChange={(e) => setFormData({ ...formData, enabled: e.target.checked })}
              />
              Enabled
            </label>
          </div>
          
          <div className="modal-footer">
            <button type="button" onClick={onClose} className="btn-cancel">
              Cancel
            </button>
            <button type="submit" className="btn-save">
              {field?.id ? 'Update Field' : 'Add Field'}
            </button>
          </div>
        </form>
      </div>
    </div>
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
  
  // Load fields from API
  useEffect(() => {
    loadFields();
  }, []);
  
  const loadFields = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${ajaxurl}?action=hezarfen_get_checkout_fields&nonce=${hezarfen_checkout_field_editor.nonce}`);
      const data = await response.json();
      
      if (data.success) {
        setFields(data.data);
      }
    } catch (error) {
      console.error('Error loading fields:', error);
    } finally {
      setLoading(false);
    }
  };
  
  const handleDragEnd = async (result) => {
    if (!result.destination) return;
    
    const { source, destination, draggableId } = result;
    
    if (source.droppableId === destination.droppableId && source.index === destination.index) {
      return;
    }
    
    // Create new fields state
    const newFields = { ...fields };
    const sourceFields = Array.from(newFields[source.droppableId]);
    const destFields = source.droppableId === destination.droppableId 
      ? sourceFields 
      : Array.from(newFields[destination.droppableId]);
    
    // Remove field from source
    const [movedField] = sourceFields.splice(source.index, 1);
    
    // Add field to destination
    if (source.droppableId !== destination.droppableId) {
      movedField.section = destination.droppableId;
    }
    destFields.splice(destination.index, 0, movedField);
    
    // Update state
    newFields[source.droppableId] = sourceFields;
    newFields[destination.droppableId] = destFields;
    setFields(newFields);
    
    // Save to server
    await saveFieldOrder(newFields);
  };
  
  const saveFieldOrder = async (fieldsData) => {
    try {
      await fetch(ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'hezarfen_reorder_checkout_fields',
          nonce: hezarfen_checkout_field_editor.nonce,
          fields: JSON.stringify(fieldsData)
        })
      });
    } catch (error) {
      console.error('Error saving field order:', error);
    }
  };
  
  const handleEditField = (field) => {
    setModalField(field);
    setIsModalOpen(true);
  };
  
  const handleAddField = (section = 'billing') => {
    setModalField({ section, is_default: false });
    setIsModalOpen(true);
  };
  
  const handleSaveField = async (fieldData) => {
    try {
      const response = await fetch(ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'hezarfen_save_checkout_field',
          nonce: hezarfen_checkout_field_editor.nonce,
          field_data: JSON.stringify(fieldData)
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        await loadFields();
        setIsModalOpen(false);
        setModalField(null);
      }
    } catch (error) {
      console.error('Error saving field:', error);
    }
  };
  
  const handleDeleteField = async (fieldId) => {
    if (!confirm('Are you sure you want to delete this field?')) return;
    
    try {
      const response = await fetch(ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'hezarfen_delete_checkout_field',
          nonce: hezarfen_checkout_field_editor.nonce,
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
  
  if (loading) {
    return (
      <div className="checkout-field-editor">
        <div className="loading">Loading fields...</div>
      </div>
    );
  }
  
  return (
    <div className="checkout-field-editor">
      <div className="editor-header">
        <h1>Checkout Fields</h1>
        <button onClick={() => handleAddField()} className="btn-add-field">
          Add New Field
        </button>
      </div>
      
      <DragDropContext onDragEnd={handleDragEnd}>
        <div className="sections-container">
          {Object.entries(sections).map(([sectionKey, sectionLabel]) => (
            <Section
              key={sectionKey}
              section={sectionKey}
              fields={fields[sectionKey] || []}
              onEdit={handleEditField}
              onDelete={handleDeleteField}
              onToggle={handleToggleField}
            />
          ))}
        </div>
      </DragDropContext>
      
      <FieldModal
        field={modalField}
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setModalField(null);
        }}
        onSave={handleSaveField}
        fieldTypes={fieldTypes}
        sections={sections}
      />
    </div>
  );
};

export default CheckoutFieldEditor;