<?php

namespace App\Livewire\Backoffice\Products;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Product;
use App\Services\CurrencyService;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Backoffice → Catalog → Catalog Products: the products the storefront sells besides the eSIM plans.
 *
 * An admin creates an item here — image, name, tagline, description, price, features, specs and a
 * badge — publishes it, and it appears on the product pages next to the eSIM packages. Nothing on
 * this page is a storefront detail the code knows about: what lands in the table is exactly what the
 * cards render.
 *
 * Security: writes go through authorizeWrite() (support admins can look, not change), uploads are
 * validated as images and re-encoded by ImageCompressionService before being stored, and the slug is
 * generated (never taken raw from the form) so it can never collide with an eSIM plan's slug.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Catalog Products — Backoffice')]
class ListProducts extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $tagline = '';

    public string $description = '';

    public string $badge = '';

    public string $priceUsd = '';

    public string $priceNgn = '';

    public string $discount = '';

    public string $ctaLabel = '';

    public string $imageAlt = '';

    public ?string $sortOrder = '0';

    public string $featureLines = '';

    public string $specLines = '';

    public bool $isActive = true;

    public bool $isFeatured = false;

    /** A freshly chosen image (uploaded when the form is saved). */
    public $image = null;

    /** A freshly chosen preview image 2/3/4 (uploaded when the form is saved). */
    public $image2 = null;
    public $image3 = null;
    public $image4 = null;

    /** Drop the stored image on save. */
    public bool $removeImage = false;
    public bool $removeImage2 = false;
    public bool $removeImage3 = false;
    public bool $removeImage4 = false;

    /** The stored image, previewed while the modal is open. */
    public ?string $existingImageUrl = null;
    public ?string $existingImage2Url = null;
    public ?string $existingImage3Url = null;
    public ?string $existingImage4Url = null;

    public string $existingImagePath = '';
    public string $existingImage2Path = '';
    public string $existingImage3Path = '';
    public string $existingImage4Path = '';

    /** Answer to "delete this?" — the confirmation lives in the modal, not a browser dialog. */
    public ?string $confirmingDeleteId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();

        $this->reset(
            'editingId', 'name', 'tagline', 'description', 'badge', 'priceUsd', 'priceNgn', 'discount', 'ctaLabel',
            'imageAlt', 'sortOrder', 'featureLines', 'specLines', 'image', 'image2', 'image3', 'image4',
            'removeImage', 'removeImage2', 'removeImage3', 'removeImage4',
            'existingImageUrl', 'existingImagePath', 'existingImage2Url', 'existingImage2Path',
            'existingImage3Url', 'existingImage3Path', 'existingImage4Url', 'existingImage4Path',
        );

        $this->sortOrder = '0';
        $this->isActive = true;
        $this->isFeatured = false;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();

        $product = Product::query()->findOrFail($id);

        $this->editingId = $product->id;
        $this->name = (string) $product->name;
        $this->tagline = (string) $product->tagline;
        $this->description = (string) $product->description;
        $this->badge = (string) $product->badge;
        $this->priceUsd = (string) $product->price_usd;
        $this->priceNgn = $product->price_ngn === null ? '' : (string) $product->price_ngn;
        $this->ctaLabel = (string) $product->cta_label;
        $this->imageAlt = (string) $product->image_alt;
        $this->sortOrder = (string) $product->sort_order;
        $this->featureLines = implode("\n", (array) ($product->features ?? []));
        $this->specLines = collect((array) ($product->specs ?? []))
            ->map(fn ($spec): string => trim(((string) ($spec['label'] ?? '')).': '.((string) ($spec['value'] ?? '')), ': '))
            ->implode("\n");
        $this->isActive = (bool) $product->is_active;
        $this->isFeatured = (bool) $product->is_featured;

        $this->image = null;
        $this->removeImage = false;
        $this->image2 = null; $this->removeImage2 = false;
        $this->image3 = null; $this->removeImage3 = false;
        $this->image4 = null; $this->removeImage4 = false;

        $this->discount = $product->discount === null ? '' : (string) $product->discount;

        $this->existingImagePath = (string) $product->image;
        $this->existingImageUrl = $product->imageUrl();
        $this->existingImage2Path = (string) $product->image_2;
        $this->existingImage2Url = $product->image_2 ? (media_url($product->image_2) ?? $product->image_2) : null;
        $this->existingImage3Path = (string) $product->image_3;
        $this->existingImage3Url = $product->image_3 ? (media_url($product->image_3) ?? $product->image_3) : null;
        $this->existingImage4Path = (string) $product->image_4;
        $this->existingImage4Url = $product->image_4 ? (media_url($product->image_4) ?? $product->image_4) : null;

        $this->resetValidation();
        $this->showModal = true;
    }

    /** Drop the image from the form — a fresh upload, or the stored one. */
    public function clearImage(): void
    {
        $this->authorizeWrite();

        $this->image = null;
        $this->removeImage = true;
    }

    public function keepImage(): void
    {
        $this->authorizeWrite();

        $this->removeImage = false;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'priceUsd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'priceNgn' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ctaLabel' => ['nullable', 'string', 'max:40'],
            'imageAlt' => ['nullable', 'string', 'max:160'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'image2' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'image3' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'image4' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'featureLines' => ['nullable', 'string', 'max:2000'],
            'specLines' => ['nullable', 'string', 'max:2000'],
        ], [
            'priceUsd.required' => 'Enter the price the product sells for.',
            'image.mimes' => 'The image must be a JPG, PNG or WebP file.',
        ]);

        $product = $this->editingId !== null
            ? Product::query()->findOrFail($this->editingId)
            : new Product;

        $name = trim($this->name);

        $attributes = [
            'name' => $name,
            // The slug is generated and kept unique against the eSIM plans as well as other products.
            'slug' => Product::slugFor($name, $product->exists ? $product->id : null),
            'tagline' => $this->nullable($this->tagline),
            'description' => $this->nullable($this->description),
            'badge' => $this->nullable($this->badge),
            'price_usd' => (float) $this->priceUsd,
            // Blank = follow the exchange rate on every read.
            'price_ngn' => $this->priceNgn === '' ? null : (float) $this->priceNgn,
            'discount' => $this->discount === '' ? null : (float) $this->discount,
            'currency' => (string) config('product.currency', 'NGN'),
            'cta_label' => $this->nullable($this->ctaLabel),
            'image_alt' => $this->nullable($this->imageAlt),
            'features' => $this->lines($this->featureLines),
            'specs' => $this->specs($this->specLines),
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'sort_order' => (int) ($this->sortOrder ?: 0),
        ];

        if ($this->image !== null) {
            $attributes['image'] = app(ImageCompressionService::class)
                ->compressAndStore($this->image, 'product-images', null, 82, 1600, 1600);
        } elseif ($this->removeImage) {
            $attributes['image'] = null;
        }

        foreach ([2 => 'image2', 3 => 'image3', 4 => 'image4'] as $n => $field) {
            $prop = 'image'.$n;
            $removeProp = 'removeImage'.$n;
            $pathProp = 'existingImage'.$n.'Path';
            $col = 'image_'.$n;
            if ($this->{$field} !== null) {
                $attributes[$col] = app(ImageCompressionService::class)
                    ->compressAndStore($this->{$field}, 'product-images', null, 82, 1600, 1600);
            } elseif ($this->{$removeProp}) {
                $attributes[$col] = null;
            }
        }

        $product->fill($attributes)->save();

        if (array_key_exists('image', $attributes) && $this->existingImagePath !== '' && $this->existingImagePath !== ($attributes['image'] ?? null)) {
            $this->deleteStored($this->existingImagePath);
        }

        foreach ([2,3,4] as $n) {
            $col = 'image_'.$n;
            $pathProp = 'existingImage'.$n.'Path';
            if (array_key_exists($col, $attributes) && $this->{$pathProp} !== '' && $this->{$pathProp} !== ($attributes[$col] ?? null)) {
                $this->deleteStored($this->{$pathProp});
            }
        }

        $this->showModal = false;

        $this->dispatch('toast', message: ($this->editingId !== null ? 'Product updated' : 'Product created').' — '.(string) $product->name, type: 'success');

        $this->reset('editingId', 'image', 'image2', 'image3', 'image4', 'removeImage', 'removeImage2', 'removeImage3', 'removeImage4', 'existingImageUrl', 'existingImagePath', 'existingImage2Url', 'existingImage2Path', 'existingImage3Url', 'existingImage3Path', 'existingImage4Url', 'existingImage4Path', 'imageAlt', 'discount');
    }

    /** Publish / unpublish straight from the list. */
    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();

        $product = Product::query()->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);

        $this->dispatch('toast', message: $product->name.($product->is_active ? ' is live on the storefront' : ' is hidden from the storefront'), type: 'success');
    }

    /** Feature / highlight the card on the storefront. */
    public function toggleFeatured(string $id): void
    {
        $this->authorizeWrite();

        $product = Product::query()->findOrFail($id);
        $product->update(['is_featured' => ! $product->is_featured]);

        $this->dispatch('toast', message: $product->name.($product->is_featured ? ' is featured' : ' is no longer featured'), type: 'success');
    }

    public function confirmDelete(string $id): void
    {
        $this->authorizeWrite();

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();

        $product = Product::query()->findOrFail($id);
        $image = $product->image;

        $this->safeDelete(function () use ($product): void {
            $product->delete();
        }, 'product', 'Product deleted');

        $this->deleteStored($image);
        $this->confirmingDeleteId = null;
    }

    public function render(): View
    {
        return view('livewire.backoffice.products.list-products', [
            'products' => Product::query()
                ->when(trim($this->search) !== '', function (Builder $query): void {
                    $term = '%'.trim($this->search).'%';

                    $query->where(fn (Builder $sub) => $sub
                        ->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('tagline', 'like', $term)
                        ->orWhere('badge', 'like', $term));
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
            'liveCount' => Product::query()->where('is_active', true)->count(),
            'totalCount' => Product::query()->count(),
            'confirmingDelete' => $this->confirmingDeleteId !== null
                ? Product::query()->find($this->confirmingDeleteId)
                : null,
            'ratePreview' => $this->ratePreview(),
        ]);
    }

    /**
     * The naira price a blank field would produce right now — shown as the field's placeholder, so an
     * admin can see what leaving it empty means before they leave it empty.
     */
    protected function ratePreview(): string
    {
        $usd = (float) ($this->priceUsd !== '' ? $this->priceUsd : 0);

        return $usd > 0
            ? number_format(app(CurrencyService::class)->toNgn($usd), 2)
            : 'e.g. 250,000.00';
    }

    /** A trimmed string, or null so the column stays empty. */
    protected function nullable(string $value): ?string
    {
        return trim($value) !== '' ? trim($value) : null;
    }

    /**
     * One feature per line.
     *
     * @return list<string>
     */
    protected function lines(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', $value) ?: [],
        ), fn (string $line): bool => $line !== ''));
    }

    /**
     * "Label: value" per line — what the card's spec grid renders.
     *
     * @return list<array{label: string, value: string}>
     */
    protected function specs(string $value): array
    {
        $specs = [];

        foreach ($this->lines($value) as $line) {
            if (! str_contains($line, ':')) {
                continue;   // a line without a colon is a typo, not a spec
            }

            [$label, $specValue] = explode(':', $line, 2);

            if (trim($label) !== '' && trim($specValue) !== '') {
                $specs[] = ['label' => trim($label), 'value' => trim($specValue)];
            }
        }

        return $specs;
    }

    /** Remove a replaced upload, never an external or root-relative path. */
    protected function deleteStored(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            return;
        }

        try {
            Storage::disk(config('filesystems.default'))->delete($path);
        } catch (\Throwable) {
            // A missing or unreadable file must never block saving the product.
        }
    }
}
