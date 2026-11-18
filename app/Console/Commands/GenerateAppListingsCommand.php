<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AppListings\AgentClient;
use App\Services\AppListings\AgentPool;
use App\Services\AppListings\AppListingGenerator;
use App\Services\AppListings\ListingRepository;
use Illuminate\Console\Command;
use Throwable;

class GenerateAppListingsCommand extends Command
{
    protected $signature = 'listings:generate
        {--count=3 : How many listings to generate (1-3)}
        {--agent= : Force one configured agent by name instead of picking at random}
        {--no-images : Keep the agent\'s remote image URLs instead of downloading them}
        {--require-images : Treat a failed artwork download as an agent failure and try the next agent}
        {--dry-run : Print the listings without writing database records}';

    protected $description = 'Generate three real app listings with a randomly chosen AI agent, falling back to another agent when one fails';

    public function handle(
        AgentPool $pool,
        AppListingGenerator $generator,
        ListingRepository $repository,
    ): int {
        $count = max(1, min((int) config('ai-listings.max_count', 3), (int) $this->option('count')));
        $downloadImages = ! (bool) $this->option('no-images')
            && ! (bool) $this->option('dry-run');
        $requireImages = (bool) $this->option('require-images')
            || (bool) config('ai-listings.require_images', true);
        $existing = $repository->existing();
        $templateVersions = $repository->publishedTemplateVersions();

        if ($templateVersions->isEmpty()) {
            $this->error('Cannot generate listings: publish at least one review-question template with questions first.');

            return self::FAILURE;
        }
        $agents = $pool->candidates($this->option('agent'));

        if ($agents === []) {
            $this->error(
                'No AI agent is ready. Set at least one API key (OPENAI_API_KEY, ANTHROPIC_API_KEY, '
                .'GEMINI_API_KEY, GROQ_API_KEY, OPENROUTER_API_KEY, DEEPSEEK_API_KEY, MISTRAL_API_KEY) '
                .'and run `php artisan config:clear`.'
            );

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Generating %d listing(s); %d agent(s) available, tried in weighted random order.',
            $count,
            count($agents)
        ));

        try {
            $outcome = $pool->attempt(
                $agents,
                function (array $agent) use ($generator, $count, $existing, $downloadImages, $requireImages): array {
                    $this->line('  → asking '.$agent['name'].' ('.$agent['model'].')…');

                    return $generator->generate($agent, $count, $existing, $downloadImages, $requireImages);
                }
            );
        } catch (Throwable $error) {
            $this->newLine();
            $this->error(AgentClient::describe($error));

            return self::FAILURE;
        }

        foreach ($outcome['attempts'] as $failure) {
            $this->warn('  skipped '.$failure['agent'].': '.$failure['error']);
        }

        $listings = $outcome['result'];
        $this->newLine();
        $this->info(sprintf(
            'Generated %d listing(s) with %s (%s).',
            count($listings),
            $outcome['agent']['name'],
            $outcome['agent']['model']
        ));

        $this->table(
            ['Name', 'Store', 'Country', 'Currency', 'Reward', 'Max', 'Shots'],
            array_map(static fn (array $listing): array => [
                $listing['name'],
                $listing['store'],
                $listing['location'],
                $listing['currency'],
                $listing['review_reward'],
                $listing['maximum_review'],
                count($listing['screenshots']),
            ], $listings)
        );

        if ((bool) $this->option('dry-run')) {
            $this->warn('Dry run: no app, screenshot, or campaign records were written to the database.');

            return self::SUCCESS;
        }

        try {
            $created = $repository->store($listings);
        } catch (Throwable $error) {
            $this->error('Could not save the listings to the database: '.AgentClient::describe($error));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Saved %d app listing(s), screenshot set(s), and active review campaign(s) to the database.',
            count($created),
        ));

        $this->table(
            ['App ID', 'Campaign ID', 'Name', 'Question template'],
            array_map(static fn (array $record): array => [
                $record['app_id'],
                $record['campaign_id'],
                $record['name'],
                $record['template_name'].' (v'.$record['template_version'].')',
            ], $created),
        );

        return self::SUCCESS;
    }
}
