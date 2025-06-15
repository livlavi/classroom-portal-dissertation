// parent_dashboard.js

document.addEventListener("DOMContentLoaded", function () {
	// Toggle Dark Mode
	const darkModeToggle = document.getElementById("dark-mode-toggle");
	if (darkModeToggle) {
		darkModeToggle.addEventListener("click", toggleDarkMode);
	}

	// Expand/Collapse Sidebar on Hover
	const sidebar = document.querySelector(".sidebar");
	if (sidebar) {
		sidebar.addEventListener("mouseenter", expandSidebar);
		sidebar.addEventListener("mouseleave", collapseSidebar);
	}

	// Handle Notifications Loading
	loadNotifications();

	// Handle Chat Modal Opening
	setupChatModal();
});

/**
 * Toggles dark mode on/off.
 */
function toggleDarkMode() {
	const body = document.body;
	const isDarkMode = body.classList.toggle("dark-mode");

	// Save preference in a cookie
	document.cookie = `dark_mode=${isDarkMode}; path=/; max-age=${60 * 60 * 24 * 30}`; // 30 days
}

/**
 * Expands the sidebar when hovered.
 */
function expandSidebar() {
	this.style.width = "250px";
	document.querySelector(".main-content").style.marginLeft = "270px";
}

/**
 * Collapses the sidebar when hover ends.
 */
function collapseSidebar() {
	this.style.width = "60px";
	document.querySelector(".main-content").style.marginLeft = "80px";
}

/**
 * Loads notifications dynamically into the notifications section.
 */
function loadNotifications() {
	const notificationsList = document.querySelector(".notifications-list");
	if (!notificationsList) return;

	// Simulate loading notifications via AJAX or API call
	setTimeout(() => {
		const notifications = [
			{ message: "Homework submission deadline extended." },
			{ message: "Parent-teacher meeting scheduled for tomorrow." },
			{ message: "New newsletter published." },
		];

		if (notifications.length === 0) {
			notificationsList.innerHTML = "<li>No notifications available.</li>";
		} else {
			notificationsList.innerHTML = notifications
				.map((notification) => `<li>${notification.message}</li>`)
				.join("");
		}
	}, 1000); // Simulate a delay for loading
}

/**
 * Sets up the chat modal functionality.
 */
function setupChatModal() {
	const openChatButtons = document.querySelectorAll("#open-chat-modal");
	openChatButtons.forEach((button) => {
		button.addEventListener("click", () => {
			window.open(
				"/Classroom Portal Dissertation/Messaging_Chat/chat.php",
				"_blank"
			);
		});
	});
}
