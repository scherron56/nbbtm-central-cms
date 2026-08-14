async function sendDataToBackground() {
  try {
    const response = await chrome.runtime.sendMessage({ data: "test" });
    console.log("Success:", response);
  } catch (error) {
    // Safely handles the disconnected script / no listener error
    console.warn("Connection could not be established; background target might be inactive or reloading.", error.message);
  }
}

sendDataToBackground();