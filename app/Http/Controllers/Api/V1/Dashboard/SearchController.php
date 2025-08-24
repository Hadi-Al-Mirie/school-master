<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\search\SearchRequest;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchController extends Controller
{
    public function index(SearchRequest $request)
    {
        $validated = $request->validated();
        $q = $validated['q'];
        $type = $validated['type'];

        try {
            switch ($type) {
                case 'teacher':
                    $results = Teacher::with(['user:id,first_name,last_name,email'])
                        ->where(function ($builder) use ($q) {
                            $builder->whereHas('user', function ($u) use ($q) {
                                $u->where('first_name', 'like', "%{$q}%")
                                    ->orWhere('last_name', 'like', "%{$q}%")
                                    ->orWhere('email', 'like', "%{$q}%");
                            })
                                ->orWhere('phone', 'like', "%{$q}%");
                        })
                        ->orderByDesc('id')
                        ->get()
                        ->map(function ($t) {
                            return [
                                'type' => 'teacher',
                                'id' => $t->id,
                                'user' => [
                                    'id' => $t->user->id ?? null,
                                    'first_name' => $t->user->first_name ?? null,
                                    'last_name' => $t->user->last_name ?? null,
                                    'email' => $t->user->email ?? null,
                                ],
                                'phone' => $t->phone,
                            ];
                        })
                        ->values();
                    break;

                case 'student':
                    $results = Student::with(['user:id,first_name,last_name,email'])
                        ->where(function ($builder) use ($q) {
                            $builder->whereHas('user', function ($u) use ($q) {
                                $u->where('first_name', 'like', "%{$q}%")
                                    ->orWhere('last_name', 'like', "%{$q}%")
                                    ->orWhere('email', 'like', "%{$q}%");
                            })
                                ->orWhere('father_name', 'like', "%{$q}%")
                                ->orWhere('mother_name', 'like', "%{$q}%")
                                ->orWhere('father_number', 'like', "%{$q}%")
                                ->orWhere('mother_number', 'like', "%{$q}%")
                                ->orWhere('location', 'like', "%{$q}%");
                        })
                        ->orderByDesc('id')
                        ->get()
                        ->map(function ($s) {
                            return [
                                'type' => 'student',
                                'id' => $s->id,
                                'user' => [
                                    'id' => $s->user->id ?? null,
                                    'first_name' => $s->user->first_name ?? null,
                                    'last_name' => $s->user->last_name ?? null,
                                    'email' => $s->user->email ?? null,
                                ],
                                'father_name' => $s->father_name ?? null,
                                'mother_name' => $s->mother_name ?? null,
                                'father_number' => $s->father_number ?? null,
                                'mother_number' => $s->mother_number ?? null,
                                'location' => $s->location ?? null,
                                'gender' => $s->gender ?? null,
                                'stage' => $s->stage->name ?? null,
                                'classroom' => $s->classroom->name ?? null,
                                'section' => $s->section->name ?? null,
                            ];
                        })
                        ->values();
                    break;

                case 'supervisor':
                    $results = Supervisor::query()
                        // Constrain + eager-load in one go (Laravel 9+)
                        ->withWhereHas('user', function ($u) use ($q) {
                            $u->where(function ($uu) use ($q) {
                                $uu->where('first_name', 'like', "%{$q}%")
                                    ->orWhere('last_name', 'like', "%{$q}%")
                                    ->orWhere('email', 'like', "%{$q}%");
                            });
                        })
                        // If the input looks like an email, also do an exact match to be safe
                        ->when(str_contains($q, '@'), function ($query) use ($q) {
                            $query->orWhereHas('user', function ($u) use ($q) {
                                $u->whereRaw('LOWER(email) = ?', [strtolower($q)]);
                            });
                        })
                        ->orderByDesc('id')
                        ->with(['user:id,first_name,last_name,email']) // ensure user is returned
                        ->get()
                        ->map(function ($sp) {
                            return [
                                'type' => 'supervisor',
                                'id' => $sp->id,
                                'user' => [
                                    'id' => $sp->user->id ?? null,
                                    'first_name' => $sp->user->first_name ?? null,
                                    'last_name' => $sp->user->last_name ?? null,
                                    'email' => $sp->user->email ?? null,
                                ],
                                'phone_number' => $sp->phone ?? null,
                                'stage' => $sp->stage->name ?? null,
                            ];
                        })
                        ->values();
                    break;

                default:
                    $results = collect();
            }

            return response()->json([
                'success' => true,
                'message' => __('dashboard/search/messages.search_success'),
                'filters' => [
                    'type' => $type,
                    'q' => $q,
                ],
                'data' => $results,
            ]);

        } catch (Throwable $e) {
            Log::error('Dashboard search failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'q' => $q,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard/search/messages.unexpected_error'),
            ], 500);
        }
    }
}