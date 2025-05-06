<?php

namespace App\Fs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a file chunk.
 *
 * @property int    $id       Chunk identifier
 * @property string $chunk_id Chunk long identifier (storage file name)
 * @property string $item_id  Item identifier
 * @property int    $sequence Chunk sequence number
 * @property int    $size     Chunk size
 */
class Chunk extends Model
{
    use SoftDeletes;

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['item_id', 'chunk_id', 'sequence', 'deleted_at', 'size'];

    /** @var string Database table name */
    protected $table = 'fs_chunks';

    /**
     * The item (file) the chunk belongs to.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
