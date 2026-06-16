<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $activeJobIds = Job::active()->pluck('id')->toArray();

        if (empty($activeJobIds)) {
            $this->command->warn('No active jobs found — skipping application seeding.');
            return;
        }

        $candidates = User::where('role', 'candidate')->get();

        foreach ($candidates as $candidate) {
            $count   = rand(2, 5);
            $count   = min($count, count($activeJobIds));
            $jobIds  = fake()->randomElements($activeJobIds, $count, false);
            $applied = Application::where('user_id', $candidate->id)->pluck('job_id')->flip();

            foreach ($jobIds as $jobId) {
                if ($applied->has($jobId)) {
                    continue;
                }

                Application::factory()->create([
                    'user_id' => $candidate->id,
                    'job_id'  => $jobId,
                ]);

                $applied->put($jobId, true);
            }
        }
    }
}
