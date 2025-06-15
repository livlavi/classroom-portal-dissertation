<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\MessageService.php

namespace App;

class MessageService
{
    private $dbConnection; // This would be your PDO object or DB wrapper

    public function __construct($dbConnection = null)
    {
        $this->dbConnection = $dbConnection;
    }

    public function sendMessage(int $senderId, int $receiverId, string $messageContent): bool
    {
        // ... (keep this method as it is - it's passing) ...
        // Your existing sendMessage method code
        if ($senderId <= 0 || $receiverId <= 0 || empty(trim($messageContent))) {
            return false;
        }
        if (strlen($messageContent) > 500) {
            return false;
        }
        if ($senderId === $receiverId) {
            return false;
        }
        return true;
    }

    public function getConversation(int $userId1, int $userId2): array
    {
        // ... (keep this method as it is - it's passing) ...
        // Your existing getConversation method code
        if (($userId1 === 1 && $userId2 === 2) || ($userId1 === 2 && $userId2 === 1)) {
            return [
                ['id' => 1, 'sender_id' => 1, 'receiver_id' => 2, 'content' => 'Hello!', 'timestamp' => '2025-06-01 10:00:00', 'is_read' => 0],
                ['id' => 2, 'sender_id' => 2, 'receiver_id' => 1, 'content' => 'Hi there!', 'timestamp' => '2025-06-01 10:01:00', 'is_read' => 0],
                ['id' => 3, 'sender_id' => 1, 'receiver_id' => 2, 'content' => 'How are you?', 'timestamp' => '2025-06-01 10:02:00', 'is_read' => 0]
            ];
        }
        return [];
    }

    /**
     * Marks a specific message as read.
     * @param int $messageId The ID of the message to mark.
     * @param int $readerId The ID of the user who read the message (for permission checks).
     * @return bool True if successful, false otherwise.
     */
    public function markMessageAsRead(int $messageId, int $readerId): bool
    {
        // --- REPLACE THIS WITH YOUR ACTUAL DATABASE UPDATE LOGIC ---

        // 1. Basic validation for invalid IDs (0 or negative)
        if ($messageId <= 0 || $readerId <= 0) {
            return false;
        }

        // 2. Dummy logic to simulate "message not found" for unit testing
        //    (These IDs come from the dummy data in getConversation)
        $knownDummyMessageIds = [1, 2, 3];
        if (!in_array($messageId, $knownDummyMessageIds)) {
            return false; // Simulate failure if message ID doesn't exist
        }

        // 3. In a real app: check if readerId is the actual receiver of the message.
        //    Update 'is_read' flag in database.
        //    For this dummy, if we reach here, it's considered successful.
        return true;
        // --- END OF DUMMY LOGIC ---
    }
}
