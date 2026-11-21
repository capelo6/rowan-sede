@php
    $clave = $campo['clave'];
    $id = 'c-'.$clave;
    $tipo = $campo['tipo'] ?? 'texto';
    $obligatorio = (bool) ($campo['obligatorio'] ?? false);
    $valor = old('datos.'.$clave, $valores[$clave] ?? '');
    $error = $errors->first('datos.'.$clave);
    $describe = trim(($campo['ayuda'] ?? false ? 'ayuda-'.$id : '').' '.($error ? 'error-'.$id : ''));
@endphp
<div class="campo">
    @if($tipo === 'si_no')
        <div class="casilla">
            <input type="hidden" name="datos[{{ $clave }}]" value="">
            <input type="checkbox" id="{{ $id }}" name="datos[{{ $clave }}]" value="1" @checked($valor) @if($describe) aria-describedby="{{ $describe }}" @endif>
            <label for="{{ $id }}">{{ $campo['etiqueta'] }}</label>
        </div>
        @if(!empty($campo['ayuda']))<span class="ayuda" id="ayuda-{{ $id }}">{{ $campo['ayuda'] }}</span>@endif
    @else
        <label for="{{ $id }}">{{ $campo['etiqueta'] }} @if($obligatorio)<span class="obligatorio">(obligatorio)</span>@endif</label>
        @if(!empty($campo['ayuda']))<span class="ayuda" id="ayuda-{{ $id }}">{{ $campo['ayuda'] }}</span>@endif
        @if($error)<span class="error-campo" id="error-{{ $id }}">{{ $error }}</span>@endif
        @if($tipo === 'texto_largo')
            <textarea id="{{ $id }}" name="datos[{{ $clave }}]" @if($obligatorio) aria-required="true" @endif @if($error) aria-invalid="true" @endif @if($describe) aria-describedby="{{ $describe }}" @endif maxlength="{{ $campo['max'] ?? 2000 }}">{{ $valor }}</textarea>
        @elseif($tipo === 'opcion')
            <select id="{{ $id }}" name="datos[{{ $clave }}]" @if($obligatorio) aria-required="true" @endif @if($error) aria-invalid="true" @endif @if($describe) aria-describedby="{{ $describe }}" @endif>
                <option value="">Elija una opción</option>
                @foreach($campo['opciones'] ?? [] as $op)
                    @php($v = is_array($op) ? $op['valor'] : $op)
                    <option value="{{ $v }}" @selected((string) $valor === (string) $v)>{{ is_array($op) ? $op['etiqueta'] : $op }}</option>
                @endforeach
            </select>
        @else
            <input id="{{ $id }}" name="datos[{{ $clave }}]" value="{{ $valor }}"
                   type="{{ ['email' => 'email', 'telefono' => 'tel', 'fecha' => 'date', 'numero' => 'number'][$tipo] ?? 'text' }}"
                   @if($tipo === 'nif') autocomplete="off" spellcheck="false" @endif
                   @if($obligatorio) aria-required="true" @endif @if($error) aria-invalid="true" @endif @if($describe) aria-describedby="{{ $describe }}" @endif
                   maxlength="{{ $campo['max'] ?? 250 }}">
        @endif
    @endif
</div>
