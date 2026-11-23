<div>
    <x-backoffice.page title="Catalog Products" subtitle="Products the storefront sells besides the eSIM plans — image, details, price and everything a buyer sees">
        @php
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
            $textInput = $input.' w-full';
        @endphp

        @if ($canWrite)
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="Add product"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add product</span>
                </button>
            </x-slot:action>
        @endif

        <div class="grid sm:grid-cols-3 gap-4">
            <x-backoffice.stat label="Live on the storefront" :value="number_format($liveCount)" icon="ri-store-3-line" />
            <x-backoffice.stat label="Products in the catalog" :value="number_format($totalCount)" icon="ri-archive-2-line" />
            <x-backoffice.stat label="eSIM plans" :value="(string) count(\App\Support\ProductCatalog::packages())" icon="ri-sim-card-2-line" hint="listed from Packages" />
        </div>

        <x-backoffice.card>
            <p class="font-sans text-muted text-[12px] -mt-1 mb-4 leading-relaxed">
                An <span class="text-text">active</span> product appears on the product pages next to the eSIM plans, in the order set by
                <span class="text-text">Sort order</span>. Leave the naira price blank and the storefront converts the USD price at the current
                exchange rate on every visit; type one and that price is used instead. Either way the amount is frozen onto an order the moment a
                buyer starts a checkout — nobody is ever charged a price other than the one their card showed.
            </p>

            <input type="search" wire:model.live.debounce.350ms="search" placeholder="Search name, slug, tagline or badge…" class="{{ $textInput }} sm:max-w-[360px]">
        </x-backoffice.card>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($products->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">
                    {{ trim($search) !== '' ? 'No products match that search.' : 'No catalog products yet — add the first one and it will show up on the storefront.' }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[980px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Product</th>
                                <th class="px-5 py-3 font-medium text-right">USD</th>
                                <th class="px-5 py-3 font-medium text-right">Storefront price</th>
                                <th class="px-5 py-3 font-medium text-right">Details</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php $rowImage = $product->imageUrl(); @endphp
                                <tr wire:key="product-{{ $product->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="size-12 rounded-[12px] overflow-hidden bg-background border border-muted/15 flex items-center justify-center shrink-0">
                                                @if ($rowImage)
                                                    <img src="{{ $rowImage }}" alt="" class="size-full object-cover">
                                                @else
                                                    <i class="ri-image-line text-muted text-[16px]"></i>
                                                @endif
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13.5px] truncate">{{ $product->name }}</p>
                                                <p class="font-sans text-muted text-[11.5px] truncate">/products/{{ $product->slug }}</p>
                                                @if ($product->badge)
                                                    <span class="inline-flex mt-1 rounded-full bg-primary/10 text-primary px-2 py-0.5 font-sans text-[10.5px]">{{ $product->badge }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="font-sans text-muted text-[12.5px] tabular-nums">${{ number_format((float) $product->price_usd, 2) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="font-sans text-text text-[13px] tabular-nums">₦{{ number_format($product->priceNgn(), 2) }}</span>
                                        @if ($product->price_ngn === null)
                                            <span class="block font-sans text-muted text-[10.5px]">follows the rate</span>
                                        @else
                                            <span class="block font-sans text-muted text-[10.5px]">pinned</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="font-sans text-muted text-[12px] tabular-nums">{{ count((array) $product->features) }} features · {{ count((array) $product->specs) }} specs</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-col items-start gap-1">
                                            @if ($product->is_active)
                                                <x-backoffice.badge tone="success">Live</x-backoffice.badge>
                                            @else
                                                <x-backoffice.badge tone="muted">Hidden</x-backoffice.badge>
                                            @endif
                                            @if ($product->is_featured)
                                                <x-backoffice.badge tone="info">Featured</x-backoffice.badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('product.checkout', $product->slug) }}" target="_blank" rel="noopener"
                                               class="size-9 rounded-full bg-background flex items-center justify-center text-muted hover:text-primary" title="View on the storefront">
                                                <i class="ri-external-link-line"></i>
                                            </a>

                                            @if ($canWrite)
                                                <button type="button" wire:click="toggleActive('{{ $product->id }}')"
                                                        class="size-9 rounded-full bg-background flex items-center justify-center {{ $product->is_active ? 'text-success' : 'text-muted' }} hover:text-primary"
                                                        title="{{ $product->is_active ? 'Hide from the storefront' : 'Publish to the storefront' }}">
                                                    <i class="{{ $product->is_active ? 'ri-eye-line' : 'ri-eye-off-line' }}"></i>
                                                </button>
                                                <button type="button" wire:click="toggleFeatured('{{ $product->id }}')"
                                                        class="size-9 rounded-full bg-background flex items-center justify-center {{ $product->is_featured ? 'text-primary' : 'text-muted' }} hover:text-primary" title="Feature this product">
                                                    <i class="ri-star-line"></i>
                                                </button>
                                                <button type="button" wire:click="edit('{{ $product->id }}')"
                                                        class="size-9 rounded-full bg-background flex items-center justify-center text-muted hover:text-primary" title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button type="button" wire:click="confirmDelete('{{ $product->id }}')"
                                                        class="size-9 rounded-full bg-background flex items-center justify-center text-danger/80 hover:text-danger" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">{{ $products->links() }}</div>
            @endif
        </x-backoffice.card>

        {{-- ── Create / edit ─────────────────────────────────────────────────────────── --}}
        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit product' : 'Add product'" size="xl">
            @php
                // Only an actual pending upload can produce a temporary URL — never trust the property's
                // type, and never let a name from elsewhere in the view shadow it.
                $pendingUpload = $image instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                    ? $image
                    : null;
                $imagePreview = $existingImageUrl;

                if ($pendingUpload !== null) {
                    // A file Livewire cannot preview (an unusual mime, or a test double with no
                    // extension) must not take the whole page down — the upload still saves.
                    try {
                        $imagePreview = $pendingUpload->temporaryUrl();
                    } catch (\Throwable) {
                        $imagePreview = $existingImageUrl;
                    }
                }

                if ($removeImage) {
                    $imagePreview = null;
                }
            @endphp

            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Name</label>
                        <input type="text" wire:model="name" maxlength="120" placeholder="Nextel 5G Router" class="{{ $textInput }}">
                        @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Badge <span class="text-muted/50">(optional pill)</span></label>
                        <input type="text" wire:model="badge" maxlength="40" placeholder="New arrival, Limited…" class="{{ $textInput }}">
                        @error('badge') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Tagline <span class="text-muted/50">(one line on the card)</span></label>
                    <input type="text" wire:model="tagline" maxlength="160" placeholder="Dual-band 5G router with a battery that lasts all day." class="{{ $textInput }}">
                    @error('tagline') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Description <span class="text-muted/50">(shown on the checkout page)</span></label>
                    <textarea wire:model="description" rows="3" maxlength="2000" placeholder="What it is, who it is for, what comes in the box." class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 w-full"></textarea>
                    @error('description') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Image</label>
                    <div class="flex items-center gap-3 rounded-[14px] bg-background px-3 py-2.5">
                        <span class="size-14 rounded-[12px] overflow-hidden shrink-0 flex items-center justify-center bg-surface border border-muted/15">
                            @if ($imagePreview)
                                <img src="{{ $imagePreview }}" alt="" class="size-full object-cover">
                            @else
                                <i class="ri-image-line text-muted text-[18px]"></i>
                            @endif
                        </span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <label class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 h-[32px] font-sans text-[12px] text-text shadow-sm cursor-pointer hover:bg-surface/70 transition">
                                <i class="ri-upload-2-line"></i>
                                <span>{{ $imagePreview ? 'Replace image' : 'Choose image' }}</span>
                                <input type="file" wire:model="image" accept="image/*" class="hidden">
                            </label>

                            @if ($imagePreview)
                                <button type="button" wire:click="clearImage" class="font-sans text-[12px] text-danger hover:underline">Remove</button>
                            @elseif ($removeImage && $existingImageUrl)
                                <button type="button" wire:click="keepImage" class="font-sans text-[12px] text-primary hover:underline">Undo</button>
                            @endif

                            <span wire:loading wire:target="image" class="font-sans text-[11px] text-muted">Uploading…</span>
                        </div>
                    </div>
                    <span class="font-sans text-[11px] text-muted">JPG, PNG or WebP up to 4MB — resized to 1600px and compressed on upload.</span>
                    @error('image') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Discount <span class="text-muted/50">(percent, optional)</span></label>
                    <input type="number" step="any" min="0" max="100" wire:model="discount" placeholder="e.g. 10" class="{{ $textInput }}">
                    @error('discount') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach ([2,3,4] as $n)
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Preview image {{ $n }}</label>
                            <input type="file" wire:model="image{{ $n }}" accept="image/*" class="{{ $textInput }}">
                            @error('image'.$n) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            @php $u = ${'existingImage'.$n.'Url'} ?? null; @endphp
                            @if ($u)
                                <img src="{{ $u }}" alt="" class="size-14 rounded-[8px] object-cover">
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Image alt text <span class="text-muted/50">(accessibility)</span></label>
                        <input type="text" wire:model="imageAlt" maxlength="160" placeholder="Defaults to the product name" class="{{ $textInput }}">
                        @error('imageAlt') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Buy button label <span class="text-muted/50">(optional)</span></label>
                        <input type="text" wire:model="ctaLabel" maxlength="40" placeholder="Buy {{ $name ?: 'this product' }}" class="{{ $textInput }}">
                        @error('ctaLabel') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Price (USD)</label>
                        <input type="number" step="any" min="0" wire:model.live.debounce.500ms="priceUsd" class="{{ $textInput }}">
                        @error('priceUsd') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Naira price <span class="text-muted/50">(blank = live rate)</span></label>
                        <input type="number" step="any" min="0" wire:model="priceNgn" placeholder="{{ $ratePreview }}" class="{{ $textInput }}">
                        @error('priceNgn') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Sort order</label>
                        <input type="number" step="1" min="0" wire:model="sortOrder" class="{{ $textInput }}">
                        @error('sortOrder') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Features <span class="text-muted/50">(one per line)</span></label>
                        <textarea wire:model="featureLines" rows="5" placeholder="Dual-band Wi-Fi 6&#10;Up to 32 connected devices&#10;SIM + eSIM ready" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 w-full"></textarea>
                        @error('featureLines') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Specs <span class="text-muted/50">(one "Label: value" per line)</span></label>
                        <textarea wire:model="specLines" rows="5" placeholder="Battery: 12 hours&#10;Network: 5G / 4G LTE&#10;Warranty: 12 months" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 w-full"></textarea>
                        @error('specLines') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-5 rounded-[14px] bg-background px-3.5 py-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="isActive" class="size-4 rounded border-muted/30 text-primary">
                        <span class="font-sans text-[13px] text-text">Live on the storefront</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="isFeatured" class="size-4 rounded border-muted/30 text-primary">
                        <span class="font-sans text-[13px] text-text">Feature it <span class="text-muted">(highlighted card)</span></span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save,image"
                            class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save product' : 'Create product' }}</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        {{-- ── Delete confirmation ───────────────────────────────────────────────────── --}}
        <x-backoffice.modal :show="$confirmingDelete !== null" title="Delete this product?" closeAction="cancelDelete">
            @if ($confirmingDelete)
                <div class="flex flex-col gap-4">
                    <p class="font-sans text-[13px] text-text">
                        <span class="font-medium">{{ $confirmingDelete->name }}</span> will be removed from the storefront and the catalog.
                    </p>
                    <p class="font-sans text-muted text-[12px] leading-relaxed">
                        Orders already placed for it are untouched — each order keeps its own snapshot of what was bought, so receipts and the
                        fulfilment queue stay correct.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelDelete" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Keep it</button>
                        <button type="button" wire:click="delete('{{ $confirmingDelete->id }}')" wire:loading.attr="disabled" wire:target="delete"
                                class="h-[42px] px-5 rounded-full bg-danger text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="delete">Delete product</span>
                            <span wire:loading wire:target="delete">Deleting…</span>
                        </button>
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
