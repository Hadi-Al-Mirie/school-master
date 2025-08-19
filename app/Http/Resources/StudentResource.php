<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return[
             'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'mother_last_name' => $this->mother_last_name,
            'class' => $this->class,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'address' => $this->address,
            'student_mobile' => $this->student_mobile,
            'parent_mobile' => $this->parent_mobile,
            'landline' => $this->landline,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
