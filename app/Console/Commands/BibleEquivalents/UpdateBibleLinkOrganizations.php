<?php

namespace App\Console\Commands\BibleEquivalents;

use App\Models\Bible\BibleLink;
use App\Models\Organization\OrganizationTranslation;
use Illuminate\Console\Command;

class UpdateBibleLinkOrganizations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:bible_links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Preform Operations on the Bible Links Table';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $update_count = 0;
        $bible_links = BibleLink::where('organization_id', null)->get();
        $organization_translations = OrganizationTranslation::all();
        $skippedProviders = [];

        // Direct Matches
        foreach ($bible_links as $bible_link) {
            $organization = $organization_translations->where('name', $bible_link->provider)->first();
            if ($organization) {
                $bible_link->organization_id = $organization->organization_id;
                $bible_link->save();
                $update_count++;
                $skippedProviders[] = $bible_link->provider;
            }
        }

        // Fuzzy Matches
        foreach ($bible_links as $bible_link) {
            // If Already Processed Skip
            $skippedProviders = array_unique($skippedProviders);
            if (in_array($bible_link->provider, $skippedProviders)) {
                continue;
            }

            // Otherwise Fuzzy Search for Provider Name
            $organizatios = OrganizationTranslation::whereFuzzy('name', $bible_link->provider)
                ->getQuery()
                ->limit(5)
                ->get();

            if (empty($organizations) || $organizations->count() == 0) {
                continue;
            }

            // Present Data to User
            $this->comment("\n\n==========$bible_link->provider==========");
            $this->info(
                json_encode(
                    $organizations->pluck('name', 'organization_id'),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                )
            );

            // Get User Input
            $confirmed = false;
            $organization_id = $this->ask('Please enter the number of the Closest Match, if none just hit enter');
            if ($organization_id === 0) {
                $skippedProviders[] = $bible_link->provider;
                continue;
            }

            while ($confirmed === false) {
                // Validate Input
                if (!\in_array($organization_id, $organizations->pluck('organization_id')->toArray())) {
                    $confirmed = true;
                }

                // Save organization_id
                if ($organization_id) {
                    $links = $bible_links->where('provider', $bible_link->provider)->all();
                    foreach ($links as $link) {
                        $link->organization_id = $organization_id;
                        $link->save();
                        $update_count++;
                    }
                    $skippedProviders[] = $bible_link->provider;
                    $confirmed = true;
                }
            }
        }
    }
}
