<?php

namespace App\Fs;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of properties for a filesystem item.
 *
 * @property int    $id       Property identifier
 * @property int    $item_id  Item identifier
 * @property string $key      Attribute name
 * @property string $value    Attrbute value
 */
class Property extends Model
{
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['item_id', 'key', 'value'];

    /** @var string Database table name */
    protected $table = 'fs_properties';


    /**
     * The item to which this property belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Item, $this>
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
