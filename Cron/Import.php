<?php

namespace Vendor\Magento\Cron;

use Vendor\Magento\Helper\ConfigValues;
use Magento\Framework\App\Filesystem\DirectoryList as DirectoryList;
use Magento\Framework\File\Csv as CSV;
use Vendor\Magento\Model\InvoicedImportFactory as InvoicedImportFactory;
use Vendor\Magento\Model\BackorderImportFactory as BackorderImportFactory;

class Import 
{
     /**
     * Directory List
     * 
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * CSV 
     */
    protected $csv;

    /**
     * Invoiced Factory
     * 
     * @var InvoicedImportFactory
     */
    protected $invoicedFactory;

    /**
     * Backorder Factory
     * 
     * @var BackorderImportFactory
     */
    protected $backorderFactory;

    // Invoiced Order CSV Values
    const INVOICED_ACCOUNT_CODE_INDEX = 0;
    const INVOICED_SPACENET_INVOICE_NUMBER_INDEX = 1;
    const INVOICED_SPACENET_ORDER_NUMBER_INDEX = 2;
    const INVOICED_SPACENET_INVOICE_DATE_INDEX = 3;
    const INVOICED_ALT_REFERENCE_NUMBER_INDEX = 4;
    const INVOICED_SKU_INDEX = 5;
    const INVOICED_INVOICED_QTY_INDEX = 6;
    const INVOICED_INVOICED_VALUE = 7;
    const INVOICED_FILENAME_INDEX = 8;
    const INVOICED_CREATED_AT_INDEX = 9;

    // Backorder CSV Values
    const BACKORDER_ACCOUNT_CODE_INDEX = 0;
    const BACKORDER_SPACENET_ORDER_NO_INDEX = 1;
    const BACKORDER_ALT_REFERENCE_NUMBER_INDEX = 2;
    const BACKORDER_SKU_INDEX = 3;
    const BACKORDER_REQUEST_DATE_INDEX = 4;
    const BACKORDER_DELIVERY_DATE_INDEX = 5;
    const BACKORDER_ORDER_QTY_INDEX = 6;
    const BACKORDER_QTY_OUTSTANDING_INDEX = 7;
    const BACKORDER_ORDER_VALUE_INDEX = 8;
    const BACKORDER_FILENAME_INDEX = 9;
    const BACKORDER_CREATED_AT_INDEX = 10;

    public function __construct(        
        DirectoryList $directoryList,        
        CSV $csv,
        InvoicedImportFactory $invoicedFactory,
        BackorderImportFactory $backorderFactory                                       
     ) 
     {       
        $this->directoryList = $directoryList;
        $this->csv = $csv;
        $this->invoicedFactory = $invoicedFactory; 
        $this->backorderFactory = $backorderFactory;                                                
     }     

    public function execute() 
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();                
        $moduleAttributes = $objectManager->create('Vendor\Magento\Helper\ConfigValues');

        /** Module Attributes */
        
        // Invoice Order Import Status&FTP
        $invoiceOrderImportStatus = $moduleAttributes->getScopeConfig('vendor_magento/general_invoice/enable_invoice');                
        
        $invoiceOrderFtp = [
            'host' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_host_invoice'),
            'port' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_port_invoice'),
            'user' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_user_invoice'),
            'pass' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_password_invoice'),
            'folder' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_folder_invoice'),
            'filename' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_file_invoice'),
            'ssl' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_invoice/ftp_ssl_invoice') 
        ];                    

        // Backorder Import Status&FTP
        $backorderImportStatus = $moduleAttributes->getScopeConfig('vendor_magento/general_backorder/enable_backorder');
        
        $backorderFtp = [
            'host' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_host_backorder'),
            'port' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_port_backorder'),
            'user' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_user_backorder'),
            'pass' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_password_backorder'),
            'folder' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_folder_backorder'),
            'filename' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_file_backorder'),
            'ssl' => $moduleAttributes->getScopeConfig('vendor_magento/ftp_backorder/ftp_ssl_backorder') 
        ];

        $chdirResult = false;
        $files = [];               
        
        /** Invoice Order Import */
        if($invoiceOrderImportStatus) {                       
            
            $ftpLogin = false;
            
            try {
                $ftpConnection = $this->connectFtp($invoiceOrderFtp);
                $ftpLogin = ftp_login($ftpConnection, $invoiceOrderFtp['user'], $invoiceOrderFtp['pass']);        
            } catch(Exception $e) {}            

            if($ftpLogin) {
                
                try {
                    $listFiles = $this->listFiles($ftpConnection, $invoiceOrderFtp['folder']);
                } catch(Exception $se) {}
                
                if(!empty($listFiles)) {
                    $files = array_diff($listFiles, ['.', '..']);                    

                    if(count($files) > 0) {
                        
                        foreach($files as $index => $fileName) {

                            if(preg_match($invoiceOrderFtp['filename'], $fileName)) {
                                // File data
                                $localFile = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/spacenet_import/invoice_order/' . $fileName;
                                $ftpDownloadFile = ftp_get($ftpConnection, $localFile, $fileName, FTP_BINARY);

                                $pathInfo = pathinfo($fileName);
                                $file = $pathInfo['filename'];
                                $extension = $pathInfo['extension'];
                                $folder = $invoiceOrderFtp['folder'];

                                $invoicedLoggedFileName = $file . '_' . date('YmdHis');
                                $invoicedCreatedAt = date('Y-m-d H:i:s');
                                
                                if($ftpDownloadFile) {
                                    $csvData = $this->csv->getData($localFile);

                                    if(!empty($csvData)) {
                                        unset($csvData[0]);

                                        foreach($csvData as $importData) {
                                            $importData[self::INVOICED_FILENAME_INDEX] = $invoicedLoggedFileName;
                                            $importData[self::INVOICED_CREATED_AT_INDEX] = $invoicedCreatedAt;                                            
                                            $importData[self::INVOICED_SPACENET_INVOICE_DATE_INDEX] = $this->convertStringToDate($importData[self::INVOICED_SPACENET_INVOICE_DATE_INDEX]);                                           
                                            $this->saveData('invoiced', $importData);
                                        }
                                    }                                   
                                }
                            }
                            
                            // Replacing processed file to archive                           
                            $archive = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/magento/invoice_order_archive/' . $file . '_' . date('YmdHis') . '.csv'; 
                            copy($localFile, $archive);                   
                            unlink($localFile);
                            ftp_delete($ftpConnection, "/$folder/$file.$extension");  
                        }
                    }               
                }                                
            }
        }

        /** Backorder Import */
        if($backorderImportStatus) {                       
            
            $ftpLogin = false;
            
            try {
                $ftpConnection = $this->connectFtp($backorderFtp);
                $ftpLogin = ftp_login($ftpConnection, $backorderFtp['user'], $backorderFtp['pass']);        
            } catch(Exception $e) {}            

            if($ftpLogin) {
                
                try {
                    $listFiles = $this->listFiles($ftpConnection, $backorderFtp['folder']);
                } catch(Exception $se) {}
                
                if(!empty($listFiles)) {
                    $files = array_diff($listFiles, ['.', '..']);                    

                    if(count($files) > 0) {
                        
                        foreach($files as $index => $fileName) {

                            if(preg_match($backorderFtp['filename'], $fileName)) {
                                // File data
                                $localFile = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/magento/backorder/' . $fileName;
                                $ftpDownloadFile = ftp_get($ftpConnection, $localFile, $fileName, FTP_BINARY);
                                
                                $pathInfo = pathinfo($fileName);
                                $file = $pathInfo['filename'];
                                $extension = $pathInfo['extension'];
                                $folder = $backorderFtp['folder'];

                                $backorderLoggedFileName = $file . '_' . date('YmdHis');
                                $backorderCreatedAt = date('Y-m-d H:i:s');                                

                                if($ftpDownloadFile) {
                                    $csvData = $this->csv->getData($localFile);                                    

                                    if(!empty($csvData)) {
                                        unset($csvData[0]);

                                        foreach($csvData as $importData) {
                                            $importData[self::BACKORDER_FILENAME_INDEX] = $backorderLoggedFileName;
                                            $importData[self::BACKORDER_CREATED_AT_INDEX] = $backorderCreatedAt;
                                            $importData[self::BACKORDER_REQUEST_DATE_INDEX] = $this->convertStringToDate($importData[self::BACKORDER_REQUEST_DATE_INDEX]);
                                            $importData[self::BACKORDER_DELIVERY_DATE_INDEX] = $this->convertStringToDate($importData[self::BACKORDER_DELIVERY_DATE_INDEX]);                                                                                                                                 
                                            $this->saveData('backorder', $importData);
                                        }
                                    }                                   
                                }
                            }
                            
                            // Replacing processed file to archive                            
                            $archive = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/magento/backorder_archive/' . $file . '_' . date('YmdHis') . '.csv'; 
                            copy($localFile, $archive);                   
                            unlink($localFile);
                            ftp_delete($ftpConnection, "/$folder/$file.$extension");  
                        }
                    }               
                }                                
            }
        }
    }    

    // FTP connection
    private function connectFtp($connectionData) 
    {
        if($connectionData['ssl'] == 1) {
            $ftpConnection = ftp_ssl_connect($connectionData['host'], $connectionData['port']);
        } else {
            $ftpConnection = ftp_connect($connectionData['host'], $connectionData['port']);
        }

        return $ftpConnection;
    }

    // Download Files
    private function listFiles($ftpConnection, $folder) 
    {
        $fileList = '';
        $chdirResult = ftp_chdir($ftpConnection, $folder);

        if($chdirResult) {
            $fileList = ftp_nlist($ftpConnection, ".");
        }
        
        return $fileList;
    }

    // Save Data
    private function saveData($type, $data)
    {            
        switch($type) {
             case 'invoiced':                                                            
                $invoiced = $this->invoicedFactory->create();
                
                $invoiced->setAccountCode($data[self::INVOICED_ACCOUNT_CODE_INDEX]);
                $invoiced->setSpacenetInvoiceNumber($data[self::INVOICED_SPACENET_INVOICE_NUMBER_INDEX]);
                $invoiced->setSpacenetOrderNumber($data[self::INVOICED_SPACENET_ORDER_NUMBER_INDEX]);
                $invoiced->setSpacenetInvoiceDate($data[self::INVOICED_SPACENET_INVOICE_DATE_INDEX]);
                $invoiced->setAltReferenceNumber($data[self::INVOICED_ALT_REFERENCE_NUMBER_INDEX]);
                $invoiced->setSku($data[self::INVOICED_SKU_INDEX]);
                $invoiced->setInvoicedQty($data[self::INVOICED_INVOICED_QTY_INDEX]);
                $invoiced->setInvoicedValue($data[self::INVOICED_INVOICED_VALUE]);
                $invoiced->setFilename($data[self::INVOICED_FILENAME_INDEX]);
                $invoiced->setCreatedAt($data[self::INVOICED_CREATED_AT_INDEX]); 

                $invoiced->save();
                break;
                
             case 'backorder':
                $backorder = $this->backorderFactory->create();

                $backorder->setAccountCode($data[self::BACKORDER_ACCOUNT_CODE_INDEX]);
                $backorder->setSpacenetOrderNumber($data[self::BACKORDER_SPACENET_ORDER_NO_INDEX]);
                $backorder->setAltReferenceNumber($data[self::BACKORDER_ALT_REFERENCE_NUMBER_INDEX]);
                $backorder->setSku($data[self::BACKORDER_SKU_INDEX]);
                $backorder->setRequestDate($data[self::BACKORDER_REQUEST_DATE_INDEX]);
                $backorder->setDeliveryDate($data[self::BACKORDER_DELIVERY_DATE_INDEX]);
                $backorder->setOrderQty($data[self::BACKORDER_ORDER_QTY_INDEX]);
                $backorder->setQuantityOutstanding($data[self::BACKORDER_QTY_OUTSTANDING_INDEX]);
                $backorder->setOrderValue($data[self::BACKORDER_ORDER_VALUE_INDEX]);
                $backorder->setFilename($data[self::BACKORDER_FILENAME_INDEX]);
                $backorder->setCreatedAt($data[self::BACKORDER_CREATED_AT_INDEX]); 

                $backorder->save();              
                break;
        }      
    }

    // Converting string to date
    private function convertStringToDate($string) 
    {               
        $year = substr($string, 0, 4);
        $month = substr($string, 4, 2);
        $day = substr($string, 6, 2);
        
        return $year . '-'. $month . '-' . $day;        
    }     
}