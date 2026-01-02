<?php

namespace Database\Seeders;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientUserSeeder extends Seeder
{
    public function run(): void
    {
        $patients = [
            [
                'phone' => '22277777777',
                'full_name' => 'Mariam Mint Sidi',
                'password' => Hash::make('patient123'),
                'profile' => [
                    'birth_date' => '1985-03-15',
                    'gender' => 'F',
                    'city' => 'Nouakchott',
                    'address' => 'Tevragh Zeina, Rue 42',
                    'lat' => 18.105,
                    'lng' => -15.960,
                    'medical_notes' => 'Diabète type 2, nécessite injections régulières d\'insuline.',
                ],
            ],
            [
                'phone' => '22288888888',
                'full_name' => 'Abdallah Ould Mohamed',
                'password' => Hash::make('patient123'),
                'profile' => [
                    'birth_date' => '1975-07-22',
                    'gender' => 'M',
                    'city' => 'Nouakchott',
                    'address' => 'Ksar, Avenue de la République',
                    'lat' => 18.092,
                    'lng' => -15.978,
                    'medical_notes' => 'Hypertension, traitement régulier nécessaire.',
                ],
            ],
            [
                'phone' => '22299999999',
                'full_name' => 'Aïcha Bint Ali',
                'password' => Hash::make('patient123'),
                'profile' => [
                    'birth_date' => '1990-11-08',
                    'gender' => 'F',
                    'city' => 'Nouakchott',
                    'address' => 'Arafat, Quartier 6',
                    'lat' => 18.120,
                    'lng' => -15.948,
                    'medical_notes' => 'Enceinte, suivi prénatal requis.',
                ],
            ],
        ];

        foreach ($patients as $patientData) {
            $profile = $patientData['profile'];
            unset($patientData['profile']);

            $user = User::query()->updateOrCreate(
                ['phone' => $patientData['phone']],
                array_merge($patientData, [
                    'role' => 'PATIENT',
                    'status' => 'ACTIVE',
                    'email' => null,
                ])
            );

            PatientProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $profile
            );
        }
    }
}
