/* ROWAN Sede · comportamiento de la sede pública. Sin dependencias; funciona sin JavaScript salvo la firma. */
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function anunciar(region, texto) {
    if (!region) { return; }
    region.textContent = '';
    window.setTimeout(function () { region.textContent = texto; }, 50);
  }

  function enviarJson(url, datos) {
    return fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(datos || {})
    }).then(function (r) {
      return r.json().then(function (j) { if (!r.ok) { throw new Error(j.error || j.message || 'Error'); } return j; });
    });
  }

  /* --- Guardado automático del borrador --- */
  var formBorrador = document.querySelector('form[data-autoguardado]');
  if (formBorrador) {
    var estado = document.getElementById('estado-guardado');
    var temporizador = null;
    var guardar = function () {
      var datos = {};
      new FormData(formBorrador).forEach(function (valor, clave) {
        var m = clave.match(/^datos\[(.+)\]$/);
        if (m) { datos[m[1]] = valor; }
      });
      formBorrador.querySelectorAll('input[type="checkbox"][name^="datos["]').forEach(function (c) {
        datos[c.name.slice(6, -1)] = c.checked ? '1' : '';
      });
      enviarJson(formBorrador.dataset.autoguardado, { datos: datos })
        .then(function (r) { anunciar(estado, 'Borrador guardado a las ' + r.guardado + '.'); })
        .catch(function () { anunciar(estado, 'No se ha podido guardar el borrador. Use el botón «Guardar borrador».'); });
    };
    formBorrador.addEventListener('input', function () {
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(guardar, 1500);
    });
    formBorrador.addEventListener('change', function () {
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(guardar, 400);
    });
  }

  /* --- AutoFirma --- */
  function autoFirma() {
    if (typeof window.AutoScript === 'undefined') { return null; }
    if (!window.__autofirmaCargado) {
      window.AutoScript.cargarAppAfirma();
      window.__autofirmaCargado = true;
    }
    return window.AutoScript;
  }

  function firmar(datosB64, alTerminar, alFallar) {
    var as = autoFirma();
    if (!as) { alFallar('No se ha cargado el componente de firma. Recargue la página.'); return; }
    as.sign(datosB64, 'SHA256withRSA', 'CAdES', 'mode=explicit\nfilters=nonexpired:', function (firmaB64) {
      alTerminar(firmaB64);
    }, function (tipo, mensaje) {
      if (tipo === 'es.gob.afirma.core.AOCancelledOperationException') {
        alFallar('Ha cancelado la operación.');
      } else {
        alFallar('No se ha podido firmar: ' + (mensaje || tipo) + '. Compruebe que tiene AutoFirma instalado.');
      }
    });
  }

  /* Acceso: firma de un reto de un solo uso */
  var botonAcceso = document.getElementById('acceder-certificado');
  if (botonAcceso) {
    var formAcceso = document.getElementById('form-acceso');
    var estadoAcceso = document.getElementById('estado-acceso');
    botonAcceso.addEventListener('click', function () {
      botonAcceso.disabled = true;
      anunciar(estadoAcceso, 'Abriendo AutoFirma. Elija su certificado en la ventana que aparecerá.');
      enviarJson(botonAcceso.dataset.reto).then(function (r) {
        firmar(r.datos, function (firma) {
          formAcceso.querySelector('input[name="firma"]').value = firma;
          anunciar(estadoAcceso, 'Comprobando el certificado…');
          formAcceso.submit();
        }, function (msg) { anunciar(estadoAcceso, msg); botonAcceso.disabled = false; });
      }).catch(function () {
        anunciar(estadoAcceso, 'No se ha podido iniciar el acceso. Recargue la página.');
        botonAcceso.disabled = false;
      });
    });
  }

  /* Firma y presentación de la solicitud */
  var botonFirma = document.getElementById('firmar-presentar');
  if (botonFirma) {
    var formPresentar = document.getElementById('form-presentar');
    var estadoFirma = document.getElementById('estado-firma');
    botonFirma.addEventListener('click', function () {
      botonFirma.disabled = true;
      anunciar(estadoFirma, 'Preparando el documento de la solicitud…');
      enviarJson(botonFirma.dataset.preparar).then(function (r) {
        formPresentar.querySelector('input[name="documento"]').value = r.documento;
        if (botonFirma.dataset.simulada === '1') {
          formPresentar.querySelector('input[name="simulada"]').value = '1';
          formPresentar.submit();
          return;
        }
        anunciar(estadoFirma, 'Abriendo AutoFirma. Firme con el mismo certificado con el que ha accedido.');
        firmar(r.datos, function (firma) {
          formPresentar.querySelector('input[name="firma"]').value = firma;
          anunciar(estadoFirma, 'Registrando su solicitud…');
          formPresentar.submit();
        }, function (msg) { anunciar(estadoFirma, msg); botonFirma.disabled = false; });
      }).catch(function (e) {
        anunciar(estadoFirma, e.message || 'No se ha podido preparar la solicitud.');
        botonFirma.disabled = false;
      });
    });
  }

  /* Pasarela de pago: envío automático del formulario (también hay botón visible) */
  var formPasarela = document.getElementById('form-pasarela');
  if (formPasarela && formPasarela.dataset.auto === '1') {
    window.setTimeout(function () { formPasarela.submit(); }, 800);
  }

  /* Confirmaciones de acciones irreversibles */
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirmar)) { ev.preventDefault(); }
    });
  });
})();
