<?php

namespace App\Sede\Pagos;

use App\Models\Liquidacion;
use App\Models\Pago;
use DomainException;
use RuntimeException;

/**
 * Pasarela Redsys por redirección (firma HMAC_SHA256_V1). Sirve para el TPV virtual de
 * cualquier entidad que use Redsys y para Bizum (DS_MERCHANT_PAYMETHODS = z).
 */
class Redsys
{
    public function __construct(private readonly Liquidador $liquidador) {}

    /**
     * Prepara el pago y devuelve los campos del formulario que se envía a Redsys.
     *
     * @return array{url: string, campos: array<string, string>, pago: Pago}
     */
    public function iniciar(Liquidacion $liquidacion, string $canal): array
    {
        if ($liquidacion->estado !== 'pendiente') {
            throw new DomainException('Esta liquidación no está pendiente de pago.');
        }
        if (! in_array($canal, ['tarjeta', 'bizum'], true)) {
            throw new DomainException('Medio de pago no válido.');
        }
        if ($canal === 'bizum' && ! config('sede.pagos.redsys.bizum')) {
            throw new DomainException('Bizum no está disponible.');
        }

        $pago = Pago::query()->create([
            'liquidacion_id' => $liquidacion->id,
            'canal' => $canal,
            'importe' => $liquidacion->total,
            'estado' => 'iniciado',
        ]);
        $pedido = $this->numeroPedido($pago->id);
        $pago->update(['pedido' => $pedido]);

        $parametros = array_filter([
            'DS_MERCHANT_AMOUNT' => (string) $liquidacion->totalCentimos(),
            'DS_MERCHANT_ORDER' => $pedido,
            'DS_MERCHANT_MERCHANTCODE' => (string) config('sede.pagos.redsys.fuc'),
            'DS_MERCHANT_CURRENCY' => (string) config('sede.pagos.redsys.moneda', '978'),
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => (string) config('sede.pagos.redsys.terminal'),
            'DS_MERCHANT_MERCHANTURL' => route('sede.pagos.notificacion'),
            'DS_MERCHANT_URLOK' => route('sede.pagos.vuelta', ['pago' => $pago->id, 'resultado' => 'ok']),
            'DS_MERCHANT_URLKO' => route('sede.pagos.vuelta', ['pago' => $pago->id, 'resultado' => 'ko']),
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr('Liquidación '.$liquidacion->referencia, 0, 125),
            'DS_MERCHANT_MERCHANTNAME' => mb_substr((string) config('sede.pagos.redsys.nombre_comercio'), 0, 25),
            'DS_MERCHANT_CONSUMERLANGUAGE' => '001',
            'DS_MERCHANT_PAYMETHODS' => $canal === 'bizum' ? 'z' : 'C',
        ], fn ($v) => $v !== '');

        $merchantParameters = base64_encode((string) json_encode($parametros, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $pago->update(['datos_pasarela' => ['enviado' => $parametros]]);

        return [
            'url' => (string) config('sede.pagos.redsys.url.'.config('sede.pagos.redsys.entorno', 'pruebas')),
            'campos' => [
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $merchantParameters,
                'Ds_Signature' => $this->firmar($pedido, $merchantParameters),
            ],
            'pago' => $pago,
        ];
    }

    /**
     * Procesa la notificación en línea de Redsys (servidor a servidor).
     * Devuelve el pago actualizado o null si la firma no es válida.
     *
     * @param  array<string, mixed>  $post
     */
    public function procesarNotificacion(array $post): ?Pago
    {
        $parametrosB64 = (string) ($post['Ds_MerchantParameters'] ?? '');
        $firmaRecibida = (string) ($post['Ds_Signature'] ?? '');
        $datos = json_decode((string) base64_decode(strtr($parametrosB64, '-_', '+/')), true);
        if (! is_array($datos)) {
            return null;
        }
        $datos = array_change_key_case($datos, CASE_LOWER);
        $pedido = (string) ($datos['ds_order'] ?? '');
        if ($pedido === '' || ! $this->firmaValida($pedido, $parametrosB64, $firmaRecibida)) {
            return null;
        }

        $pago = Pago::query()->where('pedido', $pedido)->first();
        if (! $pago) {
            return null;
        }
        $respuesta = (int) ($datos['ds_response'] ?? 9999);
        $importe = (int) ($datos['ds_amount'] ?? -1);
        $autorizado = $respuesta >= 0 && $respuesta <= 99 && $importe === $pago->liquidacion->totalCentimos();

        $pago->update([
            'codigo_respuesta' => str_pad((string) $respuesta, 4, '0', STR_PAD_LEFT),
            'datos_pasarela' => array_merge($pago->datos_pasarela ?? [], ['recibido' => $datos]),
        ]);

        if ($autorizado) {
            $canal = ($datos['ds_processedpaymethod'] ?? '') === '68' || $pago->canal === 'bizum' ? 'bizum' : 'tarjeta';

            return $this->liquidador->registrarPago($pago->liquidacion, $canal, null, $pago, $pago->datos_pasarela ?? [], (string) ($datos['ds_authorisationcode'] ?? ''));
        }
        $pago->update(['estado' => 'denegado']);

        return $pago->refresh();
    }

    public function firmar(string $pedido, string $parametrosB64): string
    {
        $clave = base64_decode((string) config('sede.pagos.redsys.clave'), true);
        if ($clave === false || $clave === '') {
            throw new RuntimeException('Falta la clave de comercio de Redsys.');
        }
        $relleno = strlen($pedido) % 8 === 0 ? $pedido : str_pad($pedido, strlen($pedido) + 8 - strlen($pedido) % 8, "\0");
        $claveDerivada = openssl_encrypt($relleno, 'des-ede3-cbc', $clave, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8));
        if ($claveDerivada === false) {
            throw new RuntimeException('No se pudo derivar la clave de firma de Redsys.');
        }

        return base64_encode(hash_hmac('sha256', $parametrosB64, $claveDerivada, true));
    }

    public function firmaValida(string $pedido, string $parametrosB64, string $firma): bool
    {
        $esperada = strtr($this->firmar($pedido, $parametrosB64), '+/', '-_');

        return hash_equals($esperada, strtr($firma, '+/', '-_'));
    }

    /** Pedido de 12 caracteres que empieza por 4 dígitos, como exige Redsys. */
    private function numeroPedido(int $pagoId): string
    {
        return now()->format('y').str_pad((string) $pagoId, 10, '0', STR_PAD_LEFT);
    }
}
