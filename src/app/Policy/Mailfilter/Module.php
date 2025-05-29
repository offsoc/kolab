<?php

namespace App\Policy\Mailfilter;

abstract class Module
{
    /** @var array Module configuration */
    protected array $config = [];

    /**
     * Module constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Handle the email message
     */
    abstract public function handle(MailParser $parser): ?Result;
}
