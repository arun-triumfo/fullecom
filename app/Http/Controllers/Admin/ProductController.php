<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductVariant;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images', 'variants']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Brand filter
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Status filter
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();

        return view('admin.products.index', compact('products', 'categories', 'brands'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        return view('admin.products.create', compact('categories', 'brands'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'sku' => 'nullable|string|max:255|unique:products',
            'barcode' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'tags' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $product = Product::create($validated);

            // Handle images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Handle tags
            if ($request->has('tags')) {
                $tags = array_filter(array_map('trim', explode(',', $request->tags)));
                foreach ($tags as $tag) {
                    $product->tags()->create(['tag' => $tag]);
                }
            }

            // Generate variants if attributes are provided
            if ($request->has('generate_variants') && $request->generate_variants) {
                $this->generateVariants($product, $request);
            }

            DB::commit();

            return redirect()->route('admin.products.index')
                ->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating product: ' . $e->getMessage());
        }
    }

    public function edit(Product $product)
    {
        $product->load(['category.attributes.values', 'brand', 'images', 'tags', 'variants.attributeValues']);
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();

        return view('admin.products.edit', compact('product', 'categories', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $product->id,
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'tags' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $product->update($validated);

            // Handle new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'is_primary' => false,
                        'sort_order' => $product->images()->max('sort_order') + $index + 1,
                    ]);
                }
            }

            // Handle tags
            $product->tags()->delete();
            if ($request->has('tags')) {
                $tags = array_filter(array_map('trim', explode(',', $request->tags)));
                foreach ($tags as $tag) {
                    $product->tags()->create(['tag' => $tag]);
                }
            }

            DB::commit();

            return redirect()->route('admin.products.index')
                ->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating product: ' . $e->getMessage());
        }
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function getCategoryAttributes($categoryId)
    {
        $category = Category::with('attributes.values')->findOrFail($categoryId);
        return response()->json($category->attributes);
    }

    private function generateVariants(Product $product, Request $request)
    {
        $category = $product->category;
        $attributes = $category->attributes()->where('type', 'select')->get();

        if ($attributes->isEmpty()) {
            return;
        }

        // Get selected attribute values from request
        $selectedAttributes = [];
        foreach ($attributes as $attribute) {
            $key = 'attribute_' . $attribute->id;
            if ($request->has($key) && is_array($request->$key)) {
                $selectedAttributes[$attribute->id] = $request->$key;
            }
        }

        if (empty($selectedAttributes)) {
            return;
        }

        // Generate all combinations
        $combinations = $this->generateCombinations($selectedAttributes);

        foreach ($combinations as $combination) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'stock_quantity' => $request->variant_stock_quantity ?? 0,
                'in_stock' => true,
            ]);

            // Attach attribute values to variant
            foreach ($combination as $attributeId => $valueId) {
                $variant->attributes()->attach($attributeId, [
                    'attribute_value_id' => $valueId,
                ]);
            }
        }
    }

    private function generateCombinations($arrays)
    {
        $result = [[]];
        
        foreach ($arrays as $key => $values) {
            $newResult = [];
            foreach ($result as $product) {
                foreach ($values as $value) {
                    $newResult[] = array_merge($product, [$key => $value]);
                }
            }
            $result = $newResult;
        }
        
        return $result;
    }

    public function generateVariantsAction(Request $request, Product $product)
    {
        $this->generateVariants($product, $request);
        return redirect()->back()->with('success', 'Variants generated successfully.');
    }
}

