<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\Source;

use Elgentos\UntranslatedStrings\Model\UntranslatedString;
use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => UntranslatedString::STATUS_NEW, 'label' => __('New')],
            ['value' => UntranslatedString::STATUS_QUEUED, 'label' => __('Queued for translation')],
            ['value' => UntranslatedString::STATUS_ERROR, 'label' => __('Error')],
        ];
    }
}
