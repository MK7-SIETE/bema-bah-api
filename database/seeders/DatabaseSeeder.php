<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Super admin ────────────────────────────────
        $superExists = DB::table('users')->where('email', env('SUPER_ADMIN_EMAIL', 'admin@mukubamotors.zm'))->exists();
        if (!$superExists) {
            DB::table('users')->insert([
                'name'       => 'Super Admin',
                'email'      => env('SUPER_ADMIN_EMAIL', 'admin@mukubamotors.zm'),
                'password'   => Hash::make(env('SUPER_ADMIN_PASSWORD', 'Platform$uper2025!')),
                'role'       => 'super',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Dealer account ─────────────────────────────
        $dealerExists = DB::table('users')->where('email', 'dealer@mukubamotors.zm')->exists();
        if (!$dealerExists) {
            DB::table('users')->insert([
                'name'       => 'Mukuba Motors Admin',
                'email'      => 'dealer@mukubamotors.zm',
                'password'   => Hash::make('GoldAdmin2025!'),
                'role'       => 'dealer',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $dealer = DB::table('users')->where('email', 'dealer@mukubamotors.zm')->first();

        // ── Sample cars ────────────────────────────────
        $carCount = DB::table('cars')->count();
        if ($carCount === 0) {
            $cars = [
                [
                    'make' => 'Toyota', 'model' => 'Land Cruiser V8', 'year' => 2020,
                    'price' => 285000, 'mileage' => 45000, 'fuel' => 'Diesel',
                    'transmission' => 'Automatic', 'color' => 'White', 'body_type' => 'SUV',
                    'condition' => 'Foreign Used', 'engine' => '4.5L V8', 'power' => '232hp',
                    'torque' => '650Nm', 'seats' => 7, 'doors' => 4, 'drive' => '4WD',
                    'description' => 'Immaculate Land Cruiser V8 in excellent condition. Full service history.',
                    'features' => json_encode(['Leather seats', 'Sunroof', 'Reverse camera', 'Cruise control', 'Climate control']),
                    'is_featured' => 1, 'is_available' => 1, 'views' => 142,
                    'images' => [
                        'https://images.unsplash.com/photo-1594950012543-e9d2bd2c6074?w=800&q=80',
                        'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
                    ],
                ],
                [
                    'make' => 'BMW', 'model' => 'X5 xDrive40i', 'year' => 2021,
                    'price' => 420000, 'mileage' => 32000, 'fuel' => 'Petrol',
                    'transmission' => 'Automatic', 'color' => 'Black', 'body_type' => 'SUV',
                    'condition' => 'Foreign Used', 'engine' => '3.0L Inline-6', 'power' => '340hp',
                    'torque' => '450Nm', 'seats' => 5, 'doors' => 4, 'drive' => 'AWD',
                    'description' => 'Stunning BMW X5 with M-Sport package. Full options including panoramic roof.',
                    'features' => json_encode(['M-Sport package', 'Panoramic roof', 'Harman Kardon', 'Head-up display']),
                    'is_featured' => 1, 'is_available' => 1, 'views' => 218,
                    'images' => [
                        'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&q=80',
                        'https://images.unsplash.com/photo-1617814076668-8dfc6fe3b744?w=800&q=80',
                    ],
                ],
                [
                    'make' => 'Mercedes-Benz', 'model' => 'C300 AMG Line', 'year' => 2019,
                    'price' => 390000, 'mileage' => 61000, 'fuel' => 'Petrol',
                    'transmission' => 'Automatic', 'color' => 'Silver', 'body_type' => 'Sedan',
                    'condition' => 'Foreign Used', 'engine' => '2.0L Turbo', 'power' => '258hp',
                    'torque' => '400Nm', 'seats' => 5, 'doors' => 4, 'drive' => 'RWD',
                    'description' => 'Elegant Mercedes C300 AMG Line with full options.',
                    'features' => json_encode(['AMG body kit', 'Burmester sound', 'Memory seats', 'LED headlights']),
                    'is_featured' => 0, 'is_available' => 1, 'views' => 87,
                    'images' => [
                        'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80',
                        'https://images.unsplash.com/photo-1580273916550-e323be2ae537?w=800&q=80',
                    ],
                ],
                [
                    'make' => 'Toyota', 'model' => 'Hilux Double Cab', 'year' => 2018,
                    'price' => 195000, 'mileage' => 88000, 'fuel' => 'Diesel',
                    'transmission' => 'Manual', 'color' => 'Silver', 'body_type' => 'Pickup',
                    'condition' => 'Foreign Used', 'engine' => '2.8L D-4D', 'power' => '177hp',
                    'torque' => '450Nm', 'seats' => 5, 'doors' => 4, 'drive' => '4WD',
                    'description' => 'Reliable Hilux in great working condition. Perfect for business and adventure.',
                    'features' => json_encode(['Canopy', 'Tow bar', 'Bull bar', 'Diff lock']),
                    'is_featured' => 0, 'is_available' => 1, 'views' => 64,
                    'images' => [
                        'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80',
                        'https://images.unsplash.com/photo-1504215680853-026ed2a45def?w=800&q=80',
                    ],
                ],
                [
                    'make' => 'Volkswagen', 'model' => 'Polo Vivo', 'year' => 2022,
                    'price' => 85000, 'mileage' => 18000, 'fuel' => 'Petrol',
                    'transmission' => 'Manual', 'color' => 'Red', 'body_type' => 'Hatchback',
                    'condition' => 'Local Used', 'engine' => '1.4L', 'power' => '85hp',
                    'torque' => '132Nm', 'seats' => 5, 'doors' => 4, 'drive' => 'FWD',
                    'description' => 'Near-new Polo Vivo with low mileage. Perfect city car.',
                    'features' => json_encode(['Bluetooth', 'USB', 'Electric windows', 'ABS']),
                    'is_featured' => 0, 'is_available' => 1, 'views' => 43,
                    'images' => [
                        'https://images.unsplash.com/photo-1609521263047-f8f205293f24?w=800&q=80',
                        'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
                    ],
                ],
                [
                    'make' => 'Land Rover', 'model' => 'Discovery Sport', 'year' => 2020,
                    'price' => 320000, 'mileage' => 52000, 'fuel' => 'Diesel',
                    'transmission' => 'Automatic', 'color' => 'Blue', 'body_type' => 'SUV',
                    'condition' => 'Foreign Used', 'engine' => '2.0L Ingenium', 'power' => '204hp',
                    'torque' => '430Nm', 'seats' => 7, 'doors' => 4, 'drive' => 'AWD',
                    'description' => 'Sophisticated Discovery Sport with 7 seats and full options.',
                    'features' => json_encode(['7 seats', 'Terrain response', 'Meridian sound', 'Pano roof']),
                    'is_featured' => 1, 'is_available' => 1, 'views' => 109,
                    'images' => [
                        'https://images.unsplash.com/photo-1519245659620-e859806a8d3b?w=800&q=80',
                        'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80',
                    ],
                ],
            ];

            foreach ($cars as $car) {
                $images = $car['images'];
                unset($car['images']);

                $carId = DB::table('cars')->insertGetId(array_merge($car, [
                    'user_id'    => $dealer->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                foreach ($images as $i => $url) {
                    DB::table('car_images')->insert([
                        'car_id'     => $carId,
                        'path'       => $url,
                        'sort_order' => $i,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // ── Sample messages ────────────────────────────
        $msgCount = DB::table('messages')->count();
        if ($msgCount === 0) {
            DB::table('messages')->insert([
                [
                    'name' => 'Mwansa Chilufya', 'email' => 'mwansa@email.com',
                    'phone' => '+260 97 123 4567',
                    'subject' => 'Enquiry about Land Cruiser',
                    'message' => 'Good morning, I am interested in the Toyota Land Cruiser V8. Is it still available?',
                    'car_id' => 1, 'type' => 'car_enquiry', 'is_read' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Grace Banda', 'email' => 'grace.banda@email.com',
                    'phone' => '+260 96 987 6543',
                    'subject' => 'Import request — Toyota Prado',
                    'message' => 'I would like to source a Toyota Prado 2022 from Japan. My budget is K250,000.',
                    'car_id' => null, 'type' => 'import_request', 'is_read' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'David Phiri', 'email' => 'd.phiri@company.zm',
                    'phone' => '+260 95 555 0101',
                    'subject' => 'BMW X5 — test drive request',
                    'message' => 'I am interested in the BMW X5. Can I schedule a test drive this Saturday?',
                    'car_id' => 2, 'type' => 'car_enquiry', 'is_read' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        }

        // ── Site config ────────────────────────────────
        $configs = [
            'dealership_name' => 'Mukuba Motors Ltd',
            'phone'           => '+260 97X XXX XXX',
            'email'           => 'info@mukubamotors.zm',
            'address'         => 'Plot 1234, Cairo Road, Lusaka, Zambia',
            'about'           => 'Mukuba Motors Limited is a Zambian premium pre-owned vehicle dealership.',
            'facebook'        => '',
            'instagram'       => '',
            'twitter'         => '',
        ];

        foreach ($configs as $key => $value) {
            $exists = DB::table('site_configs')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('site_configs')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
