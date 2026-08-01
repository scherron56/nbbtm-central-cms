chrome.runtime.sendMessage({ data: "test" }, (response) => {
  if (chrome.runtime.lastError) {
    // This safely catches and acknowledges the error instead of letting it blow up
    console.warn("Connection could not be established; target might be closed.");
    return;
  }
  // Proceed with normal response handling
  console.log("Success:", response);
});
