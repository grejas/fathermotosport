<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\MailCampaign;
use App\Services\CampanaManual;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Detalle de una campaña: estado, contadores, destinatarios y solo las acciones que
 * corresponden al estado (borrador: editar, probar, enviar; programada: probar, volver a
 * borrador, cancelar; enviando: pausar, cancelar; pausada: reanudar, cancelar). Vista
 * previa para todo el personal; duplicar y el resto, solo el administrador. Enviar pide
 * confirmar con el número de destinatarios.
 */
class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public function getTitle(): string
    {
        return (string) $this->getRecord()->name;
    }

    private function campana(): MailCampaign
    {
        return MailCampaign::findOrFail($this->getRecord()->getKey());
    }

    private function esAdmin(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    private function estado(): string
    {
        return (string) $this->campana()->status;
    }

    private function vistaPrevia(bool $conResumen)
    {
        $servicio = app(CampanaManual::class);
        $campana = $this->campana();

        return view('filament.campanas.resumen', [
            'resumen' => $conResumen ? $servicio->resumen($campana) : null,
            'errores' => $conResumen ? CampanaManual::errores($campana) : [],
            'programada' => $campana->scheduled_at?->isFuture() ? $campana->scheduled_at->timezone(config('marketing.timezone'))->format('d/m/Y H:i') : null,
            'previas' => collect(CampanaManual::IDIOMAS_ACTIVOS)->mapWithKeys(fn ($i) => [$i => $servicio->correoDePrueba($campana, $i)->render()])->all(),
            'conEspanol' => CampanaManual::idiomasConEspanol($campana->payload ?? []),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $servicio = fn () => app(CampanaManual::class);

        return [
            Actions\EditAction::make()->label('Editar')
                ->visible(fn () => CampaignResource::canEdit($this->campana())),

            Actions\Action::make('vista_previa')->label('Vista previa')->icon('heroicon-o-eye')->color('gray')
                ->modalHeading('Vista previa')->modalWidth('4xl')
                ->modalContent(fn () => $this->vistaPrevia(false))
                ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar'),

            Actions\Action::make('enviar_prueba')->label('Enviar prueba')->icon('heroicon-o-paper-airplane')->color('gray')
                ->visible(fn () => $this->esAdmin() && in_array($this->estado(), [MailCampaign::BORRADOR, MailCampaign::PROGRAMADA], true))
                ->modalHeading('Enviar una prueba')
                ->modalDescription('Solo a este correo: no cuenta en el cupo ni queda como destinatario.')
                ->form([
                    Forms\Components\TextInput::make('email')->label('Correo')->email()->required(),
                    // Con un solo idioma activo (español) no hace falta elegir.
                    Forms\Components\Select::make('idioma')->label('Idioma')->options(CampanaManual::idiomasActivos())->default('es')
                        ->hidden(count(CampanaManual::IDIOMAS_ACTIVOS) === 1),
                ])
                ->modalSubmitActionLabel('Enviar')
                ->action(function (array $data) use ($servicio) {
                    try {
                        Mail::to($data['email'])->send($servicio()->correoDePrueba($this->campana(), $data['idioma'] ?? 'es'));
                        Notification::make()->title("Prueba enviada a {$data['email']}")->success()->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('No se pudo enviar la prueba')->body('Revisa storage/logs/laravel.log.')->danger()->send();
                    }
                }),

            Actions\Action::make('enviar')
                ->label(fn () => $this->campana()->scheduled_at?->isFuture() ? 'Programar' : 'Enviar')
                ->icon('heroicon-o-envelope')->color('success')
                ->visible(fn () => $this->esAdmin() && $this->estado() === MailCampaign::BORRADOR)
                ->modalHeading(fn () => $this->campana()->scheduled_at?->isFuture() ? 'Programar la campaña' : 'Enviar la campaña')
                ->modalWidth('4xl')
                ->modalContent(fn () => $this->vistaPrevia(true))
                ->form(function () use ($servicio) {
                    $campana = $this->campana();
                    if (CampanaManual::errores($campana)) {
                        return [];
                    }
                    $n = $servicio()->resumen($campana)['recibirian'];

                    return [
                        Forms\Components\Checkbox::make('confirmo')
                            ->label("Voy a enviar este correo a {$n} clientes")
                            ->accepted()
                            ->validationMessages(['accepted' => 'Marca la casilla para confirmar el envío.']),
                    ];
                })
                ->modalSubmitAction(fn ($action) => CampanaManual::errores($this->campana()) ? false : $action->label($this->campana()->scheduled_at?->isFuture() ? 'Programar' : 'Enviar ahora'))
                ->modalCancelActionLabel('Cerrar')
                ->action(function () use ($servicio) {
                    try {
                        $campana = $servicio()->programar($this->campana());
                    } catch (RuntimeException $e) {
                        Notification::make()->title('No se envió')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->success()
                        ->title($campana->status === MailCampaign::PROGRAMADA ? 'Campaña programada' : 'Envío iniciado')
                        ->body($campana->status === MailCampaign::PROGRAMADA
                            ? 'Sale el '.$campana->scheduled_at->timezone(config('marketing.timezone'))->format('d/m/Y H:i').' (hora de Bolivia), por tandas.'
                            : "Se envía por tandas a {$campana->total_count} clientes, respetando el cupo de Resend.")
                        ->send();
                    $this->refreshFormData([]);
                }),

            Actions\Action::make('volver_a_borrador')->label('Volver a borrador')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn () => $this->esAdmin() && $this->estado() === MailCampaign::PROGRAMADA)
                ->requiresConfirmation()->modalDescription('Deja de estar programada; después puedes editarla.')
                ->action(fn () => $this->aplicar(fn () => $servicio()->volverABorrador($this->campana()), 'Volvió a borrador')),

            Actions\Action::make('pausar')->label('Pausar')->icon('heroicon-o-pause')->color('warning')
                ->visible(fn () => $this->esAdmin() && $this->estado() === MailCampaign::ENVIANDO)
                ->requiresConfirmation()->modalDescription('Lo que falta queda pendiente, sin perderse, hasta que la reanudes.')
                ->action(fn () => $this->aplicar(fn () => $servicio()->pausar($this->campana()), 'Campaña pausada')),

            Actions\Action::make('reanudar')->label('Reanudar')->icon('heroicon-o-play')->color('success')
                ->visible(fn () => $this->esAdmin() && $this->estado() === MailCampaign::PAUSADA)
                ->requiresConfirmation()
                ->action(fn () => $this->aplicar(fn () => $servicio()->reanudar($this->campana()), 'Campaña reanudada')),

            Actions\Action::make('cancelar')->label('Cancelar')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => $this->esAdmin() && in_array($this->estado(), [MailCampaign::PROGRAMADA, MailCampaign::ENVIANDO, MailCampaign::PAUSADA], true))
                ->requiresConfirmation()->modalDescription('Lo enviado queda como está; lo que falta queda cancelado y registrado.')
                ->action(fn () => $this->aplicar(fn () => $servicio()->cancelar($this->campana()), 'Campaña cancelada')),

            Actions\Action::make('duplicar')->label('Duplicar')->icon('heroicon-o-document-duplicate')->color('gray')
                ->visible(fn () => $this->esAdmin())
                ->requiresConfirmation()->modalDescription('Crea un borrador nuevo con el mismo contenido y segmento.')
                ->action(function () use ($servicio) {
                    $copia = $servicio()->duplicar($this->campana(), auth()->user());
                    Notification::make()->title('Borrador creado')->success()->send();
                    $this->redirect(CampaignResource::getUrl('edit', ['record' => $copia]));
                }),
        ];
    }

    private function aplicar(callable $accion, string $titulo): void
    {
        try {
            $accion();
            Notification::make()->title($titulo)->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title('No se pudo')->body($e->getMessage())->danger()->send();
        }
        $this->refreshFormData([]);
    }
}
