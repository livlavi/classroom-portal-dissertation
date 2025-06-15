<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/getting_informations.php';

// Check if the user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$childId = null;
$childName = 'No child associated';
$slots = [];

// Fetch parent details
try {
    $userDetails = fetchUserDetails($pdo, $_SESSION['user_id'], 'parent');

    if ($userDetails['success']) {
        $firstName = $userDetails['first_name'] ?? '';
        $lastName = $userDetails['last_name'] ?? '';
        $childFullName = $userDetails['child_full_name'] ?? 'No child associated';

        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;

        if ($childFullName !== 'No child associated') {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM Users WHERE CONCAT(first_name, ' ', last_name) = :child_full_name AND role = 'student'");
            $stmt->execute(['child_full_name' => trim($childFullName)]);
            $childUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($childUser) {
                $childId = $childUser['id'];
                $childName = $childUser['first_name'] . ' ' . $childUser['last_name'];
            }
        }
    }

    // Fetch profile photo
    $stmt = $pdo->prepare("SELECT photo_path FROM ProfilePhotos WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $profilePhoto = $stmt->fetchColumn() ?: 'default_profile.jpg';

    // Fetch available slots using the provided query
    if ($childId) {
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name AS teacher_first_name, u.last_name AS teacher_last_name
            FROM AppointmentSlots s
            JOIN Users u ON s.teacher_id = u.id
            LEFT JOIN BookedSlots b ON s.id = b.slot_id
            WHERE b.slot_id IS NULL
            ORDER BY s.date ASC, s.time ASC
        ");
        $stmt->execute();
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching parent or child details: " . $e->getMessage());
    $firstName = '';
    $lastName = '';
    $profilePhoto = 'default_profile.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent View Calendar</title>
    <link rel="stylesheet" href="../CSS/parent_dashboard.css">
</head>

<body class="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : '' ?>">
    <header class="top-bar">
        <nav class="profile-actions">
            <a href="#" id="dark-mode-toggle">Dark Mode</a>
            <a href="../../Global_PHP/profile.php">Profile</a>
            <a href="../../Global_PHP/logout.php">Logout</a>
        </nav>
        <div class="admin-profile">
            <img src="../../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <p><?= htmlspecialchars("$firstName $lastName") ?> (Parent of <?= htmlspecialchars($childName) ?>)</p>
        </div>
    </header>

    <aside class="sidebar">
        <h2>Parent Dashboard</h2>
        <ul>
            <li><a href="parent_dashboard.php">Back to Dashboard</a></li>
            <li><a href="#calendar">Available Slots</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <section id="calendar" class="section">
            <h2>Available Slots</h2>

            <?php if (!empty($slots)): ?>
            <div class="slots-container">
                <?php foreach ($slots as $slot): ?>
                <div class="slot-item">
                    <span>
                        <?= htmlspecialchars($slot['date']) ?> at <?= htmlspecialchars($slot['time']) ?>
                        (<?= htmlspecialchars($slot['teacher_first_name']) ?>
                        <?= htmlspecialchars($slot['teacher_last_name']) ?>)
                    </span>
                    <form action="book_slot.php" method="POST" style="display: inline;">
                        <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                        <input type="hidden" name="child_id" value="<?= $childId ?>">
                        <button type="submit"
                            onclick="return confirm('Are you sure you want to book this slot?');">Book</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p>No available slots at the moment.</p>
            <?php endif; ?>
        </section>
    </main>

    <script src="../JavaScript/parent_dashboard.js"></script>
    <script>
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains('dark-mode')}; path=/`;
    });
    </script>
</body>

</html>