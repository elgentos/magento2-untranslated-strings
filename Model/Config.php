<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'untranslated_strings/general/enabled';
    public const XML_PATH_API_KEY = 'untranslated_strings/general/api_key';
    public const XML_PATH_MODEL = 'untranslated_strings/general/model';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getApiKey(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_API_KEY);

        return $value === '' ? '' : $this->encryptor->decrypt($value);
    }

    public function getModel(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_MODEL) ?: 'claude-opus-4-8';
    }
}
