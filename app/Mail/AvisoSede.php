<?php

namespace App\Mail;

use App\Models\Documento;
use App\Sede\Organismo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvisoSede extends Mailable
{
    use Queueable;

    /** @param list<string> $lineas */
    public function __construct(
        public string $asunto,
        public array $lineas,
        public ?string $enlace = null,
        public ?Documento $adjunto = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto.' · '.Organismo::nombre());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.aviso', text: 'mail.aviso-texto', with: ['organismo' => Organismo::nombre()]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->adjunto || ! str_starts_with((string) $this->adjunto->mime, 'image/')) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->adjunto->contenido(), $this->adjunto->nombre)->withMime((string) $this->adjunto->mime)];
    }
}
