@extends('layouts.frontend')

@section('title', 'Order Details')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h2>Order Details - {{ $order->order_number }}</h2>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
                        <p><strong>Order Date:</strong> {{ $order->created_at->format('F d, Y h:i A') }}</p>
                        <p><strong>Status:</strong> <span class="badge bg-{{ $order->status_badge }}">{{ ucfirst($order->status) }}</span></p>
                        <p><strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</p>
                        <p><strong>Payment Status:</strong> <span class="badge bg-{{ $order->payment_status === 'success' ? 'success' : 'warning' }}">{{ ucfirst($order->payment_status) }}</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Shipping Address:</h6>
                        <p>
                            {{ $order->name }}<br>
                            {{ $order->address }}<br>
                            {{ $order->city }}, {{ $order->state }} {{ $order->pincode }}<br>
                            {{ $order->country }}<br>
                            Phone: {{ $order->phone }}<br>
                            Email: {{ $order->email }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr>
                                <td>
                                    @if($item->product->primaryImage)
                                        <img src="{{ asset('storage/' . $item->product->primaryImage->image_path) }}" alt="{{ $item->product_name }}" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                    @endif
                                    {{ $item->product_name }}
                                </td>
                                <td>
                                    @if($item->variant_details)
                                        {{ $item->variant_details }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->price, 2) }}</td>
                                <td>${{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                <td><strong>${{ number_format($order->subtotal, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Delivery Charges:</strong></td>
                                <td><strong>${{ number_format($order->delivery_charges, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                <td><strong>${{ number_format($order->total_amount, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        @if($order->notes)
        <div class="card mb-4">
            <div class="card-header">
                <h5>Order Notes</h5>
            </div>
            <div class="card-body">
                <p>{{ $order->notes }}</p>
            </div>
        </div>
        @endif

        <a href="{{ route('user.orders') }}" class="btn btn-secondary">Back to Orders</a>
    </div>
</div>
@endsection

