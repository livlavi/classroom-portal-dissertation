// Dark Mode Toggle (unchanged)
document
	.getElementById("dark-mode-toggle")
	.addEventListener("click", function () {
		document.body.classList.toggle("dark-mode");
		const isDark = document.body.classList.contains("dark-mode");
		document.cookie = `dark_mode=${isDark}; path=/; max-age=31536000`;
	});

// Make openCalendarPopup global so the inline onclick can see it:
window.openCalendarPopup = function () {
	window.open(
		"calendar_popup.php",
		"CalendarPopup",
		"width=500,height=400,resizable=yes,scrollbars=yes"
	);
};
