INSERT INTO users (username, password, email, first_name, last_name, role)
VALUES ('admin1', 'admin123', 'admin1@example.com', 'John', 'Smith', 'admin')

INSERT INTO admins(user_id, first_name, last_name, email, telephone)
VALUES(LAST_INSERT_ID(), 'John', 'Smith', 'admin1@example.com', '07864358762')

SELECT * from users;

INSERT INTO Users (username, password, email, first_name, last_name, role)
VALUES ('admin1', '$2y$10$hashedpassword', 'admin1@example.com', 'John', 'Smith', 'admin');


UPDATE Users
SET password = '$2y$10$mo7Q.dIYMEA2AKyIBGHXn.X4hRksidd5q48tEGts7OQw/HrLMRt5W'
WHERE username = 'admin1';

SELECT * from chatmessages

SELECT cm.sender_id, cm.sender_role, cm.message, cm.created_at, u.first_name, u.last_name
FROM ChatMessages cm
JOIN Users u ON cm.sender_id = u.id
WHERE (cm.sender_id = 4 AND cm.receiver_id = 7)
   OR (cm.sender_id = 4 AND cm.receiver_id = 6)
ORDER BY cm.created_at ASC;

SELECT DISTINCT 
    CASE 
        WHEN sender_id = 7 THEN receiver_id 
        ELSE sender_id 
    END AS id,
    CASE 
        WHEN sender_id = 7 THEN receiver_role 
        ELSE sender_role 
    END AS role,
    u.first_name, u.last_name,
    MAX(created_at) AS latest_message,
    SUM(CASE WHEN receiver_id = 7 AND read_status = 0 THEN 1 ELSE 0 END) AS unread_count
FROM ChatMessages cm
JOIN Users u ON u.id = 
    CASE 
        WHEN sender_id = 7 THEN receiver_id 
        ELSE sender_id 
    END
WHERE (sender_id = 7 OR receiver_id = 7)
    AND CASE 
        WHEN sender_id = 7 THEN receiver_id != 7 
        ELSE sender_id != 7 
    END
GROUP BY id, role, u.first_name, u.last_name
ORDER BY latest_message DESC;

SELECT * from chatmessages

DESCRIBE Submitted_Assessments;

SELECT * FROM Submitted_Assessments;

SELECT * FROM users

DESCRIBE Homework;

SELECT s.*, u.first_name AS teacher_first_name, u.last_name AS teacher_last_name
FROM AppointmentSlots s
JOIN Users u ON s.teacher_id = u.id
LEFT JOIN BookedSlots b ON s.id = b.slot_id
WHERE b.slot_id IS NULL
ORDER BY s.date ASC, s.time ASC

SELECT * FROM announcementrecipients;

SELECT * FROM Announcements WHERE id = 2;

SELECT DATABASE();

SELECT * FROM Announcements;

SHOW TABLES

SELECT * from calendar_events;

INSERT INTO calendar_events (title, start_date) VALUES ('Test Event', '2025-05-09');

SELECT * from notifications

SELECT * from users;

SELECT * from students;

SHOW CREATE TABLE announcementrecipients;

DELETE FROM announcementreads WHERE user_id = 47;

DELETE FROM submitted_assessments WHERE student_id = 47;

DELETE from users WHere id = 116;

SELECT * from users;

Select * from uniquecodes;

SELECT 'Teachers' as table_name, COUNT(*) as count FROM Teachers WHERE user_id = 111;

SELECT * from parents;

SELECT * from parent_student;





