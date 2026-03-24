<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Onboarding\Template\OnboardingTemplate;
use App\Onboarding\Template\StageDefinition;
use App\Onboarding\Template\StepDefinition;
use App\Onboarding\Template\TemplateRegistry;
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

    public function loadFile(string $path): OnboardingTemplate
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
                        requiredMetadata: $stepDef['required_metadata'] ?? $stepDef['required_documents'] ?? [],
                        participants: $stepDef['participants'] ?? [],
                    ),
                    $stageDef['steps'],
                ),
            ),
            $data['stages'],
        );

        return new OnboardingTemplate(
            name: $data['name'],
            clientType: $data['client_type'],
            version: $data['version'],
            description: $data['description'],
            stages: $stages,
        );
    }
}
