<?php

declare(strict_types=1);

namespace App\Membership\Template;

/**
 * Registry of available loyalty activity templates.
 * Templates are loaded from YAML files and indexed by activity_type.
 */
final class TemplateRegistry
{
    /** @var array<string, ActivityTemplate> */
    private array $templates = [];

    public function register(ActivityTemplate $template): void
    {
        $this->templates[$template->activityType] = $template;
    }

    public function getForActivityType(string $activityType): ActivityTemplate
    {
        return $this->templates[$activityType]
            ?? throw new \InvalidArgumentException("No template registered for activity type: {$activityType}");
    }

    /** @return string[] */
    public function availableActivityTypes(): array
    {
        return array_keys($this->templates);
    }
}
