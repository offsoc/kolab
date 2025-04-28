<?php

namespace App\Policy;

use Illuminate\Http\JsonResponse;

class Response
{
    public const ACTION_DEFER_IF_PERMIT = 'DEFER_IF_PERMIT';
    public const ACTION_DUNNO = 'DUNNO';
    public const ACTION_HOLD = 'HOLD';
    public const ACTION_REJECT = 'REJECT';

    /** @var string Postfix action */
    public string $action = self::ACTION_DUNNO;

    /** @var string Optional response reason message */
    public string $reason = '';

    /** @var int HTTP response code */
    public int $code = 200;

    /** @var array<string> Log entries */
    public array $logs = [];

    /** @var array<string> Headers to prepend */
    public array $prepends = [];


    /**
     * Object constructor
     *
     * @param string $action Action to take on the Postfix side
     * @param string $reason Optional reason for the action
     * @param int    $code   HTTP response code
     */
    public function __construct($action = self::ACTION_DUNNO, $reason = '', $code = 200)
    {
        $this->action = $action;
        $this->reason = $reason;
        $this->code = $code;
    }

    /**
     * Convert this object into a JSON response
     */
    public function jsonResponse(): JsonResponse
    {
        $response = [
            'response' => $this->action,
        ];

        if ($this->reason) {
            $response['reason'] = $this->reason;
        }

        if (!empty($this->logs)) {
            $response['log'] = $this->logs;
        }

        if (!empty($this->prepends)) {
            $response['prepend'] = $this->prepends;
        }

        return response()->json($response, $this->code);
    }
}
