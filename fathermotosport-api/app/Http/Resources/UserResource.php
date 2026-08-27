<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'birth_date' => $this->birth_date?->toDateString(),
            'avatar' => $this->avatar,
            'status' => $this->status,
            'loyalty_discount_used' => $this->loyalty_discount_used,
            'email_verified_at' => $this->email_verified_at,
            'last_password_change' => $this->last_password_change,
            'security_reminder_dismissed_at' => $this->security_reminder_dismissed_at,
            'needs_security_reminder' => $this->needsSecurityReminder(),
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
