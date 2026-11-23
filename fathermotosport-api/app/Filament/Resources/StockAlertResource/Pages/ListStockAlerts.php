<?php

namespace App\Filament\Resources\StockAlertResource\Pages;

use App\Filament\Resources\StockAlertResource;
use App\Models\StockAlert;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->descargarCsv()),
        ];
    }

    /** Todos los avisos en CSV (UTF-8 con BOM para que Excel muestre bien las tildes). */
    private function descargarCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Producto', 'Talla', 'Email', 'WhatsApp', 'Idioma', 'Stock hoy', 'Avisado']);

            StockAlert::with('product.variants')->orderBy('id')->chunk(500, function ($avisos) use ($out) {
                foreach ($avisos as $a) {
                    fputcsv($out, [
                        $a->created_at?->format('Y-m-d H:i'),
                        $a->product?->name,
                        $a->size,
                        $a->email,
                        $a->whatsapp,
                        $a->locale,
                        StockAlertResource::stockActual($a),
                        $a->notified_at?->format('Y-m-d H:i') ?? 'pendiente',
                    ]);
                }
            });
            fclose($out);
        }, 'avisos-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
