<?php

namespace Webkul\Product\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Shetabit\Visitor\Traits\Visitable;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Webkul\Attribute\Models\AttributeFamilyProxy;
use Webkul\Attribute\Models\AttributeProxy;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\BookingProduct\Models\BookingProductProxy;
use Webkul\CatalogRule\Models\CatalogRuleProductPriceProxy;
use Webkul\Category\Models\CategoryProxy;
use Webkul\Core\Models\ChannelProxy;
use Webkul\Inventory\Models\InventorySourceProxy;
use Webkul\Product\Contracts\Product as ProductContract;
use Webkul\Product\Database\Factories\ProductFactory;
use Webkul\Product\Type\AbstractType;

class Product extends Model implements ProductContract
{
    use HasFactory, Visitable, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type',
        'attribute_family_id',
        'sku',
        'parent_id',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'additional' => 'array',
    ];

    /**
     * The type of product.
     *
     * @var \Webkul\Product\Type\AbstractType
     */
    protected $typeInstance;

    /**
     * Get the product flat entries that are associated with product.
     * May be one for each locale and each channel.
     */
    public function product_flats(): HasMany
    {
        return $this->hasMany(ProductFlatProxy::modelClass());
    }

    /**
     * Get the categories that belong to the product.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CategoryProxy::modelClass(), "product_categories");
    }

    /**
     * The images that belong to the product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImageProxy::modelClass(), "product_id")->orderBy("position");
    }

    /**
     * The videos that belong to the product.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideoProxy::modelClass());
    }

    /**
     * Get the bundle options that owns the product.
     */
    public function bundle_options(): HasMany
    {
        return $this->hasMany(ProductBundleOptionProxy::modelClass());
    }

    /**
     * The related products that belong to the product.
     */
    public function related_products(): BelongsToMany
    {
        return $this->belongsToMany(static::class, "product_relations", "parent_id", "child_id");
    }

    /**
     * The up sells that belong to the product.
     */
    public function up_sells(): BelongsToMany
    {
        return $this->belongsToMany(static::class, "product_up_sells", "parent_id", "child_id");
    }

    /**
     * The cross sells that belong to the product.
     */
    public function cross_sells(): BelongsToMany
    {
        return $this->belongsToMany(static::class, "product_cross_sells", "parent_id", "child_id");
    }

    /**
     * Get custom attribute value for an attribute instance.
     */
    public function getCustomAttributeValue($attribute)
    {
        $locale = core()->getRequestedLocaleCode();
        $channel = core()->getRequestedChannelCode();

        if ($attribute->value_per_channel) {
            if ($attribute->value_per_locale) {
                $attributeValue = $this->attribute_values
                    ->where('channel', $channel)
                    ->where('locale', $locale)
                    ->where('attribute_id', $attribute->id)
                    ->first();

                if (empty($attributeValue[$attribute->column_name])) {
                    $attributeValue = $this->attribute_values
                        ->where('channel', core()->getDefaultChannelCode())
                        ->where('locale', core()->getDefaultLocaleCodeFromDefaultChannel())
                        ->where('attribute_id', $attribute->id)
                        ->first();
                }
            } else {
                $attributeValue = $this->attribute_values
                    ->where('channel', $channel)
                    ->where('attribute_id', $attribute->id)
                    ->first();
            }
        } else {
            if ($attribute->value_per_locale) {
                $attributeValue = $this->attribute_values
                    ->where('locale', $locale)
                    ->where('attribute_id', $attribute->id)
                    ->first();

                if (empty($attributeValue[$attribute->column_name])) {
                    $attributeValue = $this->attribute_values
                        ->where('locale', core()->getDefaultLocaleCodeFromDefaultChannel())
                        ->where('attribute_id', $attribute->id)
                        ->first();
                }
            } else {
                $attributeValue = $this->attribute_values
                    ->where('attribute_id', $attribute->id)
                    ->first();
            }
        }

        return $attributeValue[$attribute->column_name] ?? $attribute->default_value;
    }

    /**
     * Attributes to array.
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        $hiddenAttributes = $this->getHidden();

        if (isset($this->id)) {
            $familyAttributes = $this->checkInLoadedFamilyAttributes();

            foreach ($familyAttributes as $attribute) {
                if (in_array($attribute->code, $hiddenAttributes)) {
                    continue;
                }

                $attributes[$attribute->code] = $this->getCustomAttributeValue($attribute);
            }
        }

        return $attributes;
    }

    /**
     * Check in loaded family attributes.
     */
    public function checkInLoadedFamilyAttributes(): object
    {
        return core()->getSingletonInstance(AttributeRepository::class)
            ->getFamilyAttributes($this->attribute_family);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}

