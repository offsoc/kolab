<?php

namespace App\Policy\Mailfilter;

class Result
{
    public const STATUS_ACCEPT = 'ok';
    public const STATUS_REJECT = 'reject';
    public const STATUS_DISCARD = 'discard';

    protected $status;

    /**
     * Class constructor.
     *
     * @param ?string $status Delivery status
     */
    public function __construct(?string $status = null)
    {
        $this->status = $status ?: self::STATUS_ACCEPT;
    }

    /**
     * Return the status
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
