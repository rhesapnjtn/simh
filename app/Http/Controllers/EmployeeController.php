<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%')
                    ->orWhere('position', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->orderBy('name')->paginate(12)->through(fn (Employee $e) => $this->shape($e)));
    }

    public function all()
    {
        return response()->json(Employee::where('status', 'active')->orderBy('name')->get()->map(fn ($e) => $this->shape($e)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $employee = Employee::create($data);

        ActivityLogService::log('create', "Karyawan {$employee->name} ditambahkan.", $employee);

        return response()->json(['message' => 'Karyawan berhasil ditambahkan.', 'data' => $this->shape($employee)], 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateData($request);
        $employee->update($data);

        ActivityLogService::log('update', "Data karyawan {$employee->name} diperbarui.", $employee);

        return response()->json(['message' => 'Data karyawan berhasil diperbarui.', 'data' => $this->shape($employee)]);
    }

    public function destroy(Employee $employee)
    {
        $name = $employee->name;
        $employee->delete();

        ActivityLogService::log('delete', "Karyawan {$name} dihapus.");

        return response()->json(['message' => 'Karyawan dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'join_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,on_leave,resigned'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function shape(Employee $e): array
    {
        return [
            'id' => $e->id,
            'user_id' => $e->user_id,
            'name' => $e->name,
            'email' => $e->email,
            'phone' => $e->phone,
            'position' => $e->position,
            'department' => $e->department,
            'join_date' => $e->join_date?->format('Y-m-d'),
            'salary' => (float) $e->salary,
            'status' => $e->status,
            'notes' => $e->notes,
            'created_at' => $e->created_at?->format('d M Y'),
        ];
    }
}