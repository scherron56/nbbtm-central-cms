function loadReportParams(reportKey) {
  const container = document.getElementById('dynamic-params');
  const submitBtn = document.getElementById('btn-submit');

  if (!container || !submitBtn) {
    return;
  }

  container.innerHTML = '';
  submitBtn.disabled = true;

  if (!reportKey) {
    return;
  }

  fetch('get_params.php?report=' + encodeURIComponent(reportKey))
    .then(async response => {
      const text = await response.text();
      let payload = null;

      try {
        payload = text ? JSON.parse(text) : null;
      } catch (error) {
        throw new Error(text || 'Server returned an unreadable response.');
      }

      if (!response.ok) {
        const serverMessage = payload && payload.error
          ? payload.error
          : 'The server returned an error while loading report parameters.';
        throw new Error(serverMessage);
      }

      return payload;
    })
    .then(params => {
      if (params && params.error) {
        throw new Error(params.error);
      }

      if (!params || params.length === 0) {
        container.innerHTML = '<p style="grid-column: span 12; color: #64748b; font-style: italic;">No additional parameters required for this report.</p>';
        submitBtn.disabled = false;
        return;
      }

      params.forEach(param => {
        const div = document.createElement('div');
        div.className = 'field-group';
        div.style.setProperty('--colspan', '6');

        const label = document.createElement('label');
        label.htmlFor = param.param_name;
        label.textContent = param.lbl_param_name || param.param_name;

        const isRequired = Number(param.is_required) === 1;
        if (isRequired) {
          const requiredMark = document.createElement('span');
          requiredMark.className = 'text-danger';
          requiredMark.textContent = ' *';
          label.appendChild(requiredMark);
        }

        const controlType = param.control_type && param.control_type !== 'auto'
          ? param.control_type
          : null;
        const options = Array.isArray(param.static_options) ? param.static_options : [];

        let control;

        if (controlType === 'single_select' || controlType === 'multi_select') {
          control = document.createElement('select');
          control.id = param.param_name;
          control.className = 'form-control';
          control.name = controlType === 'multi_select' ? param.param_name + '[]' : param.param_name;

          if (controlType === 'single_select' && !isRequired) {
            const blankOpt = document.createElement('option');
            blankOpt.value = '';
            blankOpt.textContent = '-- Select --';
            control.appendChild(blankOpt);
          }

          options.forEach(opt => {
            const optionEl = document.createElement('option');
            optionEl.value = opt.value;
            optionEl.textContent = opt.label;
            if (controlType === 'single_select' && param.default_value !== undefined && String(opt.value) === String(param.default_value)) {
              optionEl.selected = true;
            }
            control.appendChild(optionEl);
          });

          if (controlType === 'multi_select') {
            control.multiple = true;
          }
        } else if (controlType === 'checkbox') {
          control = document.createElement('input');
          control.type = 'checkbox';
          control.id = param.param_name;
          control.name = param.param_name;
          control.className = 'form-check-input';
          control.value = '1';
          control.checked = param.default_value === '1' || param.default_value === true;
        } else {
          let inputType = 'text';
          if (controlType === 'text') {
            inputType = 'text';
          } else if (controlType === 'number') {
            inputType = 'number';
          } else if (controlType === 'date') {
            inputType = 'date';
          } else if (param.param_type === 'int' || param.param_type === 'float') {
            inputType = 'number';
          } else if (param.param_type === 'date') {
            inputType = 'date';
          }

          control = document.createElement('input');
          control.type = inputType;
          control.id = param.param_name;
          control.name = param.param_name;
          control.className = 'form-control';
          control.value = param.default_value || '';
        }

        control.required = isRequired;

        div.appendChild(label);
        div.appendChild(control);
        container.appendChild(div);
      });

      submitBtn.disabled = false;
    })
    .catch(error => {
      container.innerHTML = '';
      const errorMessage = document.createElement('span');
      errorMessage.className = 'text-danger';
      errorMessage.textContent = error && error.message
        ? error.message
        : 'Failed to communicate with server.';
      container.appendChild(errorMessage);
    });
}
