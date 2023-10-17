<?php
namespace Vendor\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Vendor\Magento\Helper\ExportCreditmemo;

class CreditMemo implements ObserverInterface 
{   
    protected $exportCreditmemo;

    public function __construct(ExportCreditmemo $exportCreditmemo) 
    {
        $this->exportCreditmemo = $exportCreditmemo;
    }    
    
    public function execute(Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();               
        
        if(!empty($creditmemo)) {
            try {
                $this->exportCreditmemo->export($creditmemo, 'refund');            
             } catch(Exception $e) {}
        }        
    }
}