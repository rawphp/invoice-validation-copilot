<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InvoiceController::class, 'show']);
Route::post('/validate', [InvoiceController::class, 'validateInvoice']);
