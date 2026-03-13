<?php

namespace App\Service\MsgCheck;

use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;


class MsgCheckResponseHandler
{
    private const checkRescheduleDelaysInMins = [1, 2, 5];
    private const messageResendDelaysInMins = [2, 5, 10];

    private const STATUS_HISTORY_SEPARATOR = ',';

    public function __construct(
    ) {
    }

    public function determineNextStep(NotificationAttempt $attempt, MsgCheckResponse $msgResponse): MsgCheckNextStep
    {
        $result = new MsgCheckNextStep($attempt);

        if ($this->isDelivered($msgResponse)) {
            $result->newStatus = NotificationAttemptStatus::Delivered;
            $result->type = MsgCheckNextStepType::MarkDelivered;

        } else if ($this->isFailed($msgResponse)) {
            $result->newStatus = NotificationAttemptStatus::Failed;

            $attemptNo = $attempt->attemptNo;
            if ($attemptNo > count(self::messageResendDelaysInMins)) {
                $result->type = MsgCheckNextStepType::SendEmail;
            } else {
                $result->type = MsgCheckNextStepType::ResendMessage;
                $result->resendDelayInMin = self::messageResendDelaysInMins[$attemptNo - 1];
            }

        } else {
            $checkNo = $this->determineCheckNo($attempt);
            if ($checkNo > count(self::checkRescheduleDelaysInMins)) {
                $result->newStatus = NotificationAttemptStatus::Failed;
                $result->type = MsgCheckNextStepType::SendEmail;

            } else {
                $result->newStatus = NotificationAttemptStatus::Queued;
                $result->type = MsgCheckNextStepType::RescheduleCheck;
                $result->recheckDelayInMin = self::checkRescheduleDelaysInMins[$checkNo - 1];
            }
        }

        $result->newGwCheckStatusHistory = $this->appendStatusHistory($attempt->gwCheckStatusHistory, $msgResponse->status);
        return $result;
    }

    private function appendStatusHistory(?string $history, string $status): string
    {
        $history = $history ? $history . self::STATUS_HISTORY_SEPARATOR : '';
        $history .= $status;
        return $history;
    }

    private function isDelivered(MsgCheckResponse $msgResponse): bool
    {
        return in_array($msgResponse->status, MsgCheckResponseStatus::DeliveredStatues);
    }

    private function isFailed(MsgCheckResponse $msgResponse): bool
    {
        return in_array($msgResponse->status, MsgCheckResponseStatus::FailedStatues);
    }

    private function determineCheckNo(NotificationAttempt $attempt): int
    {
        if (!$attempt->gwCheckStatusHistory)
            return 1;

        // Count number of comma-separated commands in gwCheckStatusHistory and add 1
        $commands = explode(self::STATUS_HISTORY_SEPARATOR, $attempt->gwCheckStatusHistory);
        return 1 + count($commands);
    }

}
