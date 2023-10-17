<?php

namespace Vendor\Magento\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class ConfigValues extends AbstractHelper
{   
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * ConfigValues constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param Context $context
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Context $context
    )
    {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    
    public function getScopeConfig($configPath)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }
}