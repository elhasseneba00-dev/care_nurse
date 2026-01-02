<?php

namespace Database\Seeders;

use App\Models\CareRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class CareRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Get sample patient and nurse users
        $patient1 = User::query()->where('phone', '22277777777')->where('role', 'PATIENT')->first();
        $patient2 = User::query()->where('phone', '22288888888')->where('role', 'PATIENT')->first();
        $nurse1 = User::query()->where('phone', '22233333333')->where('role', 'NURSE')->first();
        $nurse2 = User::query()->where('phone', '22244444444')->where('role', 'NURSE')->first();

        if (!$patient1 || !$patient2 || !$nurse1 || !$nurse2) {
            $this->command->warn('Sample users not found. Please run NurseUserSeeder and PatientUserSeeder first.');
            return;
        }

        // Create sample care requests in different statuses
        $requests = [
            [
                'patient_user_id' => $patient1->id,
                'nurse_user_id' => null,
                'visibility' => 'BROADCAST',
                'target_nurse_user_id' => null,
                'care_type' => 'Injection',
                'description' => 'Besoin d\'une injection d\'insuline ce soir.',
                'scheduled_at' => now()->addHours(3),
                'address' => 'Tevragh Zeina, Rue 42',
                'city' => 'Nouakchott',
                'lat' => 18.105,
                'lng' => -15.960,
                'status' => 'PENDING',
                'expires_at' => now()->addHours(4),
            ],
            [
                'patient_user_id' => $patient1->id,
                'nurse_user_id' => $nurse1->id,
                'visibility' => 'BROADCAST',
                'target_nurse_user_id' => null,
                'care_type' => 'Prise de tension',
                'description' => 'Contrôle de la tension artérielle.',
                'scheduled_at' => now()->subDay(),
                'address' => 'Tevragh Zeina, Rue 42',
                'city' => 'Nouakchott',
                'lat' => 18.105,
                'lng' => -15.960,
                'status' => 'ACCEPTED',
                'expires_at' => null,
            ],
            [
                'patient_user_id' => $patient2->id,
                'nurse_user_id' => $nurse2->id,
                'visibility' => 'BROADCAST',
                'target_nurse_user_id' => null,
                'care_type' => 'Pansement',
                'description' => 'Changement de pansement post-opératoire.',
                'scheduled_at' => now()->subDays(3),
                'address' => 'Ksar, Avenue de la République',
                'city' => 'Nouakchott',
                'lat' => 18.092,
                'lng' => -15.978,
                'status' => 'DONE',
                'expires_at' => null,
            ],
            [
                'patient_user_id' => $patient2->id,
                'nurse_user_id' => null,
                'visibility' => 'TARGETED',
                'target_nurse_user_id' => $nurse1->id,
                'care_type' => 'Contrôle glycémie',
                'description' => 'Contrôle de glycémie et conseil nutrition.',
                'scheduled_at' => now()->addDays(1),
                'address' => 'Ksar, Avenue de la République',
                'city' => 'Nouakchott',
                'lat' => 18.092,
                'lng' => -15.978,
                'status' => 'PENDING',
                'expires_at' => now()->addDays(2),
            ],
        ];

        foreach ($requests as $requestData) {
            CareRequest::query()->firstOrCreate(
                [
                    'patient_user_id' => $requestData['patient_user_id'],
                    'care_type' => $requestData['care_type'],
                    'scheduled_at' => $requestData['scheduled_at'],
                ],
                $requestData
            );
        }

        $this->command->info('Sample care requests created successfully.');
    }
}
