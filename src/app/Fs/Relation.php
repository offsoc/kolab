<?php

namespace App\Fs;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a filesystem relation.
 *
 * @property string $id         Relation identifier
 * @property string $item_id    Item identifier
 * @property string $related_id Related item identifier
 */
class Relation extends Model
{
    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['item_id', 'related_id'];

    /** @var string Database table name */
    protected $table = 'fs_relations';

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;
}
