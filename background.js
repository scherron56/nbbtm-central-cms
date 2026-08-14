// Listen for incoming messages from popup or content scripts
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log("Background received message:", request);

  if (request.data === "test") {
    // Send a response back to acknowledge receipt
    sendResponse({ status: "Received successfully!" });
  }

  // Return true to keep the message channel open if handling responses asynchronously
  return true;
});