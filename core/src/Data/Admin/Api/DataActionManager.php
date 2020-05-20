<?php
/**
 * Created by PhpStorm.
 * User: Thilina
 * Date: 8/19/17
 * Time: 8:02 AM
 */

namespace Data\Admin\Api;

use Classes\FileService;
use Classes\IceResponse;
use Classes\SubActionManager;
use Data\Common\Model\DataImport;
use Data\Common\Model\DataImportFile;
use Utils\LogManager;

class DataActionManager extends SubActionManager
{

    public function processDataFile($req)
    {
        $id = $req->id;
        $dataFile = new DataImportFile();
        $dataFile->Load("id = ?", array($id));
        if (empty($dataFile->id)) {
            return new IceResponse(IceResponse::ERROR, null);
        }
        $url = FileService::getInstance()->getFileUrl($dataFile->file);

        if (strstr($url, CLIENT_BASE_URL) !== false) {
            $url = str_replace(CLIENT_BASE_URL, CLIENT_BASE_PATH, $url);
        }

        LogManager::getInstance()->info("File Path:".$url);

        $data = file_get_contents($url);

        $dataImport = new DataImport();
        $dataImport->Load("id =?", array($dataFile->data_import_definition));
        if (empty($dataImport->id)) {
            return new IceResponse(IceResponse::ERROR, null);
        }

        $processClass = '\\Data\Admin\Import\\'.$dataImport->dataType;
        $processObj = new $processClass();
        $processObj->setModelObjectName($dataImport->objectType);
        $res = $processObj->process($data, $dataImport->id);
        if ($processObj->getLastStatus() === IceResponse::SUCCESS) {
            $dataFile->status = "Processed";
        } else {
            $dataFile->status = "Failed";
        }
        $dataFile->details = json_encode($res, JSON_PRETTY_PRINT);
        $dataFile->Save();
        return new IceResponse($processObj->getLastStatus(), $processObj->getResult());
    }

    private function processHeader($dataImportId, $data)
    {
    }

    public function downloadTemplate($req)
    {
        $dataImport = new DataImport();
        $dataImport->Load("id =?", array($req->id));

        $columns = json_decode($dataImport->columns);

        $headers = [];
        $sample = [];

        foreach ($columns as $column) {
            $headers[] = $column->name;
            $sample[] = $column->sampleValue;
        }

        $output = fopen('php://output', 'w');
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename='.$dataImport->name.'.csv');
        fputcsv($output, $headers);
        fputcsv($output, $sample);
        fclose($output);
        ob_flush();
        flush();
    }
}
