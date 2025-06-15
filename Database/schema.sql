USE classroom_management

CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hashed password
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'teacher', 'parent', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to the Users table
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    teacher_number VARCHAR(20) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telephone VARCHAR(20),
    subject_taught VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to the Users table
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    parent_type ENUM('mother', 'father', 'guardian') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    home_address TEXT NOT NULL,
    telephone VARCHAR(20),
    child_full_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);


CREATE TABLE Students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to the Users table
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    mother_name VARCHAR(100),
    father_name VARCHAR(100),
    year_of_study INT NOT NULL,
    main_teacher VARCHAR(100),
    address TEXT NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Parent_Student (
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (parent_id, student_id),
    FOREIGN KEY (parent_id) REFERENCES Parents(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE UniqueCodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    role ENUM('teacher', 'parent', 'student') NOT NULL,
    email VARCHAR(100) NOT NULL, -- School email associated with the code
    first_name VARCHAR(50) NOT NULL, -- First name of the person
    last_name VARCHAR(50) NOT NULL, -- Last name of the person
    used BOOLEAN DEFAULT 0, -- Whether the code has been used
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to the Users table
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telephone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    target VARCHAR(255) NOT NULL, -- 'all', 'teacher', 'student', 'parent', 'user:1,user:2'
    sender_id INT, -- Who sent it (foreign key to Users)
    type VARCHAR(50) -- 'newsletter', 'announcement', 'password_reset', 'chat', 'submission'
);

ALTER TABLE Notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0;


CREATE TABLE Newsletters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    target VARCHAR(255) NOT NULL, -- 'all', 'teacher', 'student', 'parent', 'user:1,user:2'
    sender_id INT NOT NULL,
    status ENUM('draft', 'sent') DEFAULT 'draft',
    scheduled_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(id)
);


CREATE TABLE ProfilePhotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to the Users table
    photo_path VARCHAR(255) NOT NULL, -- Path to the uploaded photo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE ChatMessages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT DEFAULT NULL, -- Null if sending to a group
    receiver_role ENUM('teacher', 'student', 'parent') DEFAULT NULL, -- Role for group messages
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(id),
    FOREIGN KEY (receiver_id) REFERENCES Users(id)
);

ALTER TABLE ChatMessages ADD COLUMN read_status TINYINT DEFAULT 0;

ALTER TABLE ChatMessages ADD COLUMN sender_role ENUM('teacher', 'student', 'parent', 'admin') NOT NULL;

DESCRIBE ChatMessages

SELECT * FROM ChatMessages WHERE receiver_id = 4 AND read_status = 0;

CREATE TABLE Attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'excused') NOT NULL,
    recorded_by INT NOT NULL, -- Teacher ID who recorded the attendance
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Users(id),
    FOREIGN KEY (recorded_by) REFERENCES Users(id)
);

CREATE TABLE Homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    attachment VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES Users(id)
);

ALTER TABLE Homework
CHANGE COLUMN attachment attachment_path VARCHAR(255) NULL,
ADD COLUMN total_questions INT NOT NULL DEFAULT 0;

CREATE TABLE Homework_Students (
    homework_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (homework_id, student_id),
    FOREIGN KEY (homework_id) REFERENCES Homework(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Submitted_Homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    homework_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_content TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submission_attachment VARCHAR(255),
    status ENUM('pending', 'reviewed', 'rejected') DEFAULT 'pending',
    correct_answers INT,
    percentage DECIMAL(5, 2),
    feedback TEXT,
    corrected_submission TEXT,
    FOREIGN KEY (homework_id) REFERENCES Homework(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date DATE NOT NULL,
    attachment VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES Users(id)
);

CREATE TABLE Submitted_Assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_content TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submission_attachment VARCHAR(255),
    status ENUM('pending', 'reviewed', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (assessment_id) REFERENCES Assessments(id),
    FOREIGN KEY (student_id) REFERENCES Users(id)
);

ALTER TABLE Submitted_Assessments
ADD COLUMN grade VARCHAR(10),
ADD COLUMN feedback TEXT;

CREATE TABLE Assessment_Students (
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (assessment_id, student_id),
    FOREIGN KEY (assessment_id) REFERENCES Assessments(id),
    FOREIGN KEY (student_id) REFERENCES Users(id)
);

CREATE TABLE Grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    subject VARCHAR(100),
    grade DECIMAL(5, 2),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Users(id),
    FOREIGN KEY (teacher_id) REFERENCES Users(id)
);

CREATE TABLE AppointmentSlots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    type ENUM('online', 'in-person') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES Users(id)
);

CREATE TABLE BookedSlots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES AppointmentSlots(id),
    FOREIGN KEY (parent_id) REFERENCES Users(id),
    FOREIGN KEY (student_id) REFERENCES Users(id)
);

CREATE TABLE Announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE AnnouncementRecipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    recipient_id INT NOT NULL,
    FOREIGN KEY (announcement_id) REFERENCES Announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES Users(id)
);

CREATE TABLE AnnouncementReads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (announcement_id, user_id),
    FOREIGN KEY (announcement_id) REFERENCES Announcements(id),
    FOREIGN KEY (user_id) REFERENCES Users(id)
);

CREATE TABLE IF NOT EXISTS calendar_events (
	id INT AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(255) NOT NULL,
	start_date DATE NOT NULL
);

CREATE TABLE StudentCalendarEvents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  student_id INT NOT NULL,
  term VARCHAR(10) NOT NULL,
  overall_grade VARCHAR(10),
  comments TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES Users(id),
  FOREIGN KEY (student_id) REFERENCES Users(id)
);


SELECT * from users

INSERT INTO UniqueCodes (code, role, email, first_name, last_name, used)
VALUES ('adbce1d1', 'teacher', 'allegra@example.com', 'Allegra', 'Kou', 0);

