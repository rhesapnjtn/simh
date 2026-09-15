<?php

namespace App\Http\Controllers;

use App\Models\AycePackage;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class AycePackageController extends Controller
{
    public function index(Request $request)
    {
        $query = AycePackage::query()->orderBy('price_per_pax');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json($query->paginate(12)->through(fn (AycePackage $p) => $this->shape($p)));
    }

    public function all()
    {
        return response()->json(
            AycePackage::where('is_active', true)->orderBy('price_per_pax')->get()->map(fn (AycePackage $p) => $this->shape($p))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $package = AycePackage::create($data);

        ActivityLogService::log('create', "Paket AYCE baru: {$package->name}.", $package);

        return response()->json(['message' => 'Paket AYCE berhasil dibuat.', 'data' => $this->shape($package)], 201);
    }

    public function update(Request $request, AycePackage $package)
    {
        $data = $this->validateData($request);

        $package->update($data);

        ActivityLogService::log('update', "Paket AYCE diperbarui: {$package->name}.", $package);

        return response()->json(['message' => 'Paket AYCE berhasil diperbarui.', 'data' => $this->shape($package)]);
    }

    public function destroy(AycePackage $package)
    {
        $name = $package->name;
        $package->delete();

        ActivityLogService::log('delete', "Paket AYCE dihapus: {$name}.");

        return response()->json(['message' => 'Paket AYCE dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price_per_pax' => ['required', 'numeric', 'min:0'],
            'min_pax' => ['nullable', 'integer', 'min:0'],
            'max_pax' => ['nullable', 'integer', 'gte:min_pax'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'description' => ['nullable', 'string'],
            'includes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function shape(AycePackage $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'price_per_pax' => (float) $p->price_per_pax,
            'min_pax' => (int) $p->min_pax,
            'max_pax' => $p->max_pax !== null ? (int) $p->max_pax : null,
            'duration_minutes' => (int) $p->duration_minutes,
            'description' => $p->description,
            'includes' => $p->includes,
            'is_active' => (bool) $p->is_active,
            'created_at' => $p->created_at?->format('d M Y H:i'),
        ];
    }
}