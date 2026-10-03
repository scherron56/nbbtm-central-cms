function loadReportParameters(reportKey) {
  const container = document.getElementById('dynamic-params');
  const submitButton = document.getElementById('btn-submit');

  if (!container || !submitButton) {
    return;
  }

  container.replaceChildren();
  submitButton.disabled = true;

  if (!reportKey) {
    return;
  }

  fetch(`get-report-parameters.php?report_key=${encodeURIComponent(reportKey)}`)
    .then(async response => {
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Unable to load report parameters.');
      }

      return data;
    })
    .then(data => {
      renderReportControls(data.parameters, 'dynamic-params');
      submitButton.disabled = false;
    })
    .catch(error => {
      const message = document.createElement('p');
      message.className = 'text-danger';
      message.textContent = error.message || 'Unable to load report parameters.';
      container.replaceChildren(message);
    });
}
