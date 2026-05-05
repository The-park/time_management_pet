<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisposableEmailDomain;
use App\Services\AdminAudit;
use App\Services\DisposableEmailImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDomainController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = DisposableEmailDomain::query();
        if ($search !== '') {
            $query->where('domain', 'like', '%'.$search.'%');
        }

        return view('admin.domains.index', [
            'domains' => $query
                ->orderBy('domain')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'total' => DisposableEmailDomain::query()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i',
                Rule::unique('disposable_email_domains', 'domain'),
            ],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $data['domain'] = mb_strtolower($data['domain']);
        $data['source'] = $data['source'] ?? 'manual';

        $domain = DisposableEmailDomain::create($data);
        AdminAudit::log('added_disposable_domain');

        return redirect()
            ->route('admin.domains.index')
            ->with('toast', "Added {$domain->domain} to the disposable list.");
    }

    public function destroy($id)
    {
        $domain = DisposableEmailDomain::query()->findOrFail($id);
        $name = $domain->domain;
        $domain->delete();
        AdminAudit::log('removed_disposable_domain');

        return redirect()
            ->route('admin.domains.index')
            ->with('toast', "Removed {$name} from the disposable list.");
    }

    public function refresh(DisposableEmailImporter $importer)
    {
        $url = config('services.disposable_domains.url');
        $source = config('services.disposable_domains.source');

        if (! $url) {
            return back()->with('toast', 'No source URL configured (services.disposable_domains.url).');
        }

        try {
            $count = $importer->importFromUrl($url, $source);
            AdminAudit::log('refreshed_disposable_domains');
            $msg = "Refreshed list — {$count} domains imported from source.";
        } catch (\Throwable $e) {
            $msg = 'Refresh failed: '.$e->getMessage();
        }

        return redirect()->route('admin.domains.index')->with('toast', $msg);
    }
}
