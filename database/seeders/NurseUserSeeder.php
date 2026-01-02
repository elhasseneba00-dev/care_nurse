<?php

namespace Database\Seeders;

use App\Models\NurseProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NurseUserSeeder extends Seeder
{
    public function run(): void
    {
        $nurses = [
            [
                'phone' => '22233333333',
                'full_name' => 'Fatima Mint Ahmed',
                'password' => Hash::make('nurse123'),
                'profile' => [
                    'diploma' => 'Diplôme d\'État Infirmier',
                    'experience_years' => 5,
                    'bio' => 'Infirmière expérimentée spécialisée en soins à domicile et injections.',
                    'city' => 'Nouakchott',
                    'address' => 'Tevragh Zeina',
                    'lat' => 18.102,
                    'lng' => -15.958,
                    'coverage_km' => 10,
                    'price_min' => 1000,
                    'price_max' => 3000,
                    'verified' => true,
                ],
            ],
            [
                'phone' => '22244444444',
                'full_name' => 'Mohamed Ould Cheikh',
                'password' => Hash::make('nurse123'),
                'profile' => [
                    'diploma' => 'Diplôme d\'État Infirmier',
                    'experience_years' => 3,
                    'bio' => 'Infirmier qualifié avec expérience en urgences et soins généraux.',
                    'city' => 'Nouakchott',
                    'address' => 'Ksar',
                    'lat' => 18.089,
                    'lng' => -15.975,
                    'coverage_km' => 15,
                    'price_min' => 800,
                    'price_max' => 2500,
                    'verified' => true,
                ],
            ],
            [
                'phone' => '22255555555',
                'full_name' => 'Aminata Ba',
                'password' => Hash::make('nurse123'),
                'profile' => [
                    'diploma' => 'Licence en Sciences Infirmières',
                    'experience_years' => 8,
                    'bio' => 'Infirmière senior avec expertise en pédiatrie et soins post-opératoires.',
                    'city' => 'Nouakchott',
                    'address' => 'Arafat',
                    'lat' => 18.123,
                    'lng' => -15.945,
                    'coverage_km' => 12,
                    'price_min' => 1200,
                    'price_max' => 3500,
                    'verified' => true,
                ],
            ],
            [
                'phone' => '22266666666',
                'full_name' => 'Hassan Abdallah',
                'password' => Hash::make('nurse123'),
                'profile' => [
                    'diploma' => 'Diplôme d\'État Infirmier',
                    'experience_years' => 2,
                    'bio' => 'Infirmier débutant motivé, disponible pour soins basiques et accompagnement.',
                    'city' => 'Nouakchott',
                    'address' => 'Sebkha',
                    'lat' => 18.096,
                    'lng' => -15.968,
                    'coverage_km' => 8,
                    'price_min' => 700,
                    'price_max' => 2000,
                    'verified' => false, // Not verified yet
                ],
            ],
        ];

        foreach ($nurses as $nurseData) {
            $profile = $nurseData['profile'];
            unset($nurseData['profile']);

            $user = User::query()->updateOrCreate(
                ['phone' => $nurseData['phone']],
                array_merge($nurseData, [
                    'role' => 'NURSE',
                    'status' => 'ACTIVE',
                    'email' => null,
                ])
            );

            NurseProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $profile
            );
        }
    }
}
