<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\Translation;

use Magento\Framework\App\ResourceConnection;

/**
 * Writes translations into Magento's core "translation" table, which the
 * framework loads natively on top of the i18n CSV dictionaries.
 */
class TranslationWriter
{
    private const TABLE = 'translation';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param array<string, string> $translations Map of source string => translation
     */
    public function save(array $translations, string $locale, int $storeId): void
    {
        if ($translations === []) {
            return;
        }

        $rows = [];
        foreach ($translations as $source => $translation) {
            $rows[] = [
                'string' => $source,
                'store_id' => $storeId,
                'translate' => $translation,
                'locale' => $locale,
                'crc_string' => crc32($source),
            ];
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::TABLE),
            $rows,
            ['translate']
        );
    }
}
