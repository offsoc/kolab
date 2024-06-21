<?php

namespace App\Fs;

use App\User;
use App\Traits\BelongsToUserTrait;
use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a filesystem item.
 *
 * @property string $id        Item identifier
 * @property int    $type      Item type
 * @property string $path      Item path (readonly)
 * @property int    $user_id   Item owner
 */
class Item extends Model
{
    use BelongsToUserTrait;
    use SoftDeletes;
    use UuidStrKeyTrait;

    public const TYPE_FILE       = 1;
    public const TYPE_COLLECTION = 2;
    public const TYPE_INCOMPLETE = 4;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['user_id', 'type'];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var string Database table name */
    protected $table = 'fs_items';


    /**
     * COntent chunks of this item (file).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function chunks()
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * Getter for the file path (without the filename) in the storage.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function path(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($this->id)) {
                    throw new \Exception("Cannot get path for an item without ID");
                }

                $id = substr($this->id, 0, 6);

                return implode('/', str_split($id, 2));
            }
        );
    }

    /**
     * Any (additional) properties of this item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Obtain the value for an item property
     *
     * @param string $key     Property name
     * @param mixed  $default Default value, to be used if not found
     *
     * @return string|null Property value
     */
    public function getProperty(string $key, $default = null)
    {
        $attr = $this->properties()->where('key', $key)->first();

        return $attr ? $attr->value : $default;
    }

    /**
     * Obtain the values for many properties in one go (for better performance).
     *
     * @param array $keys Property names
     *
     * @return array Property key=value hash, includes also requested but non-existing properties
     */
    public function getProperties(array $keys): array
    {
        $props = array_fill_keys($keys, null);

        $this->properties()->whereIn('key', $keys)->get()
            ->each(function ($prop) use (&$props) {
                $props[$prop->key] = $prop->value;
            });

        return $props;
    }

    /**
     * Remove a property
     *
     * @param string $key Property name
     */
    public function removeProperty(string $key): void
    {
        $this->setProperty($key, null);
    }

    /**
     * Create or update a property.
     *
     * @param string      $key   Property name
     * @param string|null $value The new value for the property
     */
    public function setProperty(string $key, $value): void
    {
        $this->storeProperty($key, $value);
    }

    /**
     * Create or update multiple properties in one fell swoop.
     *
     * @param array $data An associative array of key value pairs.
     */
    public function setProperties(array $data = []): void
    {
        foreach ($data as $key => $value) {
            $this->storeProperty($key, $value);
        }
    }

    /**
     * Create or update a property.
     *
     * @param string      $key   Property name
     * @param string|null $value The new value for the property
     */
    private function storeProperty(string $key, $value): void
    {
        if ($value === null || $value === '') {
            // Note: We're selecting the record first, so observers can act
            if ($prop = $this->properties()->where('key', $key)->first()) {
                $prop->delete();
            }
        } else {
            $this->properties()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * All relations for this item
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function relations()
    {
        return $this->hasMany(Relation::class);
    }

    /**
     * Child relations for this item
     *
     * Used to retrieve all items in a collection.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children()
    {
        return $this->belongsToMany(Item::class, 'fs_relations', 'item_id', 'related_id');
    }

    /**
     * Parent relations for this item
     *
     * Used to retrieve all collections of an item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function parents()
    {
        return $this->belongsToMany(Item::class, 'fs_relations', 'related_id', 'item_id');
    }
}
