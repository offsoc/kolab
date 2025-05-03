<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Contact (in the global addressbook).
 *
 * @property int    $id       The contact identifier
 * @property string $email    The contact email address
 * @property string $name     The contact (display) name
 * @property int    $user_id  The contact owner
 */
class Contact extends Model
{
    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'email',
        'name',
        'user_id',
    ];

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /**
     * Ensure the email is appropriately cased.
     *
     * @param string $email Email address
     */
    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower($email);
    }
}
