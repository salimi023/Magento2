<?php

namespace Vendor\Magento\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function map($data, $type, $objectManager = false)
    {
        $mappedData = [
            [
                'order_id',
                'account_code',
                'sku',
                'quantity',                        
                'type',
                'return_type',
                'grand_total',                                              
                'created_at',
                'updated_at'                        
            ]
        ];

        switch($type) {
            
            case 'rma':
                $inputData = [
                    $data['order_id'],
                    $data['user_id'],
                    $data['sku'],                                 
                    $data['quantity'],                            
                    'RMA',
                    $data['return_label']['value'],    
                    $data['grand_total'],                 
                    date('Y-m-d H:i:s'),
                    $data['updated_at']                
                ];
                break;

            case 'refund':
                $order = $objectManager->create('\Magento\Sales\Model\Order')->load($data->getOrderId());

                $inputData = [
                    $data->getOrderId(),
                    $order->getCustomerId(),
                    $order->getSku(),
                    $order->getQty(), 
                    'Refund',
                    $order->getLabel(),
                    $data->getGrandTotal(),                                                
                    $data->getCreatedAt(),
                    $data->getUpdatedAt()
                ];
                break;
        }

        array_push($mappedData, $inputData);

        return $mappedData;
    }
}