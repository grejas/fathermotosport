<?php

namespace App\Filament\Resources\PopupLeadResource\Pages;

use App\Filament\Resources\PopupLeadResource;
use App\Models\PopupLead;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPopupLeads extends ListRecords
{
    protected static string $resource = PopupLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->descargarCsv()),
        ];
    }

    /** Todos los contactos en CSV (UTF-8 con BOM para que Excel muestre bien las tildes). */
    private function descargarCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Email', 'WhatsApp', 'País', 'Idioma', 'Descuento %', 'Cupón', 'Usado', 'Origen', 'Campaña']);

            PopupLead::with('coupon')->orderBy('id')->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $l) {
                    fputcsv($out, [
                        $l->created_at?->format('Y-m-d H:i'),
                        $l->email,
                        $l->whatsapp,
                        $l->country,
                        $l->locale,
                        $l->percent,
                        $l->coupon?->code,
                        (int) ($l->coupon?->used_count ?? 0) > 0 ? 'sí' : 'no',
                        $l->source['utm_source'] ?? '',
                        $l->source['utm_campaign'] ?? '',
                    ]);
                }
            });
            fclose($out);
        }, 'contactos-popup-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
