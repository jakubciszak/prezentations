<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Membership\Template\ActivityTemplate;
use App\Membership\Template\StageDefinition;
use App\Membership\Template\StepDefinition;
use App\Membership\Template\TemplateRegistry;
use Symfony\Component\Yaml\Yaml;

final class YamlTemplateLoader
{
    public function __construct(private readonly string $templatesDir) {}

    public function loadAll(): TemplateRegistry
    {
        $registry = new TemplateRegistry();

        foreach (glob($this->templatesDir . '/*.yaml') as $file) {
            $template = $this->loadFile($file);
            $registry->register($template);
        }

        return $registry;
    }

    public function loadFile(string $path): ActivityTemplate
    {
        $data = Yaml::parseFile($path);

        $stages = array_map(
            fn(array $stageDef) => new StageDefinition(
                id: $stageDef['id'],
                name: $stageDef['name'],
                steps: array_map(
                    fn(array $stepDef) => new StepDefinition(
                        id: $stepDef['id'],
                        name: $stepDef['name'],
                        service: $stepDef['service'],
                        action: $stepDef['action'],
                        outcomes: $stepDef['outcomes'],
                        requiredMetadata: $stepDef['required_metadata'] ?? [],
                        participants: $stepDef['participants'] ?? [],
                    ),
                    $stageDef['steps'],
                ),
            ),
            $data['stages'],
        );

        return new ActivityTemplate(
            name: $data['name'],
            activityType: $data['client_type'],
            version: $data['version'],
            description: $data['description'],
            stages: $stages,
        );
    }
}
