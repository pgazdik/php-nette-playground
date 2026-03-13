<?php

namespace Tests\Service;

use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Service\MsgCheck\MsgCheckNextStepType;
use App\Service\MsgCheck\MsgCheckResponse;
use App\Service\MsgCheck\MsgCheckResponseHandler;
use App\Service\MsgCheck\MsgCheckResponseStatus;
use PHPUnit\Framework\TestCase;

class MsgCheckResponseHandlerTest extends TestCase
{
    private MsgCheckResponseHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new MsgCheckResponseHandler();
    }

    public function testNextStep_Delivered()
    {
        $attempt = $this->createAttempt(1, NotificationAttemptStatus::Sent);
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::DeliveryOk);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Delivered, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::MarkDelivered, $nextStep->type);
        $this->assertEquals(MsgCheckResponseStatus::DeliveryOk, $nextStep->newGwCheckStatusHistory);
    }

    public function testNextStep_Failed_Once_Resend()
    {
        $attempt = $this->createAttempt(1, NotificationAttemptStatus::Sent);
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::SendingError);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Failed, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::ResendMessage, $nextStep->type);
        $this->assertEquals(2, $nextStep->resendDelayInMin);
        $this->assertEquals(MsgCheckResponseStatus::SendingError, $nextStep->newGwCheckStatusHistory);
    }

    public function testNextStep_Failed_FourTimes_SendEmail()
    {
        // messageResendDelaysInMins count is 3, so 4th attempt should trigger SendEmail
        $attempt = $this->createAttempt(4, NotificationAttemptStatus::Sent);
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::Error);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Failed, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::SendEmail, $nextStep->type);
        $this->assertEquals(MsgCheckResponseStatus::Error, $nextStep->newGwCheckStatusHistory);
    }

    public function testNextStep_Reserved_Once_Reschedule()
    {
        $attempt = $this->createAttempt(1, NotificationAttemptStatus::Sent);
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::Reserved);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Queued, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::RescheduleCheck, $nextStep->type);
        $this->assertEquals(1, $nextStep->recheckDelayInMin);
        $this->assertEquals(MsgCheckResponseStatus::Reserved, $nextStep->newGwCheckStatusHistory);
    }

    public function testNextStep_Reserved_ThreeTimes_Reschedule()
    {
        $attempt = $this->createAttempt(1, NotificationAttemptStatus::Queued);
        $attempt->gwCheckStatusHistory = MsgCheckResponseStatus::Reserved . ',' . MsgCheckResponseStatus::Reserved;
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::Reserved);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Queued, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::RescheduleCheck, $nextStep->type);
        $this->assertEquals(5, $nextStep->recheckDelayInMin);
    }

    public function testNextStep_Reserved_FourTimes_SendEmail()
    {
        // checkRescheduleDelaysInMins count is 3, so 4th check (3 history items) should trigger SendEmail
        $attempt = $this->createAttempt(1, NotificationAttemptStatus::Queued);
        $attempt->gwCheckStatusHistory = 'r1,r2,r3';
        $response = $this->createMsgCheckResponse(MsgCheckResponseStatus::Reserved);

        $nextStep = $this->handler->determineNextStep($attempt, $response);

        $this->assertEquals(NotificationAttemptStatus::Failed, $nextStep->newStatus);
        $this->assertEquals(MsgCheckNextStepType::SendEmail, $nextStep->type);
        $this->assertEquals('r1,r2,r3,reserved', $nextStep->newGwCheckStatusHistory);
    }

    private function createAttempt(int $attemptNo, NotificationAttemptStatus $status): NotificationAttempt
    {
        return new NotificationAttempt(notificationMsgId: 1, attemptNo: $attemptNo, status: $status);
    }

    private function createMsgCheckResponse(string $status): MsgCheckResponse
    {
        return new MsgCheckResponse(status: $status, errorCode: null, sendingDate: null, deliveryDate: null);
    }

}
