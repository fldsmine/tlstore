<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $demoUsers = DB::table('users')
            ->whereIn('email', collect(range(1, 10))->map(fn (int $number) => sprintf('demo.user%02d@tlstore.test', $number)))->get(['id', 'email']);
        $now = now();
        $testimonials = [
            'Getting started was straightforward. I could see the available app tasks and the reward before deciding what to do.',
            'The app review questions gave me a clear structure for sharing useful feedback.',
            'I like that an ad share stays pending until the team checks the public post I submitted.',
            'The separate review and share wallets make it easy to understand where my earnings came from.',
            'Location coverage is clearly explained, so I can tell when a task is available to my country.',
            'The task history helps me follow submissions and see whether they are still waiting for review.',
            'Having app screenshots with the listing gives me helpful context before I start a review task.',
            'I can browse campaigns first and see when a subscription or extra location coverage is needed.',
            'The top-up wallet and earned wallets are kept separate, which makes the payment rules easier to follow.',
            'The notification inbox gives me one place to check updates about submitted tasks and account activity.',
			'Earned $500 in my first month! The platform is legit and pays fast.',
			'i have come across people who are into this and they are making more money and honestly it is real',
			'hey guys hope to see more reason of having this App',
			'Best side hustle I have found. Easy to use and great community.',
        ]; 
		
		$roleList = ['AI Creator','Freelancer','Business man','Farmer','Rector','Developer','Designer','Student'];

        foreach ($testimonials as $index => $content) {
            $user = $demoUsers->firstWhere('email', sprintf('demo.user%02d@tlstore.test', $index + 1));
            if (! $user) {
                continue;
            }

            DB::table('testimonials')->updateOrInsert(
                ['user_id' => $user->id, 'role' => $roleList[$index % count($roleList)] ],
                [
                    'content' => $content,
                    'rating' => $index === 4 ? 4 : 5,
                    'status' => 'approved',
                    'approved_by' => null,
                    'approved_at' => $now,
                    'admin_notes' => '',
                    'is_featured' => rand(0, 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
