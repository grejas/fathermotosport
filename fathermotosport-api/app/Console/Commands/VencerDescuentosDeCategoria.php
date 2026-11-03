<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Apaga el interruptor de los descuentos de categoría cuya fecha de fin ya pasó, para
 * que el panel los muestre apagados.
 *
 * Solo es para el panel: el precio deja de tener el descuento en el instante en que
 * pasa la fecha de fin, porque PrecioFinal lo evalúa en cada consulta. Si este comando
 * no corriera, nadie pagaría de menos.
 */
class VencerDescuentosDeCategoria extends Command
{
    protected $signature = 'categorias:vencer-descuentos';

    protected $description = 'Apaga el descuento de las categorías cuya fecha de fin ya pasó.';

    public function handle(): int
    {
        $vencidas = Category::query()
            ->where('discount_active', true)
            ->whereNotNull('discount_ends_at')
            ->where('discount_ends_at', '<', Carbon::now())
            ->get();

        foreach ($vencidas as $categoria) {
            // Por el modelo (no un UPDATE masivo) para que avise a la web como un
            // guardado del panel.
            $categoria->update(['discount_active' => false]);
            $this->line("Descuento vencido apagado: {$categoria->name} (-{$categoria->discount_percent} %).");
        }

        $this->info("Descuentos apagados: {$vencidas->count()}.");

        return self::SUCCESS;
    }
}
