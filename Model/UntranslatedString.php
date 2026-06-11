<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model;

use Magento\Framework\Model\AbstractModel;

class UntranslatedString extends AbstractModel
{
    public const STATUS_NEW = 'new';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_ERROR = 'error';

    protected function _construct()
    {
        $this->_init(ResourceModel\UntranslatedString::class);
    }
}
