<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class UntranslatedString extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('elgentos_untranslated_strings', 'entity_id');
    }
}
