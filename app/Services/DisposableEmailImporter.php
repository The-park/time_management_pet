<?php

namespace App\Services;

use App\Models\DisposableEmailDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DisposableEmailImporter
{
    public function importFromUrl(string $url, string $source): int
    {
        $response = Http::get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download disposable domain list.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $response->body());
        $domains = [];

        foreach ($lines as $line) {
            $domain = trim($line);
            if ($domain === '' || str_starts_with($domain, '#')) {
                continue;
            }
            $domains[] = $domain;
        }

        $now = now();
        $rows = array_map(function (string $domain) use ($source, $now) {
            return [
                'domain' => $domain,
                'source' => $source,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $domains);

        DB::transaction(function () use ($rows) {
            DisposableEmailDomain::query()->delete();

            foreach (array_chunk($rows, 1000) as $chunk) {
                DisposableEmailDomain::insert($chunk);
            }
        });

        return count($rows);
    }
}
