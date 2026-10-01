<?php

use Illuminate\Support\Facades\DB;

test('konfigurasi dasar aplikasi sesuai ERD', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
    expect(config('toko.prefix.sale'))->toBe('INV');
    expect(config('toko.allow_negative_stock'))->toBeFalse();
});

test('test berjalan di MySQL database test', function () {
    expect(config('database.default'))->toBe('mysql');
    expect(DB::connection()->getDatabaseName())->toBe('db_sumberbaru_test');
});