<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SimpleXlsxExporter
{
    /**
     * Xuất file Excel (.xlsx) chuẩn OpenXML; fallback sang CSV UTF-8 BOM nếu thiếu ext-zip.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function download(string $filenameBase, string $sheetName, array $headers, array $rows): StreamedResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return $this->downloadCsvFallback($filenameBase.'.csv', $headers, $rows);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive;
        if ($tmpFile === false || $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->downloadCsvFallback($filenameBase.'.csv', $headers, $rows);
        }

        $safeSheetName = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $sheetName) ?: 'Sheet1', 0, 31);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($safeSheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        $zip->close();

        $filename = $filenameBase.'.xlsx';

        return response()->streamDownload(function () use ($tmpFile): void {
            readfile($tmpFile);
            @unlink($tmpFile);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function downloadCsvFallback(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
        ]);
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        $escaped = $this->xmlEscape($sheetName);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$escaped.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFB91C1C"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function worksheetXml(array $headers, array $rows): string
    {
        $colCount = max(1, count($headers));
        $lastCol = $this->colLetter($colCount - 1);
        $totalRows = count($rows) + 1;

        $colsXml = '<cols>';
        for ($i = 0; $i < $colCount; $i++) {
            $maxLen = mb_strlen((string) ($headers[$i] ?? ''));
            foreach ($rows as $row) {
                $len = mb_strlen((string) ($row[$i] ?? ''));
                if ($len > $maxLen) {
                    $maxLen = $len;
                }
            }
            $width = min(44, max(13, $maxLen + 4));
            $colNum = $i + 1;
            $colsXml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$width}\" customWidth=\"1\"/>";
        }
        $colsXml .= '</cols>';

        $sheetData = '<sheetData>';
        $sheetData .= '<row r="1" ht="24" customHeight="1">';
        foreach ($headers as $cIdx => $header) {
            $ref = $this->colLetter($cIdx).'1';
            $sheetData .= '<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'.$this->xmlEscape((string) $header).'</t></is></c>';
        }
        $sheetData .= '</row>';

        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 2;
            $sheetData .= "<row r=\"{$rowNum}\">";
            foreach ($headers as $cIdx => $_) {
                $val = $row[$cIdx] ?? '';
                $ref = $this->colLetter($cIdx).$rowNum;
                if (is_int($val) || is_float($val)) {
                    $sheetData .= '<c r="'.$ref.'" t="n" s="2"><v>'.$val.'</v></c>';
                } else {
                    $sheetData .= '<c r="'.$ref.'" t="inlineStr" s="0"><is><t>'.$this->xmlEscape((string) $val).'</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastCol.$totalRows.'"/>'
            .'<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$colsXml
            .$sheetData
            .'<autoFilter ref="A1:'.$lastCol.$totalRows.'"/>'
            .'</worksheet>';
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $num = $index + 1;
        while ($num > 0) {
            $mod = ($num - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $num = intdiv($num - $mod, 26);
        }

        return $letter;
    }

    private function xmlEscape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
