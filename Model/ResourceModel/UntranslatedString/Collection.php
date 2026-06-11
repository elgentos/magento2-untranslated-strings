<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString;

use Elgentos\UntranslatedStrings\Model\ResourceModel\UntranslatedString as ResourceModel;
use Elgentos\UntranslatedStrings\Model\UntranslatedString as Model;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
