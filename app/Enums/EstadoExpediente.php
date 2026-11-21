<?php

namespace App\Enums;

/**
 * Estados del expediente. El recorrido ordinario es
 * nueva → en_revision → pendiente_pago → pagado → en_curso → cerrada;
 * esperando_documentacion y desistida dependen de la respuesta de la persona interesada.
 */
enum EstadoExpediente: string
{
    case Borrador = 'borrador';
    case Nueva = 'nueva';
    case EnRevision = 'en_revision';
    case EsperandoDocumentacion = 'esperando_documentacion';
    case PendientePago = 'pendiente_pago';
    case Pagado = 'pagado';
    case EnCurso = 'en_curso';
    case Desistida = 'desistida';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Nueva => 'Nueva',
            self::EnRevision => 'En revisión',
            self::EsperandoDocumentacion => 'Esperando documentación',
            self::PendientePago => 'Pendiente de pago',
            self::Pagado => 'Pagado',
            self::EnCurso => 'En curso',
            self::Desistida => 'Desistida',
            self::Cerrada => 'Cerrada',
        };
    }

    /** Explicación en lenguaje claro para la persona interesada. */
    public function explicacion(): string
    {
        return match ($this) {
            self::Borrador => 'Todavía no ha enviado la solicitud. Puede continuar cuando quiera.',
            self::Nueva => 'Hemos recibido su solicitud firmada. Tiene el justificante de registro en sus documentos.',
            self::EnRevision => 'El personal está comprobando su solicitud y la documentación.',
            self::EsperandoDocumentacion => 'Necesitamos que aporte documentación o datos. Revise el requerimiento y el plazo.',
            self::PendientePago => 'Su solicitud está lista para tramitar. Falta pagar la tasa dentro del plazo.',
            self::Pagado => 'Hemos registrado su pago. Tiene el justificante en sus documentos.',
            self::EnCurso => 'El servicio está programado o en ejecución.',
            self::Desistida => 'La solicitud se ha archivado porque no se completó en plazo.',
            self::Cerrada => 'El expediente ha terminado. Puede consultar la resolución en sus documentos.',
        };
    }

    /** Quién tiene que actuar en este estado. */
    public function responsable(): string
    {
        return match ($this) {
            self::Borrador, self::EsperandoDocumentacion, self::PendientePago => 'interesado',
            self::Pagado => 'sistema',
            default => 'organismo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Nueva => 'info',
            self::EnRevision => 'primary',
            self::EsperandoDocumentacion, self::PendientePago => 'warning',
            self::Pagado, self::EnCurso => 'success',
            self::Desistida => 'danger',
            self::Cerrada => 'gray',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::Desistida, self::Cerrada], true);
    }

    /** @return list<self> */
    public function siguientesPermitidos(): array
    {
        return match ($this) {
            self::Borrador => [self::Nueva],
            self::Nueva => [self::EnRevision, self::Cerrada],
            self::EnRevision => [self::EsperandoDocumentacion, self::PendientePago, self::EnCurso, self::Cerrada],
            self::EsperandoDocumentacion => [self::EnRevision, self::Desistida, self::Cerrada],
            self::PendientePago => [self::Pagado, self::EnRevision, self::Desistida, self::Cerrada],
            self::Pagado => [self::EnCurso, self::Cerrada],
            self::EnCurso => [self::Cerrada],
            self::Desistida, self::Cerrada => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientesPermitidos(), true);
    }

    /** @return array<string, string> */
    public static function opciones(bool $incluirBorrador = false): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            if (! $incluirBorrador && $caso === self::Borrador) {
                continue;
            }
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }
}
