<?php

namespace Database\Seeders;

use App\Services\DisposableEmailImporter;
use Illuminate\Database\Seeder;

class DisposableDomainsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = config('services.disposable_domains.url');
        $source = config('services.disposable_domains.source');

        app(DisposableEmailImporter::class)->importFromUrl($url, $source);
    }
}
