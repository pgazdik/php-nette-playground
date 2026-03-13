<?php

namespace App\Service\MsgCheck;

class MsgCheckResponseStatus
{
    const SendingOkNoReport = 'sending_ok_no_report';
    const SendingOk = 'sending_ok';
    const DeliveryOk = 'delivery_ok';
    const DeliveryPending = 'delivery_pending';
    const DeliveryUnknown = 'delivery_unknown';
    const DeliveryFailed = 'delivery_failed';
    const SendingError = 'sending_error';
    const Error = 'error';
    const Reserved = 'reserved';


    const DeliveredStatues = [
        self::SendingOkNoReport,
        self::SendingOk,
        self::DeliveryOk,
        self::DeliveryPending,
        self::DeliveryUnknown,
        self::DeliveryFailed
    ];

    const FailedStatues = [
        self::SendingError,
        self::Error,
    ];

}
