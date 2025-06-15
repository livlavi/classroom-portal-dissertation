<?php
session_start();
require_once '../Global_PHP/db.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Get unread counts grouped by sender
    $stmt = $pdo->prepare("
        SELECT
            sender_id,
            COUNT(*) AS unread_count,
            MAX(created_at) AS last_message_time
        FROM ChatMessages
        WHERE receiver_id = :user_id AND read_status = 0
        GROUP BY sender_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $unreadCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get last message time from any message (sent or received) per user conversation
    $stmt2 = $pdo->prepare("
        SELECT other_user_id, MAX(last_msg) AS last_message_time FROM (
            SELECT
                CASE
                    WHEN sender_id = :user_id THEN receiver_id
                    ELSE sender_id
                END AS other_user_id,
                MAX(created_at) AS last_msg
            FROM ChatMessages
            WHERE sender_id = :user_id OR receiver_id = :user_id
            GROUP BY other_user_id
        ) AS conv
        GROUP BY other_user_id
    ");
    $stmt2->execute(['user_id' => $user_id]);
    $lastMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Map other_user_id => last_message_time for all conversations
    $lastMessageMap = [];
    foreach ($lastMessages as $row) {
        $lastMessageMap[$row['other_user_id']] = $row['last_message_time'];
    }

    // Merge unread counts with last_message_time (if no unread, last_message_time still from lastMessages)
    $countsMap = [];

    // First fill with unread counts (which include unread + last_message_time for unread messages)
    foreach ($unreadCounts as $row) {
        $countsMap[$row['sender_id']] = [
            'sender_id' => $row['sender_id'],
            'unread_count' => (int)$row['unread_count'],
            'last_message_time' => $row['last_message_time'],
        ];
    }

    // Add last_message_time for users without unread messages but with conversation
    foreach ($lastMessageMap as $otherUserId => $lastMsgTime) {
        if (!isset($countsMap[$otherUserId])) {
            $countsMap[$otherUserId] = [
                'sender_id' => $otherUserId,
                'unread_count' => 0,
                'last_message_time' => $lastMsgTime,
            ];
        } else {
            // If unread last_message_time is older than conv last_message_time, update it
            if ($countsMap[$otherUserId]['last_message_time'] < $lastMsgTime) {
                $countsMap[$otherUserId]['last_message_time'] = $lastMsgTime;
            }
        }
    }

    // Convert map to array
    $counts = array_values($countsMap);

    echo json_encode(['success' => true, 'counts' => $counts]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}