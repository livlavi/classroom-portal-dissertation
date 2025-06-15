// Toggle dark mode on button click
document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
	document.body.classList.toggle("dark-mode");
});

// Load events from the database and pass them to the callback
function loadEventsFromDatabase(callback) {
	fetch("../PHP/load_events.php")
		.then((response) => response.json())
		.then((events) => callback(events))
		.catch((error) => console.error("Error loading events:", error));
}

// Initialize FullCalendar if the calendar element exists
const calendarEl = document.getElementById("calendar");
if (calendarEl) {
	const calendar = new FullCalendar.Calendar(calendarEl, {
		initialView: "dayGridMonth", // Start in month view
		initialDate: new Date(), // Use today's date
		events: function (info, successCallback, failureCallback) {
			loadEventsFromDatabase(function (events) {
				successCallback(events); // Provide fetched events to calendar
			});
		},
		headerToolbar: {
			left: "prev,next today",
			center: "",
			right: "dayGridMonth,timeGridWeek,timeGridDay",
		},
		editable: true, // Allow event editing
		droppable: true, // Support dragging external items
		selectable: true, // Allow date range selection
		select(info) {
			// Prompt user to add event
			const title = prompt("Enter event title:");
			if (title) {
				const newEvent = {
					title,
					start: info.start,
					allDay: true,
				};
				calendar.addEvent(newEvent); // Add to calendar
				saveEventToDatabase(newEvent); // Save to DB
			}
		},
		eventClick(info) {
			// Confirm and delete event
			if (confirm(`Delete event '${info.event.title}'?`)) {
				info.event.remove();
				deleteEventFromDatabase(info.event.id);
			}
		},
	});

	calendar.render();

	// Add custom title below toolbar
	const calendarHeader = calendarEl.querySelector(".fc-toolbar");
	const titleDiv = document.createElement("div");
	titleDiv.style.textAlign = "center";
	titleDiv.style.fontSize = "1.4rem";
	titleDiv.style.fontWeight = "bold";
	titleDiv.style.margin = "10px 0";
	titleDiv.textContent = calendar.view.title;

	calendarHeader.insertAdjacentElement("afterend", titleDiv);

	// Update custom title when view changes
	calendar.on("datesSet", (info) => {
		titleDiv.textContent = info.view.title;
	});
}

// Save new event to server
function saveEventToDatabase({ title, start }) {
	const localDate = start.toLocaleDateString("en-CA"); // Format: YYYY-MM-DD

	fetch("save_event.php", {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: `title=${encodeURIComponent(title)}&start=${encodeURIComponent(localDate)}`,
	})
		.then((res) => res.json())
		.then((data) => console.log(data.success ? "Event saved" : data.message))
		.catch((err) => console.error("Error saving event:", err));
}

// Delete event by ID
function deleteEventFromDatabase(eventId) {
	fetch("delete_event.php", {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: `id=${encodeURIComponent(eventId)}`,
	})
		.then((res) => res.json())
		.then((data) => console.log(data.success ? "Event deleted" : data.message))
		.catch((err) => console.error("Error deleting event:", err));
}

// Live user search functionality
document.getElementById("search-bar")?.addEventListener("input", function () {
	const query = this.value.trim();
	const resultsContainer = document.getElementById("search-results");

	if (query.length > 2) {
		// Fetch matching users
		fetch(`PHP/search.php?query=${encodeURIComponent(query)}`)
			.then((res) => res.json())
			.then((data) => {
				resultsContainer.innerHTML = data.length
					? data
							.map((user) => `<li>${user.role}: ${user.username}</li>`)
							.join("")
					: "<li>No results found.</li>";
			})
			.catch((err) => console.error("Error:", err));
	} else {
		resultsContainer.innerHTML = "";
	}
});

// Modal open buttons
document.getElementById("view-users")?.addEventListener("click", () => {
	document.getElementById("view-users-modal").style.display = "block";
});
document.getElementById("add-user")?.addEventListener("click", () => {
	document.getElementById("add-user-modal").style.display = "block";
});

// Toggle edit modal fields based on selected role
function toggleEditRoleFields(role) {
	document.getElementById("edit-parent-fields").style.display =
		role === "parent" ? "block" : "none";
	document.getElementById("edit-teacher-fields").style.display =
		role === "teacher" ? "block" : "none";
	document.getElementById("edit-student-fields").style.display =
		role === "student" ? "block" : "none";
}

// Listen for role change in edit form
document.getElementById("edit-role")?.addEventListener("change", (e) => {
	toggleEditRoleFields(e.target.value);
});

// Modal close buttons
document.querySelectorAll(".modal .close").forEach((btn) => {
	btn.addEventListener("click", () => {
		btn.closest(".modal").style.display = "none";
	});
});

// Close modal if clicking outside of it
window.addEventListener("click", (e) => {
	document.querySelectorAll(".modal").forEach((modal) => {
		if (e.target === modal) modal.style.display = "none";
	});
});

// Populate the edit modal with user data from the server
function populateEditModal(userId, role) {
	fetch(
		`../../Global_PHP/fetch_user_details.php?user_id=${userId}&role=${role}`
	)
		.then((res) => res.json())
		.then((data) => {
			if (data.success) {
				document.getElementById("edit-user-id").value = userId;
				document.getElementById("edit-first-name").value =
					data.first_name || "";
				document.getElementById("edit-last-name").value = data.last_name || "";
				document.getElementById("edit-role").value = role;
				toggleEditRoleFields(role);

				// Fill role-specific fields
				if (role === "parent") {
					document.getElementById("edit-child-full-name").value =
						data.child_full_name || "";
				} else if (role === "teacher") {
					document.getElementById("edit-teacher-number").value =
						data.teacher_number || "";
					document.getElementById("edit-subject-taught").value =
						data.subject_taught || "";
				} else if (role === "student") {
					document.getElementById("edit-student-number").value =
						data.student_number || "";
					document.getElementById("edit-year-of-study").value =
						data.year_of_study || "";
				}

				document.getElementById("edit-user-modal").style.display = "block";
			} else {
				alert("Failed to load user data: " + data.message);
			}
		})
		.catch((err) => console.error("Error fetching user data:", err));
}

/* ===== Notification Handling ===== */

// DOM elements for notifications
const notifModal = document.getElementById("notifications-modal");
const notifLink = document.getElementById("notifications-link");
const notifListModal = document.getElementById("notifications-list-modal");
const closeNotifModal = document.getElementById("close-notifications-modal");

// Create a notification <li> element with handlers
function createNotificationItem(notification) {
	const li = document.createElement("li");
	li.dataset.id = notification.id;
	const readClass = notification.is_read === "1" ? "read" : "unread";
	li.className = readClass;

	const formattedDate = new Date(notification.created_at).toLocaleString();

	li.innerHTML = `
		<p><strong>${notification.message}</strong></p>
		<small>${formattedDate}</small><br>
		<button class="mark-read-btn">${readClass === "unread" ? "Mark as Read" : "Mark as Unread"}</button>
		<button class="delete-notif-btn">Delete</button>
	`;

	li.querySelector(".mark-read-btn").addEventListener("click", () => {
		toggleRead(notification.id, li);
	});
	li.querySelector(".delete-notif-btn").addEventListener("click", () => {
		deleteNotification(notification.id, li);
	});

	return li;
}

// Populate the modal with notifications
function populateNotificationsModal(notifications) {
	notifListModal.innerHTML = "";
	if (!notifications || notifications.length === 0) {
		notifListModal.innerHTML = "<li>No notifications available.</li>";
		return;
	}
	notifications.forEach((notif) => {
		notifListModal.appendChild(createNotificationItem(notif));
	});
}

// Fetch and update notification list and badge
function updateNotifications() {
	fetch("../../Global_PHP/fetch_notifications.php")
		.then((res) => res.json())
		.then((data) => {
			const notifCount = document.getElementById("notif-count");
			if (data.success && data.notifications?.length > 0) {
				if (notifCount) {
					notifCount.textContent = data.notifications.length;
					notifCount.style.display = "inline";
				}
				if (notifModal && notifModal.style.display === "block") {
					populateNotificationsModal(data.notifications);
				}
			} else {
				if (notifCount) notifCount.style.display = "none";
				if (notifModal && notifModal.style.display === "block") {
					notifListModal.innerHTML = "<li>No notifications available.</li>";
				}
			}
		})
		.catch((err) => {
			console.error("Error fetching notifications:", err);
			if (notifModal && notifModal.style.display === "block") {
				notifListModal.innerHTML = "<li>Error loading notifications.</li>";
			}
		});
}

// Mark notification read/unread
function toggleRead(id, li) {
	const isUnread = li.classList.contains("unread");
	const newReadStatus = isUnread ? "1" : "0";

	fetch("../../Global_PHP/mark_as_read.php", {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: `id=${encodeURIComponent(id)}&read=${newReadStatus}`,
	})
		.then((res) => res.json())
		.then((data) => {
			if (data.success) {
				li.classList.toggle("unread", newReadStatus === "0");
				li.classList.toggle("read", newReadStatus === "1");
				const button = li.querySelector(".mark-read-btn");
				if (button) {
					button.textContent =
						newReadStatus === "1" ? "Mark as Unread" : "Mark as Read";
				}
				updateNotifications();
			} else {
				alert("Failed to update read status: " + data.error);
			}
		})
		.catch((err) => {
			console.error("Error updating read status:", err);
		});
}

// Delete notification from database and DOM
function deleteNotification(id, li) {
	if (!confirm("Are you sure you want to delete this notification?")) return;

	fetch("../../Global_PHP/delete_notification.php", {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: `id=${encodeURIComponent(id)}`,
	})
		.then((res) => res.text())
		.then((text) => JSON.parse(text))
		.then((data) => {
			if (data.success) {
				li.remove();
				updateNotifications();
			} else {
				alert(
					"Failed to delete notification: " + (data.error || "Unknown error")
				);
			}
		})
		.catch((err) => {
			console.error("Error deleting notification:", err);
		});
}

// Show notification modal on bell icon click
notifLink?.addEventListener("click", () => {
	if (notifModal) {
		notifModal.style.display = "block";
		updateNotifications();
	}
});

// Close modal when X is clicked
closeNotifModal?.addEventListener("click", () => {
	if (notifModal) {
		notifModal.style.display = "none";
	}
});

// Delete user from system
function deleteUser(userId) {
	fetch("delete_user.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
		},
		body: JSON.stringify({ user_id: userId }),
	})
		.then((response) => response.json())
		.then((data) => {
			if (data.success) {
				showAlert(data.message, "success");

				// Remove row from table
				const deleteBtn = document.querySelector(`[data-user-id="${userId}"]`);
				if (deleteBtn) {
					deleteBtn.closest("tr").remove();
				}

				setTimeout(() => {
					location.reload();
				}, 1500);
			} else {
				showAlert(data.message, "error");
			}
		})
		.catch((error) => {
			console.error("Error:", error);
			showAlert("An error occurred while deleting the user.", "error");
		});
}

// Show alert message on screen
function showAlert(message, type) {
	const alert = document.createElement("div");
	alert.className = `alert alert-${type}`;
	alert.textContent = message;

	document.body.insertBefore(alert, document.body.firstChild);

	setTimeout(() => {
		alert.remove();
	}, 5000);
}

// Initialize everything once DOM is ready
document.addEventListener("DOMContentLoaded", () => {
	// Delete user button
	document.addEventListener("click", function (e) {
		if (e.target.classList.contains("delete-user-btn")) {
			const userId = e.target.getAttribute("data-user-id");
			const userName =
				e.target.closest("tr").querySelector("td:first-child").textContent +
				" " +
				e.target.closest("tr").querySelector("td:nth-child(2)").textContent;

			if (
				confirm(
					`Are you sure you want to delete ${userName}? This action cannot be undone.`
				)
			) {
				deleteUser(userId);
			}
		}
	});

	// Edit user button
	document.addEventListener("click", function (e) {
		if (e.target.classList.contains("edit-user-btn")) {
			const userId = e.target.getAttribute("data-user-id");
			const role = e.target.getAttribute("data-role");
			populateEditModal(userId, role);
		}
	});

	// Add user form
	const addUserForm = document.getElementById("add-user-form");
	if (addUserForm) {
		addUserForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const formData = new FormData(this);
			fetch("../PHP/add_user.php", { method: "POST", body: formData })
				.then((res) => res.text())
				.then(() => location.reload())
				.catch((err) => console.error("Error adding user:", err));
		});
	}

	// Edit user form
	const editUserForm = document.getElementById("edit-user-form");
	if (editUserForm) {
		editUserForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const formData = new FormData(this);
			fetch("edit_user.php", { method: "POST", body: formData })
				.then(() => location.reload())
				.catch((err) => console.error("Error updating user:", err));
		});
	}

	// Newsletter form
	document.getElementById("sendNewsletter")?.addEventListener("click", () => {
		const title = document.getElementById("newsletterTitle").value;
		const content = document.getElementById("newsletterContent").value;
		const formData = new FormData();
		formData.append("title", title);
		formData.append("content", content);

		fetch("../PHP/send_newsletter.php", { method: "POST", body: formData })
			.then(() => location.reload())
			.catch((err) => console.error("Error sending newsletter:", err));
	});

	// Role-specific field visibility (add user modal)
	const roleSelect = document.getElementById("role");
	if (roleSelect) {
		function updateFields() {
			const role = roleSelect.value;
			document.getElementById("parent-fields").style.display =
				role === "parent" ? "block" : "none";
			document.getElementById("teacher-fields").style.display =
				role === "teacher" ? "block" : "none";
			document.getElementById("student-fields").style.display =
				role === "student" ? "block" : "none";
		}
		roleSelect.addEventListener("change", updateFields);
		updateFields();
	}

	// Render analytics chart
	const analyticsData = window.analyticsData || [];
	if (analyticsData.length > 0) {
		const labels = analyticsData.map((i) => i.role);
		const data = analyticsData.map((i) => i.count);
		const ctx = document.getElementById("analyticsChart")?.getContext("2d");
		if (ctx) {
			new Chart(ctx, {
				type: "bar",
				data: {
					labels,
					datasets: [
						{
							label: "User Count",
							data,
							backgroundColor: ["#3498db", "#2ecc71", "#e74c3c", "#9b59b6"],
						},
					],
				},
				options: {
					responsive: true,
					plugins: { legend: { position: "top" } },
				},
			});
		}
	}

	// Load notifications on page load
	updateNotifications();
});

// Periodically refresh notifications
setInterval(updateNotifications, 30000); // Every 30 seconds
