<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\MessageServiceTest.php

use PHPUnit\Framework\TestCase;
use App\MessageService; // Import the service class you're testing

class MessageServiceTest extends TestCase
{
    private $messageService;

    protected function setUp(): void
    {
        $this->messageService = new MessageService();
    }

    // --- Test Cases for sendMessage ---

    public function testCanSendMessageWithValidData()
    {
        $this->assertTrue($this->messageService->sendMessage(1, 2, 'This is a test message.'));
    }

    public function testCannotSendMessageWithEmptyContent()
    {
        $this->assertFalse($this->messageService->sendMessage(1, 2, ''));
        $this->assertFalse($this->messageService->sendMessage(1, 2, '   ')); // Whitespace only
    }

    public function testCannotSendMessageWithInvalidSenderOrReceiverId()
    {
        $this->assertFalse($this->messageService->sendMessage(0, 2, 'Message'));
        $this->assertFalse($this->messageService->sendMessage(1, 0, 'Message'));
    }

    public function testCannotSendMessageToSelf()
    {
        $this->assertFalse($this->messageService->sendMessage(1, 1, 'Self message'));
    }

    public function testCannotSendMessageTooLongContent()
    {
        $longMessage = str_repeat('a', 501); // Assuming max 500 characters
        $this->assertFalse($this->messageService->sendMessage(1, 2, $longMessage));
    }

    // --- Test Cases for getConversation ---

    public function testGetConversationReturnsCorrectMessages()
    {
        $conversation = $this->messageService->getConversation(1, 2);
        $this->assertCount(3, $conversation);
        $this->assertEquals('Hello!', $conversation[0]['content']);
        $this->assertEquals('Hi there!', $conversation[1]['content']);
        $this->assertEquals(1, $conversation[0]['sender_id']);
        $this->assertEquals(2, $conversation[1]['sender_id']);
    }

    public function testGetConversationReturnsEmptyArrayForNoMessages()
    {
        $conversation = $this->messageService->getConversation(10, 20); // Users with no conversation
        $this->assertEmpty($conversation);
    }

    public function testGetConversationIsBidirectional()
    {
        // Should return the same conversation regardless of user order
        $conversation1 = $this->messageService->getConversation(1, 2);
        $conversation2 = $this->messageService->getConversation(2, 1);
        $this->assertEquals($conversation1, $conversation2);
    }

    // --- Test Cases for markMessageAsRead ---

    public function testCanMarkMessageAsRead()
    {
        $this->assertTrue($this->messageService->markMessageAsRead(1, 2)); // Message 1 read by user 2
    }

    public function testCannotMarkMessageAsReadWithInvalidId()
    {
        $this->assertFalse($this->messageService->markMessageAsRead(0, 2));
        $this->assertFalse($this->messageService->markMessageAsRead(999, 2)); // Non-existent message
    }

    public function testCannotMarkMessageAsReadWithInvalidReaderId()
    {
        $this->assertFalse($this->messageService->markMessageAsRead(1, 0));
    }

    // You might add tests here to verify read status after marking
    // (This would require a more sophisticated mock or integration test with a DB)
}
