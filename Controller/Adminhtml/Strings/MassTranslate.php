<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Controller\Adminhtml\Strings;

use Elgentos\UntranslatedStrings\Model\Config;
use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString\CollectionFactory;
use Elgentos\UntranslatedStrings\Model\UntranslatedString;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Ui\Component\MassAction\Filter;

class MassTranslate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Elgentos_UntranslatedStrings::untranslated_strings';

    public const TOPIC_NAME = 'elgentos.untranslatedstrings.translate';

    /**
     * Strings per queue message; also the batch size of a single Claude API call.
     */
    private const CHUNK_SIZE = 40;

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PublisherInterface $publisher,
        private readonly Json $json,
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/');

        if ($this->config->getApiKey() === '') {
            $this->messageManager->addErrorMessage(
                __('Configure the Anthropic API key under Stores → Configuration → Advanced → Untranslated Strings first.')
            );

            return $resultRedirect;
        }

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $ids = array_map('intval', $collection->getAllIds());

        if ($ids === []) {
            $this->messageManager->addWarningMessage(__('No strings were selected.'));

            return $resultRedirect;
        }

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $this->publisher->publish(self::TOPIC_NAME, $this->json->serialize(['ids' => $chunk]));
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName('elgentos_untranslated_strings'),
            ['status' => UntranslatedString::STATUS_QUEUED, 'error' => null],
            ['entity_id IN (?)' => $ids]
        );

        $this->messageManager->addSuccessMessage(
            __(
                '%1 string(s) have been queued for translation. They are processed by the "%2" queue consumer.',
                count($ids),
                'elgentosUntranslatedStringsTranslate'
            )
        );

        return $resultRedirect;
    }
}
