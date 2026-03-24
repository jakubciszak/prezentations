<?php

declare(strict_types=1);

namespace App\Onboarding\Template;

/**
 * Registry of available onboarding templates.
 * Templates are loaded from YAML files and indexed by client_type.
 */
final class TemplateRegistry
{
    /** @var array<string, OnboardingTemplate> */
    private array $templates = [];

    public function register(OnboardingTemplate $template): void
    {
        $this->templates[$template->clientType] = $template;
    }

    public function getForClientType(string $clientType): OnboardingTemplate
    {
        return $this->templates[$clientType]
            ?? throw new \InvalidArgumentException("No template registered for client type: {$clientType}");
    }

    /** @return string[] */
    public function availableClientTypes(): array
    {
        return array_keys($this->templates);
    }
}
