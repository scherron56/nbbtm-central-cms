/**
 * Dynamically builds and inserts form controls into a container.
 * 
 * @param {Array} controls - Array of parameter control objects
 * @param {string} containerId - DOM ID where elements should be rendered
 */
function renderReportControls(controls, containerId) {
  const container = document.getElementById(containerId);
  if (!container) {
    return;
  }

  container.replaceChildren();

  controls.forEach(control => {
    // 1. Create Form Group Wrapper
    const group = document.createElement('div');
    group.className = 'field-group';
    group.style.setProperty('--colspan', '6');

    // 2. Create Label
    const label = document.createElement('label');
    label.htmlFor = control.name;
    label.textContent = control.label;
    if (control.required) {
      label.textContent += ' *';
    }

    let inputElement;

    // 3. Render Input Control Based on Type
    switch (control.type) {
      
      // MULTI-SELECT DROPDOWN (Collection / List)
      case 'multi_select':
        inputElement = document.createElement('select');
        inputElement.name = control.name;
        inputElement.id = control.name;
        inputElement.multiple = true;
        inputElement.className = 'form-control select-multi';
        populateOptions(inputElement, control);
        break;

      // SINGLE SELECT DROPDOWN
      case 'single_select':
        inputElement = document.createElement('select');
        inputElement.name = control.name;
        inputElement.id = control.name;
        inputElement.className = 'form-control';
        
        // Add default "Select All" or "Choose..." option if not required
        if (!control.required) {
          const defaultOpt = document.createElement('option');
          defaultOpt.value = '';
          defaultOpt.textContent = '-- All / Any --';
          inputElement.appendChild(defaultOpt);
        }
        populateOptions(inputElement, control);
        break;

      // DATE / DATE-TIME PICKER
      case 'date':
      case 'datetime':
        inputElement = document.createElement('input');
        inputElement.type = control.type === 'date' ? 'date' : 'datetime-local';
        inputElement.name = control.name;
        inputElement.id = control.name;
        inputElement.className = 'form-control';
        if (control.default_value) {
          inputElement.value = control.default_value; // e.g. "2026-01-01"
        }
        break;

      // BOOLEAN / CHECKBOX
      case 'checkbox':
        group.className = 'form-check mb-3'; // Distinct class for checkbox formatting
        inputElement = document.createElement('input');
        inputElement.type = 'checkbox';
        inputElement.name = control.name;
        inputElement.id = control.name;
        inputElement.className = 'form-check-input';
        inputElement.value = '1';
        if (control.default_value === true || control.default_value === 1 || control.default_value === '1') {
          inputElement.checked = true;
        }
        
        // Adjust label styling for checkbox placement
        label.className = 'form-check-label';
        break;

      // TEXT / NUMBER INPUT (Default)
      case 'text':
      case 'number':
      default:
        inputElement = document.createElement('input');
        inputElement.type = control.type === 'number' ? 'number' : 'text';
        inputElement.name = control.name;
        inputElement.id = control.name;
        inputElement.className = 'form-control';
        if (control.placeholder) {
          inputElement.placeholder = control.placeholder;
        }
        if (control.default_value) {
          inputElement.value = control.default_value;
        }
        break;
    }

    // Set mandatory constraint
    if (control.required) {
      inputElement.required = true;
    }

    // Structure DOM: Append label and element to group container
    if (control.type === 'checkbox') {
      group.appendChild(inputElement);
      group.appendChild(label);
    } else {
      group.appendChild(label);
      group.appendChild(inputElement);
    }

    container.appendChild(group);
  });
}

/**
 * Helper function to populate select options (static array or dynamic URL)
 */
function populateOptions(selectElement, control) {
  // Option Source A: Inline array
  if (Array.isArray(control.options)) {
    appendOptionItems(selectElement, control.options, control);
  } 
  // Option Source B: External PHP/MySQLi endpoint
  else if (control.data_source_url) {
    fetch(control.data_source_url)
      .then(res => res.json())
      .then(data => {
        appendOptionItems(selectElement, data, control);
      })
      .catch(err => console.error(`Error loading options for ${control.name}:`, err));
  }
}

/**
 * Appends options into the select DOM node
 */
function appendOptionItems(selectElement, items, control) {
  const valueKey = control.value_field || 'value';
  const labelKey = control.label_field || 'label';

  items.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item[valueKey];
    opt.textContent = item[labelKey];
    selectElement.appendChild(opt);
  });
}