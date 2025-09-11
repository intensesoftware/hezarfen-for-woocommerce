// Simple Working Checkout Field Editor
jQuery(document).ready(function($) {
    'use strict';

    // Simple field data
    let fieldsData = {
        billing: [
            { id: 'billing_first_name', label: 'First Name', type: 'text', column_width: 'half', is_default: true, enabled: true, required: true },
            { id: 'billing_last_name', label: 'Last Name', type: 'text', column_width: 'half', is_default: true, enabled: true, required: true },
            { id: 'billing_email', label: 'Email', type: 'email', column_width: 'full', is_default: true, enabled: true, required: true }
        ],
        shipping: [
            { id: 'shipping_address_1', label: 'Address', type: 'text', column_width: 'full', is_default: true, enabled: true, required: true }
        ],
        order: [
            { id: 'order_comments', label: 'Order Notes', type: 'textarea', column_width: 'full', is_default: true, enabled: true, required: false }
        ]
    };

    function renderInterface() {
        const container = $('#hezarfen-checkout-field-editor-react-root');
        
        let html = `
            <div class="checkout-field-editor">
                <div class="editor-header">
                    <h1>Checkout Fields</h1>
                    <button class="btn-add-field" type="button">Add New Field</button>
                </div>
                <div class="sections-container">
        `;
        
        // Render each section
        Object.entries(fieldsData).forEach(([sectionKey, fields]) => {
            const sectionTitle = sectionKey.charAt(0).toUpperCase() + sectionKey.slice(1);
            
            html += `
                <div class="section" data-section="${sectionKey}">
                    <div class="section-header">
                        <h3>${sectionTitle} Fields</h3>
                        <span class="field-count">${fields.length}</span>
                    </div>
                    <div class="section-fields sortable-fields" data-section="${sectionKey}">
            `;
            
            // Organize fields into rows
            const rows = organizeFieldsIntoRows(fields);
            
            rows.forEach((row, rowIndex) => {
                if (row.type === 'row') {
                    html += '<div class="field-row">';
                    row.fields.forEach(field => {
                        html += renderField(field);
                    });
                    html += '</div>';
                } else {
                    html += '<div class="field-row">';
                    html += renderField(row.field);
                    html += '</div>';
                }
            });
            
            if (fields.length === 0) {
                html += `
                    <div class="empty-section">
                        <p>No fields in this section</p>
                        <button class="btn-add-field" data-section="${sectionKey}" type="button">Add First Field</button>
                    </div>
                `;
            }
            
            html += '</div></div>';
        });
        
        html += '</div></div>';
        
        container.html(html);
        
        // Initialize sortable
        initSortable();
        bindEvents();
    }
    
    function renderField(field) {
        const isHalfWidth = field.column_width === 'half';
        const widthClass = isHalfWidth ? 'half-width' : 'full-width';
        const defaultClass = field.is_default ? 'default-field' : 'custom-field';
        
        return `
            <div class="field-item ${widthClass} ${defaultClass}" data-field-id="${field.id}" data-section="${field.section}">
                ${!field.is_default ? '<div class="drag-handle">⋮⋮</div>' : ''}
                <div class="field-content">
                    <div class="field-info">
                        <span class="field-name">${field.label}</span>
                        <div class="field-meta">
                            <span class="field-type">${(field.type || 'text').toUpperCase()}</span>
                            ${isHalfWidth ? '<span class="width-badge">1/2</span>' : ''}
                            ${field.required ? '<span class="required-badge">Required</span>' : ''}
                            ${!field.enabled ? '<span class="disabled-badge">Disabled</span>' : ''}
                        </div>
                    </div>
                    <div class="field-actions">
                        <button class="btn-edit" data-field-id="${field.id}" type="button">Edit</button>
                        ${!field.is_default ? `<button class="btn-delete" data-field-id="${field.id}" type="button">Delete</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    }
    
    function organizeFieldsIntoRows(fields) {
        const rows = [];
        
        for (let i = 0; i < fields.length; i++) {
            const current = fields[i];
            const next = fields[i + 1];
            
            if (current.column_width === 'half' && next && next.column_width === 'half') {
                rows.push({
                    type: 'row',
                    fields: [current, next]
                });
                i++; // Skip next field
            } else {
                rows.push({
                    type: 'single',
                    field: current
                });
            }
        }
        
        return rows;
    }
    
    function initSortable() {
        $('.sortable-fields').sortable({
            items: '.field-item:not(.default-field)',
            handle: '.drag-handle',
            placeholder: 'field-placeholder',
            tolerance: 'pointer',
            cursor: 'move',
            opacity: 0.8,
            connectWith: '.sortable-fields',
            update: function(event, ui) {
                updateFieldOrder();
            },
            start: function(event, ui) {
                ui.item.addClass('dragging');
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function(event, ui) {
                ui.item.removeClass('dragging');
                autoAdjustWidths();
                renderInterface(); // Re-render to show new layout
            }
        });
    }
    
    function updateFieldOrder() {
        // Update field order based on DOM
        Object.keys(fieldsData).forEach(section => {
            const sectionElement = $(`.sortable-fields[data-section="${section}"]`);
            const newOrder = [];
            
            sectionElement.find('.field-item').each(function() {
                const fieldId = $(this).data('field-id');
                const field = findFieldById(fieldId);
                if (field && !field.is_default) {
                    field.section = section; // Update section if moved
                    newOrder.push(field);
                }
            });
            
            // Keep default fields and add reordered custom fields
            const defaultFields = fieldsData[section].filter(f => f.is_default);
            fieldsData[section] = [...defaultFields, ...newOrder];
        });
    }
    
    function autoAdjustWidths() {
        // Auto-adjust column widths based on position
        Object.keys(fieldsData).forEach(section => {
            const fields = fieldsData[section];
            
            for (let i = 0; i < fields.length; i++) {
                const current = fields[i];
                const next = fields[i + 1];
                
                // If two fields are adjacent, make them half-width
                if (next && !current.force_full_width && !next.force_full_width) {
                    current.column_width = 'half';
                    next.column_width = 'half';
                    i++; // Skip next field
                } else {
                    current.column_width = 'full';
                }
            }
        });
    }
    
    function findFieldById(fieldId) {
        for (const section of Object.values(fieldsData)) {
            const field = section.find(f => f.id === fieldId);
            if (field) return field;
        }
        return null;
    }
    
    function bindEvents() {
        // Edit field
        $(document).on('click', '.btn-edit', function(e) {
            e.preventDefault();
            const fieldId = $(this).data('field-id');
            const field = findFieldById(fieldId);
            
            alert(`Edit Field: ${field.label}\nType: ${field.type}\nWidth: ${field.column_width}`);
        });
        
        // Delete field
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            const fieldId = $(this).data('field-id');
            
            if (confirm('Are you sure you want to delete this field?')) {
                // Remove from data
                Object.keys(fieldsData).forEach(section => {
                    fieldsData[section] = fieldsData[section].filter(f => f.id !== fieldId);
                });
                
                renderInterface();
            }
        });
        
        // Add field
        $(document).on('click', '.btn-add-field', function(e) {
            e.preventDefault();
            const section = $(this).data('section') || 'billing';
            
            const newField = {
                id: 'custom_field_' + Date.now(),
                label: 'Custom Field',
                type: 'text',
                column_width: 'full',
                is_default: false,
                enabled: true,
                required: false,
                section: section
            };
            
            fieldsData[section].push(newField);
            renderInterface();
        });
    }
    
    // Add placeholder styles
    $('<style>').text(`
        .field-placeholder {
            background: #f0f0f0;
            border: 2px dashed #ccc;
            height: 60px;
            margin: 8px 0;
            border-radius: 4px;
        }
        
        .field-item.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        
        .drag-handle {
            position: absolute;
            top: 8px;
            right: 8px;
            cursor: grab;
            color: #ccc;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .field-item:hover .drag-handle {
            opacity: 1;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
    `).appendTo('head');
    
    // Initialize
    renderInterface();
});