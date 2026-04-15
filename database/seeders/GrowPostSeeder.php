<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class GrowPostSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing seeded users — fallback to first user if not found
        $studio     = User::where('role', 'studio')->first();
        $instructor = User::where('role', 'instructor')->first();
        $admin      = User::where('role', 'admin')->first();

        // If no users exist at all, abort with helpful message
        if (!$studio && !$instructor && !$admin) {
            $this->command->warn('⚠️  No users found. Run DatabaseSeeder first: php artisan db:seed');
            return;
        }

        $studioId     = $studio?->id     ?? $admin?->id ?? 1;
        $instructorId = $instructor?->id ?? $admin?->id ?? 1;

        DB::table('grow_posts')->insert([

            // ── 1. TRAINING (featured) ─────────────────────────────
            [
                'user_id'      => $studioId,
                'type'         => 'training',
                'title'        => 'Imagine Studios Thailand — Pilates Teacher Training',
                'subtitle'     => 'Internationally accredited 500h program',
                'description'  => 'Escape to paradise and deepen your practice with Imagine Studios immersive Pilates Teacher Training in Thailand. Set amongst tropical landscapes, this internationally recognised program blends classical foundations with contemporary movement, anatomy, and intelligent cueing. Train with master educators, connect with a global community, and graduate confident, inspired, and ready to teach anywhere in the world.',
                'location'     => 'Koh Samui, Thailand',
                'date_from'    => '2026-11-01',
                'date_to'      => '2026-11-28',
                'price'        => 'From $3,800 USD',
                'spots'        => 12,
                'spots_left'   => 5,
                'disciplines'  => json_encode(['Reformer Pilates', 'Mat Pilates']),
                'tags'         => json_encode(['500h', 'Accredited', 'Residential']),
                'images'       => json_encode([]),
                'external_url' => 'https://imaginestudios.com',
                'status'       => 'approved',
                'is_featured'  => 1,
                'color'        => '#2DA4D6',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

            // ── 2. RETREAT ─────────────────────────────────────────
            [
                'user_id'      => $studioId,
                'type'         => 'retreat',
                'title'        => 'Bali Yoga & Wellness Retreat',
                'subtitle'     => 'Immersive 10-day experience in Ubud',
                'description'  => 'Join us for a transformative 10-day retreat in the spiritual heart of Bali. Daily yoga, meditation, sound healing, and breathwork sessions are woven into a program designed to restore and reconnect. Stay in a stunning eco-resort surrounded by rice terraces, enjoy organic meals, and return home renewed.',
                'location'     => 'Ubud, Bali, Indonesia',
                'date_from'    => '2026-08-05',
                'date_to'      => '2026-08-15',
                'price'        => 'From $2,200 USD (accommodation included)',
                'spots'        => 16,
                'spots_left'   => 8,
                'disciplines'  => json_encode(['Vinyasa Yoga', 'Yin Yoga', 'Meditation', 'Breathwork / Pranayama']),
                'tags'         => json_encode(['All-Inclusive', 'Accommodation', '10 Days']),
                'images'       => json_encode([]),
                'external_url' => 'https://ubudwellness.com',
                'status'       => 'approved',
                'is_featured'  => 0,
                'color'        => '#6BE6A4',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

            // ── 3. EVENT (featured) ────────────────────────────────
            [
                'user_id'      => $instructorId,
                'type'         => 'event',
                'title'        => 'Moving Guru Global Meetup — Bali',
                'subtitle'     => 'Connect with wellness instructors worldwide',
                'description'  => 'The first Moving Guru community event brings together instructors and studio owners from across the globe for a weekend of connection, workshops, and inspiration. Meet your fellow members, share stories from the road, attend skill-sharing sessions, and celebrate the global wellness community we are building together.',
                'location'     => 'Seminyak, Bali, Indonesia',
                'date_from'    => '2026-09-12',
                'date_to'      => '2026-09-14',
                'price'        => 'Free for Moving Guru members',
                'spots'        => 80,
                'spots_left'   => 34,
                'disciplines'  => json_encode([]),
                'tags'         => json_encode(['Community', 'Free', 'Networking']),
                'images'       => json_encode([]),
                'external_url' => null,
                'status'       => 'approved',
                'is_featured'  => 1,
                'color'        => '#f5fca6',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

            // ── 4. TRAINING ────────────────────────────────────────
            [
                'user_id'      => $studioId,
                'type'         => 'training',
                'title'        => 'Muay Thai Instructor Certification — Level 1 & 2',
                'subtitle'     => 'Internationally recognised program',
                'description'  => 'Earn your internationally recognised Muay Thai Instructor Certification at one of Thailand\'s most respected training camps. This intensive program covers technique, safety, curriculum design, and class management. Suitable for practitioners with 2+ years experience looking to teach professionally.',
                'location'     => 'Chiang Mai, Thailand',
                'date_from'    => '2026-10-01',
                'date_to'      => '2026-10-21',
                'price'        => 'From $1,600 USD',
                'spots'        => 20,
                'spots_left'   => 11,
                'disciplines'  => json_encode(['Muay Thai', 'Boxing']),
                'tags'         => json_encode(['Certification', '3 Weeks', 'Level 1 & 2']),
                'images'       => json_encode([]),
                'external_url' => 'https://tigermuaythai.com',
                'status'       => 'approved',
                'is_featured'  => 0,
                'color'        => '#CE4F56',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

            // ── 5. RETREAT ─────────────────────────────────────────
            [
                'user_id'      => $instructorId,
                'type'         => 'retreat',
                'title'        => 'Dance & Movement Therapy Retreat — Portugal',
                'subtitle'     => 'Expressive arts healing in the Alentejo',
                'description'  => 'A week of somatic movement, expressive dance, and therapeutic arts in a beautiful farmhouse in the Portuguese countryside. Open to all movement practitioners and those curious about the healing power of dance. Includes movement sessions, creative workshops, farm-to-table meals, and daily swims.',
                'location'     => 'Alentejo, Portugal',
                'date_from'    => '2026-07-06',
                'date_to'      => '2026-07-13',
                'price'        => 'From €1,400 (accommodation included)',
                'spots'        => 14,
                'spots_left'   => 3,
                'disciplines'  => json_encode(['Contemporary Dance', 'Dance Movement Therapy', 'Somatic Movement']),
                'tags'         => json_encode(['Almost Full!', 'All-Inclusive', 'Creative']),
                'images'       => json_encode([]),
                'external_url' => 'https://movimentoarts.pt',
                'status'       => 'approved',
                'is_featured'  => 0,
                'color'        => '#7F77DD',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

            // ── 6. TRAINING (pending — to test admin approval flow) ─
            [
                'user_id'      => $instructorId,
                'type'         => 'training',
                'title'        => 'Yoga Alliance RYT-500 — Rishikesh',
                'subtitle'     => 'Traditional Hatha & Ashtanga deep dive',
                'description'  => 'Complete your 500-hour Yoga Alliance certification at the birthplace of yoga. This residential program in Rishikesh covers advanced asana practice, pranayama, philosophy, anatomy, teaching methodology, and business of yoga. Conducted by master teachers with decades of experience in traditional lineages.',
                'location'     => 'Rishikesh, India',
                'date_from'    => '2026-12-01',
                'date_to'      => '2027-01-15',
                'price'        => 'From $2,400 USD (accommodation included)',
                'spots'        => 25,
                'spots_left'   => 9,
                'disciplines'  => json_encode(['Hatha Yoga', 'Ashtanga Yoga', 'Yin Yoga', 'Meditation']),
                'tags'         => json_encode(['Yoga Alliance', 'RYT-500', 'Residential']),
                'images'       => json_encode([]),
                'external_url' => 'https://rishikeshyogapeeth.com',
                'status'       => 'pending',   // ← pending to test admin approval
                'is_featured'  => 0,
                'color'        => '#E89560',
                'expires_at'   => null,
                'boost_until'  => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],

        ]);

        $this->command->info('✅ Seeded: 6 grow posts (5 approved, 1 pending)');
    }
}