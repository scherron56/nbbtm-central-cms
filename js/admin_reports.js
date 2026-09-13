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

        let inputType = 'text';
        if (param.param_type === 'int' || param.param_type === 'float') {
          inputType = 'number';
        } else if (param.param_type === 'date') {
          inputType = 'date';
        }

        const label = document.createElement('label');
        label.htmlFor = param.param_name;
        label.textContent = param.lbl_param_name || param.param_name;

        if (Number(param.is_required) === 1) {
          const requiredMark = document.createElement('span');
          requiredMark.className = 'text-danger';
          requiredMark.textContent = ' *';
          label.appendChild(requiredMark);
        }

        const input = document.createElement('input');
        input.type = inputType;
        input.id = param.param_name;
        input.name = param.param_name;
        input.className = 'form-control';
        input.value = param.default_value || '';
        input.required = Number(param.is_required) === 1;

        div.appendChild(label);
        div.appendChild(input);
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
