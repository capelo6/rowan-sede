<?php

namespace App\Sede\Documentos;

use App\Sede\Organismo;
use Com\Tecnick\Pdf\Tcpdf;

/**
 * Genera documentos PDF a partir de HTML sencillo (tc-lib-pdf) y, si hay certificado de sello,
 * los firma en PAdES-BASELINE-B. Usa DejaVu Sans en subconjunto, que cubre nombres con
 * caracteres fuera del alfabeto latino básico (ł, ș, ğ…).
 */
class GeneradorPdf
{
    public function __construct(private readonly Sello $sello) {}

    public static function definirFuentes(): void
    {
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', resource_path('fonts/pdf'));
        }
    }

    /**
     * @param  string  $titulo  Título del documento (metadatos y cabecera).
     * @param  string  $cuerpoHtml  HTML del cuerpo (h1-h3, p, table, ul, strong, em).
     * @param  string|null  $csv  Código seguro de verificación, que se imprime al pie.
     * @param  bool  $sellar  Firmar con el sello electrónico si está disponible.
     */
    public function generar(string $titulo, string $cuerpoHtml, ?string $csv = null, bool $sellar = true): string
    {
        self::definirFuentes();

        $pdf = new Tcpdf('mm', true, true, true);
        $pdf->setCreator('ROWAN Sede');
        $pdf->setAuthor(Organismo::nombre());
        $pdf->setTitle($titulo);
        $pdf->setSubject($titulo);

        $fuente = $pdf->font->insert($pdf->pon, 'dejavusans', '', 9.5);
        $pdf->addPage();
        $pdf->page->addContent($fuente['out']);

        $pdf->addHTMLCell($this->envolver($titulo, $cuerpoHtml, $csv), 18, 15, 174);

        if ($sellar && $this->sello->disponible()) {
            $pdf->signature()->configure($this->sello->opcionesFirma());
        }

        return $pdf->getOutPDFString();
    }

    public function estaSellando(): bool
    {
        return $this->sello->disponible();
    }

    private function envolver(string $titulo, string $cuerpo, ?string $csv): string
    {
        $org = e(Organismo::nombre());
        $nif = e(Organismo::nif());
        $dir = e(Organismo::direccion());
        $pie = $csv
            ? '<p style="font-size:7.5pt;color:#444444;">Código seguro de verificación (CSV): <strong>'.e($csv).'</strong>. '
              .'Puede comprobar la autenticidad de este documento en '.e(rtrim((string) config('app.url'), '/')).'/verificar.</p>'
            : '';

        return '<table cellpadding="0" style="width:100%;border-bottom:1px solid #1f3a5f;">'
            .'<tr><td style="font-size:12pt;color:#1f3a5f;"><strong>'.$org.'</strong></td>'
            .'<td style="text-align:right;font-size:8pt;color:#444444;">NIF '.$nif.'<br/>'.$dir.'</td></tr></table>'
            .'<h1 style="font-size:14pt;color:#1f3a5f;">'.e($titulo).'</h1>'
            .$cuerpo
            .'<p></p>'.$pie;
    }
}
