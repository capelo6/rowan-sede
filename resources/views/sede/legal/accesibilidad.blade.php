@extends('layouts.sede', ['titulo' => 'Declaración de accesibilidad'])

@php($ajuste = fn ($clave, $defecto) => \App\Models\Ajuste::obtener('accesibilidad.'.$clave, $defecto))

@section('contenido')
    <h1>Declaración de accesibilidad</h1>
    <p>{{ \App\Sede\Organismo::nombre() }} se ha comprometido a hacer accesible su sede electrónica, de conformidad con el Real Decreto 1112/2018, de 7 de septiembre, sobre accesibilidad de los sitios web y aplicaciones para dispositivos móviles del sector público.</p>
    <p>Esta declaración de accesibilidad se aplica a <strong>{{ url('/') }}</strong>.</p>

    <h2>Situación de cumplimiento</h2>
    <p>Esta sede electrónica es <strong>{{ $ajuste('situacion', 'parcialmente conforme') }}</strong> con el RD 1112/2018, debido a las excepciones y a la falta de conformidad de los aspectos que se indican a continuación.</p>

    <h2>Contenido no accesible</h2>
    <p>{!! nl2br(e($ajuste('contenido_no_accesible', 'Algunos documentos PDF generados automáticamente pueden no estar etiquetados para su lectura con lectores de pantalla (requisito 10.1.1 de la norma UNE-EN 301 549). Puede pedir una versión accesible por los medios de contacto indicados.'))) !!}</p>

    <h2>Preparación de la presente declaración</h2>
    <p>La presente declaración fue preparada el {{ $ajuste('fecha', now()->format('d/m/Y')) }}. El método empleado ha sido una {{ $ajuste('metodo', 'autoevaluación con herramientas automáticas y revisión manual') }}.</p>

    <h2>Observaciones y datos de contacto</h2>
    <p>Puede realizar comunicaciones sobre requisitos de accesibilidad (art. 10.2.a del RD 1112/2018): informar sobre posibles incumplimientos, transmitir otras dificultades de acceso al contenido o formular cualquier consulta o sugerencia de mejora.</p>
    <p>Contacto: @if(\App\Sede\Organismo::email())<a href="mailto:{{ \App\Sede\Organismo::email() }}">{{ \App\Sede\Organismo::email() }}</a>@endif @if(\App\Sede\Organismo::telefono()) · {{ \App\Sede\Organismo::telefono() }}@endif</p>
    <p>Las comunicaciones serán recibidas y tratadas por la unidad responsable de la accesibilidad del organismo.</p>

    <h2>Procedimiento de aplicación</h2>
    <p>Si una vez realizada una solicitud de información accesible o queja ésta hubiera sido desestimada, no se estuviera de acuerdo con la decisión adoptada, o la respuesta no cumpliera los requisitos contemplados en el artículo 12.5 del RD 1112/2018, la persona interesada podrá iniciar una reclamación.</p>

    <h2>Contenido opcional</h2>
    <p>La sede está diseñada con el nivel AA de las pautas WCAG 2.1: navegación completa con teclado, contraste suficiente, textos alternativos, formularios con etiquetas y mensajes de error asociados, y adaptación a pantallas desde 320 píxeles.</p>
@endsection
