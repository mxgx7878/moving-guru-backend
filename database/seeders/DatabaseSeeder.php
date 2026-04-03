<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserDetail;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. ADMIN ───────────────────────────────────────────
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@movingguru.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // ── 2. INSTRUCTOR ──────────────────────────────────────
        $instructor = User::create([
            'name'     => 'Sarah Mitchell',
            'email'    => 'instructor@movingguru.com',
            'password' => Hash::make('password'),
            'role'     => 'instructor',
        ]);

        UserDetail::create([
            'user_id'       => $instructor->id,
            'age'           => 28,
            'pronouns'      => 'She/Her',
            'studio'        => 'STRIVE Studio',
            'location'      => 'Sydney, Australia',
            'countryFrom'   => 'Australia',
            'travelingTo'   => 'Japan, UK, USA',
            'availability'  => 'May 2025 – Aug 2025',
            'availableFrom' => '2025-05-01',
            'availableTo'   => '2025-08-31',
            'flexibleDates' => true,
            'disciplines'   => ['Yoga', 'Pilates', 'Barre'],
            'languages'     => ['English', 'Spanish'],
            'openTo'        => ['Direct Hire', 'Swaps'],
            'profileStatus' => 'active',
            'bio'           => 'Certified yoga and pilates instructor with 6 years of experience. I love connecting with studios around the world and sharing movement.',
            'lookingFor'    => 'A welcoming studio in Asia or Europe for a 3-month residency.',
            'plan'          => 'monthly',
            'social_links'  => [
                ['instagram' => 'https://instagram.com/sarahmoves'],
                ['linkedin'  => 'https://linkedin.com/in/sarah-mitchell'],
            ],
            'gallery_photos' => [],
        ]);

        // ── 3. STUDIO ──────────────────────────────────────────
        $studio = User::create([
            'name'     => 'Zen Flow Studio',
            'email'    => 'studio@movingguru.com',
            'password' => Hash::make('password'),
            'role'     => 'studio',
        ]);

        UserDetail::create([
            'user_id'       => $studio->id,
            'studioName'    => 'Zen Flow Studio',
            'contactName'   => 'James Park',
            'location'      => 'Tokyo, Japan',
            'country'       => 'Japan',
            'phone'         => '+81-3-1234-5678',
            'website'       => 'https://zenflowstudio.jp',
            'studioSize'    => '6–15 instructors',
            'instagram'     => 'https://instagram.com/zenflowstudio',
            'disciplines'   => ['Yoga', 'Pilates', 'Meditation'],
            'openTo'        => ['Direct Hire', 'Swaps', 'Energy Exchange'],
            'profileStatus' => 'active',
            'bio'           => 'Zen Flow is a boutique wellness studio in the heart of Tokyo. We host international instructors year-round and love building a global community.',
            'plan'          => 'monthly',
            'social_links'  => [
                ['instagram' => 'https://instagram.com/zenflowstudio'],
            ],
            'gallery_photos' => [],
        ]);

        $this->command->info('✅ Seeded: admin, instructor, studio');
        $this->command->info('   admin@movingguru.com      / password');
        $this->command->info('   instructor@movingguru.com / password');
        $this->command->info('   studio@movingguru.com     / password');
    }
}