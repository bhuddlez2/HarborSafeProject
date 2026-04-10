// http://127.0.0.1:8000/api
<?php
use Illuminate\Support\Facades\Route;

// This replaces: app.get('/', (req, res) => { ... })
Route::get('/', function () {
    return response()->json([
        'message' => 'HarborSafe API is running!'
    ]);
});