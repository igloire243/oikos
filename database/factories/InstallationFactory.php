<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Installation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'nom' => 'Serveur principal',
            'url' => 'https://eglise.example.test',
        ];
    }

    /** Une installation déjà activée ; la clé de synchronisation en clair, si le test doit la présenter. */
    public function activee(?string $cle = null): static
    {
        return $this->state(function () use ($cle) {
            // Une clé par installation par défaut : la colonne est unique, comme en vrai.
            $cle ??= Str::random(48);

            return [
                'empreinte' => 'empreinte-'.fake()->unique()->regexify('[a-z0-9]{24}'),
                'cle_synchro_hash' => hash('sha256', $cle),
                'cle_synchro_apercu' => substr($cle, 0, 6).'…',
                'activee_le' => Carbon::now(),
                'vue_le' => Carbon::now(),
            ];
        });
    }
}
