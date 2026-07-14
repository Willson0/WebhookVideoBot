<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post("/payment/webhook_gklakdflwo12kfl", [PaymentController::class, 'webhook']);
