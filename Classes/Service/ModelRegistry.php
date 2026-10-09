<?php

declare(strict_types=1);

namespace Hn\Agent\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * The models editors may pick per task, maintained in the extension
 * configuration (settings.php → EXTENSIONS.agent):
 *
 *   'model'  => 'anthropic/claude-haiku-4.5',          // default
 *   'models' => "anthropic/claude-haiku-4.5 = Claude Haiku 4.5\n"
 *             . "anthropic/claude-sonnet-4.5 = Claude Sonnet 4.5",
 *
 * One model per line, "slug = Label" (label optional). An array
 * (slug => label) is accepted as well. The default model is always
 * available, even when it is missing from the list.
 */
class ModelRegistry
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function getDefaultModel(): string
    {
        $config = $this->extensionConfiguration->get('agent');
        return trim((string)($config['model'] ?? '')) ?: 'anthropic/claude-haiku-4.5';
    }

    /**
     * @return array<string, string> slug => label, default model first.
     */
    public function getAvailableModels(): array
    {
        $config = $this->extensionConfiguration->get('agent');
        $raw = $config['models'] ?? '';

        $models = [];
        if (is_array($raw)) {
            foreach ($raw as $slug => $label) {
                $this->addModel($models, (string)$slug, (string)$label);
            }
        } else {
            foreach (preg_split('/\R/', (string)$raw) ?: [] as $line) {
                [$slug, $label] = array_pad(explode('=', $line, 2), 2, '');
                $this->addModel($models, $slug, $label);
            }
        }

        $default = $this->getDefaultModel();
        $defaultLabel = $models[$default] ?? $default;
        unset($models[$default]);
        return [$default => $defaultLabel] + $models;
    }

    /**
     * The model to use for a task: the requested one if it is configured,
     * otherwise the default (e.g. when a model was removed from the list).
     */
    public function resolve(?string $requestedModel): string
    {
        $requestedModel = trim((string)$requestedModel);
        if ($requestedModel !== '' && isset($this->getAvailableModels()[$requestedModel])) {
            return $requestedModel;
        }
        return $this->getDefaultModel();
    }

    /**
     * @param array<string, string> $models
     */
    private function addModel(array &$models, string $slug, string $label): void
    {
        $slug = trim($slug);
        if ($slug === '' || str_starts_with($slug, '#')) {
            return;
        }
        $label = trim($label);
        $models[$slug] = $label !== '' ? $label : $slug;
    }
}
