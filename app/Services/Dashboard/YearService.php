<?php
namespace App\Services\Dashboard;
use App\Models\Year;
class YearService
{
    public function index()
    {
        $years = Year::select(['id', 'name', 'start_date', 'end_date'])->get();
        return [
            'succes' => true,
            'date' => $years
        ];
    }
    public function store(array $validated)
    {
        $year = Year::create([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date']
        ]);
        return $year;
    }
}