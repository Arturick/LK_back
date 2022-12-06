<?php

namespace App\Service;

use App\Exceptions\AppException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelService
{
    public function arrayToXls(array $data, string $filepath)
    {
        if (empty($data)) {
            throw new AppException('no data in report');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        //set headers
        $headers = array_keys($data[0]);

        foreach ($headers as $index => $header) {
            $sheet->getCellByColumnAndRow($index + 1, 1)->setValue($header);
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }

        //set data
        foreach ($data as $rowIndex => $item) {
            foreach (array_values($item) as $colIndex => $value) {
                $sheet->getCellByColumnAndRow($colIndex + 1, $rowIndex + 2)->setValue($value);
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
    }
}
