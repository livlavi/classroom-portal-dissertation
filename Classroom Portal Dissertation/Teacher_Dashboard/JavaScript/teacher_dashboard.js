document.addEventListener("DOMContentLoaded", () => {
	// All your code here, e.g.:

	// Toggle Dark Mode
	const toggleDarkMode = document.getElementById("dark-mode-toggle");
	if (toggleDarkMode) {
		toggleDarkMode.addEventListener("click", () => {
			document.body.classList.toggle("dark-mode");
			const isDarkMode = document.body.classList.contains("dark-mode");
			document.cookie = `dark_mode=${isDarkMode}; path=/; max-age=31536000`;
		});
	}

	// Attendance Container logic
	const attendanceContainer = document.getElementById("attendance-container");
	if (attendanceContainer) {
		attendanceContainer.innerHTML = "<p>Loading attendance data...</p>";

		fetch("../../Global_PHP/get_students_by_year.php")
			.then((res) => res.json())
			.then((data) => {
				if (!data.success) {
					attendanceContainer.innerHTML = `<p>Error: ${data.message}</p>`;
					return;
				}
				console.log(data.studentsGrouped);
			})
			.catch((err) => {
				attendanceContainer.innerHTML = `<p>Fetch error: ${err.message}</p>`;
			});

		fetch("../PHP/view_attendance.php")
			.then((response) => response.json())
			.then((data) => {
				if (!data.success) {
					attendanceContainer.innerHTML = `<p>Error loading data: ${data.message}</p>`;
					return;
				}
				let html = "";
				for (const [year, students] of Object.entries(data.studentsGrouped)) {
					html += `<div class="year-group"><h2>Year ${year}</h2>`;
					students.forEach((student) => {
						html += `<div class="student">
              <h4>${student.first_name} ${student.last_name}</h4>
              <p><strong>Attendance %:</strong> ${student.attendance_percentage ?? "N/A"}%</p>
              <strong>History:</strong>
              <ul class="attendance-history">`;
						if (student.history && student.history.length) {
							student.history.forEach((record) => {
								html += `<li>${record.date}: ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</li>`;
							});
						} else {
							html += "<li>No attendance records found.</li>";
						}
						html += "</ul></div>";
					});
					html += "</div>";
				}
				attendanceContainer.innerHTML = html;
			})
			.catch((error) => {
				attendanceContainer.innerHTML = `<p>Error fetching attendance: ${error.message}</p>`;
			});
	}

	// Notifications
	const notificationsList = document.querySelector(".notifications-list");
	if (notificationsList) {
		notificationsList.innerHTML = "<p>Loading notifications...</p>";
		setTimeout(() => {
			const notifications = [
				{ message: "Attendance has been updated for Class A." },
				{ message: "Homework deadline extended for Math Assignment." },
				{ message: "Parent-Teacher meeting scheduled for Friday." },
			];
			notificationsList.innerHTML = "";
			if (notifications.length > 0) {
				notifications.forEach((notification) => {
					const li = document.createElement("li");
					li.textContent = notification.message;
					notificationsList.appendChild(li);
				});
			} else {
				notificationsList.innerHTML = "<p>No notifications available.</p>";
			}
		}, 1500);
	}

	// Open Chat buttons
	document.querySelectorAll(".open-chat").forEach((button) => {
		button.addEventListener("click", () => {
			alert(
				"Live chat functionality is only available in the admin dashboard."
			);
		});
	});

	// Calendar slots
	document.querySelectorAll(".calendar-slot").forEach((slot) => {
		slot.addEventListener("click", () => {
			if (confirm("Are you sure you want to book this slot?")) {
				slot.classList.add("booked");
				slot.textContent = "Booked";
				slot.style.backgroundColor = "#28a745";
				slot.style.color = "#fff";
				alert("Slot booked successfully!");
			}
		});
	});

	// Sidebar navigation
	document.querySelectorAll("aside.sidebar ul li a").forEach((anchor) => {
		anchor.addEventListener("click", function (e) {
			const href = this.getAttribute("href");
			if (!href.startsWith("#")) {
				return;
			} else {
				e.preventDefault();
				const targetId = href.substring(1);
				const targetSection = document.getElementById(targetId);
				if (targetSection) {
					window.scrollTo({
						top: targetSection.offsetTop - 60,
						behavior: "smooth",
					});
				}
			}
		});
	});
});
