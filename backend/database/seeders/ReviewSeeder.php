<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $reviews = [
            'nike-white-thermo-fit-pullover-training-hoodie' => [
                ['Maya R.', '👩🏽', 5, 'The thermo fabric is seriously warm without feeling heavy. Sized up for layering — perfect.', 6],
                ['Tom K.', '🧑🏻', 4, 'Great fit and the zip neck keeps the wind out. Wish it had a hood pocket.', 14],
                ['Zara A.', '👩🏻', 5, 'Third Nike hoodie, still my favourite. The white colour has not yellowed after washing.', 22],
            ],
            'smart-watch-wh22-6-fitness-tracker' => [
                ['Leo P.', '🧑🏿', 5, 'Battery genuinely lasts a week and the sleep tracking is spot on.', 3],
                ['Ines G.', '👩🏼', 5, 'Bought it for running — GPS lock is fast and the display stays readable in sun.', 11],
                ['Ravi S.', '🧑🏾', 4, 'Great watch for the money. Strap could be softer out of the box.', 19],
            ],
            'lightweight-white-nike-training-shoes' => [
                ['Chloe M.', '👩🏼', 5, 'Light as a feather, zero break-in period. Ordered a second pair.', 5],
                ['Dmitri V.', '🧑🏻', 4, 'Snug fit — go half a size up if you have wide feet.', 12],
            ],
            'premium-boxing-gloves-for-pro-training' => [
                ['Coach Ray', '🧑🏿', 5, 'Wrist support is excellent for bag work. Padding holds up after months.', 8],
                ['Amina B.', '👩🏽', 4, 'Comfortable, but the closure strap runs a little long.', 17],
            ],
            'wireless-studio-headphones-pro' => [
                ['SoundCheck', '🧑🏻', 5, 'ANC kills the office hum completely. Multipoint with laptop + phone just works.', 4],
                ['Jules D.', '👩🏿', 5, 'Forty hours is not a typo — I charge these once a week.', 9],
                ['Pete H.', '🧑🏼', 4, 'Bass is warm, app EQ could be more detailed.', 15],
            ],
            'club-kit-1-recurve-archery-bow' => [
                ['ArcheryNate', '🧑🏼', 4, 'Solid starter bow, smooth draw. Arrows are basic but you outgrow them fast.', 13],
                ['Kim L.', '👩🏻', 5, 'Set up in ten minutes, groups tightened up quickly.', 25],
            ],
            'tennis-rackets-for-beginners' => [
                ['FirstServe', '🧑🏿', 4, 'Light and forgiving — exactly what a beginner needs.', 7],
                ['Ella T.', '👩🏼', 5, 'Grip swaps easily, my coach approved.', 21],
            ],
            'new-balance-574-classic-sneakers' => [
                ['Marco B.', '🧑🏻', 5, 'The grey colourway goes with everything. True to size.', 10],
                ['Nia W.', '👩🏾', 4, 'Comfortable all day, slight width in the toe box.', 18],
            ],
        ];

        foreach ($reviews as $slug => $items) {
            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                continue;
            }

            foreach ($items as [$author, $avatar, $rating, $comment, $daysAgo]) {
                Review::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'author' => $author,
                    ],
                    [
                        'avatar' => $avatar,
                        'rating' => $rating,
                        'comment' => $comment,
                        'created_at' => now()->subDays($daysAgo),
                    ],
                );
            }
        }
    }
}
