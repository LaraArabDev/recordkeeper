<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;

Route::post('/records', function (Request $request): JsonResponse {
    $order = Order::create([
        'status' => $request->input('status', 'pending'),
        'total' => $request->input('total', 0),
        'discount_code' => $request->input('discount_code'),
        'national_id' => $request->input('national_id'),
    ]);

    return response()->json(['id' => $order->id], 201);
});

Route::patch('/records/{id}', function (Request $request, int $id): JsonResponse {
    $order = Order::findOrFail($id);
    $order->update($request->only(['status', 'total', 'discount_code', 'national_id']));

    return response()->json(['id' => $order->id]);
});
