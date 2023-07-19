<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Dyrynda\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of an EventLog record.
 *
 * @property ?string $comment     Optional event description
 * @property ?array  $data        Optional event data
 * @property string  $id          Log record identifier
 * @property string  $object_id   Object identifier
 * @property string  $object_type Object type (class)
 * @property int     $type        Event type (0-255)
 * @property ?string $user_email  Acting user email
 */
class EventLog extends Model
{
    use NullableFields;
    use UuidStrKeyTrait;

    public const TYPE_SUSPENDED = 1;
    public const TYPE_UNSUSPENDED = 2;
    public const TYPE_COMMENT = 3;
    public const TYPE_MAILSENT = 4;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'comment',
        // extra event info (json)
        'data',
        'type',
        // user, domain, etc.
        'object_id',
        'object_type',
        // actor, if any
        'user_email',
    ];

    /** @var array<string, string> Casts properties as type */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'data' => 'array',
        'type' => 'integer',
    ];

    /** @var array<int, string> The attributes that can be not set */
    protected $nullable = ['comment', 'data', 'user_email'];

    /** @var string Database table name */
    protected $table = 'eventlog';

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;


    /**
     * Create an eventlog object for a specified object.
     *
     * @param object  $object  Object (User, Domain, etc.)
     * @param int     $type    Event type (use one of EventLog::TYPE_* consts)
     * @param ?string $comment Event description
     * @param ?array  $data    Extra information
     *
     * @return EventLog
     */
    public static function createFor($object, int $type, string $comment = null, array $data = null): EventLog
    {
        $event = self::create([
                'object_id' => $object->id,
                'object_type' => get_class($object),
                'type' => $type,
                'comment' => $comment,
                'data' => $data,
        ]);

        return $event;
    }

    /**
     * Principally an object such as Domain, User, Group.
     * Note that it may be trashed (soft-deleted).
     *
     * @return mixed
     */
    public function object()
    {
        return $this->morphTo()->withTrashed(); // @phpstan-ignore-line
    }

    /**
     * Get an event type name.
     *
     * @return ?string Event type name
     */
    public function eventName(): ?string
    {
        switch ($this->type) {
        case self::TYPE_SUSPENDED:
            return \trans('app.event-suspended');
        case self::TYPE_UNSUSPENDED:
            return \trans('app.event-unsuspended');
        case self::TYPE_COMMENT:
            return \trans('app.event-comment');
        case self::TYPE_MAILSENT:
            return \trans('app.event-mailsent');
        default:
            return null;
        }
    }

    /**
     * Event type mutator
     *
     * @throws \Exception
     */
    public function setTypeAttribute($type)
    {
        if (!is_numeric($type)) {
            throw new \Exception("Expecting an event type to be numeric");
        }

        $type = (int) $type;

        if ($type < 0 || $type > 255) {
            throw new \Exception("Expecting an event type between 0 and 255");
        }

        $this->attributes['type'] = $type;
    }
}
