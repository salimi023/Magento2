<?php

namespace Vendor\Magento\Helper;

use Magento\Framework\App\Filesystem\DirectoryList as DirectoryList;
use Vendor\Magento\Helper\ConfigValues;
use Vendor\Magento\Helper\SaveCreditmemoLog;
use Vendor\Magento\Helper\Data;
use Magento\Framework\App\Response\Http\FileFactory as FileFactory;
use Magento\Framework\File\Csv as CsvProcessor;

class ExportCreditmemo extends \Magento\Framework\App\Helper\AbstractHelper  
{
    /**
     * File Factory
     * 
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * CSV Processor
     * 
     * @var CsvProcessor
     */
    protected $csvProcessor;
    
    /**
     * Directory List
     * 
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * Data Mapping
     * 
     * @var Data
     */
    protected $data;

    public function __construct(
        DirectoryList $directoryList,
        FileFactory $fileFactory,
        CsvProcessor $csvProcessor,        
        SaveCreditmemoLog $saveCreditmemoLog,
        Data $data        
     ) 
     {        
        $this->directoryList = $directoryList;
        $this->fileFactory = $fileFactory;
        $this->csvProcessor = $csvProcessor;        
        $this->saveCreditmemoLog = $saveCreditmemoLog;
        $this->data = $data;        
     }       
    
    public function export($data, $type)
    {                
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();                
        $moduleAttributes = $objectManager->create('Vendor\Magento\Helper\ConfigValues');
        $creditmemo = $type === 'rma' ? $data->toArray() : $data;
        $creditmemoExportData = $this->data->map($creditmemo, $type, $objectManager);               

        // Module Status
        $creditmemoStatus = $moduleAttributes->getScopeConfig('vendor_magento/general_creditmemo/enable_creditmemo');                

        if($creditmemoStatus) {
            // FTP Credentials
            $port = !empty($moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_port_creditmemo')) ?  $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_port_creditmemo') : 21;

            $creditmemoHost = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_host_creditmemo');
            $creditmemoPort = $port;
            $creditmemoUser = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_user_creditmemo');
            $creditmemoPass = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_password_creditmemo');
            $creditmemoFolder = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_folder_creditmemo');
            $creditmemoFile = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_file_creditmemo');
            $creditmemoSSL = $moduleAttributes->getScopeConfig('vendor_magento/ftp_creditmemo/ftp_ssl_creditmemo');                                                       

            // Generate CSV file  
            $creditmemoUploadFolder = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/creditmemo_export/upload/';
            $creditmemoArchiveFolder = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/creditmemo_export/archive/';                
            $fileName = 'creditmemo_' . date('YmdHis') . '.csv';
            $logFilename = 'creditmemo_' . date('YmdHis');
            $uploadFilePath = $creditmemoUploadFolder . $fileName;
            $archiveFilePath = $creditmemoArchiveFolder . $fileName;            

            // File generation
            $this->csvProcessor
                ->setDelimiter(',')
                ->setEnclosure('"')
                ->appendData(
                    $uploadFilePath,
                    $creditmemoExportData                                   
                );

            // Change of line ending
            if(file_exists($uploadFilePath)) {
                $f = file_get_contents($uploadFilePath);
                $f = preg_replace("/(?<!r)\n/", "\r\n", $f);
                $f = preg_replace("/\" \+/", "\"+", $f);
                $f = file_put_contents($uploadFilePath, $f);                                                         
            }

            // Upload CSV through FTP 
            $connection = $creditmemoSSL ? ftp_ssl_connect($creditmemoHost, $creditmemoPort) : ftp_connect($creditmemoHost, $creditmemoPort);
                
            if($connection) {
                $login = ftp_login($connection, $creditmemoUser, $creditmemoPass);
                ftp_pasv($connection, true);
                $chdirResult = ftp_chdir($connection, $creditmemoFolder);
                $tempHandle = fopen($uploadFilePath, 'r+');
                $uploadResult = ftp_fput($connection, $fileName, $tempHandle, FTP_BINARY);
                ftp_close($connection);

                // Archive uploaded file
                if($uploadResult) {
                    $creditmemoLogData = $creditmemoExportData[1];
                    $creditmemoLogData['filename'] = $logFilename;
                    $this->saveCreditmemoLog->saveCreditmemoLog($creditmemoLogdata);
                    rename($uploadFilePath, $archiveFilePath);
                }
            }            
        }
    } 
}