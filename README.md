# AllConnectEdu

**AllConnectEdu** is a full-featured classroom management platform designed to connect teachers, students, and parents in one integrated system. Built with PHP (8.2.12), MariaDB, JavaScript, Bootstrap 4.6, and AJAX, this system provides essential tools for teaching, communication, reporting, and parental engagement.

---

## 🔧 Technologies Used

- PHP 8.2.12 (tested on XAMPP)
- MariaDB 10.4.32 (Database: `classroom_management`)
- JavaScript + AJAX
- HTML5 / CSS3
- Bootstrap 4.6

---

## 🚀 Getting Started

Follow these steps to set up and run AllConnectEdu on your local machine.

### Prerequisites

* A local web server environment like XAMPP (Windows/macOS/Linux), WAMP (Windows), or MAMP (macOS). XAMPP is recommended due to its bundled PHP and MariaDB.
* A modern web browser.

  
### Installation Steps

1.  **Clone or Download the Repository:**

    git clone: https://github.com/livlavi/classroom-portal-dissertation.git
    # Or, download the project ZIP file and extract it.
    

2.  **Place Project in Web Server Root:**
    Move the entire `AllConnectEdu` folder into your web server's document root directory.
    * **XAMPP (Windows):** `C:\xampp\htdocs\`
    * **XAMPP (macOS):** `/Applications/XAMPP/htdocs/`
    * (Adjust path if you're using WAMP, MAMP, or a different XAMPP installation path).

3.  **Database Setup:**
    * **Start your Apache and MySQL/MariaDB services** from your XAMPP/WAMP/MAMP control panel.
    * Open your database administration tool (e.g., **phpMyAdmin**, usually accessible via `http://localhost/phpmyadmin/` in your browser).
    * **Create a new database** named `classroom_management`.
    * **Import the database schema:** You will need a `database.sql` file (not included in the provided structure, but crucial for setup). Import this file into the `classroom_management` database using phpMyAdmin's "Import" tab or a command-line tool.

4.  **Configure Database Connection:**
    * Open `Global_PHP/db.php`.
    * Ensure the database connection details (host, database name, username, password) match your MariaDB/MySQL setup. For most XAMPP installations, the default is `username: root` and `password: (empty string)`.

### Accessing the Application

After completing the installation steps and starting your web server (e.g., Apache via XAMPP), open your web browser and navigate to:

http://localhost[:PORT]/Classroom%20Portal%20Dissertation/index.php

- Replace `[:PORT]` with your custom port number if you're not using the default.  
  - For example: `http://localhost:3000/Classroom%20Portal%20Dissertation/index.php`  
- If you're using the default Apache setup (usually port 80), you can skip the port and simply go to:  
  `http://localhost/Classroom%20Portal%20Dissertation/index.php`

This will take you to the main page of the application.



## 👥 Roles & Features

### 🛡️ **Admin**
The administrator is responsible for system-wide management and user provisioning.
* **User Management:** Add new users by full name and email. The system generates a unique registration code for each new user.
* **Registration Flow:** Users register themselves using the unique code, setting their own username, password, and personal details.
* **Communication:** Post global announcements and newsletters to all users.

### 🧑‍🏫 **Teachers**
Teachers manage their classes, assignments, and student interactions.
* **Academics:** Post and review homework assignments and assessments.
* **Grading & Feedback:** Grade submitted work and provide detailed feedback to students.
* **Attendance:** Take and manage daily and class-based attendance records.
* **Communication:** Send class-wide or individual announcements to students and parents. Engage in internal chat with students and parents.

### 🧑‍🎓 **Students**
Students primarily interact with their assignments, grades, and school communications.
* **Submissions:** Submit homework and assessments before their respective deadlines.
* **Progress Tracking:** View feedback, grades, and attendance reports.
* **Information Access:** Access school announcements and newsletters.
* **Personal Organization:** Maintain a personal calendar with custom notes and events.
* **Communication:** Use internal chat to communicate with teachers and classmates only.

### 👨‍👩‍👧 **Parents**
Parents can actively monitor their child's academic progress and engage with the school.
* **Child Monitoring:** Monitor their child's homework reviews, assessment feedback, grades, and attendance records.
* **School Engagement:** View the school calendar and schedule parent-teacher meetings (both in-person and online).
* **Alerts:** Receive automated alerts if their child's attendance drops below 80%.
* **Communication:** Chat with teachers and administrators.

---

## 💡 Future Enhancements 

* Implement a real-time notification system for new messages, assignments, and announcements.
* Introduce a dedicated module for creating and managing courses/classes.
* Expand chat functionality to include group chats for specific classes or topics.
* Add analytics and reporting features for teachers and administrators (e.g., student performance trends).

---

## ⚖️ License 

This project is licensed under the [MIT License](LICENSE.md) - see the `LICENSE.md` file for details.
