<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\Queue;

use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString as UntranslatedStringResource;
use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString\CollectionFactory;
use Elgentos\UntranslatedStrings\Model\Translation\ClaudeTranslator;
use Elgentos\UntranslatedStrings\Model\Translation\TranslationWriter;
use Elgentos\UntranslatedStrings\Model\UntranslatedString;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Consumes "elgentos.untranslatedstrings.translate" queue messages: translates
 * the referenced strings with Claude and stores the results in Magento's
 * translation table.
 */
class TranslateConsumer
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly UntranslatedStringResource $resource,
        private readonly ClaudeTranslator $translator,
        private readonly TranslationWriter $translationWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $message): void
    {
        try {
            $data = $this->json->unserialize($message);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(
                'Elgentos_UntranslatedStrings: invalid queue message: ' . $exception->getMessage()
            );

            return;
        }

        $ids = array_map('intval', (array)($data['ids'] ?? []));
        if ($ids === []) {
            return;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $ids]);

        $groups = [];
        foreach ($collection as $item) {
            $groups[$item->getData('locale') . '|' . $item->getData('store_id')][] = $item;
        }

        $translatedAny = false;
        foreach ($groups as $items) {
            $translatedAny = $this->processGroup($items) || $translatedAny;
        }

        if ($translatedAny) {
            $this->cacheTypeList->invalidate(['translate', 'full_page']);
        }
    }

    /**
     * @param UntranslatedString[] $items
     */
    private function processGroup(array $items): bool
    {
        $locale = (string)$items[0]->getData('locale');
        $storeId = (int)$items[0]->getData('store_id');
        $strings = array_values(array_unique(array_map(
            static fn (UntranslatedString $item): string => (string)$item->getData('string'),
            $items
        )));

        try {
            $translations = $this->translator->translate($strings, $locale);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Elgentos_UntranslatedStrings: translating %d string(s) to %s failed: %s',
                count($strings),
                $locale,
                $exception->getMessage()
            ));
            foreach ($items as $item) {
                $this->markError($item, $exception->getMessage());
            }

            return false;
        }

        $this->translationWriter->save($translations, $locale, $storeId);

        foreach ($items as $item) {
            if (isset($translations[$item->getData('string')])) {
                try {
                    $this->resource->delete($item);
                } catch (\Throwable $exception) {
                    $this->logger->error(
                        'Elgentos_UntranslatedStrings: could not delete translated row: '
                        . $exception->getMessage()
                    );
                }
            } else {
                $this->markError($item, 'The model did not return a translation for this string.');
            }
        }

        return $translations !== [];
    }

    private function markError(UntranslatedString $item, string $message): void
    {
        try {
            $item->setData('status', UntranslatedString::STATUS_ERROR);
            $item->setData('error', $message);
            $this->resource->save($item);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Elgentos_UntranslatedStrings: could not mark row as failed: ' . $exception->getMessage()
            );
        }
    }
}
