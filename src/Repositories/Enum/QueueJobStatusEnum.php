<?php

namespace App\Repositories\Enum;

class QueueJobStatusEnum
{
    public const string CREATED = 'CREATED';
    public const string IN_QUEUE = 'IN_QUEUE';
    public const string IN_FLIGHT = 'IN_FLIGHT';
    public const string COMPLETED = 'COMPLETED';
    public const string TIMED_OUT = 'TIMED_OUT';
    public const string FAILED = 'FAILED';
}