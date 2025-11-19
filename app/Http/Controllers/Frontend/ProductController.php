<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images', 'variants.attributeValues'])
            ->where('is_active', true);

        // Category filter
        if ($request->has('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('tags', function($tagQuery) use ($search) {
                      $tagQuery->where('tag', 'like', "%{$search}%");
                  });
            });
        }

        // Price filter
        if ($request->has('min_price')) {
            $query->whereRaw('COALESCE(discount_price, price) >= ?', [$request->min_price]);
        }
        if ($request->has('max_price')) {
            $query->whereRaw('COALESCE(discount_price, price) <= ?', [$request->max_price]);
        }

        // Attribute filters
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'attr_') === 0) {
                $attributeId = str_replace('attr_', '', $key);
                $query->whereHas('variants.attributeValues', function($q) use ($attributeId, $value) {
                    $q->where('attribute_id', $attributeId)
                      ->whereIn('attribute_values.id', (array)$value);
                });
            }
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderByRaw('COALESCE(discount_price, price) ASC');
                break;
            case 'price_high':
                $query->orderByRaw('COALESCE(discount_price, price) DESC');
                break;
            case 'name':
                $query->orderBy('name', 'ASC');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }

        $products = $query->paginate(24);
        $categories = Category::where('is_active', true)->get();
        
        // Get filterable attributes for active filters
        $filterableAttributes = Attribute::where('is_filterable', true)
            ->with('values')
            ->get();

        return view('frontend.products.index', compact('products', 'categories', 'filterableAttributes'));
    }

    public function show($slug)
    {
        $product = Product::with([
            'category.attributes.values',
            'brand',
            'images',
            'tags',
            'variants.attributeValues.attribute'
        ])->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        return view('frontend.products.show', compact('product', 'relatedProducts'));
    }

    public function getVariant(Request $request)
    {
        $product = Product::findOrFail($request->product_id);
        
        $selectedAttributes = $request->attributes ?? [];
        
        $variant = $product->variants()
            ->whereHas('attributeValues', function($q) use ($selectedAttributes) {
                foreach ($selectedAttributes as $attrId => $valueId) {
                    $q->where('attribute_values.id', $valueId);
                }
            }, '=', count($selectedAttributes))
            ->first();

        if ($variant) {
            return response()->json([
                'success' => true,
                'variant' => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->final_price,
                    'stock_quantity' => $variant->stock_quantity,
                    'in_stock' => $variant->in_stock,
                    'image' => $variant->image ? asset('storage/' . $variant->image) : null,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Variant not found'
        ], 404);
    }
}

