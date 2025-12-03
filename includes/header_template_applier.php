<?php
/**
 * Apply header (including logo, merges, row heights, styles) from sample_header.xlsx
 * to a target Spreadsheet worksheet.
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Copy header rows (1..6) from template sheet to target sheet and shift existing
 * content down accordingly. This assumes the report table starts from row >= 7.
 *
 * @param Worksheet $targetSheet Sheet to receive header
 * @param string $templatePath Absolute path to sample_header.xlsx
 * @param int $headerRows Number of rows to copy from template (default 6)
 * @return void
 */
function applyHeaderTemplateFromSample(Worksheet $targetSheet, string $templatePath, int $headerRows = 6): void {
    if (!file_exists($templatePath)) {
        return; // fail-safe: do nothing if template not found
    }

    $template = IOFactory::load($templatePath);
    $templateSheet = $template->getSheet(0);

    // Insert header rows at top to make room
    $targetSheet->insertNewRowBefore(1, $headerRows);

    // Determine max column in template header
    $highestColumn = $templateSheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Copy cell values and styles row-by-row
    for ($row = 1; $row <= $headerRows; $row++) {
        // Row height
        $targetSheet->getRowDimension($row)->setRowHeight($templateSheet->getRowDimension($row)->getRowHeight());

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sourceCell = $templateSheet->getCell($colLetter . $row);
            $targetCell = $targetSheet->getCell($colLetter . $row);

            // Value (keep rich text/newlines if any)
            $targetCell->setValue($sourceCell->getValue());

            // Style
            $targetSheet->duplicateStyle($templateSheet->getStyle($colLetter . $row), $colLetter . $row);
        }
    }

    // Copy merged cells that intersect header rows
    foreach ($templateSheet->getMergeCells() as $mergedRange) {
        $range = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::extractAllCellReferencesInRange($mergedRange);
        // Check if any cell in this range is within header rows
        $firstRef = $range[0];
        $lastRef = end($range);
        $firstRow = (int)preg_replace('/^\D+/', '', $firstRef);
        $lastRow = (int)preg_replace('/^\D+/', '', $lastRef);
        if ($firstRow <= $headerRows) {
            $targetSheet->mergeCells($mergedRange);
        }
    }

    // Copy drawings (e.g., logo)
    foreach ($templateSheet->getDrawingCollection() as $drawing) {
        if ($drawing instanceof Drawing) {
            $newDrawing = new Drawing();
            $newDrawing->setName($drawing->getName());
            $newDrawing->setDescription($drawing->getDescription());
            $newDrawing->setPath($drawing->getPath());
            $newDrawing->setCoordinates($drawing->getCoordinates());
            $newDrawing->setOffsetX($drawing->getOffsetX());
            $newDrawing->setOffsetY($drawing->getOffsetY());
            if ($drawing->getHeight()) {
                $newDrawing->setHeight($drawing->getHeight());
            }
            if ($drawing->getWidth()) {
                $newDrawing->setWidth($drawing->getWidth());
            }
            $newDrawing->setWorksheet($targetSheet);
        }
    }

    // Cleanup
    $template->disconnectWorksheets();
    unset($template);
}


