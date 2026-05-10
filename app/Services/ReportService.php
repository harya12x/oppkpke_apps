<?php

namespace App\Services;

use App\Models\LaporanOppkpke;
use App\Models\StrategiOppkpke;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    public function __construct(
        private OppkpkeService $oppkpkeService
    ) {}

    public function generateExcelReport(array $filters = []): string
    {
        $tahun = $filters['tahun'] ?? date('Y');
        $data = $this->oppkpkeService->getHierarchyData($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan OPPKPKE');

        // Headers
        $headers = [
            'No', 'Strategi', 'Perangkat Daerah', 'Kode Program', 'Program',
            'Kegiatan', 'Sub Kegiatan', 'Durasi', 'Besaran Manfaat', 'Jenis Bantuan',
            'Jumlah Sasaran', 'Penerima Langsung', 'Penerima Tidak Langsung',
            'Penerima Penunjang', 'Sumber', 'Sifat Bantuan', 'Lokasi',
            'Alokasi Anggaran', 'Realisasi Sem.1', 'Realisasi Sem.2', 'Total Realisasi'
        ];

        // Write headers using cell coordinates (A1, B1, C1, etc.)
        $columns = range('A', 'Z');
        foreach ($headers as $col => $header) {
            $cell = $columns[$col] . '1';
            $sheet->setCellValue($cell, $header);
        }

        // Style header
        $headerRange = 'A1:U1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);

        // Data rows
        $row = 2;
        foreach ($data as $index => $item) {
            $laporan = $item['laporan'][0] ?? [];
            
            $rowData = [
                $index + 1,
                $item['kegiatan']['program']['strategi']['nama'] ?? '',
                $item['kegiatan']['program']['perangkat_daerah']['nama'] ?? '',
                $item['kegiatan']['program']['kode_program'] ?? '',
                $item['kegiatan']['program']['nama_program'] ?? '',
                $item['kegiatan']['nama_kegiatan'] ?? '',
                $item['nama_sub_kegiatan'] ?? '',
                $laporan['durasi_pemberian'] ?? '',
                $laporan['besaran_manfaat'] ?? '',
                $laporan['jenis_bantuan'] ?? '',
                $laporan['jumlah_sasaran'] ?? 0,
                $laporan['penerima_langsung'] ?? 0,
                $laporan['penerima_tidak_langsung'] ?? 0,
                $laporan['penerima_penunjang'] ?? 0,
                $laporan['sumber_pembiayaan'] ?? 'APBD',
                $laporan['sifat_bantuan'] ?? '',
                $laporan['lokasi'] ?? '',
                $laporan['alokasi_anggaran'] ?? 0,
                $laporan['realisasi_sem1'] ?? 0,
                $laporan['realisasi_sem2'] ?? 0,
                $laporan['realisasi_total'] ?? 0,
            ];

            foreach ($rowData as $col => $value) {
                $cell = $columns[$col] . $row;
                $sheet->setCellValue($cell, $value);
            }
            
            $row++;
        }

        // Format number columns (R, S, T, U = columns 18-21)
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("R2:U{$lastRow}")->getNumberFormat()
                ->setFormatCode('#,##0.00');
            
            // Add borders to data
            $sheet->getStyle("A2:U{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);
        }

        // Auto-size columns
        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Save file
        $filename = 'laporan_oppkpke_' . $tahun . '_' . time() . '.xlsx';
        $path = storage_path('app/exports/' . $filename);
        
        if (!is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    public function generatePdfReport(array $filters = []): string
    {
        $tahun = $filters['tahun'] ?? date('Y');
        $stats = $this->oppkpkeService->getDashboardStats($tahun);
        $data = $this->oppkpkeService->getHierarchyData($filters);

        $pdf = Pdf::loadView('oppkpke.reports.pdf', compact('stats', 'data', 'tahun', 'filters'));
        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan_oppkpke_' . $tahun . '_' . time() . '.pdf';
        $path = storage_path('app/exports/' . $filename);
        
        if (!is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}