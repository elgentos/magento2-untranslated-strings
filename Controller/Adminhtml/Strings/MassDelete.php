<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Controller\Adminhtml\Strings;

use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString as UntranslatedStringResource;
use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Elgentos_UntranslatedStrings::untranslated_strings';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly UntranslatedStringResource $resource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());

        $count = 0;
        foreach ($collection as $item) {
            $this->resource->delete($item);
            $count++;
        }

        $this->messageManager->addSuccessMessage(__('%1 string(s) have been deleted.', $count));

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
