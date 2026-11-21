<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IMAGE_MAP = [
        '/images/products/brake-pad-set.svg' => '/images/products/brake-pad-set.jpg',
        '/images/products/oil-filter.svg' => '/images/products/oil-filter.jpg',
        '/images/products/spark-plugs-4-pack.svg' => '/images/products/spark-plugs-4-pack.jpg',
        '/images/products/air-filter.svg' => '/images/products/air-filter.jpg',
        '/images/products/shock-absorber.svg' => '/images/products/shock-absorber.jpg',
    ];

    private const ALT_MAP = [
        'Illustration of a brake pad set' => 'Photo of a brake pad set',
        'Illustration of an oil filter' => 'Photo of an oil filter',
        'Illustration of four spark plugs' => 'Photo of four spark plugs',
        'Illustration of an air filter' => 'Photo of an air filter',
        'Illustration of a shock absorber' => 'Photo of a shock absorber',
    ];

    public function up(): void
    {
        foreach (self::IMAGE_MAP as $old => $new) {
            DB::table('products')->where('public_image_url', $old)->update(['public_image_url' => $new]);
        }

        foreach (self::ALT_MAP as $old => $new) {
            DB::table('products')->where('public_image_alt', $old)->update(['public_image_alt' => $new]);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::IMAGE_MAP) as $old => $new) {
            DB::table('products')->where('public_image_url', $old)->update(['public_image_url' => $new]);
        }

        foreach (array_flip(self::ALT_MAP) as $old => $new) {
            DB::table('products')->where('public_image_alt', $old)->update(['public_image_alt' => $new]);
        }
    }
};
