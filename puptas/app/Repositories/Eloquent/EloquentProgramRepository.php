<?php

namespace App\Repositories\Eloquent;

use App\Models\Program;
use App\Repositories\Contracts\ProgramRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProgramRepository implements ProgramRepositoryInterface
{
    public function allWithStrands(): Collection
    {
        return Program::with('strands')->get();
    }

    public function all(): Collection
    {
        return Program::all();
    }

    public function create(array $data): Program
    {
        return Program::create($data);
    }

    public function find(int $id): Program
    {
        return Program::findOrFail($id);
    }

    public function allWithApplicationsCount(): Collection
    {
        return Program::withCount('applications')->get();
    }

    public function allWithAvailableSlots(): Collection
    {
        return Program::where('slots', '>', 0)->get();
    }

    public function allIds(): array
    {
        return Program::pluck('id')->toArray();
    }

    public function eligibleForGrades(float $english, float $math, float $science, float $gwa): Collection
    {
        return Program::with('strands')
            ->where(function ($query) use ($english, $math, $science, $gwa) {
                $query->where(function ($q) use ($english) {
                    $q->whereNull('english')->orWhereRaw('? >= english', [$english]);
                })
                    ->where(function ($q) use ($math) {
                        $q->whereNull('math')->orWhereRaw('? >= math', [$math]);
                    })
                    ->where(function ($q) use ($science) {
                        $q->whereNull('science')->orWhereRaw('? >= science', [$science]);
                    })
                    ->where(function ($q) use ($gwa) {
                        $q->whereNull('gwa')->orWhereRaw('? >= gwa', [$gwa]);
                    });
            })
            ->get();
    }

    public function allWithStrandsSelected(array $columns): Collection
    {
        return Program::with('strands')->get($columns);
    }
}