<?php

declare(strict_types=1);

namespace App\Onboarding\Engine;

use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\StepId;
use App\Onboarding\Model\Transition;
use App\Onboarding\Template\StepDefinition;
use JakubCiszak\RuleEngine\Api\NestedRuleApi;
use Munus\Collection\Stream;
use Munus\Control\Option;

/**
 * Resolves which transition to take based on step outcome.
 *
 * Uses jakubciszak/rule-engine to evaluate outcome-matching rules
 * and munus Option/Stream for safe functional processing.
 */
final class TransitionResolver
{
    /**
     * @return Option<Transition>
     */
    public function resolve(StepDefinition $stepDef, Outcome $outcome): Option
    {
        return Stream::ofAll($this->buildTransitionRules($stepDef))
            ->find(fn(array $rule) => $this->evaluateOutcomeRule($rule, $outcome))
            ->map(fn(array $rule) => $this->toTransition($rule));
    }

    /**
     * Build rule definitions from step's outcome map.
     * Each outcome becomes a rule that checks if the actual outcome matches.
     *
     * @return list<array{outcome: string, terminal: bool, case_outcome: ?string, next_step: ?string}>
     */
    private function buildTransitionRules(StepDefinition $stepDef): array
    {
        $rules = [];
        foreach ($stepDef->outcomes as $outcomeName => $config) {
            $rules[] = [
                'outcome' => $outcomeName,
                'terminal' => $config['terminal'] ?? false,
                'case_outcome' => $config['case_outcome'] ?? null,
                'next_step' => $config['next_step'] ?? null,
            ];
        }

        return $rules;
    }

    /**
     * Use rule-engine to evaluate whether this outcome matches the rule.
     */
    private function evaluateOutcomeRule(array $rule, Outcome $outcome): bool
    {
        $ruleDefinition = [
            '==' => [
                ['var' => 'actual_outcome'],
                $rule['outcome'],
            ],
        ];

        $data = ['actual_outcome' => $outcome->value];

        return NestedRuleApi::evaluate($ruleDefinition, $data);
    }

    private function toTransition(array $rule): Transition
    {
        if ($rule['terminal']) {
            $caseOutcome = CaseOutcome::tryFrom($rule['case_outcome'] ?? '')
                ?? throw new \RuntimeException("Unknown case outcome: {$rule['case_outcome']}");

            return Transition::terminal($caseOutcome);
        }

        return Transition::toNextStep(new StepId($rule['next_step']));
    }
}
