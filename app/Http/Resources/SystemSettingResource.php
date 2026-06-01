<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->localizedValue($this->value),
            'type' => $this->type,
            'group' => $this->group,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function localizedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $hasLanguageKeys = collect(LocalizedValue::LANGUAGES)
            ->contains(fn (string $language): bool => array_key_exists($language, $value));

        return $hasLanguageKeys ? LocalizedValue::get($value, $value['ru'] ?? null) : $value;
    }
}
