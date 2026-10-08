<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Webkul\Core\Models\ChannelProxy;
use Webkul\Customer\Models\CustomerProxy;
use Webkul\MarketingAutomation\Contracts\Contact as ContactContract;
use Webkul\MarketingAutomation\Database\Factories\ContactFactory;

class Contact extends Model implements ContactContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_contacts';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Assign a UUID when the record is first created.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'last_order_at' => 'datetime',
        ];
    }

    /**
     * Get the related records.
     */
    public function consents()
    {
        return $this->hasMany(ConsentProxy::modelClass());
    }

    /**
     * Get the consent history.
     */
    public function consentEvents()
    {
        return $this->hasMany(ConsentEventProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function enrolments()
    {
        return $this->hasMany(EnrolmentProxy::modelClass());
    }

    /**
     * Get the parent record.
     */
    public function customer()
    {
        return $this->belongsTo(CustomerProxy::modelClass());
    }

    /**
     * Get the parent record.
     */
    public function channel()
    {
        return $this->belongsTo(ChannelProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
