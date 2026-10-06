<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Filament\Resources\UpsellRuleResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\UpsellService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ListUpsellRules extends ListRecords
{
    protected static string $resource = UpsellRuleResource::class;

    /** Cuántos nombres se listan en la notificación antes de resumir con "y N más". */
    private const NOMBRES_EN_AVISO = 15;

    protected function getHeaderActions(): array
    {
        return [
            $this->crearEnLoteAction(),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Muchas reglas de una vez: cada disparador × cada oferta (p. ej. "a quien compre
     * cualquier casco, ofrecerle la riñonera y los guantes"), en vez de cargarlas de a una.
     */
    private function crearEnLoteAction(): Actions\Action
    {
        return Actions\Action::make('crearEnLote')
            ->label('Crear en lote')
            ->icon('heroicon-o-squares-plus')
            ->color('gray')
            ->modalHeading('Crear reglas en lote')
            ->modalDescription('Una regla por cada combinación de disparador y oferta.')
            ->modalSubmitActionLabel('Crear reglas')
            ->form([
                Forms\Components\Section::make('Disparadores')
                    ->description('Quien compre alguno de estos. La categoría y los productos se juntan sin repetir.')
                    ->columns(2)
                    ->schema([
                        $this->selectCategoria('category_id', 'Todos los productos de la categoría'),
                        $this->selectProductos('product_ids', 'Y/o estos productos'),
                    ]),

                Forms\Components\Section::make('Ofertas')
                    ->description('Lo que se le ofrece. Un producto nunca se ofrece a sí mismo.')
                    ->columns(2)
                    ->schema([
                        $this->selectCategoria('offer_category_id', 'Todos los productos de la categoría'),
                        $this->selectProductos('offer_product_ids', 'Y/o estos productos'),
                    ]),

                Forms\Components\Section::make('Condiciones')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('discount_percent')
                            ->label('Descuento')
                            ->helperText('Para todas, salvo las que tengan uno propio abajo.')
                            ->numeric()
                            ->integer()
                            ->suffix('%')
                            ->minValue(1)
                            ->maxValue(90)
                            ->required(),
                        Forms\Components\TextInput::make('priority')
                            ->label('Prioridad')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Toggle::make('sobrescribir')
                            ->label('Sobrescribir descuento')
                            ->helperText('De las reglas que ya existan. Si no, se dejan como están.')
                            ->default(false)
                            ->live()
                            ->inline(false),

                        Forms\Components\Repeater::make('descuentos')
                            ->label('Descuento distinto por producto ofrecido (opcional)')
                            ->columnSpanFull()
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar descuento propio')
                            ->reorderable(false)
                            ->live()
                            ->schema([
                                Forms\Components\Select::make('offer_product_id')
                                    ->label('Producto ofrecido')
                                    // Solo los que van a ser oferta en este lote.
                                    ->options(fn (Get $get) => $this->nombresDe($this->ofertas([
                                        'offer_category_id' => $get('../../offer_category_id'),
                                        'offer_product_ids' => $get('../../offer_product_ids'),
                                    ])))
                                    ->searchable()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required(),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->label('Descuento')
                                    ->numeric()
                                    ->integer()
                                    ->suffix('%')
                                    ->minValue(1)
                                    ->maxValue(90)
                                    ->required(),
                            ]),
                    ]),

                Forms\Components\Placeholder::make('resumen')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => $this->resumen($get)),
            ])
            ->action(function (array $data, Actions\Action $action, UpsellService $upsell): void {
                $sobrescribir = (bool) ($data['sobrescribir'] ?? false);

                try {
                    $resultado = $upsell->crearReglasEnLote(
                        $this->pares($data),
                        (int) $data['discount_percent'],
                        (int) $data['priority'],
                        $sobrescribir,
                        collect($data['descuentos'] ?? [])
                            ->mapWithKeys(fn (array $d) => [$d['offer_product_id'] => (int) $d['discount_percent']])
                            ->all(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No se creó ninguna regla')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    // El modal queda abierto con lo cargado, para corregir y reintentar.
                    $action->halt();
                }

                $this->avisarResultado($resultado, $sobrescribir);
            });
    }

    private function selectCategoria(string $campo, string $label): Forms\Components\Select
    {
        return Forms\Components\Select::make($campo)
            ->label($label)
            ->helperText('Incluye sus subcategorías. Solo productos activos.')
            ->options(fn () => Category::orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->live();
    }

    private function selectProductos(string $campo, string $label): Forms\Components\Select
    {
        return Forms\Components\Select::make($campo)
            ->label($label)
            ->multiple()
            ->options(fn () => UpsellRuleResource::opcionesDeProducto())
            ->searchable()
            ->live();
    }

    /** @return Collection<int, string> */
    private function disparadores(array $data): Collection
    {
        return app(UpsellService::class)->productosDelLote(
            filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
            $data['product_ids'] ?? [],
        );
    }

    /** @return Collection<int, string> */
    private function ofertas(array $data): Collection
    {
        return app(UpsellService::class)->productosDelLote(
            filled($data['offer_category_id'] ?? null) ? (int) $data['offer_category_id'] : null,
            $data['offer_product_ids'] ?? [],
        );
    }

    /** @return Collection<int, array{trigger: string, offer: string}> */
    private function pares(array $data): Collection
    {
        return app(UpsellService::class)->paresDelLote($this->disparadores($data), $this->ofertas($data));
    }

    /** @return array<string, string> */
    private function nombresDe(Collection $ids): array
    {
        return Product::whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Contador en vivo del modal, más los avisos de tope y de ofertas que no entran. */
    private function resumen(Get $get): HtmlString
    {
        $data = [
            'category_id' => $get('category_id'),
            'product_ids' => $get('product_ids'),
            'offer_category_id' => $get('offer_category_id'),
            'offer_product_ids' => $get('offer_product_ids'),
        ];
        $disparadores = $this->disparadores($data);
        $ofertas = $this->ofertas($data);

        if ($disparadores->isEmpty() || $ofertas->isEmpty()) {
            return $this->parrafo('Elegí al menos un disparador y una oferta.');
        }

        $upsell = app(UpsellService::class);
        $pares = $upsell->paresDelLote($disparadores, $ofertas);
        $formula = sprintf(
            '(%d %s × %d %s)',
            $disparadores->count(),
            $disparadores->count() === 1 ? 'disparador' : 'disparadores',
            $ofertas->count(),
            $ofertas->count() === 1 ? 'oferta' : 'ofertas'
        );

        if ($pares->count() > UpsellService::MAX_REGLAS_POR_LOTE) {
            return $this->parrafo(sprintf(
                '⚠ Serían %d reglas %s: el tope es %d por vez. Achicá la selección.',
                $pares->count(),
                $formula,
                UpsellService::MAX_REGLAS_POR_LOTE
            ), 'text-danger-600 dark:text-danger-400');
        }

        $existentes = $upsell->paresExistentes($pares)->count();
        $nuevas = $pares->count() - $existentes;

        $texto = sprintf('Se crearán %d %s %s', $nuevas, $nuevas === 1 ? 'regla' : 'reglas', $formula);

        if ($existentes > 0) {
            $una = $existentes === 1;
            $texto .= sprintf(' · %d ya %s', $existentes, $get('sobrescribir')
                ? ($una ? 'existe: se le actualizará el descuento' : 'existen: se les actualizará el descuento')
                : ($una ? 'existe y se omitirá' : 'existen y se omitirán'));
        }

        $html = $this->parrafo($texto.'.')->toHtml();

        // El cliente ve como mucho MAX_OFERTAS por pedido: el resto queda cargado pero
        // no se muestra. Mejor que el admin lo sepa antes de crear.
        $excedidos = $upsell->disparadoresConExcesoDeOfertas($pares);

        if ($excedidos->isNotEmpty()) {
            $html .= $this->parrafo(sprintf(
                'Nota: %d %s con más de %d ofertas activas. Al cliente se le muestran solo %d por pedido: las de mayor prioridad. %s',
                $excedidos->count(),
                $excedidos->count() === 1 ? 'disparador quedará' : 'disparadores quedarán',
                UpsellService::MAX_OFERTAS,
                UpsellService::MAX_OFERTAS,
                $this->listaCorta($excedidos).'.'
            ), 'text-warning-600 dark:text-warning-400')->toHtml();
        }

        return new HtmlString($html);
    }

    private function parrafo(string $texto, string $clase = ''): HtmlString
    {
        return new HtmlString('<p class="text-sm '.$clase.'">'.e($texto).'</p>');
    }

    /** "A, B, C y 4 más". */
    private function listaCorta(Collection $nombres): string
    {
        $resto = $nombres->count() - self::NOMBRES_EN_AVISO;

        return $nombres->take(self::NOMBRES_EN_AVISO)->implode(', ').($resto > 0 ? " y {$resto} más" : '');
    }

    /** @param  array{creadas: Collection<int, string>, existentes: Collection<int, string>}  $resultado */
    private function avisarResultado(array $resultado, bool $sobrescribir): void
    {
        $creadas = $resultado['creadas']->count();
        $existentes = $resultado['existentes'];

        $titulo = sprintf('%d %s', $creadas, $creadas === 1 ? 'creada' : 'creadas');

        if ($existentes->isNotEmpty()) {
            $titulo .= sprintf(
                ', %d %s (ya existían)',
                $existentes->count(),
                $sobrescribir
                    ? ($existentes->count() === 1 ? 'actualizada' : 'actualizadas')
                    : ($existentes->count() === 1 ? 'omitida' : 'omitidas')
            );
        }

        $aviso = Notification::make()->title($titulo);

        if ($existentes->isNotEmpty()) {
            $aviso->body(($sobrescribir ? 'Descuento actualizado en: ' : 'Omitidas: ').$this->listaCorta($existentes).'.');
        }

        // Sin ninguna nueva y sin sobrescribir no cambió nada: no es un éxito.
        ($creadas === 0 && ! $sobrescribir ? $aviso->warning() : $aviso->success())
            ->persistent()
            ->send();
    }
}
