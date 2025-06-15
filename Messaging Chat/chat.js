document.addEventListener("DOMContentLoaded", () => {
	const recipientDropdown = document.getElementById("recipient-dropdown");
	const chatInput = document.getElementById("chat-input");
	const sendMessageButton = document.getElementById("send-message");
	const chatMessages = document.getElementById("chat-messages");

	if (!recipientDropdown || !chatInput || !sendMessageButton || !chatMessages) {
		console.error("Chat elements not found.");
		return;
	}

	// Function to fetch and display messages
	function fetchMessages(otherUserId) {
		fetch(`fetch_messages.php?other_user_id=${otherUserId}`)
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					chatMessages.innerHTML = ""; // Clear existing messages
					data.messages.forEach((message) => {
						const li = document.createElement("li");
						li.textContent = `${message.message} (${new Date(message.created_at).toLocaleTimeString()})`;
						chatMessages.appendChild(li);
					});
				}
			})
			.catch((error) => console.error("Error fetching messages:", error));
	}

	// Send message
	sendMessageButton.addEventListener("click", () => {
		const message = chatInput.value.trim();
		const receiverId = recipientDropdown.value;

		if (!message || !receiverId) {
			alert("Please select a recipient and type a message.");
			return;
		}

		fetch("send_message.php", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: `receiver_id=${receiverId}&message=${encodeURIComponent(message)}`,
		})
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					chatInput.value = ""; // Clear input
					fetchMessages(receiverId); // Refresh messages
				} else {
					alert("Failed to send message: " + data.message);
				}
			})
			.catch((error) => console.error("Error sending message:", error));
	});

	// Fetch messages when a recipient is selected
	recipientDropdown.addEventListener("change", () => {
		const selectedUserId = recipientDropdown.value;
		if (selectedUserId) {
			fetchMessages(selectedUserId);
		}
	});
});

data.messages.forEach((message) => {
	const li = document.createElement("li");
	li.textContent = `[${message.first_name} ${message.last_name}] ${message.message} (${new Date(message.created_at).toLocaleTimeString()})`;
	chatMessages.appendChild(li);
});
