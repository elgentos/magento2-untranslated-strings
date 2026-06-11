<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Plugin;

use Elgentos\UntranslatedStrings\Model\Collector;
use Elgentos\UntranslatedStrings\Model\Config;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase\Renderer\Translate as TranslateRenderer;
use Magento\Framework\TranslateInterface;

/**
 * Detects phrases that are rendered without a translation in the
 * currently loaded translation dictionary.
 */
class DetectUntranslatedString
{
    private ?bool $enabled = null;

    private bool $processing = false;

    public function __construct(
        private readonly Config $config,
        private readonly Collector $collector,
        private readonly TranslateInterface $translate,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * @param string[] $source
     */
    public function afterRender(TranslateRenderer $subject, string $result, array $source): string
    {
        if ($this->processing || !$this->isEnabled()) {
            return $result;
        }

        $text = (string)end($source);
        if (trim($text) === '') {
            return $result;
        }

        $this->processing = true;
        try {
            if (!array_key_exists($text, $this->translate->getData())) {
                $this->collector->add($text, (string)$this->localeResolver->getLocale());
            }
        } catch (\Throwable) {
            // Detection must never break rendering.
        } finally {
            $this->processing = false;
        }

        return $result;
    }

    private function isEnabled(): bool
    {
        return $this->enabled ??= $this->config->isEnabled();
    }
}
