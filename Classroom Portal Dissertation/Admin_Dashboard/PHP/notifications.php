<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Notifications</title>
    <style>
        /* Simple styling */
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        ul#notifications-list {
            list-style: none;
            padding: 0;
            max-width: 600px;
            margin: 0 auto;
        }

        ul#notifications-list li {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            position: relative;
            background-color: #f9f9f9;
        }

        ul#notifications-list li.read {
            background-color: #e0e0e0;
            color: #555;
        }

        ul#notifications-list li.unread {
            font-weight: bold;
            background-color: #fff;
        }

        ul#notifications-list li button {
            margin-right: 10px;
            padding: 5px 10px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }

        button.mark-read-btn {
            background-color: #4caf50;
            color: white;
        }

        button.delete-notif-btn {
            background-color: #f44336;
            color: white;
        }

        small {
            color: #666;
        }

        /* Notification count badge */
        #notif-count {
            background: red;
            color: white;
            padding: 3px 8px;
            border-radius: 50%;
            display: inline-block;
            min-width: 24px;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
            margin-left: 8px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <h1>
        Notifications
        <span id="notif-count" style="display:none;">0</span>
    </h1>

    <a href="admin_dashboard.php">
        <button
            style="margin: 10px 0; padding: 10px 15px; font-size: 16px; border: none; background-color: #2196f3; color: white; border-radius: 6px; cursor: pointer;">
            ⬅ Return to Dashboard
        </button>
    </a>

    <ul id="notifications-list">
        <li>Loading...</li>
    </ul>

    <script>
        const notifList = document.getElementById("notifications-list");
        const notifCountElem = document.getElementById("notif-count");

        // Create one notification list item with buttons
        function createNotificationItem(notification) {
            const li = document.createElement("li");
            li.dataset.id = notification.id;
            const isRead = notification.is_read == "1";
            li.className = isRead ? "read" : "unread";

            const formattedDate = new Date(notification.created_at).toLocaleString();

            li.innerHTML = `
        <p><strong>${notification.message}</strong></p>
        <small>${formattedDate}</small><br>
        <button class="mark-read-btn">${isRead ? "Mark as Unread" : "Mark as Read"}</button>
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

        // Update notification count badge based on unread notifications
        function updateNotifCount() {
            const unreadCount = [...notifList.children].filter(li => li.classList.contains("unread")).length;

            if (unreadCount > 0) {
                notifCountElem.textContent = unreadCount;
                notifCountElem.style.display = "inline-block";
            } else {
                notifCountElem.style.display = "none";
            }
        }

        // Fetch and display notifications
        function loadNotifications() {
            notifList.innerHTML = "<li>Loading...</li>";
            fetch("../../Global_PHP/fetch_notifications.php")
                .then((res) => res.json())
                .then((data) => {
                    notifList.innerHTML = "";
                    if (data.success && data.notifications?.length > 0) {
                        data.notifications.forEach(notif => {
                            notifList.appendChild(createNotificationItem(notif));
                        });
                    } else {
                        notifList.innerHTML = "<li>No notifications available.</li>";
                    }
                    updateNotifCount();
                })
                .catch((err) => {
                    notifList.innerHTML = "<li>Error loading notifications.</li>";
                    console.error(err);
                });
        }

        // Toggle read/unread status
        function toggleRead(id, li) {
            const isUnread = li.classList.contains("unread");
            const newReadStatus = isUnread ? "1" : "0";

            fetch("../../Global_PHP/mark_as_read.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `id=${encodeURIComponent(id)}&read=${encodeURIComponent(newReadStatus)}`,
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (isUnread) {
                            li.classList.remove("unread");
                            li.classList.add("read");
                            li.querySelector(".mark-read-btn").textContent = "Mark as Unread";
                        } else {
                            li.classList.remove("read");
                            li.classList.add("unread");
                            li.querySelector(".mark-read-btn").textContent = "Mark as Read";
                        }
                        updateNotifCount();
                    } else {
                        alert("Failed to update notification status");
                    }
                })
                .catch(err => {
                    console.error("Error updating notification:", err);
                    alert("Error updating notification status");
                });
        }

        // Delete a notification
        function deleteNotification(id, li) {
            if (!confirm("Are you sure you want to delete this notification?")) return;

            fetch("../../Global_PHP/delete_notification.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `id=${encodeURIComponent(id)}`,
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        li.remove();
                        if (notifList.children.length === 0) {
                            notifList.innerHTML = "<li>No notifications available.</li>";
                        }
                        updateNotifCount();
                    } else {
                        alert("Failed to delete notification");
                    }
                })
                .catch(err => {
                    console.error("Error deleting notification:", err);
                    alert("Error deleting notification");
                });
        }

        // Initial load
        loadNotifications();
    </script>
</body>

</html>