<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;

Route::get('/secure/pdf/{filename}', function ($filename) {
    // 署名が有効かチェック
    if (!request()->hasValidSignature()) {
        abort(403, 'リンクが無効か期限切れです');
    }

    $path = storage_path("app/private/{$filename}");

    if (!file_exists($path)) {
        abort(404, 'ファイルが見つかりません');
    }

    return response()->file($path);
})->name('secure.pdf');
