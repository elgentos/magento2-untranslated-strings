<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Buffers untranslated strings during the request and flushes them
 * to the database once at shutdown.
 */
class Collector
{
    private const TABLE = 'elgentos_untranslated_strings';

    /**
     * Safety valve so a runaway page cannot buffer unbounded amounts of strings.
     */
    private const MAX_BUFFER_SIZE = 500;

    private const MAX_URL_LENGTH = 255;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $buffer = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger
    ) {
    }

    public function add(string $text, string $locale): void
    {
        $storeId = $this->getStoreId();
        $hash = sha1($locale . '|' . $storeId . '|' . $text);

        if (isset($this->buffer[$hash])) {
            $this->buffer[$hash]['count']++;

            return;
        }

        if (count($this->buffer) >= self::MAX_BUFFER_SIZE) {
            return;
        }

        $this->buffer[$hash] = [
            'string_hash' => $hash,
            'string' => $text,
            'locale' => $locale,
            'store_id' => $storeId,
            'url' => $this->getUrl(),
            'count' => 1,
        ];
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $buffer = $this->buffer;
        $this->buffer = [];

        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::TABLE);

            $select = $connection->select()
                ->from($table, 'string_hash')
                ->where('string_hash IN (?)', array_keys($buffer));
            $existing = array_flip($connection->fetchCol($select));

            $inserts = [];
            foreach ($buffer as $hash => $row) {
                if (isset($existing[$hash])) {
                    $connection->update(
                        $table,
                        [
                            'count' => new \Zend_Db_Expr(
                                $connection->quoteIdentifier('count') . ' + ' . (int)$row['count']
                            ),
                        ],
                        ['string_hash = ?' => $hash]
                    );
                } else {
                    $inserts[] = $row;
                }
            }

            if ($inserts !== []) {
                // insertOnDuplicate tolerates concurrent requests racing on the unique hash.
                $connection->insertOnDuplicate($table, $inserts, ['count']);
            }
        } catch (\Throwable $exception) {
            try {
                $this->logger->error(
                    'Elgentos_UntranslatedStrings: could not save untranslated strings: '
                    . $exception->getMessage()
                );
            } catch (\Throwable) {
                // Shutdown context; nothing sensible left to do.
            }
        }
    }

    public function __destruct()
    {
        $this->flush();
    }

    private function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getUrl(): ?string
    {
        if ($this->request instanceof HttpRequest) {
            return mb_substr($this->request->getUriString(), 0, self::MAX_URL_LENGTH);
        }

        return null;
    }
}
