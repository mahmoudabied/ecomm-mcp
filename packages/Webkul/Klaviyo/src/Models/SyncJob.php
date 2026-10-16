<?php

namespace Webkul\Klaviyo\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Klaviyo\Contracts\SyncJob as SyncJobContract;
use Webkul\Klaviyo\Database\Factories\SyncJobFactory;

class SyncJob extends Model implements SyncJobContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'klaviyo_sync_jobs';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function connection()
    {
        return $this->belongsTo(ConnectionProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SyncJobFactory::new();
    }
}
