<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Components;

class GenerateOpenApiDoc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openApi:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the application and generate OpenAPI 3.0 documentation';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $version = 4;

        define('API_URL_DOCS', config('app.api_url'));

        $swagger = (new Generator)->scan([app_path()]);
        $searchVersion = 'v' . $version;
        $swagger->tags  = $this->swaggerVersionTags($swagger->tags, $searchVersion);
        $swagger->paths = $this->swaggerVersionPaths($swagger->paths, $searchVersion);
        $swagger->paths = $this->addCommonParameters($swagger->paths);
        $swagger->components  = $this->removeUnusedComponents($swagger);

        file_put_contents(public_path('openapi.json'), $swagger->toJson());
    }

    private function swaggerVersionTags($tags, $searchVersion) : array
    {
        $searchVersionInternal = $searchVersion . '_internal';
        foreach ($tags as $key => $tag) {
            if (Str::startsWith($tags[$key]->description, $searchVersionInternal) ||
                !Str::startsWith($tags[$key]->description, $searchVersion)
            ) {
                unset($tags[$key]);
            }
        }
        return Arr::flatten($tags);
    }

    private function swaggerVersionPaths($paths, $searchVersion) : array
    {
        $searchVersionInternal = $searchVersion . '_internal';
        foreach ($paths as $key => $path) {
            if (isset($path->get->operationId) &&
                (Str::startsWith($path->get->operationId, $searchVersionInternal) ||
                !Str::startsWith($path->get->operationId, $searchVersion))
            ) {
                unset($paths[$key]);
            }
            if (isset($path->put->operationId) &&
                (Str::startsWith($path->put->operationId, $searchVersionInternal) ||
                !Str::startsWith($path->put->operationId, $searchVersion))
            ) {
                unset($paths[$key]);
            }
            if (isset($path->post->operationId) &&
                (Str::startsWith($path->post->operationId, $searchVersionInternal) ||
                !Str::startsWith($path->post->operationId, $searchVersion))
            ) {
                unset($paths[$key]);
            }
            if (isset($path->delete->operationId) &&
                (Str::startsWith($path->delete->operationId, $searchVersionInternal) ||
                !Str::startsWith($path->delete->operationId, $searchVersion))
            ) {
                unset($paths[$key]);
            }
        }
        return $paths;
    }

    private function addCommonParameters($paths) : array
    {
        foreach ($paths as $path) {
            if (isset($path->get->operationId)) {
                $path->get->parameters = $this->addCommonParametersToPath($path->get->parameters);
            }
            if (isset($path->post->operationId)) {
                $path->post->parameters = $this->addCommonParametersToPath($path->post->parameters);
            }
            if (isset($path->put->operationId)) {
                $path->put->parameters = $this->addCommonParametersToPath($path->put->parameters);
            }
            if (isset($path->delete->operationId)) {
                $path->delete->parameters  = $this->addCommonParametersToPath($path->delete->parameters);
            }
        }
        return $paths;
    }

    private function addCommonParametersToPath($parameters) : array
    {
        if (gettype($parameters) == 'string') {
            $parameters = [];
        }
        $parameters[] = new Parameter(['ref' => '#/components/parameters/version_number']);
        return $parameters;
    }
    
    private function removeUnusedComponents(OpenApi $swagger) :?Components
    {
        $schema_regex = '/(?<=schemas\\/)(.*?)(?=\\/|")/m';
        preg_match_all($schema_regex, $swagger->toJson(), $matches);
        $schemas_used = array_unique($matches[0]);
        foreach ($swagger->components->schemas as $key => $schema) {
            if (!in_array($schema->schema, $schemas_used)) {
                unset($swagger->components->schemas[$key]);
            }
        }
        return $swagger->components;
    }
}
