@extends('layouts.frontend')

@section('title', $product->name)

@section('content')
<div class="row">
    <div class="col-md-6">
        <!-- Product Images -->
        <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                @foreach($product->images as $index => $image)
                <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                    <img src="{{ asset('storage/' . $image->image_path) }}" class="d-block w-100" alt="{{ $product->name }}" style="max-height: 500px; object-fit: contain;">
                </div>
                @endforeach
            </div>
            @if($product->images->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <h1>{{ $product->name }}</h1>
        <p class="text-muted">{{ $product->category->name }}</p>

        <!-- Price -->
        <div class="mb-3">
            @if($product->discount_price)
                <span class="price-old fs-4">${{ number_format($product->price, 2) }}</span>
                <span class="price-new fs-3">${{ number_format($product->discount_price, 2) }}</span>
                <span class="badge bg-danger">{{ $product->discount_percentage }}% OFF</span>
            @else
                <span class="price-new fs-3">${{ number_format($product->price, 2) }}</span>
            @endif
        </div>

        <!-- Description -->
        <div class="mb-4">
            <p>{{ $product->description }}</p>
        </div>

        <!-- Variant Selection Form -->
        <form action="{{ route('cart.add') }}" method="POST" id="add-to-cart-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="variant_id" id="selected_variant_id">

            @if($product->category->attributes->count() > 0)
                @foreach($product->category->attributes as $attribute)
                    @if($attribute->type === 'select' && $attribute->values->count() > 0)
                        <div class="mb-3">
                            <label class="form-label"><strong>{{ $attribute->name }}</strong> {{ $attribute->pivot->is_required ? '<span class="text-danger">*</span>' : '' }}</label>
                            <select class="form-select variant-select" 
                                    name="attribute_{{ $attribute->id }}" 
                                    data-attribute-id="{{ $attribute->id }}"
                                    {{ $attribute->pivot->is_required ? 'required' : '' }}>
                                <option value="">Select {{ $attribute->name }}</option>
                                @foreach($attribute->values as $value)
                                    <option value="{{ $value->id }}" 
                                            data-color="{{ $value->color_code }}"
                                            data-display="{{ $value->display_value ?? $value->value }}">
                                        {{ $value->display_value ?? $value->value }}
                                        @if($value->color_code)
                                            <span style="background-color: {{ $value->color_code }}; width: 20px; height: 20px; display: inline-block; border: 1px solid #ddd;"></span>
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endforeach
            @endif

            <!-- Quantity -->
            <div class="mb-3">
                <label class="form-label"><strong>Quantity</strong></label>
                <input type="number" class="form-control" name="quantity" value="1" min="1" id="quantity" required>
            </div>

            <!-- Stock Status -->
            <div class="mb-3">
                <div id="stock-status">
                    @if($product->in_stock)
                        <span class="badge bg-success">In Stock</span>
                    @else
                        <span class="badge bg-danger">Out of Stock</span>
                    @endif
                </div>
            </div>

            <!-- Add to Cart Button -->
            <button type="submit" class="btn btn-primary btn-lg w-100" id="add-to-cart-btn" {{ !$product->in_stock ? 'disabled' : '' }}>
                <i class="bi bi-cart-plus"></i> Add to Cart
            </button>
        </form>

        <!-- Product Info -->
        <div class="mt-4">
            <ul class="list-unstyled">
                <li><strong>SKU:</strong> {{ $product->sku }}</li>
                @if($product->brand)
                    <li><strong>Brand:</strong> {{ $product->brand->name }}</li>
                @endif
                @if($product->tags->count() > 0)
                    <li><strong>Tags:</strong> 
                        @foreach($product->tags as $tag)
                            <span class="badge bg-secondary">{{ $tag->tag }}</span>
                        @endforeach
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>

<!-- Related Products -->
@if($relatedProducts->count() > 0)
<div class="row mt-5">
    <div class="col-12">
        <h3>Related Products</h3>
        <div class="row">
            @foreach($relatedProducts as $related)
            <div class="col-md-3 mb-4">
                <div class="card product-card h-100">
                    @if($related->primaryImage)
                        <img src="{{ asset('storage/' . $related->primaryImage->image_path) }}" class="card-img-top" alt="{{ $related->name }}" style="height: 200px; object-fit: cover;">
                    @endif
                    <div class="card-body">
                        <h6 class="card-title">{{ $related->name }}</h6>
                        <p class="card-text">
                            <span class="price-new">${{ number_format($related->final_price, 2) }}</span>
                        </p>
                        <a href="{{ route('products.show', $related->slug) }}" class="btn btn-sm btn-primary">View</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    let selectedAttributes = {};
    const variantSelects = document.querySelectorAll('.variant-select');

    variantSelects.forEach(select => {
        select.addEventListener('change', function() {
            const attributeId = this.dataset.attributeId;
            if (this.value) {
                selectedAttributes[attributeId] = this.value;
            } else {
                delete selectedAttributes[attributeId];
            }
            updateVariant();
        });
    });

    function updateVariant() {
        const productId = {{ $product->id }};
        const attributes = selectedAttributes;

        if (Object.keys(attributes).length === 0) {
            // No variant selected, use product defaults
            document.getElementById('selected_variant_id').value = '';
            updateStockStatus({{ $product->stock_quantity }}, {{ $product->in_stock ? 'true' : 'false' }});
            return;
        }

        fetch('{{ route("products.variant") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
            },
            body: JSON.stringify({
                product_id: productId,
                attributes: attributes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('selected_variant_id').value = data.variant.id;
                updateStockStatus(data.variant.stock_quantity, data.variant.in_stock);
                
                // Update variant image if available
                if (data.variant.image) {
                    // You can update the carousel image here if needed
                }
            } else {
                document.getElementById('selected_variant_id').value = '';
                updateStockStatus(0, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function updateStockStatus(stock, inStock) {
        const statusDiv = document.getElementById('stock-status');
        const addBtn = document.getElementById('add-to-cart-btn');
        
        if (inStock && stock > 0) {
            statusDiv.innerHTML = `<span class="badge bg-success">In Stock (${stock} available)</span>`;
            addBtn.disabled = false;
        } else {
            statusDiv.innerHTML = `<span class="badge bg-danger">Out of Stock</span>`;
            addBtn.disabled = true;
        }
    }
</script>
@endpush

