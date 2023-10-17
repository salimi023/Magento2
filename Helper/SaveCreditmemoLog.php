<?php
 
 namespace Vendor\Magento\Helper;

 use Vendor\Magento\Model\CreditmemoLogFactory as CreditmemoLogFactory; 

 class SaveCreditmemoLog extends \Magento\Framework\App\Helper\AbstractHelper 
{
    /**
     * Model Factory
     * 
     * @var CreditmemoLogFactory
     */
    protected $modelFactory; 

    /**
     * CreditmemoLog constructor
     *     
     * @param CreditmemoLogFactory $modelFactory      
     */

    public function __construct(       
        CreditmemoLogFactory $modelFactory                       
        ) 
    {        
        $this->modelFactory = $modelFactory;                
    }

    public function saveCreditmemoLog($data)
    {                                             
        $model = $this->modelFactory->create();
        
        $model->setOrderId($data[0]);
        $model->setAccountCode($data[1]);
        $model->setSku($data[2]);
        $model->setQuantity($data[3]);
        $model->setType($data[4]);
        $model->setGrandTotal($data[5]);
        $model->setCreatedAt($data[6]);
        //$model->setUpdatedAt($data[7]);
        $model->setFilename($data['filename']);              
        
        return $model->save();
    }
}