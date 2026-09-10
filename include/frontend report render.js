// Load parameter schema from PHP endpoint on page load or report switch
function loadReportParameters(reportId) {
  fetch(`/api/get-report-parameters.php?report_id=${encodeURIComponent(reportId)}`)
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      return res.json();
    })
    .then(data => {
      // Pass the parameter schema array directly into your JS renderer function
      renderReportControls(data.parameters, 'report-parameters-container');
    })
    .catch(err => {
      console.error('Failed to fetch parameter configuration:', err);
    });
}

// Example usage: Initialize the form for 'ministry_report'
document.addEventListener('DOMContentLoaded', () => {
  loadReportParameters('ministry_report');
});