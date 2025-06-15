// JavaScript/login.js

// Example: Simulate error handling
const errorMessage = document.getElementById("error-message");
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get("error")) {
	errorMessage.textContent = "Invalid username or password.";
}
