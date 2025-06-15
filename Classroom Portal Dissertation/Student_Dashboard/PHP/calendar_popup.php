<?php
session_start();
require_once '../../Global_PHP/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
    }

    #calendar {
        padding: 20px;
        max-width: 900px;
        margin: 0 auto;
    }

    #modalOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 900;
    }

    #eventModal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        z-index: 1000;
        width: 300px;
    }

    #eventModal label {
        font-weight: bold;
    }
    </style>
</head>

<body>
    <div id="modalOverlay"></div>
    <div id="calendar"></div>

    <div id="eventModal" role="dialog" aria-modal="true">
        <h3>Add / Edit Event</h3>
        <form id="eventForm">
            <input type="hidden" id="eventId" name="id">
            <input type="hidden" id="eventDate" name="start_date">
            <label for="eventTitle">Title:</label><br>
            <input type="text" id="eventTitle" name="title" required><br><br>
            <button type="submit">Save</button>
            <button type="button" id="deleteBtn"
                style="display:none; background:#e74c3c; color:white; margin-left:8px;">Delete</button>
            <button type="button" id="cancelBtn" style="margin-left:8px;">Cancel</button>
        </form>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const calendarEl = document.getElementById('calendar');
        const overlay = document.getElementById('modalOverlay');
        const modal = document.getElementById('eventModal');
        const form = document.getElementById('eventForm');
        const idInput = document.getElementById('eventId');
        const dateInput = document.getElementById('eventDate');
        const titleInput = document.getElementById('eventTitle');
        const cancelBtn = document.getElementById('cancelBtn');

        // Initialize FullCalendar
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            displayEventTime: false, // hide times like "12a"
            selectable: true,
            editable: true,
            events: 'fetch_events.php',
            eventDidMount: info => info.event.setProp('allDay', true),
            dateClick: info => openModal(info.dateStr),
            eventClick: info => openModal(info.event.startStr, {
                id: info.event.id,
                title: info.event.title,
                start_date: info.event.startStr
            }),
            eventDrop: info => updateDate(info.event.id, info.event.startStr),
            eventResize: info => updateDate(info.event.id, info.event.startStr)
        });
        calendar.render();

        function openModal(dateStr, ev = null) {
            overlay.style.display = modal.style.display = 'block';
            dateInput.value = dateStr;
            if (ev) {
                idInput.value = ev.id;
                titleInput.value = ev.title;
            } else {
                idInput.value = '';
                titleInput.value = '';
            }
        }

        function closeModal() {
            overlay.style.display = modal.style.display = 'none';
        }

        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        form.addEventListener('submit', e => {
            e.preventDefault();
            const data = new URLSearchParams(new FormData(form));
            fetch('add_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: data
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        calendar.refetchEvents();
                        closeModal();
                    } else {
                        alert('Error: ' + json.message);
                    }
                })
                .catch(() => alert('Server error'));
        });
        const deleteBtn = document.getElementById('deleteBtn');

        // In openModal(), show/hide delete button:
        function openModal(dateStr, ev = null) {
            overlay.style.display = modal.style.display = 'block';
            dateInput.value = dateStr;
            if (ev) {
                idInput.value = ev.id;
                titleInput.value = ev.title;
                deleteBtn.style.display = 'inline-block';
            } else {
                idInput.value = '';
                titleInput.value = '';
                deleteBtn.style.display = 'none';
            }
        }

        // Handle delete click:
        deleteBtn.addEventListener('click', () => {
            if (!idInput.value) return;
            if (!confirm('Delete this event?')) return;

            fetch('delete_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        id: idInput.value
                    })
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        calendar.refetchEvents();
                        closeModal();
                    } else {
                        alert('Error deleting: ' + json.message);
                    }
                })
                .catch(() => alert('Server error'));
        });


        function updateDate(id, newDate) {
            fetch('update_event_date.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        id,
                        start_date: newDate
                    })
                })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) alert('Update failed');
                })
                .catch(() => alert('Server error'));
        }
    });
    </script>
</body>

</html>