<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class JobListingSeeder extends Seeder
{
    public function run(): void
    {
        $studio = User::where('role', 'studio')->first();

        if (!$studio) {
            $this->command->warn('⚠️  No studio user found. Run DatabaseSeeder first.');
            return;
        }

        $studioId = $studio->id;
        $now      = now();

        $rows = [
            ['Vinyasa Yoga Teacher — Bali Studio', 'hire', 'permanent', 'Bali, Indonesia', ['Vinyasa Yoga', 'Hatha Yoga'], '2026-06-01', '6 months', 'USD 2,500/mo + housing', 'cert_200hr', 1, 0, 0],
            ['Reformer Pilates Specialist Wanted', 'hire', 'permanent', 'Sydney, Australia', ['Reformer Pilates', 'Mat Pilates'], '2026-05-15', 'Ongoing', 'AUD 65/hr', 'cert_comprehensive', 2, 0, 1],
            ['Surf Coach — 3 Month Residency', 'hire', 'temporary', 'Byron Bay, Australia', ['Surfing'], '2026-07-01', '3 months', 'AUD 70/hr + accommodation', 'none', 1, 0, 2],
            ['Boxing Coach — Substitute Cover', 'hire', 'substitute', 'Tokyo, Japan', ['Boxing', 'Kickboxing'], '2026-05-10', '2 months', 'JPY 8,000/session', 'none', 1, 0, 3],
            ['Mat Pilates Swap — Lisbon Studio', 'swap', 'permanent', 'Lisbon, Portugal', ['Mat Pilates', 'Barre'], '2026-08-01', '1 month', 'Studio exchange — mutual cover', 'cert_200hr', 1, 0, 4],
            ['Yoga Teacher Swap — Bangkok ↔ Anywhere', 'swap', 'permanent', 'Bangkok, Thailand', ['Vinyasa Yoga', 'Yin Yoga'], '2026-06-15', '2 months', 'Mutual cover + room', 'cert_200hr', 1, 0, 5],
            ['Personal Trainer — Weekend Cover', 'hire', 'weekend_cover', 'London, United Kingdom', ['Personal Trainer'], '2026-05-01', 'Ongoing', 'GBP 45/session', 'cert_specialized', 1, 0, 6],
            ['Climbing Instructor — Casual', 'hire', 'casual', 'Chamonix, France', ['Climbing / Bouldering'], '2026-06-10', 'Ongoing', 'EUR 35/hr', 'cert_specialized', 1, 0, 7],
            ['Energy Exchange — Yoga in Costa Rica', 'energy_exchange', 'temporary', 'Manuel Antonio, Costa Rica', ['Yin Yoga', 'Restorative Yoga'], '2026-07-15', '6 weeks', 'Accommodation + meals', 'cert_200hr', 1, 0, 8],
            ['Naturopath & Wellness Coach', 'hire', 'permanent', 'Byron Bay, Australia', ['Naturopath', 'Nutritionist'], '2026-05-20', 'Ongoing', 'AUD 90/hr', 'bachelors', 1, 0, 9],
            ['Tennis Coach — Summer Camp', 'hire', 'temporary', 'Marbella, Spain', ['Tennis'], '2026-07-01', '8 weeks', 'EUR 2,800/mo + housing', 'cert_specialized', 2, 0, 10],
            ['Acupuncturist for Boutique Wellness', 'hire', 'permanent', 'Singapore', ['Acupuncture', 'Traditional Chinese Medicine'], '2026-06-01', 'Ongoing', 'SGD 80/session', 'masters', 1, 0, 11],
            ['AFL Coach — Season Contract', 'hire', 'temporary', 'Melbourne, Australia', ['AFL'], '2026-04-01', '6 months', 'AUD 4,500/mo', 'cert_specialized', 1, 0, 12],
            ['Snow Sports Instructor — Winter Season', 'hire', 'temporary', 'Niseko, Japan', ['Snow Sports'], '2026-12-01', '3 months', 'JPY 280,000/mo + lift pass', 'cert_specialized', 3, 0, 13],
            ['Reformer Pilates Swap — Tokyo ↔ Bali', 'swap', 'permanent', 'Tokyo, Japan', ['Reformer Pilates'], '2026-09-01', '1 month', 'Studio swap exchange', 'cert_comprehensive', 1, 0, 14],
            ['Massage Therapist — On-call', 'hire', 'casual', 'Ubud, Indonesia', ['Massage', 'Bowen Therapy'], '2026-05-15', 'Ongoing', 'USD 30/hr', 'diploma', 2, 0, 15],
            ['Energy Exchange — Sound Healing in India', 'energy_exchange', 'temporary', 'Rishikesh, India', ['Sound Bath / Sound Healing', 'Meditation'], '2026-08-15', '1 month', 'Room + board + workshops', 'none', 1, 0, 16],
            ['Barre Instructor — Permanent Hire', 'hire', 'permanent', 'Cape Town, South Africa', ['Barre'], '2026-06-01', 'Ongoing', 'ZAR 350/hr', 'cert_200hr', 1, 0, 17],
            ['Sailing Coach — Mediterranean Summer', 'hire', 'temporary', 'Palma, Spain', ['Sailing'], '2026-06-15', '12 weeks', 'EUR 3,200/mo', 'cert_specialized', 1, 0, 18],
            ['Kickboxing Coach Swap — LA ↔ Bangkok', 'swap', 'permanent', 'Los Angeles, United States', ['Kickboxing', 'Muay Thai'], '2026-09-15', '6 weeks', 'Studio exchange', 'none', 1, 0, 19],
        ];

        $payload = collect($rows)->map(function ($r) use ($studioId, $now) {
            [$title, $type, $roleType, $location, $disciplines, $startDate,
             $duration, $compensation, $qual, $vacancies, $filled, $daysAgo] = $r;

            return [
                'studio_id'           => $studioId,
                'title'               => $title,
                'type'                => $type,
                'role_type'           => $roleType,
                'description'         => "Looking for a passionate teacher to join us in {$location}. Strong communication and a love for the craft are essential. Open to local applicants and travelling instructors.",
                'disciplines'         => json_encode($disciplines),
                'location'            => $location,
                'start_date'          => $startDate,
                'duration'             => $duration,
                'compensation'        => $compensation,
                'requirements'        => '2+ years teaching experience, fluent English, references on request.',
                'qualification_level' => $qual,
                'is_active'           => true,
                'vacancies'           => $vacancies,
                'positions_filled'    => $filled,
                'created_at'          => $now->copy()->subDays($daysAgo),
                'updated_at'          => $now->copy()->subDays($daysAgo),
            ];
        })->all();

        DB::table('job_listings')->insert($payload);

        $this->command->info('✅ Seeded ' . count($payload) . ' job listings.');
    }
}