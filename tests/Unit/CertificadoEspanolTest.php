<?php

namespace Tests\Unit;

use App\Sede\Identidad\CertificadoEspanol;
use PHPUnit\Framework\TestCase;
use Tests\Support\Certificados;

class CertificadoEspanolTest extends TestCase
{
    public function test_valida_nif_nie_y_cif(): void
    {
        $this->assertTrue(CertificadoEspanol::nifValido('12345678Z'));
        $this->assertFalse(CertificadoEspanol::nifValido('12345678A'));
        $this->assertTrue(CertificadoEspanol::nifValido('X1234567L'));
        $this->assertTrue(CertificadoEspanol::nifValido('B12345674'));
        $this->assertSame('12345678Z', CertificadoEspanol::normalizarNif('IDCES-12345678Z'));
        $this->assertSame('B12345674', CertificadoEspanol::normalizarNif('VATES-B12345674'));
        $this->assertNull(CertificadoEspanol::normalizarNif('IDCES-12345678A'));
    }

    public function test_lee_certificado_de_persona_fisica(): void
    {
        $c = CertificadoEspanol::desdePem(Certificados::personaFisica()['cert']);

        $this->assertSame(CertificadoEspanol::PERSONA_FISICA, $c->tipo);
        $this->assertSame('12345678Z', $c->nif);
        $this->assertSame('María', $c->nombre);
        $this->assertSame('Pérez Gómez', $c->apellidos);
        $this->assertTrue($c->vigente());
    }

    public function test_lee_certificado_de_representante(): void
    {
        $c = CertificadoEspanol::desdePem(Certificados::representante()['cert']);

        $this->assertSame(CertificadoEspanol::REPRESENTANTE_JURIDICA, $c->tipo);
        $this->assertSame('00000000T', $c->nif);
        $this->assertSame('B12345674', $c->nifEntidad);
        $this->assertSame('EMPRESA DE PRUEBA SL', $c->razonSocial);
    }
}
