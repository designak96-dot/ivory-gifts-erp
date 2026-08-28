<?php
use App\Http\Controllers\Api\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;
Route::post('/v1/integrations/woocommerce/orders',WooCommerceWebhookController::class)->middleware('throttle:120,1');
