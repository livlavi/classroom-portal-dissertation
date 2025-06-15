document.addEventListener("DOMContentLoaded", () => {
	console.log("chat.js loaded");

	const chatInput = document.getElementById("chat-input");
	const sendMessageButton = document.getElementById("send-message");
	const chatMessages = document.getElementById("chat-messages");
	const profileInfo = document.getElementById("profile-info");
	const recipientName = document.getElementById("recipient-name");
	const chatHistory = document.getElementById("chat-history");
	let currentRecipientId = null;

	const basePath = "/Classroom Portal Dissertation/Messaging_Chat/";
	const currentUserId = window.currentUserId;
	let preSelectedRecipientId = window.preSelectedRecipientId;
	// Handle dropdown user selection
	document.querySelectorAll(".user-dropdown").forEach((select) => {
		select.addEventListener("change", (e) => {
			const selectedUserId = e.target.value;
			if (!selectedUserId) return; // no selection

			const selectedOption = e.target.options[e.target.selectedIndex];
			const userName = selectedOption.text;

			openChatWithUser(selectedUserId, userName);

			// Clear selection so user can re-select same user if needed
			e.target.value = "";
		});
	});

	function fetchMessages(otherUserId) {
		console.log(`Fetching messages for user ${otherUserId}`);
		fetch(`${basePath}fetch_messages.php?other_user_id=${otherUserId}`)
			.then((response) => {
				if (!response.ok)
					throw new Error(`HTTP error! Status: ${response.status}`);
				return response.json();
			})
			.then((data) => {
				console.log("Fetch response:", data);
				chatMessages.innerHTML = "";
				if (data.success) {
					if (data.messages.length === 0) {
						chatMessages.innerHTML = "<li>No messages yet.</li>";
					} else {
						data.messages.forEach((message) => {
							const li = document.createElement("li");
							const senderRole = message.sender_role.toLowerCase();
							li.textContent = `[${message.first_name} ${message.last_name}] ${message.message} (${new Date(message.created_at).toLocaleTimeString()})`;
							li.classList.add(`${senderRole}-message`);
							chatMessages.appendChild(li);
						});
					}
					chatMessages.scrollTop = chatMessages.scrollHeight;
				} else {
					chatMessages.innerHTML = `<li>${data.message}</li>`;
				}
			})
			.catch((error) => {
				console.error("Error fetching messages:", error);
				chatMessages.innerHTML = `<li>Error loading messages: ${error.message}</li>`;
			});
	}

	sendMessageButton.addEventListener("click", () => {
		const message = chatInput.value.trim();
		if (!message || !currentRecipientId) {
			alert("Please select a recipient and type a message.");
			return;
		}

		const receiverElement = document.querySelector(
			`.user-item[data-user-id="${currentRecipientId}"]`
		);
		const receiverRole = receiverElement
			? receiverElement.dataset.role
			: "unknown";

		console.log(
			`Sending message to ${currentRecipientId} (${receiverRole}): ${message}`
		);

		fetch(`${basePath}send_message.php`, {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: `receiver_id=${currentRecipientId}&message=${encodeURIComponent(message)}&receiver_role=${receiverRole}`,
		})
			.then((response) => {
				if (!response.ok)
					throw new Error(`HTTP error! Status: ${response.status}`);
				return response.json();
			})
			.then((data) => {
				console.log("Send response:", data);
				if (data.success) {
					chatInput.value = "";
					fetchMessages(currentRecipientId);
					updateUserList(); // refresh list to update badges if needed
					document
						.querySelectorAll(".user-item")
						.forEach((item) => item.classList.remove("active"));
					const newItem = document.querySelector(
						`.user-item[data-user-id="${currentRecipientId}"]`
					);
					if (newItem) newItem.classList.add("active");
				} else {
					alert("Failed to send message: " + data.message);
				}
			})
			.catch((error) => console.error("Error sending message:", error));
	});

	async function updateUserList() {
		try {
			// Fetch users and unread counts (with last_message_time)
			const [usersRes, countsRes] = await Promise.all([
				fetch(`${basePath}fetch_users.php`),
				fetch(`${basePath}fetch_unread_counts.php`),
			]);
			const usersData = await usersRes.json();
			const countsData = await countsRes.json();

			if (!usersData.success || !countsData.success) {
				console.error("Failed to fetch users or counts");
				return;
			}

			// Flatten all users into one array
			const allUsers = [].concat(
				usersData.users.teacher || [],
				usersData.users.student || [],
				usersData.users.parent || [],
				usersData.users.admin || []
			);

			// Map user_id => { unread_count, last_message_time }
			const unreadMap = {};
			countsData.counts.forEach((c) => {
				unreadMap[c.sender_id] = {
					unread_count: c.unread_count,
					last_message_time: c.last_message_time,
				};
			});

			// Combine user info with unread count + last message time
			const usersWithMeta = allUsers.map((user) => {
				const meta = unreadMap[user.id] || {
					unread_count: 0,
					last_message_time: null,
				};
				return {
					...user,
					unread_count: meta.unread_count,
					last_message_time: meta.last_message_time,
				};
			});

			// Sort by last_message_time descending (most recent first)
			usersWithMeta.sort((a, b) => {
				const aTime = a.last_message_time ? new Date(a.last_message_time) : 0;
				const bTime = b.last_message_time ? new Date(b.last_message_time) : 0;
				return bTime - aTime;
			});

			// Clear existing list
			chatHistory.innerHTML = "";

			// Render user items with unread badges
			usersWithMeta.forEach((user) => {
				const li = document.createElement("li");
				li.className = "user-item";
				li.dataset.userId = user.id;
				li.dataset.role = user.role;
				li.textContent = `${user.first_name} ${user.last_name} (${user.role})`;

				if (user.unread_count > 0) {
					const span = document.createElement("span");
					span.className = "unread-count";
					span.textContent = user.unread_count;
					li.appendChild(span);
				}

				li.addEventListener("click", () => {
					openChatWithUser(user.id, `${user.first_name} ${user.last_name}`);
					setActiveUserItem(user.id);
				});

				chatHistory.appendChild(li);
			});

			// Open preselected user if any
			if (preSelectedRecipientId) {
				setActiveUserItem(preSelectedRecipientId);
				openChatWithUser(preSelectedRecipientId, null);
				preSelectedRecipientId = null;
			}
		} catch (error) {
			console.error("Error updating user list:", error);
		}
	}

	function setActiveUserItem(userId) {
		document.querySelectorAll(".user-item").forEach((item) => {
			item.classList.toggle("active", item.dataset.userId == userId);
		});
	}

	function setupDropdownUserClicks() {
		document.querySelectorAll(".dropdown-user-item").forEach((item) => {
			item.addEventListener("click", () => {
				const userId = item.dataset.userId;
				const userName = item.textContent.trim();
				openChatWithUser(userId, userName);
				setActiveUserItem(userId);
			});
		});
	}

	function openChatWithUser(userId, userName) {
		currentRecipientId = userId;
		if (userName) {
			recipientName.textContent = userName;
		} else {
			const li = document.querySelector(`.user-item[data-user-id="${userId}"]`);
			if (li)
				recipientName.textContent = li.textContent
					.replace(/\(\w+\)$/, "")
					.trim();
		}
		profileInfo.style.display = "block";

		fetchMessages(userId);

		// Mark messages as read, then refresh user list (to remove badges)
		fetch(`${basePath}mark_messages_read.php`, {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: `user_id=${currentUserId}&other_user_id=${userId}`,
		})
			.then((res) => res.json())
			.then((data) => {
				if (data.success) {
					updateUserList();
				} else {
					console.error("Failed to mark messages as read:", data.message);
				}
			})
			.catch((error) =>
				console.error("Error marking messages as read:", error)
			);
	}

	// Initial load
	updateUserList();
	setupDropdownUserClicks();

	// Poll every 5 seconds for new messages & user list update
	setInterval(() => {
		if (currentRecipientId) {
			fetchMessages(currentRecipientId);
		}
		updateUserList();
	}, 5000);
});
